<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewControlThemeHelper;
use Burki24\SymconModuleHelper\IPSViewStylePresetHelper;
use Burki24\SymconModuleHelper\IPSViewStyleProfileHelper;

require_once __DIR__ . '/../src/IPSViewControlThemeHelper.php';
require_once __DIR__ . '/../src/IPSViewStyleProfileHelper.php';

$fields = IPSViewControlThemeHelper::fields();
assertSameValue(109, count($fields), 'The native IPSView color catalogue must expose all 109 known fields.');
assertSameValue(109, count(array_unique($fields)), 'Native IPSView color field names must be unique.');

$families = IPSViewControlThemeHelper::families();
assertSameValue(15, count($families), 'The native IPSView catalogue must expose 15 color families.');
assertSameValue(17, count($families[IPSViewControlThemeHelper::FAMILY_BASE]), 'Base family size changed unexpectedly.');
assertSameValue(12, count($families[IPSViewControlThemeHelper::FAMILY_FLOW]), 'Flow family size changed unexpectedly.');
assertSameValue(10, count($families[IPSViewControlThemeHelper::FAMILY_CALENDAR]), 'Calendar family size changed unexpectedly.');

$brownson2023Fields = [
    'ColorText',
    'ColorTextOn',
    'ColorTextOff',
    'ColorTextLabel',
    'ColorBack',
    'ColorBackOff',
    'ColorBackOn',
    'ColorBackLabel',
    'ColorBorder',
    'ColorLine',
    'ColorBorderLabel',
    'ColorView',
    'ColorPage',
    'ColorIcon',
    'ColorPopupShadow',
    'ColorPopupBack',
    'ColorPopupBorder',
    'ColorAssocTextOn',
    'ColorAssocTextOff',
    'ColorAssocBackOn',
    'ColorAssocBackOff',
    'ColorAssocBorder',
    'ColorAssocShadow',
    'ColorTabTextOn',
    'ColorTabTextOff',
    'ColorTabBackOn',
    'SwitchTrackColorActive',
    'SwitchTrackColorInactive',
    'SwitchThumbColorActive',
    'SwitchThumbColorInactive',
    'SliderTrackColorActive',
    'SliderTrackColorInactive',
    'SliderTickColorActive',
    'SliderTickColorInactive',
    'SliderThumbColorInner',
    'SliderThumbColorOuter',
    'ProgressbarTrackColorActive',
    'ProgressbarTrackColorInactive',
    'ProgressbarTickColorActive',
    'ProgressbarTickColorInactive',
    'ProgressbarThumbColorInner',
    'ProgressbarThumbColorOuter',
    'CircleTrackColorActive',
    'CircleTrackColorInactive',
    'CircleTickColorActive',
    'CircleTickColorInactive',
    'CircleThumbColorInner',
    'CircleThumbColorOuter',
    'FlowBorderColorPositive',
    'FlowBorderColorNegative',
    'FlowBorderColorNeutral',
    'FlowLineColorPositive',
    'FlowLineColorNegative',
    'FlowLineColorNeutral',
    'FlowTextColorPositive',
    'FlowTextColorNegative',
    'FlowTextColorNeutral',
    'FlowAnimationColorPositive',
    'FlowAnimationColorNegative',
    'FlowAnimationColorNeutral',
    'GaugeTrackColor',
    'GaugeTickColor',
    'GaugeLabelColor',
    'GaugeNeedleColor',
    'GaugeKnobColor',
    'ShadowBackColor',
    'ShadowBorderColor',
    'ShadowColor',
    'GridLineColor',
    'DialogBackColor',
    'DialogTextColor',
    'DialogButtonBackColor',
    'DialogButtonTextColor'
];
assertSameValue(73, count($brownson2023Fields), 'The Brownson 2023 regression list must contain 73 native colors.');
assertSameValue(
    [],
    array_values(array_diff($brownson2023Fields, $fields)),
    'Every Brownson 2023 native color must remain covered by the central catalogue.'
);
assertSameValue(
    36,
    count(array_diff($fields, $brownson2023Fields)),
    'The catalogue must retain the 36 newer native IPSView colors beyond Brownson 2023.'
);

$catalog = IPSViewControlThemeHelper::catalog();
assertSameValue(
    'ViewBackground',
    $catalog['ColorView']['styleField'],
    'ColorView must map to the view background instead of ColorPage.'
);
assertSameValue(
    'PageBackground',
    $catalog['ColorPage']['styleField'],
    'ColorPage must remain a separate page background.'
);
assertSameValue(
    'Text',
    $catalog['DialogButtonTextColor']['styleField'],
    'The legacy dialog button text field must remain supported.'
);
assertTrueValue(
    $catalog['DialogButtonTextColor']['legacy'],
    'The unsplit dialog button text field must be marked as legacy.'
);
assertFalseValue(
    $catalog['DialogButtonTextColorEnabled']['legacy'],
    'The newer enabled dialog button text field must not be marked as legacy.'
);
assertFalseValue(
    $catalog['ColorView']['legacy'],
    'ColorView must remain a current native field because IPSView still separates View and page colors.'
);

$verifiedCurrentDocument = new stdClass();
$verifiedCurrentDocument->ColorView = (object) IPSViewControlThemeHelper::createColor('#FF00FF');
$verifiedCurrentDocument->ColorPage = (object) IPSViewControlThemeHelper::createColor('#00FFFF');
assertSameValue(
    'ViewBackground',
    IPSViewControlThemeHelper::styleFieldForDocument($verifiedCurrentDocument, 'ColorView'),
    'Current IPSView ColorView must map to the View background.'
);
assertSameValue(
    'PageBackground',
    IPSViewControlThemeHelper::styleFieldForDocument($verifiedCurrentDocument, 'ColorPage'),
    'Current IPSView ColorPage must map independently to the page background.'
);
assertSameValue(
    IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND,
    IPSViewControlThemeHelper::presetRoleForDocument($verifiedCurrentDocument, 'ColorView'),
    'Current ColorView must resolve to the View-background preset role.'
);
assertSameValue(
    IPSViewStylePresetHelper::ROLE_PAGE_BACKGROUND,
    IPSViewControlThemeHelper::presetRoleForDocument($verifiedCurrentDocument, 'ColorPage'),
    'Current ColorPage must resolve to the page-background preset role.'
);
$currentRoleMapping = IPSViewControlThemeHelper::presetRoleMappingForDocument($verifiedCurrentDocument);
assertTrueValue(
    in_array('ColorView', $currentRoleMapping[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND], true),
    'Current document mapping must expose ColorView through the View-background role.'
);
assertTrueValue(
    in_array('ColorPage', $currentRoleMapping[IPSViewStylePresetHelper::ROLE_PAGE_BACKGROUND], true),
    'Current document mapping must expose ColorPage through the page-background role.'
);

$missingViewDocument = new stdClass();
$missingViewDocument->ColorPage = (object) IPSViewControlThemeHelper::createColor('#404040');
assertSameValue(
    'PageBackground',
    IPSViewControlThemeHelper::styleFieldForDocument($missingViewDocument, 'ColorPage'),
    'Missing ColorView must never reinterpret ColorPage as the View background.'
);
assertSameValue(
    IPSViewStylePresetHelper::ROLE_PAGE_BACKGROUND,
    IPSViewControlThemeHelper::presetRoleForDocument($missingViewDocument, 'ColorPage'),
    'ColorPage must keep the page-background role even when ColorView is absent.'
);
$missingViewRoleMapping = IPSViewControlThemeHelper::presetRoleMappingForDocument($missingViewDocument);
assertFalseValue(
    in_array('ColorPage', $missingViewRoleMapping[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND] ?? [], true),
    'ColorPage must never appear in the View-background role.'
);
assertTrueValue(
    in_array('ColorPage', $missingViewRoleMapping[IPSViewStylePresetHelper::ROLE_PAGE_BACKGROUND], true),
    'ColorPage must remain in the page-background role.'
);

assertSameValue(
    IPSViewStylePresetHelper::ROLE_ACCENT,
    $catalog['SliderTrackColorActive']['presetRole'],
    'Slider active track must keep its established accent preset role.'
);

$profileFields = IPSViewStyleProfileHelper::styleFields();
foreach (IPSViewControlThemeHelper::styleFields() as $styleField) {
    assertTrueValue(
        in_array($styleField, $profileFields, true),
        'Native mapping uses an unknown Style Profile field: ' . $styleField
    );
}

$styleColors = [];
$seed = 0x102030;
foreach (IPSViewControlThemeHelper::styleFields() as $index => $styleField) {
    $styleColors[$styleField] = sprintf('#%06X', ($seed + ($index * 0x010101)) & 0xFFFFFF);
}

$gradientOverride = IPSViewControlThemeHelper::createColor(
    '#BE0A0A',
    170,
    1,
    '12',
    false,
    '',
    '#6E0A0A',
    255
);
$gradientOverride['FutureBlendMode'] = 'overlay';

$derived = IPSViewControlThemeHelper::fromStyleColors(
    $styleColors,
    [
        'ColorBackOff'    => $gradientOverride,
        'FutureNeonColor' => [
            'A'       => 123,
            'R'       => 1,
            'G'       => 2,
            'B'       => 3,
            'Type'    => 7,
            'Pattern' => 'future',
            'IsEmpty' => false,
            'Name'    => 'Future',
            'Vendor'  => ['mode' => 'x']
        ]
    ]
);
assertSameValue(109, count($derived['colors']), 'Derived native themes must contain all 109 known fields.');
assertSameValue(
    $styleColors['ViewBackground'],
    IPSViewControlThemeHelper::colorToHex($derived['colors']['ColorView']),
    'ColorView must derive from ViewBackground.'
);
assertSameValue(
    $styleColors['PageBackground'],
    IPSViewControlThemeHelper::colorToHex($derived['colors']['ColorPage']),
    'ColorPage must derive independently from PageBackground.'
);
assertSameValue(
    '#BE0A0A',
    IPSViewControlThemeHelper::colorToHex($derived['colors']['ColorBackOff']),
    'Known native overrides must replace semantic defaults.'
);
assertSameValue(170, $derived['colors']['ColorBackOff']['A'], 'Native alpha must survive overrides.');
assertSameValue(1, $derived['colors']['ColorBackOff']['Type'], 'Native gradient type must survive overrides.');
assertSameValue(255, $derived['colors']['ColorBackOff']['A2'], 'Secondary gradient alpha must survive overrides.');
assertSameValue(110, $derived['colors']['ColorBackOff']['R2'], 'Secondary gradient red channel must survive overrides.');
assertSameValue(
    'overlay',
    $derived['colors']['ColorBackOff']['FutureBlendMode'],
    'Unknown future properties inside known native colors must be preserved.'
);
assertTrueValue(
    array_key_exists('FutureNeonColor', $derived['unknownColors']),
    'Unknown future native color fields must be preserved separately.'
);
assertSameValue(
    ['mode' => 'x'],
    $derived['unknownColors']['FutureNeonColor']['Vendor'],
    'Unknown nested native color metadata must be preserved.'
);

$document = new stdClass();
$document->ColorView = (object) IPSViewControlThemeHelper::createColor('#000000');
$document->ColorBackOff = (object) IPSViewControlThemeHelper::createColor('#000000');
$document->FutureNeonColor = (object) IPSViewControlThemeHelper::createColor('#000000');
$document->Colors = [
    (object) IPSViewControlThemeHelper::createColor('#112233', 255, 0, '12', false, 'Named One')
];

$theme = IPSViewControlThemeHelper::extract($document);
assertSameValue(2, count($theme['colors']), 'Extraction must separate known native fields.');
assertSameValue(1, count($theme['unknownColors']), 'Extraction must preserve unknown top-level native fields.');
assertSameValue(1, count($theme['namedColors']), 'Extraction must preserve the free named IPSView palette.');
assertSameValue(
    'Named One',
    $theme['namedColors'][0]['Name'],
    'Named IPSView colors must preserve their names.'
);

$encoded = IPSViewControlThemeHelper::encode($derived);
$decoded = IPSViewControlThemeHelper::decode($encoded);
assertSameValue($derived, $decoded, 'Native theme JSON must roundtrip deterministically.');

$target = new stdClass();
$target->ColorView = (object) IPSViewControlThemeHelper::createColor('#000000');
$target->FutureNeonColor = (object) IPSViewControlThemeHelper::createColor('#000000');

$report = IPSViewControlThemeHelper::apply($target, $derived);
assertSameValue(1, $report['knownApplied'], 'Default apply must update only known fields already present.');
assertSameValue(1, $report['unknownApplied'], 'Default apply must update unknown fields already present.');
assertFalseValue(
    property_exists($target, 'ColorPage'),
    'Default apply must not add native properties missing from an older IPSView document.'
);

$completeTarget = new stdClass();
$completeReport = IPSViewControlThemeHelper::apply($completeTarget, $derived, true);
assertSameValue(109, $completeReport['knownApplied'], 'Complete apply must create all 109 known native fields.');
assertSameValue(1, $completeReport['unknownApplied'], 'Complete apply must create preserved future fields.');

$failed = false;
try {
    IPSViewControlThemeHelper::decode('{"schema":"burki24.ipsview-native-theme","version":2,"colors":{}}');
} catch (InvalidArgumentException) {
    $failed = true;
}
assertTrueValue($failed, 'Unsupported native theme versions must be rejected.');

$failed = false;
try {
    IPSViewControlThemeHelper::normalizeColor([
        'R'  => 1,
        'G'  => 2,
        'B'  => 3,
        'R2' => 4
    ]);
} catch (InvalidArgumentException) {
    $failed = true;
}
assertTrueValue($failed, 'Partial secondary native colors must be rejected.');

fwrite(STDOUT, "IPSView control/native theme helper tests passed.\n");
