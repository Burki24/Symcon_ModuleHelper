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

function multipleLibraryWorker(string $first, string $second): array
{
    $autoloads = [];
    spl_autoload_register(static function (string $name) use (&$autoloads): void
    {
        $autoloads[] = $name;
    });
    $hashes = [];
    $cachedBefore = 0;
    foreach ([$first, $second] as $directory) {
        foreach (multipleLibraryHelpers() as $name) {
            if (function_exists('opcache_is_script_cached') && opcache_is_script_cached(realpath($directory . '/' . $name . '.php'))) {
                ++$cachedBefore;
            }
        }
    }
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
    $firstLabel = basename(dirname($first, 2)) === 'library-a' ? 'Library A' : 'Library B';
    assertSameValue($firstLabel, $translator->fromDirectory($first . '/translations'), 'First catalog was not resolved.');
    assertSameValue($firstLabel === 'Library A' ? 'Library B' : 'Library A', $translator->fromDirectory($second . '/translations'), 'Catalog caching must include its physical directory.');

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
    return ['success' => true, 'cachedBefore' => $cachedBefore];
}

/** Exercise separate PHP requests sharing a real opcode cache, not eval or fresh CLI workers. */
function multipleLibraryOpcodeCache(array $directories): void
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    assertTrueValue(is_resource($socket), 'The opcode-cache fixture must reserve a loopback port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $output = tmpfile();
    $process = proc_open(
        [PHP_BINARY, '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.file_update_protection=0',
            '-d', 'display_errors=1', '-S', $address, __FILE__],
        [0 => ['pipe', 'r'], 1 => $output, 2 => $output],
        $pipes,
        null,
        array_merge(getenv(), ['SYMC_HELPER_TEST_DIRECTORIES' => json_encode($directories, JSON_THROW_ON_ERROR)])
    );
    assertTrueValue(is_resource($process), 'The isolated opcode-cache server must start.');
    fclose($pipes[0]);
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $probe = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
            if (is_resource($probe)) {
                fclose($probe);
                $ready = true;
                break;
            }
            usleep(20000);
        }
        assertTrueValue($ready, 'The isolated opcode-cache server must become ready.');
        $request = static function (string $query) use ($address): array
        {
            $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
            $body = file_get_contents('http://' . $address . '/?' . $query, false, $context);
            assertTrueValue(is_string($body), 'The opcode-cache request must return a response.');
            $result = json_decode($body, true);
            assertTrueValue(is_array($result) && ($result['success'] ?? false), 'The cached file-load request failed: ' . substr($body, 0, 4000));
            return $result;
        };
        $request('mode=health');
        // Compile each independent copy in a separate request before loading both.
        // The old early-return guard passes cold CLI includes but fails here.
        $request('mode=warm&copy=0');
        $request('mode=warm&copy=1');
        foreach ([0, 1, 0, 1] as $reverse) {
            $result = $request('mode=both&reverse=' . $reverse);
            assertSameValue(count(multipleLibraryHelpers()) * 2, $result['cachedBefore'], 'Both physical bundles must already be cached before either loading order.');
        }
    } catch (Throwable $exception) {
        rewind($output);
        throw new RuntimeException($exception->getMessage() . "\n" . stream_get_contents($output, 4000), 0, $exception);
    } finally {
        proc_terminate($process);
        proc_close($process);
        fclose($output);
    }
}

if (PHP_SAPI === 'cli-server') {
    header('Content-Type: application/json');
    assertTrueValue((opcache_get_status(false)['opcache_enabled'] ?? false) === true, 'OPcache must actually be active.');
    $directories = json_decode(getenv('SYMC_HELPER_TEST_DIRECTORIES'), true, 16, JSON_THROW_ON_ERROR);
    $result = ['success' => true];
    if (($_GET['mode'] ?? '') === 'warm') {
        $directory = $directories[(int) ($_GET['copy'] ?? 0)];
        foreach (multipleLibraryHelpers() as $name) {
            require_once $directory . '/' . $name . '.php';
        }
    } elseif (($_GET['mode'] ?? '') === 'both') {
        if (($_GET['reverse'] ?? '') === '1') {
            $directories = array_reverse($directories);
        }
        $result = multipleLibraryWorker(...$directories);
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
    return;
}

if (($argv[1] ?? '') === '--worker') {
    multipleLibraryWorker($argv[2], $argv[3]);
    echo "Independent library paths loaded safely.\n";
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
    foreach (array_combine($multipleLibraryDirectories, ['Library A', 'Library B']) as $directory => $label) {
        file_put_contents($directory . '/translations/SameCatalog.json', json_encode(['translations' => ['label' => ['de' => $label]]], JSON_THROW_ON_ERROR));
    }
    foreach ([$multipleLibraryDirectories, array_reverse($multipleLibraryDirectories)] as [$first, $second]) {
        $process = proc_open([PHP_BINARY, '-d', 'opcache.enable_cli=0', __FILE__, '--worker', $first, $second], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
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
    multipleLibraryOpcodeCache($multipleLibraryDirectories);
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

echo "Shared helper declarations verified across two physical libraries, both loading orders and warm OPcache requests.\n";
