<?php

declare(strict_types=1);

require_once __DIR__ . '/reverse-geocoding.php';

$nomParts = ['house_number' => '7a', 'road' => 'Example Street', 'quarter' => 'Example Quarter',
    'city'                  => 'Example Town', 'postcode' => '12345', 'county' => 'Example County',
    'state'                 => 'Example State', 'country' => 'Example Country'];
$cloudParts = ['housenumber' => '7a', 'street' => 'Example Street', 'suburb' => 'Example Quarter',
    'city'                   => 'Example Town', 'postcode' => '12345', 'county' => 'Example County',
    'state'                  => 'Example State', 'country' => 'Example Country'];

foreach ([18 => 'Example Street 7a, 12345 Example Town, Example Country',
    17       => 'Example Street, 12345 Example Town, Example Country',
    14       => 'Example Quarter, 12345 Example Town, Example Country',
    10       => 'Example Town, Example Country'] as $zoom => $expected) {
    $nom = new ReverseGeocodingHarness();
    $nom->response['body'] = json_encode(['display_name' => '7a, Example Street, Example Quarter, Example County, Example State, Example Town, 12345, Example Country', 'address' => $nomParts], JSON_THROW_ON_ERROR);
    $cloud = new ReverseGeocodingHarness();
    $cloud->response['body'] = json_encode(['results' => [array_merge($cloudParts, ['formatted' => 'An intentionally different provider layout'])]], JSON_THROW_ON_ERROR);
    assertSameValue($expected, $nom->resolve(zoom: $zoom), 'Nominatim must use the compact precision-limited layout.');
    assertSameValue($expected, $cloud->geoResolve(zoom: $zoom), 'Geoapify must use exactly the same compact layout.');
    assertSameValue($expected, $nom->cached(zoom: $zoom), 'The compact result is cached.');
    assertSameValue(1, $cloud->usage()['used'], 'Formatting must not perform extra Geoapify requests.');
}

foreach ([
    [['house_number' => '7a', 'city' => 'Example Town', 'postcode' => '12345'], 18, '12345 Example Town'],
    [['road' => '  Example   Street ', 'house_number' => ' 7a ', 'village' => 'Example Village', 'postcode' => '01234'], 18, 'Example Street 7a, 01234 Example Village'],
    [['quarter' => 'Example Town', 'city' => 'Example Town', 'country' => 'Example Country'], 14, 'Example Town, Example Country'],
    [['road' => 'Example Street', 'quarter' => 'Example Quarter', 'postcode' => '12345', 'country' => 'Example Country'], 18, 'Example Street, 12345 Example Quarter, Example Country'],
    [['road' => 'Example Street', 'quarter' => 'Example Quarter', 'postcode' => '12345', 'county' => 'Example County', 'country' => 'Example Country'], 10, 'Example County, Example Country'],
    [['city' => 'Example Town', 'country' => 'Example Country'], 4, 'Example Country'],
    [['state' => 'Example State', 'country' => 'Example Country'], 8, 'Example State, Example Country'],
    [['road' => 'Beispielstraße', 'house_number' => '7a', 'city' => 'Beispielstadt', 'postcode' => '01234', 'country' => 'Deutschland'], 18, 'Beispielstraße 7a, 01234 Beispielstadt, Deutschland'],
    [[], 18, '']
] as [$parts, $zoom, $expected]) {
    $nom = new ReverseGeocodingHarness();
    $nom->response['body'] = json_encode(['display_name' => 'Unfiltered details must never be used as a fallback', 'address' => $parts], JSON_THROW_ON_ERROR);
    assertSameValue($expected, $nom->resolve(zoom: $zoom), 'Missing, duplicated and rural components must remain readable without invented detail.');
}

foreach ([18, 17, 14, 10] as $zoom) {
    $nom = new ReverseGeocodingHarness();
    $nom->response['body'] = '{"display_name":"7, A precise house address without components"}';
    assertSameValue('', $nom->resolve(zoom: $zoom), 'Unstructured Nominatim text cannot bypass compact formatting or precision.');
    $cloud = new ReverseGeocodingHarness();
    $cloud->response['body'] = '{"results":[{"formatted":"7, A precise house address without components"}]}';
    assertSameValue('', $cloud->geoResolve(zoom: $zoom), 'Unstructured Geoapify text cannot bypass compact formatting or precision.');
}

$legacy = new ReverseGeocodingHarness();
$scope = hash('sha256', 'http://nominatim.example:8080/osm|de');
$key = hash('sha256', 'detail-v1|' . $scope . '|18|48.100000|11.500000');
$legacy->attributes['ReverseGeocodingCache'] = json_encode(['entries' => [$key => ['address' => 'Old verbose provider result', 'expires' => 200000]]], JSON_THROW_ON_ERROR);
$legacy->attributes['GeoapifyDailyUsage'] = json_encode(['day' => gmdate('Y-m-d', $legacy->now), 'used' => 2], JSON_THROW_ON_ERROR);
assertSameValue(null, $legacy->cached(), 'Cached verbose strings must not hide the new common format.');
assertSameValue(0, $legacy->usage()['remaining'], 'Format migration must preserve the existing daily budget.');
foreach (["bad\ncomponent", str_repeat('x', 2049)] as $invalid) {
    $nom = new ReverseGeocodingHarness();
    $nom->response['body'] = json_encode(['address' => ['road' => $invalid]], JSON_THROW_ON_ERROR);
    assertSameValue('', $nom->resolve(), 'Invalid selected components are contained.');
    $cloud = new ReverseGeocodingHarness();
    $cloud->response['body'] = json_encode(['results' => [['street' => $invalid]]], JSON_THROW_ON_ERROR);
    assertSameValue('', $cloud->geoResolve(), 'The same component validation protects Geoapify.');
}
fwrite(STDOUT, "Compact cross-provider address and precision contracts passed.\n");
