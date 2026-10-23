<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/GeofenceHelper.php';

$geofence = new class() {
    use Burki24\SymconModuleHelper\GeofenceHelper;

    public function run(?array $fix, array $state = [], int $now = 2000, array $settings = [], ?array $zones = null): array
    {
        return $this->EvaluateGeofence($zones ?? [['id' => 'home', 'name' => 'Home', 'latitude' => 0, 'longitude' => 0, 'radius' => 100]], 'home', $fix, $state, $now, $settings);
    }

    public function distance(float $a, float $b, float $c, float $d): float
    {
        return $this->GeofenceDistanceMeters($a, $b, $c, $d);
    }

    public function aggregate(array $states): int
    {
        return $this->AggregateGeofencePresence($states);
    }
};
foreach ([[[], -1], [[0], 0], [[0, 0], 0], [[1, 0], 1], [[1, -1], 1], [[-1, 0], -1], [[-1, -1], -1]] as [$members, $expected]) {
    assertSameValue($expected, $geofence->aggregate($members), 'Aggregate presence follows the explicit any-present/all-absent contract.');
}
foreach ([[true], ['1'], [2], [1, null], ['member' => 0]] as $invalid) {
    try {
        $geofence->aggregate($invalid);
        throw new RuntimeException('Invalid aggregate member states were accepted.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
$fix = ['latitude' => 0.0, 'longitude' => 0.0, 'accuracy' => 5, 'timestamp' => 1900];
assertTrueValue(abs($geofence->distance(0, 0, 0, 90) - 10007557.22101796) < 0.01, 'Equatorial quarter-circle distance.');
assertTrueValue(abs($geofence->distance(0, 179.999, 0, -179.999) - 222.39016) < 0.01, 'Antimeridian distance must take the short arc.');
assertSameValue(0.0, $geofence->distance(90, 0, 90, 0), 'Equal points have zero distance.');
$home = $geofence->run($fix);
assertSameValue(1, $home['presence'], 'First confident fix establishes presence.');
assertSameValue(100, $home['duration'], 'Stay begins at the observed position timestamp.');
$boundaryFix = array_replace($fix, ['longitude' => 0.0009, 'timestamp' => 2001]);
$boundary = $geofence->run($boundaryFix, $home['state'], 2001);
assertSameValue(1, $boundary['presence'], 'A boundary fix must hold the previously confirmed zone.');
assertSameValue('held', $boundary['reason'], 'Hysteresis must be visible.');
$exitFix = array_replace($fix, ['longitude' => 0.002, 'timestamp' => 2010]);
$pending = $geofence->run($exitFix, $boundary['state'], 2010);
assertSameValue(true, $pending['pending'], 'First exit observation starts confirmation.');
assertSameValue(1, $pending['presence'], 'A pending exit retains the confirmed zone.');
$duplicate = $geofence->run($exitFix, $pending['state'], 2050);
assertSameValue(true, $duplicate['pending'], 'Timer reads of one fix must never confirm transitions.');
$outside = $geofence->run(array_replace($exitFix, ['timestamp' => 2051]), $duplicate['state'], 2051);
assertSameValue(0, $outside['presence'], 'A distinct later observation confirms exit.');
assertSameValue(0, $outside['duration'], 'Outside has no zone stay duration.');
$returned = $geofence->run(array_replace($fix, ['timestamp' => 2060]), $outside['state'], 2060, ['confirmSeconds' => 0]);
assertSameValue(1, $returned['presence'], 'Explicit zero confirmation allows immediate change.');
foreach ([null, array_replace($fix, ['accuracy'                                => null]), array_replace($fix, ['accuracy' => 0]), array_replace($fix, ['accuracy' => 101]),
    array_replace($fix, ['latitude' => INF]), array_replace($fix, ['timestamp' => 5000]), array_replace($fix, ['isOld' => true]), array_replace($fix, ['isInaccurate' => true])] as $bad) {
    $unknown = $geofence->run($bad, $home['state']);
    assertSameValue(-1, $unknown['presence'], 'Unreliable fixes must be unknown, not absent.');
    assertSameValue(0, $unknown['duration'], 'Unknown must not accumulate unobserved stays.');
    assertSameValue(null, $unknown['distance'], 'Unreliable distances must not be current.');
}
$expired = $geofence->run($fix, $home['state'], 3000);
assertSameValue('stale', $expired['reason'], 'Timer must invalidate an aged fix without network I/O.');
assertSameValue(-1, $expired['presence'], 'Age expiration must clear presence.');
$jump = $geofence->run(array_replace($fix, ['latitude' => 10, 'timestamp' => 1901]), $home['state'], 2000);
assertSameValue('jump', $jump['reason'], 'Implausible movement must be rejected.');
assertSameValue(-1, $jump['presence'], 'Jump must not falsely confirm absence.');
$old = $geofence->run(array_replace($fix, ['timestamp' => 1899]), $home['state']);
assertSameValue('outOfOrder', $old['reason'], 'Older observations must not roll state backwards.');
$conflict = $geofence->run(array_replace($fix, ['longitude' => 0.001]), $home['state']);
assertSameValue('conflicting', $conflict['reason'], 'One timestamp cannot represent conflicting positions.');
$initialBoundary = $geofence->run($boundaryFix, [], 2001);
assertSameValue(-1, $initialBoundary['presence'], 'Boundary without history must be unknown.');
assertTrueValue(is_float($initialBoundary['distance']), 'A valid boundary fix can still provide a reference distance.');
$restored = $geofence->run($fix, json_decode(json_encode($home['state'], JSON_THROW_ON_ERROR), true), 2010);
assertSameValue(110, $restored['duration'], 'Restart-safe state must preserve confirmed stay start.');
$afterGap = $geofence->run(array_replace($fix, ['timestamp' => 3010]), $home['state'], 3010);
assertSameValue(0, $afterGap['duration'], 'A long observation gap must not count as a continuous stay.');
$cancelled = $geofence->run(array_replace($fix, ['timestamp' => 2020]), $pending['state'], 2020);
assertSameValue(false, $cancelled['pending'], 'A clear return cancels a pending exit.');
assertSameValue(1900, $cancelled['since'], 'Cancelled transitions retain the observed stay start.');
$reentry = $geofence->run(array_replace($fix, ['timestamp' => 2060]), $outside['state'], 2060);
assertSameValue(0, $reentry['presence'], 'Entry needs confirmation just like exit.');
$entered = $geofence->run(array_replace($fix, ['timestamp' => 2090]), $reentry['state'], 2090);
assertSameValue(1, $entered['presence'], 'A new later fix confirms reentry.');
assertSameValue(2090, $entered['since'], 'Confirmed transitions start at the confirming sample.');
$qualityLost = $geofence->run(null, $home['state'], 2000);
$sameOldFix = $geofence->run($fix, $qualityLost['state'], 2000);
assertSameValue(-1, $sameOldFix['presence'], 'After a loss of quality the same cached fix cannot restore occupancy.');
$speedOff = $geofence->run(array_replace($fix, ['latitude' => 10, 'timestamp' => 1901]), $home['state'], 2000, ['maxSpeed' => 0, 'confirmSeconds' => 0]);
assertSameValue(0, $speedOff['presence'], 'The configured zero speed limit disables jump rejection.');
$overlap = [['id' => 'work', 'name' => 'Work', 'latitude' => 0, 'longitude' => 0, 'radius' => 200],
    ['id' => 'home', 'name' => 'Home', 'latitude' => 0, 'longitude' => 0, 'radius' => 100]];
assertSameValue('home', $geofence->run($fix, [], 2000, [], $overlap)['zone'], 'A confident reference-zone match wins overlap to avoid false absence.');
$ambiguous = $geofence->run($boundaryFix, [], 2001, [], $overlap);
assertSameValue(-1, $ambiguous['presence'], 'A boundary in the reference zone cannot establish false absence in an overlapping zone.');
foreach ([['maxAge' => 0], ['maxAccuracy' => NAN], ['confirmSeconds' => -1], ['maxSpeed' => 2001], ['unexpected' => 1]] as $invalidSettings) {
    try {
        $geofence->run($fix, [], 2000, $invalidSettings);
        throw new RuntimeException('Invalid settings must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
foreach ([[], [['id' => 'home', 'name' => 'Home', 'latitude' => 91, 'longitude' => 0, 'radius' => 100]],
    [['id' => 'home', 'name' => 'Home', 'latitude' => 0, 'longitude' => 0, 'radius' => 10]],
    array_merge($overlap, [$overlap[0]])] as $invalidZones) {
    try {
        $geofence->run($fix, [], 2000, [], $invalidZones);
        throw new RuntimeException('Invalid zones must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
fwrite(STDOUT, "Geofence geometry, quality, confirmation and restart contracts passed.\n");
