<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;
use Burki24\SymconModuleHelper\IPSViewStyleHelper;

require_once __DIR__ . '/../src/IPSViewStyleConfigurationHelper.php';

final class IPSViewStyleConfigurationHelperHarness
{
    use IPSViewStyleConfigurationHelper;

    /** @var array<string,mixed> */
    private array $properties = [];

    /** @var array<string,int> */
    private array $attributes = [];

    private ?string $translationLanguage = null;

    public function register(): void
    {
        $this->RegisterIPSViewStyleProperties();
    }

    /** @return array<int,array<string,mixed>> */
    public function formItems(string $width = '240px'): array
    {
        return $this->IPSViewStyleFormItems($width);
    }

    /** @param array<int,array<string,mixed>> $elements */
    public function insertFormItems(array &$elements): bool
    {
        return $this->InsertIPSViewStyleFormItems($elements);
    }

    /** @return array<string,string> */
    public function overrides(): array
    {
        return $this->IPSViewStyleNativeColorOverrides();
    }

    /** @return array<string,mixed> */
    public function nativeTheme(): array
    {
        return $this->IPSViewStyleNativeTheme();
    }

    /** @return array<string,string> */
    public function overrideProperties(): array
    {
        return $this->IPSViewStyleNativeOverrideProperties();
    }

    public function setTranslationLanguage(?string $language): void
    {
        $this->translationLanguage = $language;
    }

    public function setProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    /** @return array<string,mixed> */
    public function properties(): array
    {
        return $this->properties;
    }

    protected function HelperTranslationLanguageOverride(): ?string
    {
        return $this->translationLanguage;
    }

    protected function RegisterPropertyInteger(string $name, int $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyBoolean(string $name, bool $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyString(string $name, string $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyFloat(string $name, float $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterAttributeInteger(string $name, int $default): void
    {
        $this->attributes[$name] = $default;
    }

    protected function ReadPropertyInteger(string $name): int
    {
        $value = $this->properties[$name] ?? 0;

        return is_int($value) ? $value : 0;
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return ($this->properties[$name] ?? false) === true;
    }

    protected function ReadPropertyString(string $name): string
    {
        $value = $this->properties[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    protected function ReadPropertyFloat(string $name): float
    {
        $value = $this->properties[$name] ?? 0.0;

        return is_float($value) || is_int($value) ? (float) $value : 0.0;
    }

    protected function ReadAttributeInteger(string $name): int
    {
        return $this->attributes[$name] ?? 0;
    }

    protected function WriteAttributeInteger(string $name, int $value): void
    {
        $this->attributes[$name] = $value;
    }

    protected function RegisterMessage(int $senderID, int $message): void
    {
        unset($senderID, $message);
    }

    protected function UnregisterMessage(int $senderID, int $message): void
    {
        unset($senderID, $message);
    }
}

/** @param array<int,array<string,mixed>> $items */
function findStyleConfigurationItem(array $items, string $name): ?array
{
    foreach ($items as $item) {
        if (($item['name'] ?? null) === $name) {
            return $item;
        }
        if (isset($item['items']) && is_array($item['items'])) {
            $found = findStyleConfigurationItem($item['items'], $name);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

$harness = new IPSViewStyleConfigurationHelperHarness();
$harness->register();
$properties = $harness->properties();
$overrideProperties = $harness->overrideProperties();

if (count($overrideProperties) !== 15) {
    throw new RuntimeException('The shared IPSView configuration must register all 15 native color families.');
}
foreach ($overrideProperties as $propertyName) {
    if (($properties[$propertyName] ?? null) !== '[]') {
        throw new RuntimeException('A native IPSView override property was not registered with an empty default list.');
    }
}

$formItems = $harness->formItems();
$nativePanel = findStyleConfigurationItem($formItems, 'IPSViewStyleNativeColorsPanel');
if ($nativePanel === null || ($nativePanel['expanded'] ?? true) !== false) {
    throw new RuntimeException('The advanced native IPSView color panel must exist and be collapsed by default.');
}

$switchPanel = findStyleConfigurationItem($formItems, 'IPSViewStyleNativeFamily_switch');
if ($switchPanel === null) {
    throw new RuntimeException('The shared IPSView configuration is missing the Switch family.');
}
$switchList = $switchPanel['items'][0] ?? null;
if (!is_array($switchList) || ($switchList['name'] ?? null) !== 'IPSViewStyleNativeSwitchColors') {
    throw new RuntimeException('The Switch family does not use the shared native override property.');
}
$rows = $switchList['values'] ?? [];
if (count($rows) !== 4) {
    throw new RuntimeException('The Switch family must expose all four native IPSView switch colors.');
}

$harness->setProperty(
    'IPSViewStyleNativeSwitchColors',
    json_encode(
        [
            [
                'Override'    => true,
                'Field'       => 'SwitchTrackColorActive',
                'DerivedFrom' => 'Accent color',
                'Color'       => 0x123456
            ],
            [
                'Override'    => false,
                'Field'       => 'SwitchTrackColorInactive',
                'DerivedFrom' => 'Inactive control background',
                'Color'       => 0x654321
            ]
        ],
        JSON_THROW_ON_ERROR
    )
);
$overrides = $harness->overrides();
if (($overrides['SwitchTrackColorActive'] ?? null) !== '#123456') {
    throw new RuntimeException('An enabled native IPSView color override was not resolved.');
}
if (array_key_exists('SwitchTrackColorInactive', $overrides)) {
    throw new RuntimeException('A disabled native IPSView color override must continue to inherit its base color.');
}

$nativeTheme = $harness->nativeTheme();
if (count($nativeTheme['colors'] ?? []) !== 109) {
    throw new RuntimeException('The shared IPSView configuration must resolve the complete 109-field native theme.');
}
if (($nativeTheme['colors']['SwitchTrackColorActive']['R'] ?? null) !== 0x12
    || ($nativeTheme['colors']['SwitchTrackColorActive']['G'] ?? null) !== 0x34
    || ($nativeTheme['colors']['SwitchTrackColorActive']['B'] ?? null) !== 0x56) {
    throw new RuntimeException('The native IPSView theme does not contain the enabled Switch override.');
}
if (($nativeTheme['colors']['ShadowColor']['R'] ?? null) !== 0
    || ($nativeTheme['colors']['ShadowColor']['G'] ?? null) !== 0
    || ($nativeTheme['colors']['ShadowColor']['B'] ?? null) !== 0) {
    throw new RuntimeException('The shared CSS shadow color was not bridged to the native IPSView ShadowColor field.');
}

$harness->setProperty('IPSViewStyleSource', IPSViewStyleHelper::IPSVIEW_STYLE_SOURCE_DARK);
if ($harness->overrides() !== []) {
    throw new RuntimeException('Native overrides must only modify the editable custom style source.');
}

$harness->setTranslationLanguage('de');
$germanPanel = findStyleConfigurationItem($harness->formItems(), 'IPSViewStyleNativeColorsPanel');
if (($germanPanel['caption'] ?? null) !== 'Erweiterte IPSView-Farben') {
    throw new RuntimeException('The shared native IPSView color panel is not translated by the helper catalog.');
}

$elements = [
    [
        'type'    => 'Label',
        'caption' => 'Configure the shared IPSView style used by the standalone HTML page.'
    ]
];
if (!$harness->insertFormItems($elements)
    || findStyleConfigurationItem($elements, 'IPSViewStyleNativeColorsPanel') === null) {
    throw new RuntimeException('The existing style-form marker did not expand to the complete shared configuration.');
}

fwrite(STDOUT, "IPSView shared style configuration checks passed.\n");
