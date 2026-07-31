<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

/**
 * Provides reusable date formatting for Symcon module data.
 *
 * Intended for module data received from APIs or configuration sources. The
 * helper formats parseable date strings and preserves the original text when
 * PHP cannot interpret the supplied date.
 *
 * @version 1.0.1
 */
trait DateHelper
{
    /**
     * Formats a date string with a configurable output format.
     *
     * Non-string or empty values return an empty string. If the supplied date
     * cannot be parsed, the original string is returned unchanged.
     *
     * @param mixed  $value  Date value to format.
     * @param string $format DateTime output format; defaults to German date notation.
     *
     * @return string Formatted date, original text for an unknown format, or an empty string.
     */
    protected function FormatDate(mixed $value, string $format = 'd.m.Y'): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $date = date_create_immutable($value);
        if ($date === false) {
            return $value;
        }

        return $date->format($format);
    }
}
