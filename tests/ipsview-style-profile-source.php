<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewFontCatalogHelper;
use Burki24\SymconModuleHelper\IPSViewStyleHelper;
use Burki24\SymconModuleHelper\IPSViewStyleProfileHelper;

require_once __DIR__ . '/../src/IPSViewStyleHelper.php';

final class IPSViewStyleProfileSourceHarness
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
    public function style(?string $document = null): array
    {
        return $this->IPSViewResolvedStyle($document);
    }

    public function css(?string $document = null): string
    {
        return $this->IPSViewStyleCSSVariables(':root', $document);
    }

    public function rootFontSize(?string $document = null): string
    {
        return $this->IPSViewStyleRootFontSize($document);
    }

    public function source(): int
    {
        return $this->IPSViewStyleSource();
    }

    public function profileMediaID(): int
    {
        return $this->IPSViewStyleProfileMediaID();
    }

    public function registerMediaMessages(): void
    {
        $this->RegisterIPSViewStyleMediaMessages();
    }

    public function isMediaUpdate(int $senderID, int $message): bool
    {
        return $this->IsIPSViewStyleMediaUpdate($senderID, $message);
    }

    /** @return array<int,array{action:string,senderID:int,message:int}> */
    public function messages(): array
    {
        return $this->messages;
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

$profileStyle = [
    'ViewBackground'            => '#123456',
    'PageBackground'            => '#F4F5F7',
    'LabelBackground'           => '#FFFFFF',
    'ControlBackground'         => '#FFFFFF',
    'ControlActiveBackground'   => '#E9EDF2',
    'ControlInactiveBackground' => '#F1F3F4',
    'Text'                      => '#202124',
    'TextActive'                => '#202124',
    'TextInactive'              => '#6F7378',
    'LabelText'                 => '#202124',
    'Icon'                      => '#5F6368',
    'Border'                    => '#C6CBD2',
    'Line'                      => '#D8DDE4',
    'PopupBackground'           => '#FFFFFF',
    'PopupBorder'               => '#C6CBD2',
    'Accent'                    => '#55CBB5',
    'Information'               => '#4A90E2',
    'Positive'                  => '#56C881',
    'Warning'                   => '#E6A93F',
    'Critical'                  => '#E36D6D',
    'ShadowColor'               => '#000000',
    'ViewBackgroundOpacity'     => 85,
    'PageBackgroundOpacity'     => 100,
    'LabelBackgroundOpacity'    => 0,
    'ControlBackgroundOpacity'  => 72,
    'ControlActiveOpacity'      => 64,
    'ControlInactiveOpacity'    => 48,
    'PopupBackgroundOpacity'    => 88,
    'BorderOpacity'             => 50,
    'LineOpacity'               => 40,
    'PopupBorderOpacity'        => 60,
    'ShadowOpacity'             => 20,
    'PopupShadowOpacity'        => 35,
    'FontFamily'                => 'Open Sans',
    'FontStyle'                 => 'Bold Italic',
    'FontSize'                  => 16,
    'FontScale'                 => 125,
    'BorderRadius'              => 9,
    'BorderWidth'               => 1.5,
    'LineWidth'                 => 1,
    'ShadowBlur'                => 18,
    'ShadowSpread'              => 0,
    'ShadowOffsetX'             => 0,
    'ShadowOffsetY'             => 8,
    'DisabledOpacity'           => 66,
    'GradientStrength'          => 0
];
$profile = IPSViewStyleProfileHelper::create('Profile source test', $profileStyle);
$profileJSON = IPSViewStyleProfileHelper::encode($profile, false);

$harness = new IPSViewStyleProfileSourceHarness();
$harness->register();
$properties = $harness->properties();

assertSameValue(0, IPSViewStyleProfileSourceHarness::IPSVIEW_STYLE_SOURCE_CUSTOM, 'Custom source ID must remain unchanged.');
assertSameValue(1, IPSViewStyleProfileSourceHarness::IPSVIEW_STYLE_SOURCE_MEDIA, 'IPSView media source ID must remain unchanged.');
assertSameValue(2, IPSViewStyleProfileSourceHarness::IPSVIEW_STYLE_SOURCE_LIGHT, 'Light source ID must remain unchanged.');
assertSameValue(3, IPSViewStyleProfileSourceHarness::IPSVIEW_STYLE_SOURCE_DARK, 'Dark source ID must remain unchanged.');
assertSameValue(4, IPSViewStyleProfileSourceHarness::IPSVIEW_STYLE_SOURCE_PROFILE, 'Style Profile must use source ID 4.');
assertSameValue(0, $properties['IPSViewStyleProfileMediaID'], 'No Style Profile media object may be selected by default.');

$formJSON = json_encode($harness->formItems(), JSON_THROW_ON_ERROR);
assertTrueValue(str_contains($formJSON, 'Style profile'), 'The source selector must expose Style Profile V1.');
assertTrueValue(str_contains($formJSON, 'IPSViewStyleProfileMediaID'), 'The form must expose a dedicated Style Profile media selector.');

$harness->setProperty('IPSViewStyleSource', 4);
$harness->setProperty('IPSViewStyleProfileMediaID', 55200);
$harness->setProperty('IPSViewStyleFontScale', 200);
$harness->setProperty('IPSViewStyleDisabledOpacity', 10);
$harness->setProperty('IPSViewStyleGradientStrength', 80);

assertSameValue(4, $harness->source(), 'The Style Profile source must be accepted.');
assertSameValue(55200, $harness->profileMediaID(), 'The selected Style Profile media ID must be exposed.');

$style = $harness->style($profileJSON);
assertSameValue('rgba(18, 52, 86, 0.850)', $style['ViewBackground'], 'Profile view opacity must be applied to its canonical color.');
assertSameValue('rgba(255, 255, 255, 0.720)', $style['ControlBackground'], 'Profile control opacity must be applied independently.');
assertSameValue('rgba(255, 255, 255, 0.000)', $style['LabelBackground'], 'Profile surfaces must support full transparency.');
assertSameValue(IPSViewFontCatalogHelper::FONT_OPEN_SANS, $style['FontFamily'], 'Profile fonts must use the shared canonical family.');
assertSameValue(IPSViewFontCatalogHelper::STYLE_BOLD_ITALIC, $style['FontStyle'], 'Profile font cuts must reach the resolved style.');
assertSameValue(1.25, $style['FontScale'], 'Profile font scale must override the instance-level custom value.');
assertSameValue(0.66, $style['DisabledOpacity'], 'Profile inactive opacity must override the instance-level custom value.');
assertSameValue(0.2, $style['ShadowOpacity'], 'Profile shadow opacity must be preserved.');
assertSameValue(0.35, $style['PopupShadowOpacity'], 'Profile popup shadow opacity must be preserved.');
assertSameValue('20px', $harness->rootFontSize($profileJSON), 'Profile font size and scale must determine the root font size.');

$css = $harness->css($profileJSON);
assertTrueValue(str_contains($css, '--ipsview-font-style: italic;'), 'Bold Italic profiles must expose an italic CSS font style.');
assertTrueValue(str_contains($css, '--ipsview-font-weight: 700;'), 'Bold Italic profiles must expose a bold CSS font weight.');
assertTrueValue(str_contains($css, '--ipsview-font-scale: 1.25;'), 'Profile font scale must be exposed as the shared CSS factor.');
assertTrueValue(str_contains($css, '--ipsview-disabled-opacity: 0.66;'), 'Profile inactive opacity must be exposed through CSS.');

$base64Profile = $harness->style(base64_encode($profileJSON));
assertSameValue($style['FontStyle'], $base64Profile['FontStyle'], 'Base64-encoded profile JSON must be accepted for compatibility with media payloads.');

$harness->registerMediaMessages();
assertSameValue(
    [['action' => 'register', 'senderID' => 55200, 'message' => 10905]],
    $harness->messages(),
    'The active Style Profile media object must be monitored for MM_UPDATE.'
);
assertTrueValue($harness->isMediaUpdate(55200, 10905), 'Updates from the active Style Profile media must be recognized.');
assertFalseValue($harness->isMediaUpdate(55201, 10905), 'Updates from unrelated media objects must be ignored.');

$harness->setProperty('IPSViewStyleSource', 1);
$harness->setProperty('IPSViewStyleMediaID', 34100);
$harness->registerMediaMessages();
assertSameValue(
    [
        ['action' => 'register', 'senderID' => 55200, 'message' => 10905],
        ['action' => 'unregister', 'senderID' => 55200, 'message' => 10905],
        ['action' => 'register', 'senderID' => 34100, 'message' => 10905]
    ],
    $harness->messages(),
    'Switching between media-backed sources must move the update registration safely.'
);

$harness->setProperty('IPSViewStyleSource', 4);
$invalidStyle = $harness->style('{broken');
assertSameValue('#FFFFFF', $invalidStyle['ControlBackground'], 'Invalid Style Profile JSON must fall back safely to the light preset.');

$futureProfile = $profile;
$futureProfile['version'] = 2;
$futureJSON = json_encode($futureProfile, JSON_THROW_ON_ERROR);
$futureStyle = $harness->style($futureJSON);
assertSameValue('#FFFFFF', $futureStyle['ControlBackground'], 'Unsupported future profile versions must fall back safely to the light preset.');

$harness->setProperty('IPSViewStyleProfileMediaID', -10);
assertSameValue(0, $harness->profileMediaID(), 'Negative Style Profile media IDs must normalize to zero.');
$harness->setProperty('IPSViewStyleSource', 99);
assertSameValue(0, $harness->source(), 'Unknown source IDs must continue to fall back to the custom source.');

fwrite(STDOUT, "IPSView Style Profile source tests passed.\n");
