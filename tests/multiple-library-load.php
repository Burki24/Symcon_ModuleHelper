<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** The common direct and transitive dependencies of JSLive and SymconECharts. */
function multipleLibraryHelpers(): array
{
    return [
        'ConfigurationFormHelper', 'DataFlowHelper', 'DebugHelper', 'HelperTranslationHelper',
        'ResponsiveVisualizationHelper', 'VariablePresentationNormalizationHelper',
        'VisualizationAssetHelper', 'VisualizationThemeHelper'
    ];
}

function multipleLibraryWorker(string $first, string $second): void
{
    $autoloads = [];
    spl_autoload_register(static function (string $name) use (&$autoloads): void
    {
        $autoloads[] = $name;
    });
    $hashes = [];
    foreach ([$first, $second] as $directory) {
        foreach (multipleLibraryHelpers() as $name) {
            $file = $directory . '/' . $name . '.php';
            $hashes[$file] = hash_file('sha256', $file);
            require_once $file;
        }
    }
    foreach (multipleLibraryHelpers() as $name) {
        $symbol = 'Burki24\\SymconModuleHelper\\' . $name;
        $reflection = new ReflectionClass($symbol);
        assertSameValue(realpath($first . '/' . $name . '.php'), $reflection->getFileName(), 'A second library must retain the first definition: ' . $name);
        // Plain require is intentional: safety must be provided by the helper,
        // not only by PHP's include-once cache for an identical physical file.
        require $first . '/' . $name . '.php';
        require $second . '/' . $name . '.php';
    }
    assertSameValue([], $autoloads, 'Declaration guards must not invoke unrelated autoloaders.');

    // Only the second library subscribes this catalog-owning helper. Its
    // dependency may nevertheless already have been declared by the first.
    require_once $second . '/VisualizationThemeConfigurationHelper.php';
    $theme = new class() {
        use \Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;

        public function form(): array
        {
            return $this->VisualizationThemeFormItems();
        }

        protected function ReadPropertyBoolean(string $name): bool
        {
            return false;
        }

        protected function HelperTranslationLanguageOverride(): string
        {
            return 'de';
        }
    };
    assertSameValue('Eigene Kachelfarben verwenden', $theme->form()[1]['caption'], 'The caller must use its own vendored translation catalog.');

    $translator = new class() {
        use \Burki24\SymconModuleHelper\HelperTranslationHelper;

        public function fromDirectory(string $directory): string
        {
            return $this->TranslateHelperTextFromDirectory($directory, 'SameCatalog', 'label', 'Fallback', 'de');
        }
    };
    assertSameValue('Library A', $translator->fromDirectory($first . '/translations'), 'First catalog was not resolved.');
    assertSameValue('Library B', $translator->fromDirectory($second . '/translations'), 'Catalog caching must include its physical directory.');

    $transport = new class() {
        use \Burki24\SymconModuleHelper\DataFlowHelper;

        public function roundtrip(): array
        {
            return $this->DecodeDataFlowMessage($this->EncodeDataFlowMessage('synthetic-data-id', ['value' => 1.0]), 'synthetic-data-id');
        }
    };
    assertSameValue(['DataID' => 'synthetic-data-id', 'value' => 1.0], $transport->roundtrip(), 'Both libraries must retain the transport contract.');
    $presentation = \Burki24\SymconModuleHelper\VariablePresentationNormalizationHelper::Normalize(
        2,
        ['PRESENTATION' => \Burki24\SymconModuleHelper\VariablePresentationNormalizationHelper::VALUE, 'SUFFIX' => ' %', 'DIGITS' => 0]
    );
    assertSameValue('exact', $presentation['conversion']['status'], 'The shared class must remain usable after both loads.');
    assertSameValue(' %', $presentation['native']['parameters']['SUFFIX'], 'Formatting must not depend on the loading order.');
    foreach ($hashes as $file => $hash) {
        assertSameValue($hash, hash_file('sha256', $file), 'Loading helpers must never rewrite a vendored file.');
    }
    echo "Independent library paths loaded safely.\n";
}

if (($argv[1] ?? '') === '--worker') {
    multipleLibraryWorker($argv[2], $argv[3]);
    exit(0);
}

$multipleLibraryRoot = sys_get_temp_dir() . '/symcon-helper-load-' . bin2hex(random_bytes(8));
$multipleLibraryDirectories = [$multipleLibraryRoot . '/library-a/libs/helper', $multipleLibraryRoot . '/library-b/libs/helper'];
try {
    foreach ($multipleLibraryDirectories as $directory) {
        if (!mkdir($directory, 0700, true)) {
            throw new RuntimeException('Cannot prepare isolated helper copies.');
        }
        foreach (multipleLibraryHelpers() as $name) {
            if (!copy(__DIR__ . '/../src/' . $name . '.php', $directory . '/' . $name . '.php')) {
                throw new RuntimeException('Cannot copy a canonical helper.');
            }
        }
        copy(__DIR__ . '/../src/VisualizationThemeConfigurationHelper.php', $directory . '/VisualizationThemeConfigurationHelper.php');
        mkdir($directory . '/translations', 0700);
        copy(__DIR__ . '/../src/translations/VisualizationThemeConfigurationHelper.json', $directory . '/translations/VisualizationThemeConfigurationHelper.json');
    }
    foreach ([$multipleLibraryDirectories, array_reverse($multipleLibraryDirectories)] as [$first, $second]) {
        foreach ([$first => 'Library A', $second => 'Library B'] as $directory => $label) {
            file_put_contents($directory . '/translations/SameCatalog.json', json_encode(['translations' => ['label' => ['de' => $label]]], JSON_THROW_ON_ERROR));
        }
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $first, $second], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the isolated declaration test.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        assertSameValue(0, $exitCode, "Libraries must coexist in either loading order.\n" . $stdout . $stderr);
    }
} finally {
    foreach ($multipleLibraryDirectories as $directory) {
        foreach (['VisualizationThemeConfigurationHelper.php', 'translations/VisualizationThemeConfigurationHelper.json', 'translations/SameCatalog.json'] as $relative) {
            if (is_file($directory . '/' . $relative)) {
                unlink($directory . '/' . $relative);
            }
        }
        if (is_dir($directory . '/translations')) {
            rmdir($directory . '/translations');
        }
        foreach (multipleLibraryHelpers() as $name) {
            $file = $directory . '/' . $name . '.php';
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ([$directory, dirname($directory), dirname($directory, 2)] as $created) {
            if (is_dir($created)) {
                rmdir($created);
            }
        }
    }
    if (is_dir($multipleLibraryRoot)) {
        rmdir($multipleLibraryRoot);
    }
}

echo "Shared helper declarations verified across two physical libraries and both loading orders.\n";
