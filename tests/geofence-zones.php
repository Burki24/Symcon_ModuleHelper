<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/GeofenceHelper.php';

$engine = new class() {
    use Burki24\SymconModuleHelper\GeofenceHelper;

    public function run(array $zones, array $fix, array $state = [], array $settings = []): array
    {
        // Isolate zone rules; the existing suite tests the shared speed filter.
        $settings += ['maxSpeed' => 0];
        return $this->EvaluateGeofence($zones, 'home', $fix, $state, $fix['timestamp'], $settings);
    }

    public function normalize(array $zones): array
    {
        return $this->NormalizeGeofenceZones($zones);
    }

    public function resolve(array $zones, array $settings = []): array
    {
        return $this->ResolveGeofenceZoneSettings($zones, $settings);
    }
};
$legacy = [['id' => 'home', 'name' => 'Home', 'latitude' => 0.0, 'longitude' => 0.0, 'radius' => 100.0]];
assertSameValue($legacy, $engine->normalize($legacy), 'Legacy normalized geometry must remain byte-compatible for source identities.');
$inherited = $legacy;
$inherited[0] += ['customGPS' => false, 'hysteresis' => 999, 'maxAccuracy' => 0, 'confirmSeconds' => -1];
assertSameValue($legacy, $engine->normalize($inherited), 'Dormant custom values must neither override globals nor reset source identity.');
assertSameValue(8, $engine->resolve($inherited, ['hysteresis' => 8])['home']['hysteresis'], 'Explicit inheritance must resolve current global defaults.');
$zones = $legacy;
$zones[0] += ['customGPS' => true, 'hysteresis' => 10, 'maxAccuracy' => 10, 'confirmSeconds' => 60];
$zones[] = ['id' => 'work', 'name' => 'Work', 'latitude' => 0, 'longitude' => 0.01, 'radius' => 100,
    'customGPS'  => true, 'hysteresis' => 30, 'maxAccuracy' => 50, 'confirmSeconds' => 5];
$fix = static fn (float $longitude, int $timestamp, float $accuracy = 5): array => ['latitude' => 0.0, 'longitude' => $longitude, 'timestamp' => $timestamp, 'accuracy' => $accuracy];
$home = $engine->run($zones, $fix(0, 2000));
assertSameValue(1, $home['presence'], 'Qualified first home fix must enter home.');
$departure = $engine->run($zones, $fix(0.01, 2010), $home['state']);
assertSameValue(true, $departure['pending'], 'Departure must use the stricter source confirmation, not the destination interval.');
$early = $engine->run($zones, $fix(0.01, 2016), $departure['state']);
assertSameValue('home', $early['zone'], 'Short destination confirmation cannot bypass the home exit delay.');
$arrived = $engine->run($zones, $fix(0.01, 2070), $early['state']);
assertSameValue('work', $arrived['zone'], 'Both source and destination intervals must finish before a switch.');
assertSameValue(2070, $arrived['since'], 'Destination stay starts with the confirming fix.');
$returning = $engine->run($zones, $fix(0, 2080), $arrived['state']);
$notYet = $engine->run($zones, $fix(0, 2086), $returning['state']);
assertSameValue('work', $notYet['zone'], 'Home entry must use the destination confirmation even when work exit is short.');
$returned = $engine->run($zones, $fix(0, 2140), $notYet['state']);
assertSameValue('home', $returned['zone'], 'Home entry must finish its own interval.');
$coarseWork = $engine->run($zones, $fix(0.01, 2200, 30));
assertSameValue('work', $coarseWork['zone'], 'A distant broad zone may accept its own accuracy, beyond the home threshold.');
assertSameValue(0, $coarseWork['presence'], 'The full accuracy envelope must prove the device is outside home.');
$coarseHome = $engine->run($zones, $fix(0, 2200, 30));
assertSameValue(-1, $coarseHome['presence'], 'A loose destination cannot establish an unqualified home fix.');
$lost = $engine->run($zones, $fix(0.01, 2210, 30), $home['state']);
assertSameValue(-1, $lost['presence'], 'Bad source accuracy must invalidate occupancy instead of confirming a false exit.');
$stillLost = $engine->run($zones, $fix(0.01, 2220, 30), json_decode(json_encode($lost['state']), true));
assertSameValue(-1, $stillLost['presence'], 'New coarse fixes and serialization cannot bypass the source exit-quality guard.');
assertSameValue(0, $stillLost['duration'], 'Unknown must not continue an old stay.');
$recovered = $engine->run($zones, $fix(0.01, 2230), $stillLost['state']);
assertSameValue(-1, $recovered['presence'], 'A new qualified destination must still confirm departure after Unknown.');
assertSameValue(true, $recovered['pending'], 'Unknown cannot bypass the longer source confirmation interval.');
$recovered = $engine->run($zones, $fix(0.01, 2290), $recovered['state']);
assertSameValue('work', $recovered['zone'], 'Distinct later quality and confirmation permit recovery in another zone.');
assertSameValue(0, $recovered['duration'], 'Recovery starts a new observed stay.');
$workBoundary = $engine->run($zones, $fix(0.011, 2300), $recovered['state']);
assertSameValue('held', $workBoundary['reason'], 'A secondary zone uses its own larger exit hysteresis.');
assertSameValue(2290, $workBoundary['since'], 'Boundary stabilization must preserve the stay start.');
$workExit = $engine->run($zones, $fix(0.012, 2310), $workBoundary['state']);
$workOutside = $engine->run($zones, $fix(0.012, 2315), $workExit['state']);
assertSameValue('', $workOutside['zone'], 'Exiting all zones uses the source override rather than the global interval.');
$zero = $legacy;
$zero[0] += ['customGPS' => true, 'hysteresis' => 0, 'maxAccuracy' => 20, 'confirmSeconds' => 0];
$outside = $engine->run($zero, $fix(0.01, 2300));
$immediate = $engine->run($zero, $fix(0, 2301), $outside['state']);
assertSameValue(1, $immediate['presence'], 'Explicit zero buffer and confirmation must not be replaced by inherited defaults.');
$small = $zero;
$small[0]['radius'] = 10;
assertSameValue(0.0, $engine->resolve($small)['home']['hysteresis'], 'A small custom zone may override a larger global buffer.');
$overlap = $zones;
$overlap[1]['longitude'] = 0;
$overlap[1]['radius'] = 200;
$overlap[0]['confirmSeconds'] = 0;
$overlap[1]['confirmSeconds'] = 0;
$inLargerZone = $engine->run($overlap, $fix(0.0013, 2400));
assertSameValue('work', $inLargerZone['zone'], 'The outer part of an overlapping larger zone must be identifiable.');
$intoReference = $engine->run($overlap, $fix(0, 2410), $inLargerZone['state']);
assertSameValue('home', $intoReference['zone'], 'A qualified overlapping reference zone must retain its documented priority.');
foreach ([['customGPS' => 'false'], ['customGPS' => null], ['customGPS' => true, 'hysteresis' => 100], ['customGPS' => true, 'maxAccuracy' => 0], ['customGPS' => true, 'confirmSeconds' => -1]] as $bad) {
    try {
        $engine->resolve([array_replace($legacy[0], $bad)]);
        throw new RuntimeException('Invalid zone settings were accepted.');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
fwrite(STDOUT, "Zone GPS inheritance, entry/exit quality, confirmation, hysteresis and recovery contracts passed.\n");
