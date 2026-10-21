<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/OpenFreeMapHelper.php';

final class OpenFreeMapHarness
{
    use \Burki24\SymconModuleHelper\OpenFreeMapHelper;

    public array $properties = [];

    public function register(string $prefix): void
    {
        $this->RegisterOpenFreeMapProperties($prefix);
    }

    public function design(string $prefix): array
    {
        return $this->ReadOpenFreeMapDesign($prefix);
    }

    public function form(string $prefix, bool $enabled): array
    {
        return $this->OpenFreeMapFormSection($prefix, $enabled);
    }

    public function assets(): array
    {
        return $this->OpenFreeMapAssets('/hook/example/assets/maplibre-gl.js');
    }

    public function renderer(): string
    {
        return $this->OpenFreeMapRendererAsset();
    }

    protected function RegisterPropertyString(string $name, string $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyBoolean(string $name, bool $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyInteger(string $name, int $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function ReadPropertyString(string $name): string
    {
        return $this->properties[$name];
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return $this->properties[$name];
    }

    protected function ReadPropertyInteger(string $name): int
    {
        return $this->properties[$name];
    }
}

$vectorHelper = new OpenFreeMapHarness();
$vectorHelper->register('Tile');
$vectorHelper->register('IPSView');
assertSameValue('auto', $vectorHelper->design('Tile')['theme'], 'Map follows the designer by default.');
$vectorHelper->properties['IPSViewMapTheme'] = 'dark';
$vectorHelper->properties['IPSViewMapLightWaterColor'] = 0x123456;
assertSameValue('auto', $vectorHelper->design('Tile')['theme'], 'Output profiles must remain independent.');
assertSameValue('#123456', $vectorHelper->design('IPSView')['lightWaterColor'], 'Palette values must be normalized.');
$vectorHelper->properties['TileMapLightStyle'] = 'unrecognized';
assertSameValue('liberty', $vectorHelper->design('Tile')['lightStyle'], 'Unknown styles use a defined default.');
$vectorForm = $vectorHelper->form('IPSView', false);
assertSameValue('OpenFreeMap', $vectorForm['caption'], 'Each designer receives a dedicated map menu.');
$walkVectorFields = static function (array $items) use (&$walkVectorFields): void
{
    foreach ($items as $item) {
        if (isset($item['name'])) {
            assertFalseValue($item['enabled'], 'Inherited output fields must be disabled.');
        }
        $walkVectorFields($item['items'] ?? []);
    }
};
$walkVectorFields($vectorForm['items']);
$vectorAssets = $vectorHelper->assets();
assertTrueValue(strlen($vectorAssets['script']) + strlen($vectorAssets['css']) < 1048576, 'Map assets must respect the Symcon output-buffer limit.');
$compressedRenderer = $vectorHelper->renderer();
assertTrueValue(strlen($compressedRenderer) < 1048576, 'The compressed renderer must fit the HTTP output buffer.');
assertSameValue(hash_file('sha256', __DIR__ . '/../src/maps/vendor/maplibre-gl.js'), hash('sha256', gzdecode($compressedRenderer)), 'Compressed delivery must preserve the pinned vendor bytes.');
fwrite(STDOUT, "OpenFreeMap profile, form and output-budget tests passed.\n");
