<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/VariablePresentationNormalizationHelper.php';

use Burki24\SymconModuleHelper\VariablePresentationNormalizationHelper;

function assertPresentationNormalization(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$profile = ['ProfileName' => 'Example.Number', 'ProfileType' => 2, 'IsReadOnly' => false,
    'MinValue'            => -20.0, 'MaxValue' => 60.0, 'StepSize' => 0.1, 'Digits' => 1,
    'Prefix'              => 'ca. ', 'Suffix' => ' °C', 'Icon' => '', 'Associations' => []];
$before = $profile;
$result = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number'], ['Example.Number' => $profile]);
assertPresentationNormalization($result['conversion']['status'] === 'exact', 'Numeric profile must normalize.');
assertPresentationNormalization($result['native']['parameters']['SUFFIX'] === ' °C', 'Suffix whitespace lost.');
assertPresentationNormalization($result['effective']['stepSize'] === 0.1, 'Step metadata lost.');
assertPresentationNormalization(!isset($result['native']['parameters']['STEP_SIZE']), 'Read-only value must not invent slider parameters.');
assertPresentationNormalization($before === $profile, 'Profile mutated.');
$native = VariablePresentationNormalizationHelper::Normalize(2, ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE,
    'MIN'                                                                       => -20.0, 'MAX' => 60.0, 'PREFIX' => 'ca. ', 'SUFFIX' => ' °C', 'DIGITS' => 1]);
foreach (['minimum', 'maximum', 'prefix', 'suffix', 'digits'] as $field) {
    assertPresentationNormalization($native['effective'][$field] === $result['effective'][$field], 'Legacy/native mismatch: ' . $field);
}
$override = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number', 'SUFFIX' => '', 'DIGITS' => 0], ['Example.Number' => $profile]);
assertPresentationNormalization($override['effective']['suffix'] === '' && $override['effective']['digits'] === 0, 'Explicit empty/zero overrides lost.');
$contact = $profile;
$contact['ProfileType'] = 0;
$contact['Associations'] = [['Value' => 0.0, 'Name' => 'Closed', 'Icon' => 'door-closed', 'Color' => 32768], ['Value' => 1.0, 'Name' => 'Open', 'Icon' => 'door-open', 'Color' => 16711680]];
$boolean = VariablePresentationNormalizationHelper::Normalize(0, ['PROFILE' => 'Example.Contact'], ['Example.Contact' => $contact]);
assertPresentationNormalization($boolean['conversion']['status'] === 'exact', 'Boolean associations must normalize.');
assertPresentationNormalization($boolean['native']['parameters']['OPTIONS'][0]['Value'] === false, 'Boolean type lost.');
assertPresentationNormalization($boolean['native']['parameters']['OPTIONS'][1]['ColorValue'] === 16711680, 'Color lost.');
assertPresentationNormalization($boolean['native']['parameters']['OPTIONS'][1]['IconValue'] === 'door-open', 'Icon lost.');
$states = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Unknown'], []);
assertPresentationNormalization($states['conversion']['status'] === 'blocked' && $states['native'] === null, 'Missing profile must block.');
$profile['Associations'] = [['Value' => 10, 'Name' => 'Ten', 'Icon' => '', 'Color' => -1]];
$numeric = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number'], ['Example.Number' => $profile]);
assertPresentationNormalization($numeric['conversion']['status'] === 'exact' && $numeric['native']['parameters']['INTERVALS'][0]['ConstantValue'] === 'Ten', 'Constant numeric association lost.');
$levels = $profile;
$levels['ProfileType'] = 1;
$levels['MinValue'] = 0;
$levels['MaxValue'] = 100;
$levels['Associations'] = array_map(static fn (int $value): array => ['Value' => $value, 'Name' => (string) $value, 'Icon' => '', 'Color' => -1], [0, 50, 100]);
$thresholds = VariablePresentationNormalizationHelper::Normalize(1, ['PROFILE' => 'Levels'], ['Levels' => $levels]);
assertPresentationNormalization($thresholds['native']['parameters']['INTERVALS'] === [
    ['IntervalMinValue' => 0, 'IntervalMaxValue' => 50, 'ConstantActive' => true, 'ConstantValue' => '0', 'ConversionFactor' => 1, 'PrefixActive' => false, 'SuffixActive' => false, 'DigitsActive' => false, 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'Color' => -1],
    ['IntervalMinValue' => 50, 'IntervalMaxValue' => 100, 'ConstantActive' => true, 'ConstantValue' => '50', 'ConversionFactor' => 1, 'PrefixActive' => false, 'SuffixActive' => false, 'DigitsActive' => false, 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'Color' => -1],
    ['IntervalMinValue' => 100, 'IntervalMaxValue' => 100, 'ConstantActive' => true, 'ConstantValue' => '100', 'ConversionFactor' => 1, 'PrefixActive' => false, 'SuffixActive' => false, 'DigitsActive' => false, 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'Color' => -1]
], 'Documented 0–49, 50–99, 100 threshold boundaries changed.');
$profile['Suffix'] = '%';
$profile['Associations'] = [];
$scaled = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number'], ['Example.Number' => $profile]);
$literal = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number', 'PERCENTAGE' => false], ['Example.Number' => $profile]);
assertPresentationNormalization($scaled['effective']['percentage'] === true && $literal['effective']['percentage'] === false, 'Native percentage and literal chart suffix must remain distinct.');
$profile['Associations'] = [['Value' => 10, 'Name' => 'Ten', 'Icon' => '', 'Color' => -1]];
$profile['Suffix'] = ' °C';
$profile['Associations'][0]['Name'] = 'Level %d';
$dynamic = VariablePresentationNormalizationHelper::Normalize(2, ['PROFILE' => 'Example.Number'], ['Example.Number' => $profile]);
assertPresentationNormalization($dynamic['conversion']['status'] === 'blocked', 'Dynamic printf text must not become a constant.');
$percent = VariablePresentationNormalizationHelper::Normalize(1, ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE, 'MIN' => 0, 'MAX' => 255, 'SUFFIX' => ' %', 'PERCENTAGE' => false]);
assertPresentationNormalization($percent['effective']['percentage'] === false && $percent['effective']['maximum'] === 255, 'Percent suffix must not normalize values.');
$parameters = ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE, 'INTERVALS_ACTIVE' => true,
    'INTERVALS'               => json_encode([['IntervalMinValue' => 1000, 'IntervalMaxValue' => 999999, 'ConversionFactor' => 1000, 'SuffixActive' => true, 'SuffixValue' => ' kW', 'ColorActive' => true, 'Color' => 123]])];
$intervals = VariablePresentationNormalizationHelper::Normalize(2, $parameters);
assertPresentationNormalization($intervals['native']['parameters']['INTERVALS'][0]['ConversionFactor'] === 1000, 'Conversion factor lost.');
$sdk = VariablePresentationNormalizationHelper::ToSDK($intervals);
assertPresentationNormalization(is_string($sdk['INTERVALS']) && !isset($sdk['PROFILE']), 'Native SDK serialization must encode lists without profile dependency.');

// Symcon 9.1 exports CONTENT_COLOR independently of the default COLOR.
foreach ([VariablePresentationNormalizationHelper::VALUE, VariablePresentationNormalizationHelper::SLIDER,
    VariablePresentationNormalizationHelper::ENUMERATION] as $presentationID) {
    foreach ([-1, 0, 0xFFFFFF, 0x12ABEF, 0x8044CC11, 0xFFFFFFFF] as $contentColor) {
        $coloredPresentation = ['PRESENTATION' => $presentationID, 'COLOR' => 0xFF00FF, 'CONTENT_COLOR' => $contentColor];
        $colored = VariablePresentationNormalizationHelper::Normalize(1, $coloredPresentation);
        assertPresentationNormalization($colored['conversion']['status'] === 'exact', 'Native CONTENT_COLOR must not block a supported presentation.');
        assertPresentationNormalization($colored['original']['presentation'] === $coloredPresentation, 'Original native colors changed.');
        assertPresentationNormalization($colored['effective']['color'] === 0xFF00FF
            && $colored['effective']['contentColor'] === $contentColor, 'Default and content colors must stay independent, including zero and automatic color.');
        assertPresentationNormalization(
            VariablePresentationNormalizationHelper::ToSDK($colored) === $coloredPresentation,
            'SDK serialization must preserve both native color parameters without conversion.'
        );
        assertPresentationNormalization(
            in_array(['source'                               => '/original/presentation/CONTENT_COLOR',
                'target'                                     => '/native/parameters/CONTENT_COLOR', 'status' => 'exact'], $colored['conversion']['fieldCoverage'], true),
            'The content color must have an exact source and target in the field coverage.'
        );
    }
}
$legacyColored = VariablePresentationNormalizationHelper::Normalize(
    2,
    ['PROFILE' => 'Example.Number', 'CONTENT_COLOR' => 0],
    ['Example.Number' => $before]
);
assertPresentationNormalization($legacyColored['conversion']['status'] === 'exact'
    && $legacyColored['effective']['contentColor'] === 0
    && $legacyColored['native']['parameters']['CONTENT_COLOR'] === 0, 'Legacy conversion must retain explicit native content color overrides.');
assertPresentationNormalization(!array_key_exists('CONTENT_COLOR', $result['native']['parameters']), 'A missing content color must not be invented.');
foreach (['0', 1.0, false, null, []] as $invalidColor) {
    $invalidPresentation = ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE, 'CONTENT_COLOR' => $invalidColor];
    $blockedColor = VariablePresentationNormalizationHelper::Normalize(2, $invalidPresentation);
    assertPresentationNormalization($blockedColor['conversion']['status'] === 'blocked'
        && $blockedColor['original']['presentation'] === $invalidPresentation, 'Invalid content color types must block while retaining the original definition.');
}
// Synthetic full native shape observed on the Symcon 9.1 test plane; no object IDs or values.
$symcon91Presentation = [
    'COLOR'               => -1, 'CONTENT_COLOR' => -1, 'DECIMAL_SEPARATOR' => 'Client', 'DIGITS' => 2,
    'DISPLAY_TYPE'        => 0, 'ICON' => '', 'INTERVALS' => '[]', 'INTERVALS_ACTIVE' => false,
    'MAX'                 => 100, 'MIN' => 0, 'PERCENTAGE' => false, 'PREFIX' => '',
    'PRESENTATION'        => VariablePresentationNormalizationHelper::VALUE,
    'PREVIEW_STYLE'       => 1, 'SHOW_PREVIEW' => true, 'SUFFIX' => '',
    'THOUSANDS_SEPARATOR' => '', 'USAGE_TYPE' => 0
];
$symcon91 = VariablePresentationNormalizationHelper::Normalize(2, $symcon91Presentation);
assertPresentationNormalization(
    $symcon91['conversion']['status'] === 'exact',
    'The complete Symcon 9.1 native presentation must normalize: ' . json_encode($symcon91['conversion']['issues'])
);
assertPresentationNormalization($symcon91['original']['presentation'] === $symcon91Presentation, 'Complete native presentation changed.');
$roundTrip91 = VariablePresentationNormalizationHelper::ToSDK($symcon91);
$expectedRoundTrip91 = $symcon91Presentation;
ksort($roundTrip91);
ksort($expectedRoundTrip91);
assertPresentationNormalization($roundTrip91 === $expectedRoundTrip91, 'The complete SDK round trip lost native fields or types.');
$symcon91Fields = ['displayType' => 'DISPLAY_TYPE', 'previewStyle' => 'PREVIEW_STYLE', 'showPreview' => 'SHOW_PREVIEW'];
foreach ($symcon91Fields as $effectiveKey => $parameter) {
    assertPresentationNormalization($symcon91['effective'][$effectiveKey] === $symcon91Presentation[$parameter], 'Native display/preview metadata missing: ' . $parameter);
    assertPresentationNormalization(in_array(['source' => '/original/presentation/' . $parameter,
        'target'                                       => '/native/parameters/' . $parameter, 'status' => 'exact'], $symcon91['conversion']['fieldCoverage'], true), 'Native display/preview field coverage missing: ' . $parameter);
    assertPresentationNormalization(!array_key_exists($parameter, $result['native']['parameters']), 'Missing native display/preview fields must not be invented.');
}
$displayOverrides = ['DISPLAY_TYPE' => 1, 'PREVIEW_STYLE' => 0, 'SHOW_PREVIEW' => false];
foreach ([VariablePresentationNormalizationHelper::VALUE, VariablePresentationNormalizationHelper::SLIDER,
    VariablePresentationNormalizationHelper::ENUMERATION] as $presentationID) {
    $display91 = VariablePresentationNormalizationHelper::Normalize(1, array_replace(
        $symcon91Presentation,
        ['PRESENTATION' => $presentationID, 'DISPLAY' => 2],
        $displayOverrides
    ));
    assertPresentationNormalization($display91['conversion']['status'] === 'exact'
        && $display91['effective']['displayType'] === 1 && $display91['effective']['previewStyle'] === 0
        && $display91['effective']['showPreview'] === false && $display91['effective']['percentage'] === false
        && $display91['native']['parameters']['DISPLAY'] === 2, 'Display and preview settings must retain zero/false and stay independent of percentage scaling and DISPLAY.');
}
$legacyDisplay91 = VariablePresentationNormalizationHelper::Normalize(
    2,
    ['PROFILE' => 'Example.Number'] + $displayOverrides,
    ['Example.Number' => $before]
);
assertPresentationNormalization($legacyDisplay91['conversion']['status'] === 'exact'
    && $legacyDisplay91['effective']['displayType'] === 1 && $legacyDisplay91['effective']['previewStyle'] === 0
    && $legacyDisplay91['effective']['showPreview'] === false, 'Legacy conversion lost explicit native display/preview overrides.');
foreach (['DISPLAY_TYPE' => ['0', 0.0, false, null, []], 'PREVIEW_STYLE' => ['1', 1.0, true, null, []],
    'SHOW_PREVIEW'       => [1, 0.0, 'false', null, []]] as $parameter => $invalidValues) {
    foreach ($invalidValues as $invalidValue) {
        $invalid91Presentation = array_replace($symcon91Presentation, [$parameter => $invalidValue]);
        $invalid91 = VariablePresentationNormalizationHelper::Normalize(2, $invalid91Presentation);
        assertPresentationNormalization($invalid91['conversion']['status'] === 'blocked'
            && $invalid91['original']['presentation'] === $invalid91Presentation, 'Invalid native field types must block with their original data: ' . $parameter);
    }
}
foreach ([['DIGITS' => '2'], ['MIN' => INF], ['OPTIONS' => '{broken'], ['UNKNOWN_PARAMETER' => 1]] as $invalid) {
    $blocked = VariablePresentationNormalizationHelper::Normalize(2, ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE] + $invalid);
    assertPresentationNormalization($blocked['conversion']['status'] === 'blocked', 'Invalid or unknown value parameter accepted.');
}
fwrite(STDOUT, "Native/legacy normalization, exact formatting, Boolean states, intervals and blocked ambiguity verified.\n");
