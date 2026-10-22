<?php

declare(strict_types=1);

require_once __DIR__ . '/reverse-geocoding.php';

$cloud = new ReverseGeocodingHarness();
$cloud->now = 1792627190; // Synthetic UTC day close to midnight.
$cloud->response['body'] = json_encode(['results' => [[
    'formatted'   => 'Example Street 7, Example Quarter, Example Town',
    'housenumber' => '7', 'street' => 'Example Street', 'suburb' => 'Example Quarter',
    'city'        => 'Example Town', 'country' => 'Example Country'
]]], JSON_THROW_ON_ERROR);
assertSameValue(2, $cloud->usage()['remaining'], 'New instances have their full configured budget.');
assertSameValue(null, $cloud->geoCached(), 'Uncached Geoapify addresses are pending.');
assertSameValue('Example Street 7, Example Town, Example Country', $cloud->geoResolve(), 'Building detail is available at the accurate tier.');
parse_str(parse_url($cloud->requests[0], PHP_URL_QUERY), $query);
assertSameValue('https://api.geoapify.com/v1/geocode/reverse', strtok($cloud->requests[0], '?'), 'Only the fixed HTTPS provider endpoint is used.');
assertSameValue(['lat' => '48.100000', 'lon' => '11.500000', 'format' => 'json', 'lang' => 'de', 'limit' => '1', 'type' => 'building', 'apiKey' => 'synthetic-api-key'], $query, 'Documented parameters must carry the selected detail and key.');
$cloud->geoResolve();
assertSameValue(1, $cloud->usage()['used'], 'Cache reads must not spend another request.');
assertSameValue('Example Quarter, Example Town, Example Country', $cloud->geoResolve(zoom: 14), 'Coarse results omit street and house even if the provider ignores type.');
assertSameValue('', $cloud->geoResolve(lat: 48.2), 'Exhausted daily budget prevents network access.');
assertSameValue(2, count($cloud->requests), 'No request may exceed the local daily limit.');
$cloud->attributes['ReverseGeocodingCache'] = '[]';
assertSameValue(0, $cloud->usage()['remaining'], 'Clearing addresses must not reset the budget.');
$restarted = new ReverseGeocodingHarness();
$restarted->attributes = $cloud->attributes;
$restarted->now = $cloud->now;
assertSameValue('', $restarted->geoResolve(key: 'another-synthetic-key'), 'Restarts and key changes cannot reset the instance budget.');
$cloud->now = $cloud->usage()['nextReset'];
assertSameValue(2, $cloud->usage()['remaining'], 'The next UTC day restores the budget.');
$cloud->response = ['status' => 429, 'body' => 'provider detail must stay private'];
assertSameValue('', $cloud->geoResolve(), 'Rate-limit failures are contained.');
assertSameValue(1, $cloud->usage()['used'], 'Failed HTTP attempts also spend local budget.');
$cloud->geoResolve(lat: 48.3);
assertSameValue(3, count($cloud->requests), 'Cooldown prevents retry storms.');
$cloud->now += 301;
$cloud->response['status'] = 200;
$cloud->response['body'] = '{"results":[]}';
assertSameValue('', $cloud->geoResolve(), 'Missing coverage is negatively cached.');
assertSameValue(2, $cloud->usage()['used'], 'A missing result still counts as one attempt.');
assertTrueValue(!str_contains($cloud->attributes['ReverseGeocodingCache'], 'synthetic-api-key'), 'Caches never persist plaintext keys.');
assertTrueValue(!str_contains($cloud->attributes['GeoapifyDailyUsage'], 'synthetic-api-key'), 'Usage counters contain no credentials.');
$blocked = new ReverseGeocodingHarness();
$blocked->attributes['GeoapifyDailyUsage'] = 'corrupt';
assertSameValue(0, $blocked->usage()['remaining'], 'Corrupt quota data fails closed.');
assertSameValue('', $blocked->geoResolve(), 'Invalid counters must not permit uncounted calls.');
assertSameValue([], $blocked->requests, 'Corrupt counters perform no HTTP.');
$invalidDay = new ReverseGeocodingHarness();
$invalidDay->attributes['GeoapifyDailyUsage'] = '{"day":"1900-99-99","used":0}';
assertSameValue(0, $invalidDay->usage()['remaining'], 'Invalid calendar days cannot reset the budget.');
$blocked->now = $blocked->usage()['nextReset'];
assertSameValue(2, $blocked->usage()['remaining'], 'Corrupt-counter containment recovers on the next UTC day.');
$zero = new ReverseGeocodingHarness();
assertSameValue('', $zero->geoResolve(limit: 0), 'Zero budget disables new requests.');
assertSameValue([], $zero->requests, 'Zero budget must never contact the provider.');
foreach (['', "bad\nkey", 'https://not-a-key.example'] as $key) {
    try {
        $zero->geoResolve(key: $key);
        throw new RuntimeException('Invalid Geoapify key accepted.');
    } catch (InvalidArgumentException) {
    }
}
fwrite(STDOUT, "Geoapify provider, precision and persistent UTC budget contracts passed.\n");

$tiers = new ReverseGeocodingHarness();
$tiers->response['body'] = '{"results":[{"formatted":"7, Private Street, Example Quarter, Example Town","street":"Private Street","suburb":"Example Quarter","city":"Example Town","country":"Example Country"}]}';
assertSameValue('Private Street, Example Town, Example Country', $tiers->geoResolve(zoom: 17, limit: 10), 'Street detail omits the house number.');
assertSameValue('Example Town, Example Country', $tiers->geoResolve(zoom: 10, limit: 10), 'City detail omits street and suburb.');
assertSameValue(null, $tiers->geoCached(zoom: 10, key: 'different-synthetic-key'), 'Credentials must have separate cache scopes.');
foreach (['{"results":{}}', '{"results":[42]}', 'not-json', '{"results":[{"formatted":"bad\\u0000text"}]}', str_repeat('x', 65537)] as $invalid) {
    $bad = new ReverseGeocodingHarness();
    $bad->response['body'] = $invalid;
    assertSameValue('', $bad->geoResolve(), 'Malformed and oversized provider data is contained.');
    assertSameValue(1, $bad->usage()['used'], 'Malformed responses count as attempts.');
}
foreach ([-1, 1000001] as $limit) {
    try {
        $tiers->geoResolve(limit: $limit);
        throw new RuntimeException('Invalid daily budget accepted.');
    } catch (InvalidArgumentException) {
    }
}
$regional = new class() {
    use \Burki24\SymconModuleHelper\ReverseGeocodingHelper;
    public function language(string $language): string
    {
        return $this->NormalizeGeoapifyLanguage($language);
    }
};
assertSameValue('de', $regional->language('de-DE'), 'Regional language codes use the supported base language.');
