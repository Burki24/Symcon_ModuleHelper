<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewColorPaletteHelper;

require_once __DIR__ . '/../src/IPSViewColorPaletteHelper.php';

final class IPSViewColorPaletteHelperHarness
{
    use IPSViewColorPaletteHelper;

    /** @var array<string,int> */
    private array $properties = [];

    /** @param array<string,int> $defaults */
    public function register(array $defaults = []): void
    {
        $this->RegisterIPSViewColorProperties($defaults);
    }

    /** @return array<int,array<string,mixed>> */
    public function formItems(string $width = '250px'): array
    {
        return $this->IPSViewColorFormItems($width);
    }

    /** @return array<string,string> */
    public function palette(): array
    {
        return $this->IPSViewColorPalette();
    }

    /** @return array<string,string> */
    public function resolvedPalette(): array
    {
        return $this->IPSViewResolvedColorPalette();
    }

    public function css(bool $transparent = false, string $selector = ':root'): string
    {
        return $this->IPSViewColorCSSVariables($transparent, $selector);
    }

    public function setProperty(string $name, int $value): void
    {
        $this->properties[$name] = $value;
    }

    protected function RegisterPropertyInteger(string $name, int $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function ReadPropertyInteger(string $name): int
    {
        return $this->properties[$name] ?? -1;
    }
}

/** @return array{red:float,green:float,blue:float} */
function parseTestCSSColor(string $color): array
{
    if (preg_match('/^#([0-9A-F]{6})$/', $color, $matches) === 1) {
        return [
            'red'   => (float) hexdec(substr($matches[1], 0, 2)),
            'green' => (float) hexdec(substr($matches[1], 2, 2)),
            'blue'  => (float) hexdec(substr($matches[1], 4, 2))
        ];
    }

    throw new RuntimeException('Unexpected CSS color in test: ' . $color);
}

/** @param array{red:float,green:float,blue:float} $color */
function testRelativeLuminance(array $color): float
{
    $channel = static function (float $value): float
    {
        $normalized = $value / 255.0;

        return $normalized <= 0.03928
            ? $normalized / 12.92
            : (($normalized + 0.055) / 1.055) ** 2.4;
    };

    return (0.2126 * $channel($color['red']))
        + (0.7152 * $channel($color['green']))
        + (0.0722 * $channel($color['blue']));
}

/**
 * @param array{red:float,green:float,blue:float} $first
 * @param array{red:float,green:float,blue:float} $second
 */
function testContrastRatio(array $first, array $second): float
{
    $firstLuminance = testRelativeLuminance($first);
    $secondLuminance = testRelativeLuminance($second);
    $lighter = max($firstLuminance, $secondLuminance);
    $darker = min($firstLuminance, $secondLuminance);

    return ($lighter + 0.05) / ($darker + 0.05);
}

$harness = new IPSViewColorPaletteHelperHarness();
$harness->register([
    'Page'          => 0xD8C59B,
    'Surface'       => 0x9B795A,
    'SurfaceStrong' => 0xAD8A69,
    'Text'          => 0xFFFFFF,
    'MutedText'     => 0xF1E6D5,
    'Accent'        => 0xE0BE63,
    'Success'       => 0x78D79C,
    'Warning'       => 0xFFD166,
    'Danger'        => 0xFF8174
]);

assertSameValue(
    [
        'Page'          => '#D8C59B',
        'Surface'       => '#9B795A',
        'SurfaceStrong' => '#AD8A69',
        'Text'          => '#FFFFFF',
        'MutedText'     => '#F1E6D5',
        'Accent'        => '#E0BE63',
        'Success'       => '#78D79C',
        'Warning'       => '#FFD166',
        'Danger'        => '#FF8174'
    ],
    $harness->palette(),
    'Configured colors must be returned as normalized uppercase CSS hex values.'
);

$formItems = $harness->formItems('240px');
assertSameValue(5, count($formItems), 'The color form must contain two labels and three color rows.');
assertSameValue('Label', $formItems[0]['type'], 'The form must start with a description label.');
assertSameValue('Label', $formItems[4]['type'], 'The form must end with a contrast explanation.');
foreach ([1, 2, 3] as $rowIndex) {
    assertSameValue('RowLayout', $formItems[$rowIndex]['type'], 'Color controls must be grouped in row layouts.');
    assertSameValue(3, count($formItems[$rowIndex]['items']), 'Each color row must contain three controls.');
    foreach ($formItems[$rowIndex]['items'] as $item) {
        assertSameValue('SelectColor', $item['type'], 'Every palette control must use SelectColor.');
        assertSameValue(false, $item['allowTransparent'], 'Palette colors must not accept transparent values.');
        assertSameValue('240px', $item['width'], 'The requested control width must be applied.');
    }
}

$resolved = $harness->resolvedPalette();
foreach (['Surface', 'SurfaceStrong', 'SurfaceSoft'] as $surfaceKey) {
    $surface = parseTestCSSColor($resolved[$surfaceKey]);
    assertTrueValue(
        testContrastRatio(parseTestCSSColor($resolved['Text']), $surface) >= 4.49,
        'Primary text must keep readable contrast on ' . $surfaceKey . '.'
    );
    assertTrueValue(
        testContrastRatio(parseTestCSSColor($resolved['MutedText']), $surface) >= 4.49,
        'Secondary text must keep readable contrast on ' . $surfaceKey . '.'
    );
    assertTrueValue(
        testContrastRatio(parseTestCSSColor($resolved['FaintText']), $surface) >= 3.49,
        'Faint text must keep its minimum contrast on ' . $surfaceKey . '.'
    );
}

$css = $harness->css(true, '.calendar-ipsview');
assertTrueValue(str_starts_with($css, ".calendar-ipsview {\n"), 'CSS variables must use the requested selector.');
assertTrueValue(str_contains($css, '--ipsview-background: transparent;'), 'Transparent mode must expose a transparent background token.');
assertTrueValue(str_contains($css, '--ipsview-surface:'), 'CSS must expose the surface token.');
assertTrueValue(str_contains($css, '--ipsview-text:'), 'CSS must expose the text token.');
assertTrueValue(str_contains($css, '--ipsview-danger-soft:'), 'CSS must expose derived soft status tokens.');
assertFalseValue(str_contains($harness->css(false), '--ipsview-background: transparent;'), 'Opaque mode must use the configured page color.');

$harness->setProperty('IPSViewPageColorValue', -1);
assertSameValue('#F4F5F7', $harness->palette()['Page'], 'Invalid stored colors must fall back to the neutral helper default.');

try {
    $harness->register(['Unknown' => 0xFFFFFF]);
    throw new RuntimeException('Unknown palette keys must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'Unknown'), 'Unknown palette errors must name the invalid key.');
}

try {
    $harness->register(['Page' => 0x1000000]);
    throw new RuntimeException('Out-of-range colors must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), '0xFFFFFF'), 'Invalid color errors must explain the accepted RGB range.');
}

try {
    $harness->formItems('  ');
    throw new RuntimeException('An empty form-control width must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'width'), 'Empty width errors must explain the invalid argument.');
}

try {
    $harness->css(false, ':root { color: red; }');
    throw new RuntimeException('CSS rule delimiters in the selector must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'selector'), 'Invalid selector errors must explain the invalid argument.');
}

$previousNumericLocale = setlocale(LC_NUMERIC, 0);
$commaNumericLocale = setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'German');
if ($commaNumericLocale !== false) {
    assertTrueValue(
        preg_match('/rgba\([^)]*, 0\.\d{3}\)/', $harness->css()) === 1,
        'Legacy IPSView palette alpha colors must use a CSS decimal point independently of LC_NUMERIC.'
    );
}
if (is_string($previousNumericLocale)) {
    setlocale(LC_NUMERIC, $previousNumericLocale);
}

fwrite(STDOUT, "IPSViewColorPaletteHelper tests passed.\n");
