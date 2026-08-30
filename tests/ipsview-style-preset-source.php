<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewStyleHelper;
use Burki24\SymconModuleHelper\IPSViewStylePresetHelper;

require_once __DIR__ . '/../src/IPSViewStyleHelper.php';

final class IPSViewStylePresetSourceHarness
{
    use IPSViewStyleHelper;

    /** @var array<string,mixed> */
    private array $properties = [];

    /** @var array<string,int> */
    private array $attributes = [];

    /** @var array<int,array{action:string,senderID:int,message:int}> */
    private array $messages = [];

    public function register(): void
    {
        $this->RegisterIPSViewStyleProperties();
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

    /** @return array<int,array<string,mixed>> */
    public function formItems(): array
    {
        return $this->IPSViewStyleFormItems();
    }

    /** @return array<string,string|float> */
    public function style(): array
    {
        return $this->IPSViewResolvedStyle();
    }

    public function source(): int
    {
        return $this->IPSViewStyleSource();
    }

    public function preset(): string
    {
        return $this->IPSViewStylePreset();
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

        return is_int($value) || is_float($value) ? (float) $value : 0.0;
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
        $this->messages[] = ['action' => 'register', 'senderID' => $senderID, 'message' => $message];
    }

    protected function UnregisterMessage(int $senderID, int $message): void
    {
        $this->messages[] = ['action' => 'unregister', 'senderID' => $senderID, 'message' => $message];
    }
}

$harness = new IPSViewStylePresetSourceHarness();
$harness->register();
$properties = $harness->properties();

assertSameValue(0, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_CUSTOM, 'Custom source ID must remain unchanged.');
assertSameValue(1, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_MEDIA, 'IPSView media source ID must remain unchanged.');
assertSameValue(2, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_LIGHT, 'Legacy light source ID must remain unchanged.');
assertSameValue(3, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_DARK, 'Legacy dark source ID must remain unchanged.');
assertSameValue(4, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PROFILE, 'Style Profile source ID must remain unchanged.');
assertSameValue(5, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET, 'The legacy generic preset source must keep source ID 5.');
assertSameValue(6, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_LIGHT, 'Central Light must use source ID 6.');
assertSameValue(7, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_DARK, 'Central Dark must use source ID 7.');
assertSameValue(8, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_WARM, 'Central Warm must use source ID 8.');
assertSameValue(9, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_COOL, 'Central Cool must use source ID 9.');
assertSameValue(10, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_EARTHY, 'Central Earthy must use source ID 10.');
assertSameValue(11, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_WATER, 'Central Water must use source ID 11.');
assertSameValue(12, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_SUNNY, 'Central Sunny must use source ID 12.');
assertSameValue(
    IPSViewStylePresetHelper::PRESET_STANDARD,
    $properties['IPSViewStylePreset'],
    'The legacy preset property must remain available for stored source-5 configurations.'
);

$formJSON = json_encode($harness->formItems(), JSON_THROW_ON_ERROR);
assertTrueValue(!str_contains($formJSON, '"name":"IPSViewStylePreset"'), 'The form must no longer expose a second preset selector.');
assertTrueValue(!str_contains($formJSON, '"value":5'), 'The generic preset source must be hidden for new configurations.');

$directSources = [
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_LIGHT  => IPSViewStylePresetHelper::PRESET_LIGHT,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_DARK   => IPSViewStylePresetHelper::PRESET_DARK,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_WARM   => IPSViewStylePresetHelper::PRESET_WARM,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_COOL   => IPSViewStylePresetHelper::PRESET_COOL,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_EARTHY => IPSViewStylePresetHelper::PRESET_EARTHY,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_WATER  => IPSViewStylePresetHelper::PRESET_WATER,
    IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET_SUNNY  => IPSViewStylePresetHelper::PRESET_SUNNY
];

foreach ($directSources as $source => $preset) {
    assertTrueValue(
        str_contains($formJSON, '"value":' . $source),
        sprintf('Direct source %d must be available in the style source selector.', $source)
    );

    $harness->setProperty('IPSViewStyleSource', $source);
    assertSameValue($source, $harness->source(), sprintf('Direct source %d must be accepted.', $source));

    $palette = IPSViewStylePresetHelper::palette($preset);
    $style = $harness->style();
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND],
        $style['ViewBackground'],
        sprintf('%s must use the centralized view background.', $preset)
    );
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_ACCENT],
        $style['Accent'],
        sprintf('%s must use the centralized accent color.', $preset)
    );
}

$harness->setProperty('IPSViewStyleSource', 5);
$harness->setProperty('IPSViewStylePreset', IPSViewStylePresetHelper::PRESET_WARM);
assertSameValue(5, $harness->source(), 'Stored generic preset source 5 must remain accepted.');
$legacyFormJSON = json_encode($harness->formItems(), JSON_THROW_ON_ERROR);
assertTrueValue(str_contains($legacyFormJSON, '"value":5'), 'Stored source 5 must remain representable in the form.');
assertTrueValue(str_contains($legacyFormJSON, 'previous selection'), 'Stored source 5 must be marked as a previous selection.');
$legacyPalette = IPSViewStylePresetHelper::palette(IPSViewStylePresetHelper::PRESET_WARM);
assertSameValue(
    $legacyPalette[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND],
    $harness->style()['ViewBackground'],
    'Stored source 5 must continue resolving its previously selected preset.'
);

$harness->setProperty('IPSViewStyleSource', 2);
$legacyLight = $harness->style();
assertSameValue('#FFFFFF', $legacyLight['ControlBackground'], 'The existing light source must keep its previous rendering.');
$harness->setProperty('IPSViewStyleSource', 3);
$legacyDark = $harness->style();
assertSameValue('#1B2639', $legacyDark['ControlBackground'], 'The existing dark source must keep its previous rendering.');

fwrite(STDOUT, "IPSView direct preset source tests passed.\n");
