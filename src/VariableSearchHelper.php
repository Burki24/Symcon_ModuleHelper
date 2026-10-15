<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use InvalidArgumentException;

/**
 * Searches variables below a bounded Symcon object-tree root.
 *
 * The helper deliberately returns only neutral object metadata. Consumers
 * remain responsible for form fields, captions and domain-specific defaults.
 * Submitted selections can be resolved against a freshly repeated search so
 * stale or forged variable IDs are never accepted silently.
 *
 * @version 1.0.0
 */
final class VariableSearchHelper
{
    private const VARIABLE_OBJECT_TYPE = 2;
    private const VARIABLE_TYPES = [0, 1, 2, 3];

    /**
     * @param list<int> $variableTypes
     * @param list<int> $excludedVariableIDs
     * @param list<string> $additionalNameContains
     *
     * @return array{
     *     rows:list<array{VariableID:int,Name:string,Path:string,VariableType:int}>,
     *     excluded:int,
     *     visited:int,
     *     truncated:bool
     * }
     */
    public static function Search(
        int $rootObjectID,
        string $nameContains,
        array $variableTypes = self::VARIABLE_TYPES,
        bool $includeHidden = false,
        int $maxDepth = 10,
        int $maxObjects = 5000,
        int $maxResults = 500,
        array $excludedVariableIDs = [],
        array $additionalNameContains = []
    ): array {
        self::ValidateSearchArguments(
            $rootObjectID,
            $nameContains,
            $variableTypes,
            $maxDepth,
            $maxObjects,
            $maxResults,
            $additionalNameContains
        );

        $normalizedTypes = array_fill_keys(array_values(array_unique($variableTypes)), true);
        $excludedIDs = [];
        foreach ($excludedVariableIDs as $variableID) {
            if (is_int($variableID) && $variableID > 0) {
                $excludedIDs[$variableID] = true;
            }
        }

        $term = trim($nameContains);
        $nameTerms = array_values(array_filter(
            [$term, ...array_map(static fn (string $value): string => trim($value), $additionalNameContains)],
            static fn (string $value): bool => $value !== ''
        ));
        $rows = [];
        $excluded = 0;
        $visited = 0;
        $truncated = false;
        $seenObjectIDs = [];

        $walk = static function (int $objectID, int $depth, string $parentPath) use (
            &$walk,
            &$rows,
            &$excluded,
            &$visited,
            &$truncated,
            &$seenObjectIDs,
            $nameTerms,
            $normalizedTypes,
            $excludedIDs,
            $includeHidden,
            $maxDepth,
            $maxObjects,
            $maxResults
        ): void {
            if ($truncated || isset($seenObjectIDs[$objectID])) {
                return;
            }
            if ($visited >= $maxObjects) {
                $truncated = true;
                return;
            }

            $seenObjectIDs[$objectID] = true;
            ++$visited;
            $object = $objectID === 0
                ? ['ObjectType' => 0, 'ObjectName' => 'Symcon', 'ObjectIsHidden' => false]
                : IPS_GetObject($objectID);
            if (!is_array($object)) {
                return;
            }
            if (!$includeHidden && (bool) ($object['ObjectIsHidden'] ?? false)) {
                return;
            }

            $name = trim((string) ($object['ObjectName'] ?? ''));
            if ($objectID === 0 && $name === '') {
                $name = 'Symcon';
            }
            $path = $parentPath === '' ? $name : $parentPath . ' > ' . $name;
            $objectType = $object['ObjectType'] ?? null;

            if ($objectType === self::VARIABLE_OBJECT_TYPE && IPS_VariableExists($objectID)) {
                $variable = IPS_GetVariable($objectID);
                $variableType = is_array($variable) ? ($variable['VariableType'] ?? null) : null;
                if (is_int($variableType)
                    && isset($normalizedTypes[$variableType])
                    && self::NameMatchesAll($name, $nameTerms)) {
                    if (isset($excludedIDs[$objectID])) {
                        ++$excluded;
                    } elseif (count($rows) >= $maxResults) {
                        $truncated = true;
                    } else {
                        $rows[] = [
                            'VariableID'   => $objectID,
                            'Name'         => $name,
                            'Path'         => $path,
                            'VariableType' => $variableType
                        ];
                    }
                }
                return;
            }

            if ($depth >= $maxDepth) {
                return;
            }

            $children = IPS_GetChildrenIDs($objectID);
            if (!is_array($children)) {
                return;
            }
            foreach ($children as $childID) {
                if (!is_int($childID) || $childID < 1) {
                    continue;
                }
                $walk($childID, $depth + 1, $path);
                if ($truncated) {
                    break;
                }
            }
        };

        $walk($rootObjectID, 0, '');
        usort(
            $rows,
            static fn (array $left, array $right): int => strnatcasecmp($left['Path'], $right['Path'])
                ?: ($left['VariableID'] <=> $right['VariableID'])
        );

        return [
            'rows'      => $rows,
            'excluded'  => $excluded,
            'visited'   => $visited,
            'truncated' => $truncated
        ];
    }

    /**
     * @param list<array<string,mixed>> $submittedCandidates
     * @param list<array{VariableID:int,Name:string,Path:string,VariableType:int}> $freshCandidates
     *
     * @return list<int>
     */
    public static function ResolveSelectedVariableIDs(array $submittedCandidates, array $freshCandidates): array
    {
        $allowed = [];
        foreach ($freshCandidates as $candidate) {
            $variableID = $candidate['VariableID'] ?? null;
            if (is_int($variableID) && $variableID > 0) {
                $allowed[$variableID] = true;
            }
        }

        $selected = [];
        foreach ($submittedCandidates as $candidate) {
            if (!is_array($candidate) || ($candidate['Selected'] ?? false) !== true) {
                continue;
            }
            $variableID = $candidate['VariableID'] ?? null;
            if (!is_int($variableID) || !isset($allowed[$variableID])) {
                throw new InvalidArgumentException('The selected variables changed; search again.');
            }
            $selected[$variableID] = true;
        }

        return array_keys($selected);
    }

    /** @param list<int> $variableTypes */
    private static function ValidateSearchArguments(
        int $rootObjectID,
        string $nameContains,
        array $variableTypes,
        int $maxDepth,
        int $maxObjects,
        int $maxResults,
        array $additionalNameContains
    ): void {
        if ($rootObjectID < 0 || ($rootObjectID > 0 && !IPS_ObjectExists($rootObjectID))) {
            throw new InvalidArgumentException('Select a valid search root.');
        }
        foreach ([$nameContains, ...$additionalNameContains] as $nameTerm) {
            if (!is_string($nameTerm) || mb_strlen(trim($nameTerm), 'UTF-8') > 120) {
                throw new InvalidArgumentException('The variable-name search may contain at most 120 characters.');
            }
        }
        if ($variableTypes === [] || array_values(array_unique($variableTypes)) !== $variableTypes) {
            throw new InvalidArgumentException('Select at least one unique variable type.');
        }
        foreach ($variableTypes as $variableType) {
            if (!is_int($variableType) || !in_array($variableType, self::VARIABLE_TYPES, true)) {
                throw new InvalidArgumentException('The selected variable type is invalid.');
            }
        }
        if ($maxDepth < 0 || $maxDepth > 100 || $maxObjects < 1 || $maxObjects > 100000
            || $maxResults < 1 || $maxResults > 10000) {
            throw new InvalidArgumentException('The search limits are invalid.');
        }
    }

    /** @param list<string> $nameTerms */
    private static function NameMatchesAll(string $name, array $nameTerms): bool
    {
        foreach ($nameTerms as $nameTerm) {
            if (mb_stripos($name, $nameTerm, 0, 'UTF-8') === false) {
                return false;
            }
        }

        return true;
    }
}
