<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @var array<int,array<string,mixed>> $searchObjects */
$searchObjects = [
    0    => ['ObjectType' => 0, 'ObjectName' => 'Symcon', 'ObjectIsHidden' => false],
    1000 => ['ObjectType' => 0, 'ObjectName' => 'House', 'ObjectIsHidden' => false],
    1100 => ['ObjectType' => 1, 'ObjectName' => 'Ground floor', 'ObjectIsHidden' => false],
    1101 => ['ObjectType' => 2, 'ObjectName' => 'Window', 'ObjectIsHidden' => false],
    1102 => ['ObjectType' => 2, 'ObjectName' => 'Temperature', 'ObjectIsHidden' => false],
    1200 => ['ObjectType' => 0, 'ObjectName' => 'Hidden room', 'ObjectIsHidden' => true],
    1201 => ['ObjectType' => 2, 'ObjectName' => 'Hidden window', 'ObjectIsHidden' => false],
    1300 => ['ObjectType' => 0, 'ObjectName' => 'Upper floor', 'ObjectIsHidden' => false],
    1301 => ['ObjectType' => 2, 'ObjectName' => 'Window', 'ObjectIsHidden' => false],
];

/** @var array<int,list<int>> $searchChildren */
$searchChildren = [
    0    => [1000],
    1000 => [1300, 1100, 1200],
    1100 => [1102, 1101],
    1200 => [1201],
    1300 => [1301],
];

/** @var array<int,array{VariableType:int}> $searchVariables */
$searchVariables = [
    1101 => ['VariableType' => 0],
    1102 => ['VariableType' => 2],
    1201 => ['VariableType' => 0],
    1301 => ['VariableType' => 0],
];

if (!function_exists('IPS_ObjectExists')) {
    function IPS_ObjectExists(int $objectID): bool
    {
        return isset($GLOBALS['searchObjects'][$objectID]);
    }
}

if (!function_exists('IPS_GetObject')) {
    /** @return array<string,mixed> */
    function IPS_GetObject(int $objectID): array
    {
        if ($objectID === 0) {
            throw new RuntimeException('The Symcon root must not be read through IPS_GetObject().');
        }

        return $GLOBALS['searchObjects'][$objectID] ?? [];
    }
}

if (!function_exists('IPS_GetChildrenIDs')) {
    /** @return list<int> */
    function IPS_GetChildrenIDs(int $objectID): array
    {
        return $GLOBALS['searchChildren'][$objectID] ?? [];
    }
}

if (!function_exists('IPS_VariableExists')) {
    function IPS_VariableExists(int $variableID): bool
    {
        return isset($GLOBALS['searchVariables'][$variableID]);
    }
}

if (!function_exists('IPS_GetVariable')) {
    /** @return array{VariableType:int} */
    function IPS_GetVariable(int $variableID): array
    {
        return $GLOBALS['searchVariables'][$variableID] ?? ['VariableType' => -1];
    }
}

require_once __DIR__ . '/../src/VariableSearchHelper.php';

use Burki24\SymconModuleHelper\VariableSearchHelper;

$result = VariableSearchHelper::Search(1000, 'window', [0]);
assertSameValue(2, count($result['rows']), 'Visible Boolean variables with the same name must remain distinct by ID.');
assertSameValue([1101, 1301], array_column($result['rows'], 'VariableID'), 'Search results must be sorted by path.');
assertSameValue('House > Ground floor > Window', $result['rows'][0]['Path'], 'Search results must contain their readable path.');
assertSameValue(0, $result['excluded'], 'No variable should be excluded without configured IDs.');
assertFalseValue($result['truncated'], 'A bounded complete search must not be marked as truncated.');

$excluded = VariableSearchHelper::Search(1000, 'window', [0], false, 10, 5000, 500, [1101]);
assertSameValue([1301], array_column($excluded['rows'], 'VariableID'), 'Configured variable IDs must be excluded without using their names.');
assertSameValue(1, $excluded['excluded'], 'Excluded matching variables must be counted.');

$hidden = VariableSearchHelper::Search(1000, 'window', [0], true);
assertSameValue([1101, 1201, 1301], array_column($hidden['rows'], 'VariableID'), 'Hidden subtrees must only be searched when requested.');

$typed = VariableSearchHelper::Search(1000, '', [2]);
assertSameValue([1102], array_column($typed['rows'], 'VariableID'), 'An empty name filter must still respect the selected variable types.');

$rootSearch = VariableSearchHelper::Search(0, 'temperature', [2]);
assertSameValue([1102], array_column($rootSearch['rows'], 'VariableID'), 'The synthetic Symcon root must support complete tree searches.');

$allTerms = VariableSearchHelper::Search(1000, 'temp', [2], additionalNameContains: ['rature']);
assertSameValue([1102], array_column($allTerms['rows'], 'VariableID'), 'All supplied name filters must match case-insensitively.');

$depthLimited = VariableSearchHelper::Search(1000, 'window', [0], false, 1);
assertSameValue([], $depthLimited['rows'], 'Variables below the configured depth must not be returned.');

$resultLimited = VariableSearchHelper::Search(1000, 'window', [0], false, 10, 5000, 1);
assertSameValue(1, count($resultLimited['rows']), 'The result limit must bound returned candidates.');
assertTrueValue($resultLimited['truncated'], 'Reaching the result limit must be reported.');

$submitted = [
    ['Selected' => true, 'VariableID' => 1101],
    ['Selected' => false, 'VariableID' => 1301],
    ['Selected' => true, 'VariableID' => 1101],
];
assertSameValue(
    [1101],
    VariableSearchHelper::ResolveSelectedVariableIDs($submitted, $result['rows']),
    'Selected IDs must be unique and limited to the fresh result.'
);

try {
    VariableSearchHelper::ResolveSelectedVariableIDs(
        [['Selected' => true, 'VariableID' => 9999]],
        $result['rows']
    );
    throw new RuntimeException('A forged variable ID must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue('The selected variables changed; search again.', $exception->getMessage(), 'A stale selection must report a stable error.');
}

try {
    VariableSearchHelper::Search(9999, 'window');
    throw new RuntimeException('An invalid root must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue('Select a valid search root.', $exception->getMessage(), 'An invalid root must report a stable error.');
}

fwrite(STDOUT, "VariableSearchHelper tests passed.\n");
