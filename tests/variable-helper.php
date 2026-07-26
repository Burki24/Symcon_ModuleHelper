<?php

declare(strict_types=1);

/** @var array<int,array<string,int|false>> $variableObjectsByParent */
$variableObjectsByParent = [];

/** @var array<int,bool> $existingVariableIDs */
$existingVariableIDs = [];

/** @var array<int,mixed> $variableValuesByID */
$variableValuesByID = [];

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

if (!function_exists('GetValue')) {
    function GetValue(int $variableID): mixed
    {
        /** @var array<int,mixed> $values */
        $values = $GLOBALS['variableValuesByID'];

        return $values[$variableID] ?? null;
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

    public function variableValue(string $ident, ?int $parentID = null, mixed $default = null): mixed
    {
        return $this->GetVariableValueByIdent($ident, $parentID, $default);
    }

    public function booleanValue(string $ident, ?int $parentID = null, bool $default = false): bool
    {
        return $this->GetBooleanVariableValueByIdent($ident, $parentID, $default);
    }

    public function floatValue(string $ident, ?int $parentID = null, float $default = 0.0): float
    {
        return $this->GetFloatVariableValueByIdent($ident, $parentID, $default);
    }

    public function integerValue(string $ident, ?int $parentID = null, int $default = 0): int
    {
        return $this->GetIntegerVariableValueByIdent($ident, $parentID, $default);
    }

    public function stringValue(string $ident, ?int $parentID = null, string $default = ''): string
    {
        return $this->GetStringVariableValueByIdent($ident, $parentID, $default);
    }
}

$firstModule = new VariableHelperHarness(1001);
$secondModule = new VariableHelperHarness(1002);

$variableObjectsByParent[1001] = [
    'Temperature' => 2001,
    'ObjectOnly'  => 2002,
    'ZeroValue'   => 0,
    'Broken'      => false,
    'Count'       => 2003,
    'Name'        => 2004,
    'Active'      => 2005,
    'NumericOn'   => 2006,
    'NumericOff'  => 2007,
    'TextNumber'  => 2008,
];
$variableObjectsByParent[1002] = [
    'Temperature' => 3001,
];
$variableObjectsByParent[4001] = [
    'LastSynchronization' => 5001,
    'ExternalName'        => 5002,
];

$existingVariableIDs = [
    2001 => true,
    2003 => true,
    2004 => true,
    2005 => true,
    2006 => true,
    2007 => true,
    2008 => true,
    3001 => true,
    5001 => true,
    5002 => true,
];

$variableValuesByID = [
    2001 => 21.75,
    2003 => 42,
    2004 => 'Wolf CGB-20',
    2005 => true,
    2006 => 1,
    2007 => 0.0,
    2008 => '12.5',
    3001 => 18.5,
    5001 => 1234567890,
    5002 => 'External calendar',
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

assertSameValue(21.75, $firstModule->variableValue('Temperature'), 'Raw value lookup must preserve the native Float value.');
assertSameValue(42, $firstModule->variableValue('Count'), 'Raw value lookup must preserve the native Integer value.');
assertSameValue('fallback', $firstModule->variableValue('Missing', default: 'fallback'), 'Missing raw values must return the caller-defined default.');
assertSameValue('External calendar', $firstModule->variableValue('ExternalName', 4001), 'Raw value lookup must support an explicit parent ID.');

assertTrueValue($firstModule->booleanValue('Active'), 'Native Boolean true values must be returned unchanged.');
assertTrueValue($firstModule->booleanValue('NumericOn'), 'Non-zero numeric values must normalize to Boolean true.');
assertFalseValue($firstModule->booleanValue('NumericOff'), 'Zero numeric values must normalize to Boolean false.');
assertTrueValue($firstModule->booleanValue('Missing', default: true), 'Missing Boolean values must return the caller-defined default.');
assertFalseValue($firstModule->booleanValue('TextNumber'), 'String values must not be converted to Boolean values.');

assertSameValue(21.75, $firstModule->floatValue('Temperature'), 'Native Float values must be returned unchanged.');
assertSameValue(42.0, $firstModule->floatValue('Count'), 'Native Integer values must normalize to Float.');
assertSameValue(9.5, $firstModule->floatValue('Missing', default: 9.5), 'Missing numeric values must return the caller-defined default.');
assertSameValue(7.5, $firstModule->floatValue('TextNumber', default: 7.5), 'String values must not be converted to numeric values.');

assertSameValue(42, $firstModule->integerValue('Count'), 'Native Integer values must be returned unchanged.');
assertSameValue(-1, $firstModule->integerValue('Temperature', default: -1), 'Float values must not be converted to Integer values.');
assertSameValue(1234567890, $firstModule->integerValue('LastSynchronization', 4001), 'Integer value lookup must support an explicit parent ID.');

assertSameValue('Wolf CGB-20', $firstModule->stringValue('Name'), 'Native String values must be returned unchanged.');
assertSameValue('unknown', $firstModule->stringValue('Count', default: 'unknown'), 'Integer values must not be converted to String values.');
assertSameValue('External calendar', $firstModule->stringValue('ExternalName', 4001), 'String value lookup must support an explicit parent ID.');

fwrite(STDOUT, "VariableHelper tests passed.\n");
