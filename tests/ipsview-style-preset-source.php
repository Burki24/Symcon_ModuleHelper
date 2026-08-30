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
assertSameValue(5, IPSViewStylePresetSourceHarness::IPSVIEW_STYLE_SOURCE_PRESET, 'The shared preset source must use source ID 5.');
assertSameValue(
    IPSViewStylePresetHelper::PRESET_STANDARD,
    $properties['IPSViewStylePreset'],
    'The shared preset property must default to IPSView Standard.'
);

$formJSON = json_encode($harness->formItems(), JSON_THROW_ON_ERROR);
assertTrueValue(str_contains($formJSON, 'Shared preset'), 'The style source selector must expose the shared preset source.');
assertTrueValue(str_contains($formJSON, 'IPSViewStylePreset'), 'The style form must expose the centralized preset selector.');
foreach (IPSViewStylePresetHelper::ids() as $preset) {
    assertTrueValue(
        str_contains($formJSON, '"value":"' . $preset . '"'),
        sprintf('The preset selector must expose %s.', $preset)
    );
}

$harness->setProperty('IPSViewStyleSource', 5);
assertSameValue(5, $harness->source(), 'The shared preset source must be accepted.');

foreach (IPSViewStylePresetHelper::ids() as $preset) {
    $harness->setProperty('IPSViewStylePreset', $preset);
    assertSameValue($preset, $harness->preset(), sprintf('%s must remain the active preset.', $preset));

    $palette = IPSViewStylePresetHelper::palette($preset);
    $style = $harness->style();
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND],
        $style['ViewBackground'],
        sprintf('%s must use the centralized view background.', $preset)
    );
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_SURFACE],
        $style['ControlBackground'],
        sprintf('%s must use the centralized surface color.', $preset)
    );
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_ACTIVE],
        $style['ControlActiveBackground'],
        sprintf('%s must use the centralized active color.', $preset)
    );
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_ACCENT],
        $style['Accent'],
        sprintf('%s must use the centralized accent color.', $preset)
    );
    assertSameValue(
        $palette[IPSViewStylePresetHelper::ROLE_ERROR],
        $style['Critical'],
        sprintf('%s must use the centralized error color.', $preset)
    );
}

$harness->setProperty('IPSViewStylePreset', 'unknown');
assertSameValue(
    IPSViewStylePresetHelper::PRESET_STANDARD,
    $harness->preset(),
    'Unknown preset identifiers must fall back safely to IPSView Standard.'
);
$standardStyle = $harness->style();
assertSameValue('#404040', $standardStyle['ViewBackground'], 'The safe preset fallback must resolve the standard palette.');

$harness->setProperty('IPSViewStyleSource', 2);
$legacyLight = $harness->style();
assertSameValue('#FFFFFF', $legacyLight['ControlBackground'], 'The existing light source must keep its previous rendering.');
$harness->setProperty('IPSViewStyleSource', 3);
$legacyDark = $harness->style();
assertSameValue('#1B2639', $legacyDark['ControlBackground'], 'The existing dark source must keep its previous rendering.');

fwrite(STDOUT, "IPSView shared preset source tests passed.\n");
