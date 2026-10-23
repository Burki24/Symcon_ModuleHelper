<?php

declare(strict_types=1);

$presentationConstants = [
    'VARIABLE_PRESENTATION_VALUE_PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
    'VARIABLE_PRESENTATION_WEB_CONTENT'        => '{9DE1D610-5106-97FB-714D-1AADEDF8377A}',
    'VARIABLE_PRESENTATION_DATE_TIME'          => '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}',
    'VARIABLE_PRESENTATION_SLIDER'             => '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}',
    'VARIABLE_PRESENTATION_COLOR'              => '{05CC3CC2-A0B2-5837-A4A7-A07EA0B9DDFB}',
    'VARIABLE_PRESENTATION_DURATION'           => '{08A6AF76-394E-D354-48D5-BFC690488E4E}',
    'VARIABLE_PRESENTATION_SWITCH'             => '{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}',
    'VARIABLE_PRESENTATION_SHUTTER'            => '{6075FC22-69AF-B110-3749-C24138883082}',
    'VARIABLE_PRESENTATION_ENUMERATION'        => '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}',
];
foreach ($presentationConstants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

require_once __DIR__ . '/../src/VariablePresentationHelper.php';

use Burki24\SymconModuleHelper\VariablePresentationHelper;

final class VariablePresentationHarness
{
    use VariablePresentationHelper;

    /** @return array<string,mixed> */
    public function boolean(string $trueCaption, string $falseCaption): array
    {
        return $this->BooleanPresentation($trueCaption, $falseCaption);
    }

    /** @return array<string,mixed> */
    public function text(bool $multiline = false): array
    {
        return $this->TextPresentation($multiline);
    }

    /** @return array<string,mixed> */
    public function value(array $arguments = []): array
    {
        return $this->ValuePresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function temperature(array $arguments = []): array
    {
        return $this->TemperaturePresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function percent(array $arguments = []): array
    {
        return $this->PercentPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function rpm(array $arguments = []): array
    {
        return $this->RpmPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function integer(array $arguments = []): array
    {
        return $this->IntegerPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function decimal(array $arguments = []): array
    {
        return $this->DecimalPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function slider(array $arguments = []): array
    {
        return $this->SliderPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function brightness(array $arguments = []): array
    {
        return $this->BrightnessPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function colorTemperature(array $arguments = []): array
    {
        return $this->ColorTemperaturePresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function color(array $arguments = []): array
    {
        return $this->ColorPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function duration(int $countdownType = 0, int $format = 2, bool $milliseconds = false): array
    {
        return $this->DurationPresentation($countdownType, $format, $milliseconds);
    }

    /** @return array<string,mixed> */
    public function switch(array $arguments = []): array
    {
        return $this->SwitchPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function shutter(array $arguments = []): array
    {
        return $this->ShutterPresentation(...$arguments);
    }

    /** @return array<string,mixed> */
    public function enumeration(array $options, int $layout = 0, int $display = 0, string $icon = ''): array
    {
        return $this->EnumerationPresentation($options, $layout, $display, $icon);
    }

    /** @return array<string,mixed> */
    public function options(array $options, string $icon = '', int $color = -1, string $prefix = '', string $suffix = ''): array
    {
        return $this->OptionsPresentation($options, $icon, $color, $prefix, $suffix);
    }

    /** @return array<string,mixed> */
    public function integerOptions(array $options, string $icon = '', int $color = -1, string $prefix = '', string $suffix = ''): array
    {
        return $this->IntegerOptionsPresentation($options, $icon, $color, $prefix, $suffix);
    }

    /** @return array<string,mixed> */
    public function webContent(int $htmlType = 0, bool $padding = true): array
    {
        return $this->WebContentPresentation($htmlType, $padding);
    }

    /** @return array<string,mixed> */
    public function dateTimeTemplate(string $template): array
    {
        return $this->DateTimeTemplatePresentation($template);
    }

    /** @return array<string,mixed> */
    public function dateTime(int $date = 1, bool $monthText = true, bool $dayOfWeek = true, int $time = 1): array
    {
        return $this->DateTimePresentation($date, $monthText, $dayOfWeek, $time);
    }
}

$presentation = new VariablePresentationHarness();

$boolean = $presentation->boolean('Ja/東京', 'Nein');
assertSameValue(
    VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    $boolean['PRESENTATION'] ?? null,
    'Boolean presentation must use the native value presentation.'
);
$booleanOptions = json_decode((string) ($boolean['OPTIONS'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
assertSameValue(
    [
        ['Value' => false, 'Caption' => 'Nein'],
        ['Value' => true, 'Caption' => 'Ja/東京']
    ],
    $booleanOptions,
    'Boolean presentation must encode false and true captions in stable order.'
);
assertTrueValue(
    str_contains((string) $boolean['OPTIONS'], 'Ja/東京'),
    'Boolean presentation options must keep Unicode and slashes readable.'
);

assertSameValue(
    [
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'MULTILINE'    => false
    ],
    $presentation->text(),
    'Text presentation must default to single-line output.'
);
assertSameValue(true, $presentation->text(true)['MULTILINE'] ?? null, 'Text presentation must support multiline output.');

$value = $presentation->value([
    'prefix'     => '~',
    'suffix'     => ' V',
    'digits'     => 2,
    'min'        => -5,
    'max'        => 400,
    'icon'       => 'bolt',
    'usageType'  => 0,
    'intervals'  => [['IntervalMinValue' => 0, 'IntervalMaxValue' => 10]],
]);
assertSameValue(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $value['PRESENTATION'] ?? null, 'Numeric values must use value presentation.');
assertSameValue('~', $value['PREFIX'] ?? null, 'Numeric values must keep the configured prefix.');
assertSameValue(' V', $value['SUFFIX'] ?? null, 'Numeric values must keep the configured suffix.');
assertSameValue(2, $value['DIGITS'] ?? null, 'Numeric values must keep configured digits.');
assertSameValue(true, $value['INTERVALS_ACTIVE'] ?? null, 'Numeric value intervals must activate interval handling.');
assertSameValue(
    [['IntervalMinValue' => 0, 'IntervalMaxValue' => 10]],
    json_decode((string) ($value['INTERVALS'] ?? ''), true, 512, JSON_THROW_ON_ERROR),
    'Numeric value intervals must round-trip through JSON.'
);

$temperature = $presentation->temperature();
assertSameValue(' °C', $temperature['SUFFIX'] ?? null, 'Temperature presentation must default to Celsius.');
assertSameValue(1, $temperature['DIGITS'] ?? null, 'Temperature presentation must default to one decimal digit.');
assertSameValue(1, $temperature['USAGE_TYPE'] ?? null, 'Temperature presentation must declare temperature usage.');
assertSameValue(-40.0, $temperature['MIN'] ?? null, 'Temperature presentation must keep the Wolf-compatible default minimum.');
assertSameValue(100.0, $temperature['MAX'] ?? null, 'Temperature presentation must keep the Wolf-compatible default maximum.');

$percent = $presentation->percent();
assertSameValue(' %', $percent['SUFFIX'] ?? null, 'Percent presentation must append the percent unit.');
assertSameValue(true, $percent['PERCENTAGE'] ?? null, 'Percent presentation must use percentage display mode.');
assertSameValue(100, $percent['MAX'] ?? null, 'Percent presentation must default to 0..100.');

$rpm = $presentation->rpm();
assertSameValue(' U/min', $rpm['SUFFIX'] ?? null, 'RPM presentation must use rotations per minute.');
assertSameValue(10000, $rpm['MAX'] ?? null, 'RPM presentation must keep the Wolf-compatible default maximum.');

assertSameValue(' bar', $presentation->integer(['unit' => 'bar'])['SUFFIX'] ?? null, 'Integer presentation must support arbitrary units.');
assertSameValue(3, $presentation->decimal(['unit' => 'kW', 'digits' => 3])['DIGITS'] ?? null, 'Decimal presentation must support configurable digits.');

$slider = $presentation->slider([
    'min'          => 10,
    'max'          => 30,
    'stepSize'     => 0.5,
    'gradientType' => 1,
    'usageType'    => 0,
    'suffix'       => ' °C',
    'digits'       => 1,
    'icon'         => 'temperature-half',
]);
assertSameValue(VARIABLE_PRESENTATION_SLIDER, $slider['PRESENTATION'] ?? null, 'Slider presentation must use the native slider presentation.');
assertSameValue(0.5, $slider['STEP_SIZE'] ?? null, 'Slider presentation must keep fractional step sizes.');
assertSameValue(1, $slider['GRADIENT_TYPE'] ?? null, 'Slider presentation must keep the requested gradient type.');
assertSameValue(0, $slider['USAGE_TYPE'] ?? null, 'Temperature slider usage must be supported.');

$brightness = $presentation->brightness();
assertSameValue(2, $brightness['USAGE_TYPE'] ?? null, 'Brightness presentation must declare intensity usage.');
assertSameValue(true, $brightness['PERCENTAGE'] ?? null, 'Brightness presentation must use percentage display mode.');
assertSameValue('sun', $brightness['ICON'] ?? null, 'Brightness presentation must default to the sun icon.');

$colorTemperature = $presentation->colorTemperature(['minKelvin' => 2700, 'maxKelvin' => 6500]);
assertSameValue(3, $colorTemperature['GRADIENT_TYPE'] ?? null, 'Color-temperature presentation must use a custom gradient.');
assertSameValue(1, $colorTemperature['USAGE_TYPE'] ?? null, 'Color-temperature presentation must declare tuneable-white usage.');
assertSameValue(' K', $colorTemperature['SUFFIX'] ?? null, 'Color-temperature presentation must use Kelvin.');
$gradient = json_decode((string) ($colorTemperature['CUSTOM_GRADIENT'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
assertSameValue(2700, $gradient[0]['Value'] ?? null, 'Color-temperature gradient must start at the configured minimum.');
assertSameValue(6500, $gradient[count($gradient) - 1]['Value'] ?? null, 'Color-temperature gradient must end at the configured maximum.');

$reversedColorTemperature = $presentation->colorTemperature(['minKelvin' => 6500, 'maxKelvin' => 2700]);
assertSameValue(2700, $reversedColorTemperature['MIN'] ?? null, 'Color-temperature ranges must normalize reversed boundaries.');
assertSameValue(6500, $reversedColorTemperature['MAX'] ?? null, 'Color-temperature ranges must normalize reversed boundaries.');

$color = $presentation->color();
assertSameValue(
    [
        'PRESENTATION' => VARIABLE_PRESENTATION_COLOR,
        'ENCODING'     => 0,
        'COLOR_SPACE'  => 1
    ],
    $color,
    'Color presentation must default to RGB in sRGB space.'
);

$duration = $presentation->duration();
assertSameValue(
    [
        'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
        'COUNTDOWN_TYPE' => 0,
        'FORMAT'         => 2,
        'MILLISECONDS'   => false
    ],
    $duration,
    'Duration presentation must default to a plain hours/minutes/seconds value.'
);

$switch = $presentation->switch();
assertSameValue(VARIABLE_PRESENTATION_SWITCH, $switch['PRESENTATION'] ?? null, 'Switch presentation must use the native switch presentation.');
assertSameValue('power', $switch['ICON_TRUE'] ?? null, 'Switch presentation must default to a power icon for true.');
assertSameValue('power-off', $switch['ICON_FALSE'] ?? null, 'Switch presentation must default to a power-off icon for false.');
assertSameValue(60, $switch['GLOW_INTENSITY'] ?? null, 'Switch presentation must keep the Wolf-compatible glow intensity.');

$shutter = $presentation->shutter();
assertSameValue(VARIABLE_PRESENTATION_SHUTTER, $shutter['PRESENTATION'] ?? null, 'Shutter presentation must use the native shutter presentation.');
assertSameValue(100, $shutter['OPEN_OUTSIDE_VALUE'] ?? null, 'Shutter presentation must default to 100 for open.');
assertSameValue(0, $shutter['CLOSE_INSIDE_VALUE'] ?? null, 'Shutter presentation must default to 0 for closed.');

$enumerationOptions = [
    ['Value' => 0, 'Caption' => 'Auto', 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
    ['Value' => 1, 'Caption' => 'Manual', 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
];
$enumeration = $presentation->enumeration($enumerationOptions, 1, 2, 'menu');
assertSameValue(VARIABLE_PRESENTATION_ENUMERATION, $enumeration['PRESENTATION'] ?? null, 'Enumeration presentation must use the native enumeration presentation.');
assertSameValue(1, $enumeration['LAYOUT'] ?? null, 'Enumeration presentation must keep the requested layout.');
assertSameValue(2, $enumeration['DISPLAY'] ?? null, 'Enumeration presentation must keep the requested display mode.');
assertSameValue($enumerationOptions, json_decode((string) $enumeration['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Enumeration options must round-trip through JSON.');

$readOnlyDefinitions = [['Value' => false, 'Caption' => 'No'], ['Value' => true, 'Caption' => 'Yes']];
$readOnlyOptions = $presentation->options($readOnlyDefinitions, 'list', -1, '[', ']');
assertSameValue(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $readOnlyOptions['PRESENTATION'] ?? null, 'Read-only options must use value presentation.');
assertSameValue('list', $readOnlyOptions['ICON'] ?? null, 'Read-only options must keep the configured icon.');
assertSameValue($readOnlyDefinitions, json_decode((string) $readOnlyOptions['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Boolean options must retain their native JSON contract.');
$stringOptions = [['Value' => 'idle', 'Caption' => 'Idle'], ['Value' => 'active', 'Caption' => 'Active']];
assertSameValue($stringOptions, json_decode($presentation->options($stringOptions)['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'String options must remain unchanged.');

assertSameValue($enumerationOptions, json_decode($presentation->options($enumerationOptions)['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Existing options serialization must remain unchanged for staged distribution.');

// Official SDK: OPTIONS only applies to Boolean/String; Integer requires constant intervals.
// https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/value-presentation/
$integerOptions = [
    ['Value' => -1, 'Caption' => 'Unavailable'],
    ['Value' => 0, 'Caption' => 'No'],
    ['Value' => 1, 'Caption' => 'Yes/東京', 'IconActive' => true, 'IconValue' => 'check', 'ColorActive' => true, 'ColorValue' => 0x00AA00]
];
$integerStates = $presentation->integerOptions($integerOptions, 'list', 0x445566, '[', ']');
assertSameValue(false, isset($integerStates['OPTIONS']), 'Integer options must not use the unsupported OPTIONS parameter.');
assertSameValue(true, $integerStates['INTERVALS_ACTIVE'] ?? null, 'Integer states must activate numeric intervals.');
$integerIntervals = json_decode($integerStates['INTERVALS'], true, 512, JSON_THROW_ON_ERROR);
assertSameValue([-1, 0, 1], array_column($integerIntervals, 'IntervalMinValue'), 'Negative, zero and positive integer states must remain distinct.');
assertSameValue([-1, 0, 1], array_column($integerIntervals, 'IntervalMaxValue'), 'Each state must match exactly, without covering gaps.');
assertSameValue([true, true, true], array_column($integerIntervals, 'ConstantActive'), 'Intervals must display text constants.');
assertSameValue(['Unavailable', 'No', 'Yes/東京'], array_column($integerIntervals, 'ConstantValue'), 'State captions must survive UTF-8 encoding.');
assertSameValue('check', $integerIntervals[2]['IconValue'], 'State-specific icon must survive interval conversion.');
// Native MCP verification on Symcon 9.1 rejects Color; ColorValue is accepted.
assertSameValue(0x00AA00, $integerIntervals[2]['ColorValue'], 'Interval colors must use the native ColorValue key.');
assertSameValue(false, isset($integerIntervals[2]['Color']), 'Do not emit the rejected Color interval parameter.');
assertSameValue(true, $integerIntervals[2]['ColorActive'], 'Explicit color activation must survive.');
assertSameValue([-1, 1, 'list', 0x445566, '[', ']'], [$integerStates['MIN'], $integerStates['MAX'], $integerStates['ICON'], $integerStates['COLOR'], $integerStates['PREFIX'], $integerStates['SUFFIX']], 'Numeric presentation must retain range and global decoration.');
$sparse = json_decode($presentation->integerOptions([['Value' => 8, 'Caption' => 'Eight'], ['Value' => -7, 'Caption' => 'Minus seven']])['INTERVALS'], true, 512, JSON_THROW_ON_ERROR);
assertSameValue([8, -7], array_column($sparse, 'IntervalMaxValue'), 'Sparse and unordered options must not fill unmatched ranges.');

assertSameValue(
    [
        'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
        'HTML_TYPE'    => 0,
        'PADDING'      => true
    ],
    $presentation->webContent(),
    'Web content presentation must match the LMNB defaults.'
);
assertSameValue(
    [
        'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
        'HTML_TYPE'    => 1,
        'PADDING'      => false
    ],
    $presentation->webContent(1, false),
    'Web content presentation must allow webpage mode and padding control.'
);

try {
    $presentation->webContent(2);
    throw new RuntimeException('Unsupported HTML types must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'HTML type'), 'Invalid HTML type errors must be descriptive.');
}

assertSameValue(
    [
        'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
        'TEMPLATE'     => '{BB0E9933-0403-BD3A-D1C9-255646934B00}'
    ],
    $presentation->dateTimeTemplate('{BB0E9933-0403-BD3A-D1C9-255646934B00}'),
    'Date/time template presentation must preserve a native Symcon template.'
);
try {
    $presentation->dateTimeTemplate('   ');
    throw new RuntimeException('An empty date/time template must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'template'), 'Invalid template errors must be descriptive.');
}

assertSameValue(
    [
        'PRESENTATION'    => VARIABLE_PRESENTATION_DATE_TIME,
        'DATE'            => 1,
        'MONTH_TEXT'      => true,
        'DAY_OF_THE_WEEK' => true,
        'TIME'            => 1
    ],
    $presentation->dateTime(),
    'Date/time presentation must match the LMNB defaults.'
);
assertSameValue(
    [
        'PRESENTATION'    => VARIABLE_PRESENTATION_DATE_TIME,
        'DATE'            => 0,
        'MONTH_TEXT'      => false,
        'DAY_OF_THE_WEEK' => false,
        'TIME'            => 2
    ],
    $presentation->dateTime(0, false, false, 2),
    'Date/time presentation must allow all native display options.'
);

$invalidCalls = [
    static fn () => $presentation->value(['digits' => -1]),
    static fn () => $presentation->value(['usageType' => 2]),
    static fn () => $presentation->slider(['min' => 5, 'max' => 5]),
    static fn () => $presentation->slider(['stepSize' => -1]),
    static fn () => $presentation->slider(['gradientType' => 4]),
    static fn () => $presentation->slider(['usageType' => 6]),
    static fn () => $presentation->colorTemperature(['minKelvin' => 0]),
    static fn () => $presentation->color(['encoding' => 4]),
    static fn () => $presentation->duration(3),
    static fn () => $presentation->switch(['glowIntensity' => 101]),
    static fn () => $presentation->shutter(['sunPosition' => 3]),
    static fn () => $presentation->enumeration([]),
    static fn () => $presentation->options([]),
    static fn () => $presentation->integerOptions([]),
    static fn () => $presentation->integerOptions([['Value' => false, 'Caption' => 'No']]),
    static fn () => $presentation->integerOptions([['Value' => '0', 'Caption' => 'No']]),
    static fn () => $presentation->integerOptions([['Value' => 0.5, 'Caption' => 'Half']]),
    static fn () => $presentation->integerOptions([['Value' => 0, 'Caption' => 'A'], ['Value' => 0, 'Caption' => 'B']]),
    static fn () => $presentation->integerOptions([['Value' => -1, 'Caption' => null]]),
];
foreach ($invalidCalls as $invalidCall) {
    try {
        $invalidCall();
        throw new RuntimeException('Invalid presentation parameters must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}

foreach ([[-1, 1], [4, 1], [1, -1], [1, 3]] as [$date, $time]) {
    try {
        $presentation->dateTime($date, true, true, $time);
        throw new RuntimeException('Unsupported date/time display values must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}

fwrite(STDOUT, "VariablePresentationHelper tests passed.\n");
