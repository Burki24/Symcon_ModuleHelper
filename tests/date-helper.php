<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DateHelper.php';

use Burki24\SymconModuleHelper\DateHelper;

final class DateHelperHarness
{
    use DateHelper;

    public function formatDateValue(mixed $value, string $format = 'd.m.Y'): string
    {
        return $this->FormatDate($value, $format);
    }
}

$date = new DateHelperHarness();

assertSameValue(
    '26.07.2026',
    $date->formatDateValue('2026-07-26'),
    'The default date format must use German date notation.'
);
assertSameValue(
    '2026/07/26',
    $date->formatDateValue('2026-07-26', 'Y/m/d'),
    'A custom DateTime output format must be supported.'
);
assertSameValue(
    'not-a-date',
    $date->formatDateValue('not-a-date'),
    'Unparseable date text must be preserved unchanged.'
);
assertSameValue('', $date->formatDateValue(''), 'An empty date string must return an empty string.');
assertSameValue('', $date->formatDateValue(null), 'A non-string date value must return an empty string.');

fwrite(STDOUT, "DateHelper tests passed.\n");
