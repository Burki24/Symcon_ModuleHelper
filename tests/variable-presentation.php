<?php

declare(strict_types=1);

if (!defined('VARIABLE_PRESENTATION_VALUE_PRESENTATION')) {
    define('VARIABLE_PRESENTATION_VALUE_PRESENTATION', '{3319437D-7CDE-699D-750A-3C6A3841FA75}');
}
if (!defined('VARIABLE_PRESENTATION_WEB_CONTENT')) {
    define('VARIABLE_PRESENTATION_WEB_CONTENT', '{9DE1D610-5106-97FB-714D-1AADEDF8377A}');
}
if (!defined('VARIABLE_PRESENTATION_DATE_TIME')) {
    define('VARIABLE_PRESENTATION_DATE_TIME', '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}');
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

foreach ([[-1, 1], [4, 1], [1, -1], [1, 3]] as [$date, $time]) {
    try {
        $presentation->dateTime($date, true, true, $time);
        throw new RuntimeException('Unsupported date/time display values must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}

fwrite(STDOUT, "VariablePresentationHelper tests passed.\n");
