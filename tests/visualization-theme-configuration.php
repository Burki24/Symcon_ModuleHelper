<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;

require_once __DIR__ . '/../src/VisualizationThemeConfigurationHelper.php';

final class VisualizationThemeConfigurationHelperHarness
{
    use VisualizationThemeConfigurationHelper;

    /** @var array<string,int|bool> */
    private array $properties = [];

    public function register(): void
    {
        $this->RegisterVisualizationThemeProperties();
    }

    /** @return array<int,array<string,mixed>> */
    public function formItems(string $width = '260px'): array
    {
        return $this->VisualizationThemeFormItems($width);
    }

    /** @param array<int,array<string,mixed>> $elements */
    public function insert(array &$elements): bool
    {
        return $this->InsertVisualizationThemeFormItems($elements);
    }

    public function css(array $overrides = []): string
    {
        return $this->VisualizationThemeCSS($overrides);
    }

    public function set(string $name, int|bool $value): void
    {
        $this->properties[$name] = $value;
    }

    /** @return array<string,int|bool> */
    public function properties(): array
    {
        return $this->properties;
    }

    protected function RegisterPropertyBoolean(string $name, bool $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyInteger(string $name, int $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return (bool) $this->properties[$name];
    }

    protected function ReadPropertyInteger(string $name): int
    {
        return (int) $this->properties[$name];
    }

    protected function HelperTranslationLanguageOverride(): string
    {
        return 'de';
    }
}

$themeConfigurationHarness = new VisualizationThemeConfigurationHelperHarness();
$themeConfigurationHarness->register();

assertSameValue(10, count($themeConfigurationHarness->properties()), 'Theme configuration must register one switch and nine colors.');
assertFalseValue($themeConfigurationHarness->properties()['VisualizationThemeUseCustomColors'], 'Native Symcon colors must be the default.');
assertFalseValue(str_contains($themeConfigurationHarness->css(), '--symc-heading: #'), 'Inactive custom colors must not override native tokens.');

$formItems = $themeConfigurationHarness->formItems();
assertSameValue('Eigene Kachelfarben verwenden', $formItems[1]['caption'], 'The helper must translate its own form captions.');
assertFalseValue($formItems[2]['items'][0]['enabled'], 'Custom color controls must be disabled in native mode.');

$themeConfigurationHarness->set('VisualizationThemeUseCustomColors', true);
$defaultCustomCSS = $themeConfigurationHarness->css();
assertFalseValue(str_contains($defaultCustomCSS, '--symc-text: #202124;'), 'Default text colors must continue to follow the native Symcon token.');
assertFalseValue(str_contains($defaultCustomCSS, '--symc-background: #FFFFFF;'), 'Default backgrounds must continue to follow the native Symcon token.');
assertFalseValue(str_contains($defaultCustomCSS, '--symc-accent: #55CBB5;'), 'Default accents must continue to follow the native Symcon token.');

$themeConfigurationHarness->set('VisualizationThemeHeadingColor', 0x123456);
$customCSS = $themeConfigurationHarness->css(['--symc-accent' => '#abcdef', 'invalid' => '#000000']);
assertTrueValue(str_contains($customCSS, '--symc-heading: #123456;'), 'Configured heading colors must be emitted.');
assertFalseValue(str_contains($customCSS, '--symc-text: #202124;'), 'Unchanged colors must not be emitted beside changed colors.');
assertTrueValue(str_contains($customCSS, '--symc-accent: #ABCDEF;'), 'Explicit valid overrides must win and normalize.');
assertFalseValue(str_contains($customCSS, 'invalid: #000000'), 'Invalid token names must be ignored.');
assertTrueValue($themeConfigurationHarness->formItems()[2]['items'][0]['enabled'], 'Custom color controls must be enabled in custom mode.');

$elements = [[
    'type'  => 'ExpansionPanel',
    'items' => [[
        'type'    => 'Label',
        'caption' => 'Configure the shared Symcon tile theme used by the HTML-SDK visualization.'
    ]]
]];
assertTrueValue($themeConfigurationHarness->insert($elements), 'The shared marker must be replaced recursively.');
assertSameValue('Label', $elements[0]['items'][0]['type'], 'The inserted form must begin with its description.');

$failed = false;
try {
    $themeConfigurationHarness->formItems('  ');
} catch (InvalidArgumentException) {
    $failed = true;
}
assertTrueValue($failed, 'Empty color-control widths must be rejected.');

fwrite(STDOUT, "VisualizationThemeConfigurationHelper tests passed.\n");
