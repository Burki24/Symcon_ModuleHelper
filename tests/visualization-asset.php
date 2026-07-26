<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\VisualizationAssetHelper;

require_once __DIR__ . '/../src/VisualizationAssetHelper.php';

final class VisualizationAssetHelperHarness
{
    use VisualizationAssetHelper;

    /** @var list<array{message:string,data:mixed,format:int}> */
    private array $debugMessages = [];

    public function load(string $filename): string
    {
        return $this->VisualizationAsset($filename);
    }

    /** @return list<array{message:string,data:mixed,format:int}> */
    public function debugMessages(): array
    {
        return $this->debugMessages;
    }

    protected function SendDebug(string $message, mixed $data, int $format): void
    {
        $this->debugMessages[] = [
            'message' => $message,
            'data'    => $data,
            'format'  => $format
        ];
    }
}

$visualizationDirectory = __DIR__ . '/visualization';
$assetPath = $visualizationDirectory . '/helper-asset.txt';

if (!is_dir($visualizationDirectory) && !mkdir($visualizationDirectory) && !is_dir($visualizationDirectory)) {
    throw new RuntimeException('Visualization test directory could not be created.');
}

file_put_contents($assetPath, 'Visualization helper asset');

try {
    $helper = new VisualizationAssetHelperHarness();

    assertSameValue(
        'Visualization helper asset',
        $helper->load('helper-asset.txt'),
        'The helper must load assets relative to the concrete class.'
    );

    assertSameValue(
        '',
        $helper->load('missing-asset.txt'),
        'A missing visualization asset must return an empty string.'
    );

    $debugMessages = $helper->debugMessages();
    assertSameValue(1, count($debugMessages), 'A missing asset must emit exactly one debug message.');
    assertSameValue(
        'VisualizationAsset',
        $debugMessages[0]['message'],
        'The debug context must identify the helper method.'
    );
    assertTrueValue(
        is_string($debugMessages[0]['data'])
        && str_contains($debugMessages[0]['data'], 'missing-asset.txt'),
        'The debug output must contain the missing asset path.'
    );
} finally {
    if (is_file($assetPath)) {
        unlink($assetPath);
    }
    if (is_dir($visualizationDirectory)) {
        rmdir($visualizationDirectory);
    }
}

fwrite(STDOUT, "VisualizationAssetHelper tests passed.\n");
