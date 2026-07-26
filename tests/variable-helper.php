<?php

declare(strict_types=1);

/** @var array<int,array<string,int|false>> $variableObjectsByParent */
$variableObjectsByParent = [];

/** @var array<int,bool> $existingVariableIDs */
$existingVariableIDs = [];

if (!function_exists('IPS_GetObjectIDByIdent')) {
    function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
    {
        /** @var array<int,array<string,int|false>> $objectsByParent */
        $objectsByParent = $GLOBALS['variableObjectsByParent'];

        return $objectsByParent[$parentID][$ident] ?? false;
    }
}

if (!function_exists('IPS_VariableExists')) {
    function IPS_VariableExists(int $variableID): bool
    {
        /** @var array<int,bool> $variableIDs */
        $variableIDs = $GLOBALS['existingVariableIDs'];

        return $variableIDs[$variableID] ?? false;
    }
}

require_once __DIR__ . '/../src/VariableHelper.php';

use Burki24\SymconModuleHelper\VariableHelper;

final class VariableHelperHarness
{
    use VariableHelper;

    public function __construct(public int $InstanceID)
    {
    }

    public function variableID(string $ident, ?int $parentID = null): int
    {
        return $this->GetVariableIDByIdent($ident, $parentID);
    }

    public function variableAvailable(string $ident, ?int $parentID = null): bool
    {
        return $this->VariableExists($ident, $parentID);
    }
}

$firstModule = new VariableHelperHarness(1001);
$secondModule = new VariableHelperHarness(1002);

$variableObjectsByParent[1001] = [
    'Temperature' => 2001,
    'ObjectOnly'  => 2002,
    'ZeroValue'   => 0,
    'Broken'      => false,
];
$variableObjectsByParent[1002] = [
    'Temperature' => 3001,
];
$variableObjectsByParent[4001] = [
    'LastSynchronization' => 5001,
];

$existingVariableIDs = [
    2001 => true,
    3001 => true,
    5001 => true,
];

assertSameValue(2001, $firstModule->variableID('Temperature'), 'A matching variable ID must be returned for the current module instance.');
assertTrueValue($firstModule->variableAvailable('Temperature'), 'A positive variable ID must be reported as available.');

assertSameValue(0, $firstModule->variableID('Missing'), 'A missing ident must be normalized to variable ID 0.');
assertFalseValue($firstModule->variableAvailable('Missing'), 'A missing ident must be reported as unavailable.');

assertSameValue(0, $firstModule->variableID('ZeroValue'), 'Variable ID 0 must be treated as unavailable.');
assertFalseValue($firstModule->variableAvailable('ZeroValue'), 'Variable ID 0 must not be reported as available.');

assertSameValue(0, $firstModule->variableID('Broken'), 'A failed Symcon lookup must be normalized to variable ID 0.');
assertFalseValue($firstModule->variableAvailable('Broken'), 'A failed Symcon lookup must be reported as unavailable.');

assertSameValue(0, $firstModule->variableID('ObjectOnly'), 'A matching non-variable object must not be returned as a variable.');
assertFalseValue($firstModule->variableAvailable('ObjectOnly'), 'A matching non-variable object must not be reported as an available variable.');

assertSameValue(3001, $secondModule->variableID('Temperature'), 'Variable lookup must remain scoped to the current module instance.');

assertSameValue(
    5001,
    $firstModule->variableID('LastSynchronization', 4001),
    'An explicit parent ID must allow variable lookup below another Symcon object.'
);
assertTrueValue(
    $firstModule->variableAvailable('LastSynchronization', 4001),
    'Variable existence checks must support an explicit parent ID.'
);
assertSameValue(
    0,
    $firstModule->variableID('Temperature', 4001),
    'An explicit parent ID must replace, not supplement, the current module instance.'
);

fwrite(STDOUT, "VariableHelper tests passed.\n");
