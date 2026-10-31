<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use InvalidArgumentException;
use Throwable;

/**
 * Loss-aware, read-only native/legacy presentation snapshots for migrations.
 * Lists remain arrays until ToSDK(); no variable, profile or value is written.
 * Unknown semantics remain blocked with their complete original definition.
 * Native COLOR and CONTENT_COLOR are retained as independent parameters.
 * Native display/preview settings are preserved without deriving value scaling.
 *
 * @version 1.0.2
 */
final class VariablePresentationNormalizationHelper
{
    public const VALUE = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
    public const LEGACY = '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}';
    public const SLIDER = '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}';
    public const ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';

    /** Read all source metadata without changing the variable or executing code. */
    public static function Capture(int $variableID): array
    {
        $variable = IPS_GetVariable($variableID);
        $presentation = IPS_GetVariablePresentation($variableID);
        $rawPresentation = $presentation;
        if (!is_array($variable) || !is_array($presentation)) {
            throw new InvalidArgumentException('Variable presentation is unavailable.');
        }
        $profiles = [];
        $names = [$variable['VariableProfile'] ?? '', $variable['VariableCustomProfile'] ?? '', $presentation['PROFILE'] ?? ''];
        foreach (array_unique($names) as $name) {
            if (is_string($name) && $name !== '' && IPS_VariableProfileExists($name)) {
                $profiles[$name] = IPS_GetVariableProfile($name);
            }
        }
        $templates = [];
        try {
            if (isset($presentation['TEMPLATE'])) {
                $template = IPS_GetTemplate($presentation['TEMPLATE']);
                if (($template['PresentationID'] ?? null) !== ($presentation['PRESENTATION'] ?? null) || !is_array($template['Values'] ?? null)) {
                    throw new InvalidArgumentException('Native template is unavailable or incompatible.');
                }
                $templates[$presentation['TEMPLATE']] = $template;
                $presentation = array_replace($template['Values'], $presentation);
                unset($presentation['TEMPLATE']);
            }
            $objectIcon = function_exists('IPS_GetObject') ? (IPS_GetObject($variableID)['ObjectIcon'] ?? '') : '';
            $profileIcon = $profiles[$presentation['PROFILE'] ?? '']['Icon'] ?? '';
            if (($presentation['ICON'] ?? $profileIcon) === '' && $objectIcon !== '') {
                $presentation['ICON'] = $objectIcon;
            }
            $result = self::Normalize($variable['VariableType'], $presentation, $profiles);
        } catch (Throwable $exception) {
            $result = self::Blocked(['presentation' => $presentation, 'profiles' => $profiles], $exception->getMessage());
        }
        $result['original']['variable'] = array_intersect_key($variable, array_flip([
            'VariableType', 'VariableProfile', 'VariableCustomProfile', 'VariablePresentation', 'VariableCustomPresentation',
            'VariableAction', 'VariableCustomAction'
        ]));
        $result['original']['templates'] = $templates;
        $result['original']['presentation'] = $rawPresentation;
        $result['original']['objectIcon'] = $objectIcon ?? '';
        foreach ($result['conversion']['fieldCoverage'] as &$entry) {
            $key = basename($entry['target']);
            if (!array_key_exists($key, $rawPresentation)) {
                foreach ($templates as $templateID => $template) {
                    if (array_key_exists($key, $template['Values'])) {
                        $entry['source'] = '/original/templates/' . $templateID . '/Values/' . $key;
                    }
                }
                if ($key === 'ICON' && ($objectIcon ?? '') !== '' && ($profileIcon ?? '') === '') {
                    $entry['source'] = '/original/objectIcon';
                }
            }
        }
        unset($entry);
        return $result;
    }

    /** Normalize an effective presentation, optionally resolving its legacy profile. */
    public static function Normalize(int $variableType, array $presentation, array $profiles = []): array
    {
        $original = ['presentation' => $presentation, 'profiles' => $profiles];
        try {
            if (!in_array($variableType, [0, 1, 2, 3], true)) {
                throw new InvalidArgumentException('Unsupported variable type.');
            }
            $parameters = $presentation;
            $id = $parameters['PRESENTATION'] ?? self::VALUE;
            if (isset($parameters['PROFILE']) || $id === self::LEGACY) {
                $name = $parameters['PROFILE'] ?? '';
                $profile = $profiles[$name] ?? null;
                if (!is_array($profile) || ($profile['ProfileType'] ?? null) !== $variableType) {
                    throw new InvalidArgumentException('Legacy profile is missing or has a different type.');
                }
                $parameters = array_replace(self::FromProfile($variableType, $profile, $parameters['PERCENTAGE'] ?? null), $parameters);
                $id = self::VALUE;
            }
            unset($parameters['PRESENTATION'], $parameters['PROFILE']);
            if (isset($parameters['TEMPLATE'])) {
                throw new InvalidArgumentException('Native template must be resolved before normalization.');
            }
            if (!is_string($id) || preg_match('/^\{[0-9A-Fa-f-]{36}\}$/D', $id) !== 1) {
                throw new InvalidArgumentException('Native presentation ID is invalid.');
            }
            if (!in_array($id, [self::VALUE, self::SLIDER, self::ENUMERATION], true)) {
                throw new InvalidArgumentException('This native presentation requires a dedicated migration rule.');
            }
            foreach (['OPTIONS', 'INTERVALS', 'CUSTOM_GRADIENT'] as $key) {
                if (isset($parameters[$key])) {
                    $parameters[$key] = self::ListValue($parameters[$key]);
                }
            }
            self::Validate($id, $parameters);
            $keys = ['prefix'        => 'PREFIX', 'suffix' => 'SUFFIX', 'digits' => 'DIGITS', 'minimum' => 'MIN',
                'maximum'            => 'MAX', 'stepSize' => 'STEP_SIZE', 'icon' => 'ICON', 'color' => 'COLOR',
                'contentColor'       => 'CONTENT_COLOR',
                'displayType'        => 'DISPLAY_TYPE', 'previewStyle' => 'PREVIEW_STYLE', 'showPreview' => 'SHOW_PREVIEW',
                'percentage'         => 'PERCENTAGE', 'usageType' => 'USAGE_TYPE', 'decimalSeparator' => 'DECIMAL_SEPARATOR',
                'thousandsSeparator' => 'THOUSANDS_SEPARATOR', 'options' => 'OPTIONS', 'intervals' => 'INTERVALS'];
            $effective = ['kind' => match ($id) {
                self::SLIDER => 'slider', self::ENUMERATION => 'enumeration', default => 'value'
            }];
            foreach ($keys as $key => $nativeKey) {
                $effective[$key] = $parameters[$nativeKey] ?? (in_array($key, ['options', 'intervals'], true) ? [] : null);
            }
            if (isset($profile)) {
                $effective['stepSize'] = $profile['StepSize'];
            }
            $effective['extra'] = array_diff_key($parameters, array_flip(array_values($keys)));
            $coverage = [];
            $profileFields = ['MIN' => 'MinValue', 'MAX' => 'MaxValue', 'DIGITS' => 'Digits', 'PREFIX' => 'Prefix',
                'SUFFIX'            => 'Suffix', 'ICON' => 'Icon', 'PERCENTAGE' => 'Suffix', 'USAGE_TYPE' => 'ProfileName',
                'OPTIONS'           => 'Associations', 'INTERVALS' => 'Associations', 'INTERVALS_ACTIVE' => 'Associations'];
            foreach ($parameters as $key => $_) {
                $source = '/original/presentation/' . $key;
                if (!array_key_exists($key, $presentation) && isset($profile)) {
                    $source = '/original/profiles/' . str_replace(['~', '/'], ['~0', '~1'], $presentation['PROFILE']) . '/' . ($profileFields[$key] ?? $key);
                }
                $coverage[] = ['source' => $source, 'target' => '/native/parameters/' . $key, 'status' => 'exact'];
            }
            if (isset($profile)) {
                $coverage[] = ['source' => '/original/profiles/' . str_replace(['~', '/'], ['~0', '~1'], $presentation['PROFILE']) . '/StepSize',
                    'target'            => '/effective/stepSize', 'status' => 'exact'];
            }
            return ['original' => $original, 'effective' => $effective,
                'native'       => ['presentationId' => $id, 'parameters' => $parameters],
                'conversion'   => ['status' => 'exact', 'ruleVersion' => '1.0', 'fieldCoverage' => $coverage, 'issues' => []]];
        } catch (Throwable $exception) {
            return self::Blocked($original, $exception->getMessage());
        }
    }

    /** Encode only the SDK list fields; never writes or registers a presentation. */
    public static function ToSDK(array $snapshot): array
    {
        if (($snapshot['conversion']['status'] ?? null) !== 'exact' || !is_array($snapshot['native'] ?? null)) {
            throw new InvalidArgumentException('A blocked presentation cannot be applied.');
        }
        $parameters = $snapshot['native']['parameters'];
        foreach (['OPTIONS', 'INTERVALS', 'CUSTOM_GRADIENT'] as $key) {
            if (isset($parameters[$key])) {
                $parameters[$key] = json_encode($parameters[$key], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return ['PRESENTATION' => $snapshot['native']['presentationId']] + $parameters;
    }

    private static function FromProfile(int $type, array $profile, ?bool $percentage = null): array
    {
        foreach (['MinValue', 'MaxValue', 'StepSize', 'Digits', 'Prefix', 'Suffix', 'Icon', 'Associations'] as $key) {
            if (!array_key_exists($key, $profile)) {
                throw new InvalidArgumentException('Incomplete legacy profile: ' . $key);
            }
        }
        foreach (['MinValue', 'MaxValue', 'StepSize'] as $key) {
            if ((!is_int($profile[$key]) && !is_float($profile[$key])) || !is_finite((float) $profile[$key])) {
                throw new InvalidArgumentException('Invalid profile numeric field: ' . $key);
            }
        }
        foreach (['Prefix', 'Suffix', 'Icon'] as $key) {
            if (!is_string($profile[$key])) {
                throw new InvalidArgumentException('Invalid profile text field: ' . $key);
            }
        }
        foreach (self::ListValue($profile['Associations']) as $association) {
            if (!is_array($association) || !array_key_exists('Value', $association)
                || !is_string($association['Name'] ?? null) || !is_string($association['Icon'] ?? null)
                || !is_int($association['Color'] ?? null)) {
                throw new InvalidArgumentException('Invalid profile association.');
            }
        }
        // Special profiles carry more semantics than their numeric suffix/range.
        $name = $profile['ProfileName'] ?? '';
        if (is_string($name) && preg_match('/^~(?:UnixTimestamp|Duration|HexColor|HTMLBox|TextBox|Shutter|SwitchMode)/', $name)) {
            throw new InvalidArgumentException('Special legacy profile requires a dedicated migration rule.');
        }
        $parameters = ['PREFIX' => $profile['Prefix'], 'SUFFIX' => $profile['Suffix'], 'ICON' => $profile['Icon']];
        if ($type === 1 || $type === 2) {
            $parameters += ['MIN' => $profile['MinValue'], 'MAX' => $profile['MaxValue'], 'DIGITS' => $profile['Digits'],
                'PERCENTAGE'      => $percentage ?? trim($profile['Suffix']) === '%', 'USAGE_TYPE' => in_array($name, ['~Temperature', '~Temperature.Room', '~Temperature.Fahrenheit', '~Temperature.Difference'], true) ? 1 : 0];
            if ($profile['Associations'] !== []) {
                if ($parameters['PERCENTAGE']) {
                    throw new InvalidArgumentException('Combined percentage scaling and association intervals require a dedicated migration rule.');
                }
                $associations = self::ListValue($profile['Associations']);
                usort($associations, static fn (array $a, array $b): int => $a['Value'] <=> $b['Value']);
                $intervals = [];
                foreach ($associations as $index => $association) {
                    $value = $association['Value'] ?? null;
                    $until = $associations[$index + 1]['Value'] ?? $profile['MaxValue'];
                    if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
                        || $value > $until || ($index > 0 && $value <= $associations[$index - 1]['Value'])
                        || !is_string($association['Name'] ?? null) || preg_match('/%(?!%)/', str_replace('%%', '', $association['Name'])) === 1) {
                        throw new InvalidArgumentException('Association bounds or dynamic printf texts require a dedicated migration rule.');
                    }
                    $intervals[] = ['IntervalMinValue' => $value, 'IntervalMaxValue' => $until,
                        'ConstantActive'               => $association['Name'] !== '', 'ConstantValue' => str_replace('%%', '%', $association['Name']),
                        'ConversionFactor'             => 1, 'PrefixActive' => false, 'SuffixActive' => false, 'DigitsActive' => false,
                        'IconActive'                   => $association['Icon'] !== '', 'IconValue' => $association['Icon'],
                        'ColorActive'                  => true, 'Color' => $association['Color']];
                }
                $parameters['INTERVALS_ACTIVE'] = true;
                $parameters['INTERVALS'] = $intervals;
            }
        } else {
            $options = [];
            $seen = [];
            foreach (self::ListValue($profile['Associations']) as $association) {
                if (preg_match('/%(?!%)/', str_replace('%%', '', $association['Name'])) === 1) {
                    throw new InvalidArgumentException('Dynamic association text requires a dedicated migration rule.');
                }
                $value = $association['Value'] ?? null;
                if ($type === 0) {
                    if (!in_array($value, [0, 0.0, 1, 1.0, false, true], true)) {
                        throw new InvalidArgumentException('Boolean association is not Boolean-compatible.');
                    }
                    $value = (bool) $value;
                } elseif (!is_string($value)) {
                    throw new InvalidArgumentException('String association requires a string value.');
                }
                $identity = json_encode($value, JSON_THROW_ON_ERROR);
                if (isset($seen[$identity])) {
                    throw new InvalidArgumentException('Duplicate association value.');
                }
                $seen[$identity] = true;
                $options[] = ['Value' => $value, 'Caption' => str_replace('%%', '%', $association['Name']),
                    'IconActive'      => $association['Icon'] !== '', 'IconValue' => $association['Icon'],
                    'ColorActive'     => true, 'ColorValue' => $association['Color']];
            }
            $parameters['OPTIONS'] = $options;
        }
        return $parameters;
    }

    private static function Validate(string $id, array $parameters): void
    {
        $strings = ['PREFIX', 'SUFFIX', 'ICON', 'THOUSANDS_SEPARATOR', 'DECIMAL_SEPARATOR'];
        $numbers = ['MIN', 'MAX', 'STEP_SIZE'];
        $integers = ['DIGITS', 'COLOR', 'CONTENT_COLOR', 'USAGE_TYPE', 'GRADIENT_TYPE', 'LAYOUT', 'DISPLAY', 'DISPLAY_TYPE', 'PREVIEW_STYLE'];
        $booleans = ['PERCENTAGE', 'INTERVALS_ACTIVE', 'MULTILINE', 'SHOW_PREVIEW'];
        foreach ($parameters as $key => $value) {
            $valid = match (true) {
                in_array($key, $strings, true)                                    => is_string($value),
                in_array($key, $numbers, true)                                    => (is_int($value) || is_float($value)) && is_finite((float) $value),
                in_array($key, $integers, true)                                   => is_int($value),
                in_array($key, $booleans, true)                                   => is_bool($value),
                in_array($key, ['OPTIONS', 'INTERVALS', 'CUSTOM_GRADIENT'], true) => is_array($value) && array_is_list($value),
                default                                                           => false
            };
            if (!$valid || ($key === 'DIGITS' && $value < 0)) {
                throw new InvalidArgumentException('Invalid or unsupported native parameter: ' . $key);
            }
        }
        if ($id === self::VALUE && array_key_exists('STEP_SIZE', $parameters)) {
            throw new InvalidArgumentException('Value presentation does not support STEP_SIZE.');
        }
        foreach ($parameters['OPTIONS'] ?? [] as $option) {
            if (!is_array($option) || !array_key_exists('Value', $option) || !is_string($option['Caption'] ?? null)) {
                throw new InvalidArgumentException('Native option is invalid.');
            }
            self::ValidateFields($option, ['Value' => 'scalar', 'Caption' => 'string', 'IconActive' => 'boolean',
                'IconValue'                        => 'string', 'ColorActive' => 'boolean', 'ColorValue' => 'integer', 'Color' => 'integer',
                'ContentColorActive'               => 'boolean', 'ContentColorValue' => 'integer']);
        }
        foreach ($parameters['INTERVALS'] ?? [] as $interval) {
            if (!is_array($interval) || !isset($interval['IntervalMinValue'], $interval['IntervalMaxValue'])
                || $interval['IntervalMinValue'] > $interval['IntervalMaxValue'] || ($interval['ConversionFactor'] ?? 1) <= 0) {
                throw new InvalidArgumentException('Native interval is invalid.');
            }
            self::ValidateFields($interval, ['IntervalMinValue' => 'number', 'IntervalMaxValue' => 'number',
                'ConversionFactor'                              => 'number', 'ConstantActive' => 'boolean', 'ConstantValue' => 'string',
                'PrefixActive'                                  => 'boolean', 'PrefixValue' => 'string', 'SuffixActive' => 'boolean', 'SuffixValue' => 'string',
                'DigitsActive'                                  => 'boolean', 'DigitsValue' => 'integer', 'IconActive' => 'boolean', 'IconValue' => 'string',
                'ColorActive'                                   => 'boolean', 'Color' => 'integer', 'ColorValue' => 'integer',
                'ContentColorActive'                            => 'boolean', 'ContentColorValue' => 'integer']);
        }
        // Validate nested numeric values/UTF-8 without changing them.
        json_encode($parameters, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function ValidateFields(array $row, array $schema): void
    {
        foreach ($row as $key => $value) {
            $valid = match ($schema[$key] ?? '') {
                'string' => is_string($value), 'boolean' => is_bool($value), 'integer' => is_int($value),
                'scalar' => is_scalar($value), 'number' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                default  => false
            };
            if (!$valid) {
                throw new InvalidArgumentException('Invalid native list parameter: ' . $key);
            }
        }
    }

    private static function ListValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('Presentation list must be an array.');
        }
        return $value;
    }

    private static function Blocked(array $original, string $message): array
    {
        return ['original' => $original, 'effective' => null, 'native' => null,
            'conversion'   => ['status' => 'blocked', 'ruleVersion' => '1.0', 'fieldCoverage' => [],
                'issues'                => [['code' => 'PRESENTATION_UNSUPPORTED', 'message' => $message]]]];
    }
}
