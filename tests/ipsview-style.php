<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewStyleHelper;

require_once __DIR__ . '/../src/IPSViewStyleHelper.php';

final class IPSViewStyleHelperHarness
{
    use IPSViewStyleHelper;

    /** @var array<string,mixed> */
    private array $properties = [];

    /** @var array<int,array{action:string,senderID:int,message:int}> */
    private array $messages = [];

    /** @var array<string,int> */
    private array $attributes = [];

    public function register(): void
    {
        $this->RegisterIPSViewStyleProperties();
    }

    /** @return array<int,array<string,mixed>> */
    public function formItems(string $width = '240px'): array
    {
        return $this->IPSViewStyleFormItems($width);
    }

    /** @return array<string,string|float> */
    public function style(?string $document = null): array
    {
        return $this->IPSViewResolvedStyle($document);
    }

    public function css(string $selector = ':root', ?string $document = null): string
    {
        return $this->IPSViewStyleCSSVariables($selector, $document);
    }

    public function source(): int
    {
        return $this->IPSViewStyleSource();
    }

    public function mediaID(): int
    {
        return $this->IPSViewStyleMediaID();
    }

    public function registerMediaMessages(): void
    {
        $this->RegisterIPSViewStyleMediaMessages();
    }

    public function isMediaUpdate(int $senderID, int $message): bool
    {
        return $this->IsIPSViewStyleMediaUpdate($senderID, $message);
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
        $this->messages[] = ['action' => 'register', 'senderID' => $senderID, 'message' => $message];
    }

    protected function UnregisterMessage(int $senderID, int $message): void
    {
        $this->messages[] = ['action' => 'unregister', 'senderID' => $senderID, 'message' => $message];
    }
}

/** @return array{A:int,R:int,G:int,B:int,Type:int,Pattern:string} */
function ipsViewTestColor(int $red, int $green, int $blue, int $alpha = 255): array
{
    return [
        'A'       => $alpha,
        'R'       => $red,
        'G'       => $green,
        'B'       => $blue,
        'Type'    => 0,
        'Pattern' => '12'
    ];
}

$harness = new IPSViewStyleHelperHarness();
$harness->register();
$properties = $harness->properties();

assertSameValue(0, $properties['IPSViewStyleSource'], 'The universal style must default to the custom source.');
assertSameValue(0, $properties['IPSViewStyleMediaID'], 'No IPSView media object may be selected by default.');
assertSameValue(false, $properties['IPSViewStyleTransparentBackground'], 'The background must be opaque by default.');
assertSameValue(100, $properties['IPSViewStyleFontScale'], 'The default font scale must be 100 percent.');
assertSameValue(0xF4F5F7, $properties['IPSViewStyleViewBackgroundColor'], 'The helper must own neutral custom defaults.');
assertSameValue(0x56C881, $properties['IPSViewStylePositiveColor'], 'Positive status colors must use universal terminology.');
assertSameValue(0xE36D6D, $properties['IPSViewStyleCriticalColor'], 'Critical status colors must use universal terminology.');
assertSameValue(52, $properties['IPSViewStyleDisabledOpacity'], 'Inactive control opacity must be centralized.');
assertSameValue(28, $properties['IPSViewStyleGradientStrength'], 'Gradient strength must be centralized.');

$formItems = $harness->formItems('230px');
assertSameValue('Label', $formItems[0]['type'], 'The form must start with a shared-style explanation.');
assertSameValue('RowLayout', $formItems[1]['type'], 'The source settings must be grouped in one row.');
assertSameValue('Select', $formItems[1]['items'][0]['type'], 'The style source must use a Select control.');
assertSameValue('SelectMedia', $formItems[1]['items'][1]['type'], 'The IPSView source must use a media selector.');
$formJSON = json_encode($formItems, JSON_THROW_ON_ERROR);
assertFalseValue(str_contains($formJSON, 'Alarm'), 'Universal style labels must not contain alarm-specific terminology.');
assertFalseValue(str_contains($formJSON, 'delay color'), 'Universal style labels must not contain module-specific delay terminology.');
assertTrueValue(str_contains($formJSON, 'Positive status'), 'The form must expose a universal positive status color.');
assertTrueValue(str_contains($formJSON, 'Critical status'), 'The form must expose a universal critical status color.');
assertSameValue(21, substr_count($formJSON, 'SelectColor'), 'The custom source must expose all universal color roles.');
assertTrueValue(str_contains($formJSON, 'Gradient strength'), 'Shared gradient strength must be configured centrally.');
assertTrueValue(str_contains($formJSON, 'Inactive opacity'), 'Shared inactive opacity must be configured centrally.');
assertTrueValue(str_contains($formJSON, '230px'), 'The requested color-control width must be applied.');

$custom = $harness->style();
assertSameValue('#F4F5F7', $custom['ViewBackground'], 'Custom mode must use the shared neutral view background.');
assertSameValue('#56C881', $custom['Positive'], 'Custom mode must expose the positive semantic role.');
assertSameValue('#E36D6D', $custom['Critical'], 'Custom mode must expose the critical semantic role.');
assertTrueValue(str_starts_with((string) $custom['GradientPositive'], 'linear-gradient('), 'Positive gradients must be generated by the helper.');
assertTrueValue(str_starts_with((string) $custom['GradientCritical'], 'linear-gradient('), 'Critical gradients must be generated by the helper.');
assertSameValue(0.52, $custom['DisabledOpacity'], 'The inactive-control opacity must be resolved as a CSS fraction.');

$customCSS = $harness->css('.module-view');
assertTrueValue(str_starts_with($customCSS, ".module-view {\n"), 'CSS variables must use the requested selector.');
assertTrueValue(str_contains($customCSS, '--ipsview-control-background:'), 'CSS must expose the universal control background.');
assertTrueValue(str_contains($customCSS, '--ipsview-control-background-active:'), 'CSS must expose the universal active background.');
assertTrueValue(str_contains($customCSS, '--ipsview-text-inactive:'), 'CSS must expose inactive text.');
assertTrueValue(str_contains($customCSS, '--ipsview-information:'), 'CSS must expose an information role.');
assertTrueValue(str_contains($customCSS, '--ipsview-positive:'), 'CSS must expose a positive role.');
assertTrueValue(str_contains($customCSS, '--ipsview-critical:'), 'CSS must expose a critical role.');
assertTrueValue(str_contains($customCSS, '--ipsview-gradient-warning:'), 'CSS must expose centralized semantic gradients.');
assertTrueValue(str_contains($customCSS, '--ipsview-disabled-opacity: 0.52;'), 'CSS must expose centralized inactive opacity.');
assertTrueValue(str_contains($customCSS, '--ipsview-shadow:'), 'CSS must expose the shared box shadow.');
assertTrueValue(str_contains($customCSS, '--ipsview-surface:'), 'CSS must retain compatibility aliases for current consumers.');
assertTrueValue(str_contains($customCSS, '--ipsview-danger:'), 'CSS must retain the legacy critical-color alias.');

$ipsViewDocument = [
    'DefaultFontFamily'   => 'Roboto',
    'DefaultFontSize'     => 11,
    'DefaultBorderRadius' => 6,
    'DefaultBorderWidth'  => 1.5,
    'ColorText'           => ipsViewTestColor(255, 255, 255),
    'ColorTextOn'         => ipsViewTestColor(255, 255, 255),
    'ColorTextOff'        => ipsViewTestColor(180, 180, 180),
    'ColorTextLabel'      => ipsViewTestColor(255, 255, 255),
    'ColorBack'           => ipsViewTestColor(47, 47, 47),
    'ColorBackOn'         => ipsViewTestColor(127, 127, 127),
    'ColorBackOff'        => ipsViewTestColor(38, 38, 38),
    'ColorBackLabel'      => ipsViewTestColor(255, 255, 255, 0),
    'ColorBorder'         => ipsViewTestColor(127, 127, 127),
    'ColorLine'           => ipsViewTestColor(96, 96, 96),
    'LineWidth'           => 1.5,
    'ColorPage'           => ipsViewTestColor(147, 137, 83),
    'ColorIcon'           => ipsViewTestColor(255, 255, 255),
    'ColorPopupShadow'    => ipsViewTestColor(0, 0, 0),
    'ColorPopupBack'      => ipsViewTestColor(37, 37, 37),
    'ColorPopupBorder'    => ipsViewTestColor(127, 127, 127),
    'SliderTrackColorActive' => ipsViewTestColor(0, 122, 255),
    'CalendarTodayHighlightColor' => ipsViewTestColor(32, 160, 245),
    'ShadowColor'         => ipsViewTestColor(13, 13, 13),
    'ShadowSpreadRadius'  => 2,
    'ShadowBlurRadius'    => 5,
    'ShadowOffsetX'       => 3,
    'ShadowOffsetY'       => 3,
    'Colors'              => [
        [...ipsViewTestColor(60, 184, 0), 'Name' => 'Grün'],
        [...ipsViewTestColor(240, 215, 0), 'Name' => 'Gelb'],
        [...ipsViewTestColor(186, 22, 10), 'Name' => 'Rot']
    ]
];
$documentJSON = json_encode($ipsViewDocument, JSON_THROW_ON_ERROR);
$harness->setProperty('IPSViewStyleSource', 1);
$harness->setProperty('IPSViewStyleMediaID', 34100);
$mediaStyle = $harness->style($documentJSON);
assertSameValue('#938953', $mediaStyle['ViewBackground'], 'The IPSView page color must become the shared view background.');
assertSameValue('#2F2F2F', $mediaStyle['ControlBackground'], 'The IPSView standard control background must be imported.');
assertSameValue('#7F7F7F', $mediaStyle['ControlActiveBackground'], 'The IPSView active control background must be imported.');
assertSameValue('rgba(255, 255, 255, 0.000)', $mediaStyle['LabelBackground'], 'Transparent IPSView label backgrounds must retain their alpha channel.');
assertSameValue('#007AFF', $mediaStyle['Accent'], 'The active IPSView slider color must become the universal accent.');
assertSameValue('#20A0F5', $mediaStyle['Information'], 'The IPSView calendar highlight must become the universal information color.');
assertSameValue('#3CB800', $mediaStyle['Positive'], 'A named green favorite must become the universal positive color.');
assertSameValue('#F0D700', $mediaStyle['Warning'], 'A named yellow favorite must become the universal warning color.');
assertSameValue('#BA160A', $mediaStyle['Critical'], 'A named red favorite must become the universal critical color.');
assertSameValue('Roboto', $mediaStyle['FontFamily'], 'The IPSView standard font family must be imported.');
assertSameValue(11.0, $mediaStyle['FontSize'], 'The IPSView standard font size must be imported.');
assertSameValue(6.0, $mediaStyle['BorderRadius'], 'The IPSView standard border radius must be imported.');
assertSameValue(1.5, $mediaStyle['BorderWidth'], 'The IPSView standard border width must be imported.');
assertTrueValue(str_contains((string) $mediaStyle['Shadow'], '3px 3px 5px 2px'), 'IPSView shadow geometry must be translated into CSS.');

$base64Style = $harness->style(base64_encode($documentJSON));
assertSameValue($mediaStyle['Positive'], $base64Style['Positive'], 'Base64-encoded IPSView documents must be supported.');
assertSameValue($mediaStyle['FontFamily'], $base64Style['FontFamily'], 'Base64 documents must preserve standard typography.');

$harness->setProperty('IPSViewStyleTransparentBackground', true);
$harness->setProperty('IPSViewStyleFontScale', 115);
$mediaCSS = $harness->css(':root', $documentJSON);
assertTrueValue(str_contains($mediaCSS, '--ipsview-background: transparent;'), 'Transparent mode must override only the outer background token.');
assertTrueValue(str_contains($mediaCSS, '--ipsview-font-scale: 1.15;'), 'The shared font scale must be rendered as a CSS factor.');
assertTrueValue(str_contains($mediaCSS, '--ipsview-gradient-critical: linear-gradient('), 'Imported IPSView styles must receive the same centralized gradients.');

$harness->registerMediaMessages();
assertSameValue(
    [['action' => 'register', 'senderID' => 34100, 'message' => 10905]],
    $harness->messages(),
    'The selected IPSView media object must be monitored for MM_UPDATE.'
);
assertTrueValue($harness->isMediaUpdate(34100, 10905), 'The helper must identify updates from the active IPSView media object.');
assertFalseValue($harness->isMediaUpdate(34101, 10905), 'Updates from other media objects must be ignored.');

$harness->setProperty('IPSViewStyleMediaID', 34101);
$harness->registerMediaMessages();
assertSameValue(
    [
        ['action' => 'register', 'senderID' => 34100, 'message' => 10905],
        ['action' => 'unregister', 'senderID' => 34100, 'message' => 10905],
        ['action' => 'register', 'senderID' => 34101, 'message' => 10905]
    ],
    $harness->messages(),
    'Changing the IPSView media source must remove the old message registration.'
);

$harness->setProperty('IPSViewStyleSource', 2);
$light = $harness->style();
assertSameValue('#FFFFFF', $light['ControlBackground'], 'The light preset must be fully owned by the helper.');
$harness->setProperty('IPSViewStyleSource', 3);
$dark = $harness->style();
assertSameValue('#1B2639', $dark['ControlBackground'], 'The dark preset must be fully owned by the helper.');
assertSameValue('dark', $dark['ColorScheme'], 'The dark preset must announce a dark color scheme.');

$harness->setProperty('IPSViewStyleSource', 99);
assertSameValue(0, $harness->source(), 'Unknown style sources must fall back to the custom source.');
$harness->setProperty('IPSViewStyleMediaID', -10);
assertSameValue(0, $harness->mediaID(), 'Negative media IDs must be normalized to zero.');

try {
    $harness->formItems('  ');
    throw new RuntimeException('An empty color-control width must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'width'), 'Empty width errors must explain the invalid argument.');
}

try {
    $harness->css(':root { color: red; }');
    throw new RuntimeException('CSS rule delimiters in the selector must throw InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'selector'), 'Invalid selector errors must explain the invalid argument.');
}

fwrite(STDOUT, "IPSViewStyleHelper tests passed.\n");
