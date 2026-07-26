<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use JsonException;

/**
 * Creates reusable native Symcon presentation arrays for status variables.
 *
 * Intended for current IPSModuleStrict modules. The helper deliberately returns
 * presentation arrays only and has no dependency on a concrete module class.
 *
 * @version 1.0.0
 */
trait VariablePresentationHelper
{
    /**
     * JSON flags used for presentation option lists.
     */
    private const VARIABLE_PRESENTATION_JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Creates a value presentation for a boolean variable with custom captions.
     *
     * @param string $trueCaption  Caption displayed for true.
     * @param string $falseCaption Caption displayed for false.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws JsonException If the option list unexpectedly cannot be encoded.
     */
    protected function BooleanPresentation(string $trueCaption, string $falseCaption): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => $this->EncodePresentationOptions([
                [
                    'Value'   => false,
                    'Caption' => $falseCaption
                ],
                [
                    'Value'   => true,
                    'Caption' => $trueCaption
                ]
            ])
        ];
    }

    /**
     * Creates a value presentation for a string variable.
     *
     * @param bool $multiline True to allow multiline display.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function TextPresentation(bool $multiline = false): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'MULTILINE'    => $multiline
        ];
    }

    /**
     * Creates a web-content presentation.
     *
     * @param int  $htmlType 0 for HTML content, 1 for a webpage URL.
     * @param bool $padding  True to use the presentation's padding setting.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function WebContentPresentation(int $htmlType = 0, bool $padding = true): array
    {
        if ($htmlType < 0 || $htmlType > 1) {
            throw new \InvalidArgumentException('HTML type must be 0 (HTML content) or 1 (webpage).');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
            'HTML_TYPE'    => $htmlType,
            'PADDING'      => $padding
        ];
    }

    /**
     * Creates a date/time presentation using a native Symcon template.
     *
     * This is useful when an existing module already relies on one of Symcon's
     * date/time templates and should keep that exact presentation behavior.
     *
     * @param string $template Native Symcon template GUID.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DateTimeTemplatePresentation(string $template): array
    {
        if (trim($template) === '') {
            throw new \InvalidArgumentException('Date/time template must not be empty.');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
            'TEMPLATE'     => $template
        ];
    }

    /**
     * Creates a date/time presentation.
     *
     * @param int  $date         Date display: 0 none, 1 year/month/day, 2 month/day, 3 year/day.
     * @param bool $monthText    True to render the month as text.
     * @param bool $dayOfTheWeek True to include the day of the week.
     * @param int  $time         Time display: 0 none, 1 hours/minutes, 2 hours/minutes/seconds.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DateTimePresentation(
        int $date = 1,
        bool $monthText = true,
        bool $dayOfTheWeek = true,
        int $time = 1
    ): array {
        if ($date < 0 || $date > 3) {
            throw new \InvalidArgumentException('Date display must be between 0 and 3.');
        }

        if ($time < 0 || $time > 2) {
            throw new \InvalidArgumentException('Time display must be between 0 and 2.');
        }

        return [
            'PRESENTATION'    => VARIABLE_PRESENTATION_DATE_TIME,
            'DATE'            => $date,
            'MONTH_TEXT'      => $monthText,
            'DAY_OF_THE_WEEK' => $dayOfTheWeek,
            'TIME'            => $time
        ];
    }

    /**
     * Encodes presentation options with consistent JSON flags.
     *
     * @param list<array<string,mixed>> $options
     *
     * @return string JSON-encoded option list.
     *
     * @throws JsonException If the options cannot be encoded.
     */
    private function EncodePresentationOptions(array $options): string
    {
        return json_encode($options, self::VARIABLE_PRESENTATION_JSON_FLAGS);
    }
}
