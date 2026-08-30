<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewFontCatalogHelper;
use Burki24\SymconModuleHelper\IPSViewStyleProfileHelper;

require_once __DIR__ . '/../src/IPSViewStyleProfileHelper.php';

$style = [
    'ViewBackground'            => '#f4f5f7',
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
    'ViewBackgroundOpacity'     => 100,
    'PageBackgroundOpacity'     => 100,
    'LabelBackgroundOpacity'    => 100,
    'ControlBackgroundOpacity'  => 100,
    'ControlActiveOpacity'      => 100,
    'ControlInactiveOpacity'    => 100,
    'PopupBackgroundOpacity'    => 100,
    'BorderOpacity'             => 100,
    'LineOpacity'               => 100,
    'PopupBorderOpacity'        => 100,
    'ShadowOpacity'             => 24,
    'PopupShadowOpacity'        => 32,
    'FontFamily'                => 'Open Sans',
    'FontStyle'                 => 'Bold Italic',
    'FontSize'                  => 16,
    'FontScale'                 => 100,
    'BorderRadius'              => 8,
    'BorderWidth'               => 1.0,
    'LineWidth'                 => 1,
    'ShadowBlur'                => 18,
    'ShadowSpread'              => 0,
    'ShadowOffsetX'             => 0,
    'ShadowOffsetY'             => 8,
    'DisabledOpacity'           => 52,
    'GradientStrength'          => 28
];

$assertInvalid = static function (callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
};

$contract = IPSViewStyleProfileHelper::contract();
assertSameValue('burki24.ipsview-style', $contract['schema'], 'The style profile schema identifier must remain stable.');
assertSameValue(1, $contract['version'], 'The first style profile contract must use version 1.');
assertSameValue(46, count(IPSViewStyleProfileHelper::styleFields()), 'Style Profile V1 must expose all 46 canonical source fields.');
assertTrueValue(in_array('FontStyle', IPSViewStyleProfileHelper::styleFields(), true), 'FontStyle must be part of the V1 contract.');
assertFalseValue(in_array('ColorScheme', IPSViewStyleProfileHelper::styleFields(), true), 'Derived color schemes must not be serialized.');
assertFalseValue(in_array('GradientAccent', IPSViewStyleProfileHelper::styleFields(), true), 'Derived CSS gradients must not be serialized.');
assertFalseValue(in_array('ControlSoftBackground', IPSViewStyleProfileHelper::styleFields(), true), 'Derived soft colors must not be serialized.');
assertFalseValue(in_array('BackgroundImage', IPSViewStyleProfileHelper::styleFields(), true), 'Assistant-specific background images must not be serialized.');
assertFalseValue(in_array('Scope', IPSViewStyleProfileHelper::styleFields(), true), 'Assistant application scopes must not be serialized.');

$profile = IPSViewStyleProfileHelper::create(
    '  Mein Hausdesign  ',
    $style,
    [
        'description' => ' Gemeinsamer Stil ',
        'createdBy'   => 'IPSViewAssistant',
        'createdAt'   => '2026-08-30T09:30:00+02:00'
    ]
);

assertSameValue('Mein Hausdesign', $profile['name'], 'Profile names must be trimmed.');
assertSameValue('Gemeinsamer Stil', $profile['description'], 'Descriptions must be normalized.');
assertSameValue('#F4F5F7', $profile['style']['ViewBackground'], 'Colors must normalize to uppercase #RRGGBB.');
assertSameValue(IPSViewFontCatalogHelper::FONT_OPEN_SANS, $profile['style']['FontFamily'], 'Readable font aliases must normalize to canonical IPSView values.');
assertSameValue(IPSViewFontCatalogHelper::STYLE_BOLD_ITALIC, $profile['style']['FontStyle'], 'Font cuts must normalize through the shared font catalogue.');
assertSameValue(8.0, $profile['style']['BorderRadius'], 'Numeric geometry values must normalize to floats.');
assertTrueValue(IPSViewStyleProfileHelper::isValid($profile), 'A normalized V1 profile must validate.');

$json = IPSViewStyleProfileHelper::encode($profile);
$decoded = IPSViewStyleProfileHelper::decode($json);
assertSameValue($profile, $decoded, 'Encoded profiles must round-trip without changing canonical values.');
assertTrueValue(str_ends_with($json, "\n"), 'Pretty profile JSON must end with one newline.');
assertTrueValue(IPSViewStyleProfileHelper::isValidJson($json), 'Encoded profile JSON must validate.');
$fractionalTimestamp = $profile;
$fractionalTimestamp['createdAt'] = '2026-08-30T09:30:00.123+02:00';
assertTrueValue(IPSViewStyleProfileHelper::isValid($fractionalTimestamp), 'RFC 3339 timestamps with fractional seconds must validate.');
assertFalseValue(IPSViewStyleProfileHelper::isValidJson('{broken'), 'Malformed JSON must not validate.');

$extended = $profile;
$extended['futureTopLevel'] = true;
$extended['style']['FutureStyleField'] = 'ignored';
$normalizedExtended = IPSViewStyleProfileHelper::normalize($extended);
assertFalseValue(array_key_exists('futureTopLevel', $normalizedExtended), 'Unknown top-level V1 fields must be tolerated but discarded.');
assertFalseValue(array_key_exists('FutureStyleField', $normalizedExtended['style']), 'Unknown style V1 fields must be tolerated but discarded.');

$systemStyle = $style;
$systemStyle['FontFamily'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
$systemStyle['FontStyle'] = 'bold';
$systemProfile = IPSViewStyleProfileHelper::create('System', $systemStyle);
assertSameValue(IPSViewStyleProfileHelper::FONT_SYSTEM, $systemProfile['style']['FontFamily'], 'The current shared system stack must normalize to the portable system sentinel.');
assertSameValue(IPSViewFontCatalogHelper::STYLE_BOLD, $systemProfile['style']['FontStyle'], 'System fonts may use standard browser cuts.');

$dancingStyle = $style;
$dancingStyle['FontFamily'] = 'Dancing Script';
$dancingStyle['FontStyle'] = 'bold';
$dancingProfile = IPSViewStyleProfileHelper::create('Dancing', $dancingStyle);
assertSameValue(IPSViewFontCatalogHelper::STYLE_BOLD, $dancingProfile['style']['FontStyle'], 'Available native cuts must validate.');

$invalidDancing = $dancingStyle;
$invalidDancing['FontStyle'] = 'italic';
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::create('Invalid dancing', $invalidDancing),
    'Unavailable native font cuts must be rejected.'
);

$missingField = $style;
unset($missingField['ShadowBlur']);
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::create('Missing field', $missingField),
    'Style Profile V1 must reject incomplete snapshots.'
);

$invalidColor = $style;
$invalidColor['Accent'] = 'red';
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::create('Invalid color', $invalidColor),
    'Colors outside #RRGGBB must be rejected.'
);

$invalidOpacity = $style;
$invalidOpacity['ShadowOpacity'] = 101;
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::create('Invalid opacity', $invalidOpacity),
    'Opacity values outside 0..100 must be rejected.'
);

$invalidGeometry = $style;
$invalidGeometry['ShadowSpread'] = -21;
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::create('Invalid geometry', $invalidGeometry),
    'Geometry values outside the central StyleHelper range must be rejected.'
);

$wrongSchema = $profile;
$wrongSchema['schema'] = 'other.schema';
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::normalize($wrongSchema),
    'Unknown style profile schemas must be rejected.'
);

$futureVersion = $profile;
$futureVersion['version'] = 2;
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::normalize($futureVersion),
    'Unsupported future profile versions must be rejected explicitly.'
);

$invalidTimestamp = $profile;
$invalidTimestamp['createdAt'] = '2026-08-30 09:30:00';
$assertInvalid(
    static fn () => IPSViewStyleProfileHelper::normalize($invalidTimestamp),
    'createdAt must use an RFC 3339 timestamp with timezone.'
);

fwrite(STDOUT, "IPSView style profile tests passed.\n");
