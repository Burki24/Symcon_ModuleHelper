<?php

declare(strict_types=1);

$translationFiles = glob(__DIR__ . '/../src/translations/*.json');
assertTrueValue(is_array($translationFiles), 'The helper translation files could not be enumerated.');
assertTrueValue($translationFiles !== [], 'No helper translation files were found.');

foreach ($translationFiles as $translationFile) {
    $original = file_get_contents($translationFile);
    assertTrueValue($original !== false, 'Unable to read helper translation file: ' . $translationFile);

    $normalized = str_replace(["\r\n", "\r"], "\n", $original);
    $normalized = rtrim($normalized, "\n");
    $decoded = json_decode($normalized, false, 512, JSON_THROW_ON_ERROR);
    $canonical = json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );

    assertSameValue(
        $canonical,
        $normalized,
        'Helper translation JSON does not match the canonical Symcon formatting: ' . basename($translationFile)
    );
}

fwrite(STDOUT, "Helper translation JSON style tests passed.\n");
