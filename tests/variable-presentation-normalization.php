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
foreach ([['DIGITS' => '2'], ['MIN' => INF], ['OPTIONS' => '{broken'], ['UNKNOWN_PARAMETER' => 1]] as $invalid) {
    $blocked = VariablePresentationNormalizationHelper::Normalize(2, ['PRESENTATION' => VariablePresentationNormalizationHelper::VALUE] + $invalid);
    assertPresentationNormalization($blocked['conversion']['status'] === 'blocked', 'Invalid or unknown value parameter accepted.');
}
fwrite(STDOUT, "Native/legacy normalization, exact formatting, Boolean states, intervals and blocked ambiguity verified.\n");
