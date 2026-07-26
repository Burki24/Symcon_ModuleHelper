<?php

declare(strict_types=1);

/** @var array<int,array{ConnectionID?:int}> $parentConnectionInstances */
$parentConnectionInstances = [];
/** @var array<int,bool> $existingParentInstances */
$existingParentInstances = [];

if (!function_exists('IPS_GetInstance')) {
    /** @return array{ConnectionID?:int} */
    function IPS_GetInstance(int $instanceID): array
    {
        /** @var array<int,array{ConnectionID?:int}> $instances */
        $instances = $GLOBALS['parentConnectionInstances'];

        return $instances[$instanceID] ?? [];
    }
}

if (!function_exists('IPS_InstanceExists')) {
    function IPS_InstanceExists(int $instanceID): bool
    {
        /** @var array<int,bool> $instances */
        $instances = $GLOBALS['existingParentInstances'];

        return $instances[$instanceID] ?? false;
    }
}

require_once __DIR__ . '/../src/ParentConnectionHelper.php';

use Burki24\SymconModuleHelper\ParentConnectionHelper;

final class ParentConnectionHarness
{
    use ParentConnectionHelper;

    public function __construct(public int $InstanceID)
    {
    }

    public function parentID(): int
    {
        return $this->GetParentID();
    }

    public function parentAvailable(): bool
    {
        return $this->HasParent();
    }
}

$instance = new ParentConnectionHarness(1001);

$parentConnectionInstances[1001] = ['ConnectionID' => 2001];
$existingParentInstances[2001] = true;
assertSameValue(2001, $instance->parentID(), 'Connected parent ID must be returned from the Symcon instance information.');
assertTrueValue($instance->parentAvailable(), 'An existing connected parent instance must be reported as available.');

$existingParentInstances[2001] = false;
assertFalseValue($instance->parentAvailable(), 'A referenced parent that no longer exists must be reported as unavailable.');

$parentConnectionInstances[1001] = ['ConnectionID' => 0];
assertSameValue(0, $instance->parentID(), 'A module without a connected parent must return parent ID 0.');
assertFalseValue($instance->parentAvailable(), 'A module without a connected parent must be reported as unavailable.');

$parentConnectionInstances[1001] = [];
assertSameValue(0, $instance->parentID(), 'Missing ConnectionID information must safely fall back to parent ID 0.');
assertFalseValue($instance->parentAvailable(), 'Missing ConnectionID information must not report a parent connection.');

fwrite(STDOUT, "ParentConnectionHelper tests passed.\n");
