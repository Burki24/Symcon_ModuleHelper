<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ConfigurationFormHelper.php';

use Burki24\SymconModuleHelper\ConfigurationFormHelper;

final class ConfigurationFormHarness
{
    use ConfigurationFormHelper;

    /** @return array<string,mixed> */
    public function load(): array
    {
        return $this->LoadConfigurationForm();
    }

    /** @param array<string,mixed> $form */
    public function encode(array $form): string
    {
        return $this->EncodeConfigurationForm($form);
    }
}

$formPath = __DIR__ . '/form.json';
$hadExistingForm = is_file($formPath);
$existingForm = $hadExistingForm ? file_get_contents($formPath) : false;
$harness = new ConfigurationFormHarness();

try {
    if (is_file($formPath) && !unlink($formPath)) {
        throw new RuntimeException('Temporary form.json could not be removed before the test.');
    }

    try {
        $harness->load();
        throw new RuntimeException('A missing form.json must throw RuntimeException.');
    } catch (RuntimeException $exception) {
        assertTrueValue(
            str_contains($exception->getMessage(), 'could not be read'),
            'A missing form.json exception must explain that the form could not be read.'
        );
    }

    file_put_contents($formPath, '{invalid');
    try {
        $harness->load();
        throw new RuntimeException('Invalid JSON must throw UnexpectedValueException.');
    } catch (UnexpectedValueException $exception) {
        assertTrueValue(
            $exception->getPrevious() instanceof JsonException,
            'Invalid JSON must preserve the original JsonException.'
        );
    }

    file_put_contents($formPath, '[{"type":"Label"}]');
    try {
        $harness->load();
        throw new RuntimeException('A JSON list root must be rejected.');
    } catch (UnexpectedValueException $exception) {
        assertTrueValue(
            str_contains($exception->getMessage(), 'JSON object'),
            'A JSON list root exception must explain that an object is required.'
        );
    }

    file_put_contents($formPath, '{}');
    assertSameValue([], $harness->load(), 'An empty JSON object must load as an empty associative array.');

    $form = [
        'elements' => [
            [
                'type'    => 'Label',
                'caption' => 'München/東京'
            ]
        ],
        'actions' => [],
        'status'  => []
    ];
    $json = json_encode($form, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($formPath, $json);
    assertSameValue($form, $harness->load(), 'A valid configuration form must round-trip through LoadConfigurationForm().');

    $encoded = $harness->encode($form);
    assertSameValue($form, json_decode($encoded, true, 512, JSON_THROW_ON_ERROR), 'Encoded forms must round-trip.');
    assertTrueValue(str_contains($encoded, 'München/東京'), 'Encoded forms must keep Unicode and slashes readable.');
    assertSameValue('{}', $harness->encode([]), 'An empty configuration form must serialize as a JSON object.');

    try {
        $harness->encode([['type' => 'Label']]);
        throw new RuntimeException('A list-shaped PHP array must not be accepted as a configuration-form root.');
    } catch (UnexpectedValueException $exception) {
        assertTrueValue(
            str_contains($exception->getMessage(), 'associative array'),
            'A list-shaped form exception must explain that an associative array is required.'
        );
    }
} finally {
    if ($hadExistingForm && is_string($existingForm)) {
        file_put_contents($formPath, $existingForm);
    } elseif (is_file($formPath)) {
        unlink($formPath);
    }
}

fwrite(STDOUT, "ConfigurationFormHelper tests passed.\n");
