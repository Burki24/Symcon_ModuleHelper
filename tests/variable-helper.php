<?php

declare(strict_types=1);

/** @var array<int,array<string,int|false>> $variableObjectsByParent */
$variableObjectsByParent = [];

if (!function_exists('IPS_GetObjectIDByIdent')) {
    function IPS_GetObjectIDByIdent(string $ident, int $parentID): int|false
    {
        /** @var array<int,array<string,int|false>> $objectsByParent */
        $objectsByParent = $GLOBALS['variableObjectsByParent'];

        return $objectsByParent[$parentID][$ident] ?? false;
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

    public function variableID(string $ident): int
    {
        return $this->GetVariableIDByIdent($ident);
    }

    public function variableAvailable(string $ident): bool
    {
        return $this->VariableExists($ident);
    }
}

$firstModule = new VariableHelperHarness(1001);
$secondModule = new VariableHelperHarness(1002);

$variableObjectsByParent[1001] = [
    'Temperature' => 2001,
    'ZeroValue'   => 0,
    'Broken'      => false,
];
$variableObjectsByParent[1002] = [
    'Temperature' => 3001,
];

assertSameValue(2001, $firstModule->variableID('Temperature'), 'A matching variable ID must be returned for the current module instance.');
assertTrueValue($firstModule->variableAvailable('Temperature'), 'A positive variable ID must be reported as available.');

assertSameValue(0, $firstModule->variableID('Missing'), 'A missing ident must be normalized to variable ID 0.');
assertFalseValue($firstModule->variableAvailable('Missing'), 'A missing ident must be reported as unavailable.');

assertSameValue(0, $firstModule->variableID('ZeroValue'), 'Variable ID 0 must be treated as unavailable.');
assertFalseValue($firstModule->variableAvailable('ZeroValue'), 'Variable ID 0 must not be reported as available.');

assertSameValue(0, $firstModule->variableID('Broken'), 'A failed Symcon lookup must be normalized to variable ID 0.');
assertFalseValue($firstModule->variableAvailable('Broken'), 'A failed Symcon lookup must be reported as unavailable.');

assertSameValue(3001, $secondModule->variableID('Temperature'), 'Variable lookup must remain scoped to the current module instance.');

fwrite(STDOUT, "VariableHelper tests passed.\n");
