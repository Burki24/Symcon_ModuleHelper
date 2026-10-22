<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ReverseGeocodingHelper.php';

final class ReverseGeocodingHarness
{
    use \Burki24\SymconModuleHelper\ReverseGeocodingHelper {
        RequestNominatim as protected ActualRequestNominatim;
    }

    public array $attributes = [];
    public array $requests = [];
    public array $response = ['status' => 200, 'body' => '{"display_name":"7, Example Street, Example Town","address":{"road":"Example Street","house_number":"7","city":"Example Town"}}'];
    public int $now = 100000;

    public function __construct()
    {
        $this->RegisterReverseGeocodingCache();
    }

    public function cached(string $server = 'http://nominatim.example:8080/osm', float $lat = 48.1, float $lon = 11.5, string $language = 'de', int $zoom = 18): ?string
    {
        return $this->ReadNominatimAddress($server, $lat, $lon, $language, $zoom);
    }

    public function resolve(string $server = 'http://nominatim.example:8080/osm', float $lat = 48.1, float $lon = 11.5, string $language = 'de', int $zoom = 18): string
    {
        return $this->ResolveNominatimAddress($server, $lat, $lon, $language, $zoom);
    }

    public function precision(?float $accuracy): array
    {
        return $this->ReverseGeocodingPrecision($accuracy);
    }

    public function geoCached(float $lat = 48.1, int $zoom = 18, string $key = 'synthetic-api-key'): ?string
    {
        return $this->ReadGeoapifyAddress($key, $lat, 11.5, 'de', $zoom);
    }

    public function geoResolve(float $lat = 48.1, int $zoom = 18, int $limit = 2, string $key = 'synthetic-api-key'): string
    {
        return $this->ResolveGeoapifyAddress($key, $lat, 11.5, 'de', $zoom, $limit);
    }

    public function usage(int $limit = 2): array
    {
        return $this->GeoapifyDailyUsage($limit);
    }

    public function server(string $url): string
    {
        return $this->NormalizeNominatimServer($url);
    }

    public function http(string $url): array
    {
        return $this->ActualRequestNominatim($url);
    }

    protected function RequestGeoapify(string $url): array
    {
        $this->requests[] = $url;
        return $this->response;
    }

    protected function RegisterAttributeString(string $name, string $default): void
    {
        $this->attributes[$name] ??= $default;
    }

    protected function ReadAttributeString(string $name): string
    {
        return $this->attributes[$name];
    }

    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    protected function ReverseGeocodingNow(): int
    {
        return $this->now;
    }

    protected function RequestNominatim(string $url): array
    {
        $this->requests[] = $url;
        return $this->response;
    }
}

$geo = new ReverseGeocodingHarness();
assertSameValue(null, $geo->cached(), 'An empty cache must not initiate a request.');
assertSameValue([], $geo->requests, 'Rendering a cache miss must stay offline.');
assertSameValue('Example Street 7, Example Town', $geo->resolve(), 'Reverse result is normalized.');
parse_str(parse_url($geo->requests[0], PHP_URL_QUERY), $query);
assertSameValue('/osm/reverse', parse_url($geo->requests[0], PHP_URL_PATH), 'Base path is preserved.');
assertSameValue(['lat', 'lon', 'format', 'addressdetails', 'accept-language', 'zoom', 'layer'], array_keys($query), 'Only coordinates and format/language/detail options leave Symcon.');
assertSameValue('jsonv2', $query['format'], 'API format must be explicit.');
assertSameValue('18', $query['zoom'], 'Existing callers retain building-level detail.');
assertSameValue('address', $query['layer'], 'Reverse requests must use the address layer.');
assertSameValue($geo->resolve(), $geo->cached(), 'Cached address is reused.');
assertSameValue(1, count($geo->requests), 'Identical coordinates must hit persistent cache.');
$restart = new ReverseGeocodingHarness();
$restart->attributes = $geo->attributes;
assertSameValue($geo->cached(), $restart->cached(), 'Cache survives object reconstruction.');
assertSameValue(null, $geo->cached(lon: 11.6), 'Moved devices must not inherit old addresses.');
assertSameValue(null, $geo->cached(language: 'en'), 'Language separates cache entries.');
assertSameValue(null, $geo->cached(server: 'https://other.example'), 'Server separates cache entries.');
$geo->now += 86401;
assertSameValue(null, $geo->cached(), 'Expired addresses require a new resolution.');
$geo->response = ['status' => 503, 'body' => '<html>private error</html>'];
assertSameValue('', $geo->resolve(), 'Provider failure is contained.');
assertSameValue('', $geo->resolve(lon: 11.6), 'A failed server has a cooldown across coordinates.');
assertSameValue(2, count($geo->requests), 'Failure must not trigger one request per device.');
$geo->now += 301;
assertSameValue(null, $geo->cached(), 'Failure becomes retryable.');
$geo->response = ['status' => 200, 'body' => '{"error":"Unable to geocode"}'];
assertSameValue('', $geo->resolve(), 'No coverage is negatively cached.');
assertSameValue('', $geo->cached(), 'Known missing result differs from a pending lookup.');
$geo->now += 3601;
$geo->response = ['status' => 200, 'body' => '{"display_name":"Ignored raw text","address":{"road":"<script>literal text</script>"}}'];
assertSameValue('<script>literal text</script>', $geo->resolve(), 'Address remains plain data; UI must escape it.');

foreach (['', 'ftp://example.org', 'https://user:secret@example.org', 'https://example.org?token=x', 'https://example.org/#x', 'https://nominatim.openstreetmap.org', 'https://NOMINATIM.OPENSTREETMAP.ORG.:443/', 'https://github.com/Burki24/Nominatim'] as $invalid) {
    try {
        $geo->server($invalid);
        throw new RuntimeException('Invalid/public/repository URL accepted.');
    } catch (InvalidArgumentException) {
    }
}
foreach ([[91.0, 0.0], [0.0, -181.0], [NAN, 0.0], [INF, 0.0]] as [$lat, $lon]) {
    try {
        $geo->resolve(lat: $lat, lon: $lon);
        throw new RuntimeException('Invalid coordinate accepted.');
    } catch (InvalidArgumentException) {
    }
}
for ($index = 0; $index < 520; $index++) {
    $geo->resolve(lat: 40 + $index / 10000);
}
$persisted = json_decode($geo->attributes['ReverseGeocodingCache'], true, 512, JSON_THROW_ON_ERROR);
assertTrueValue(count($persisted['entries']) <= 512, 'Persistent location history must stay bounded.');
$geo->attributes['ReverseGeocodingCache'] = 'corrupt';
assertSameValue(null, $geo->cached(), 'Corrupt cache must degrade to a miss.');
foreach (['{"display_name":5}', 'not json', '{"display_name":"' . str_repeat('x', 2049) . '"}'] as $invalidBody) {
    $invalidGeo = new ReverseGeocodingHarness();
    $invalidGeo->response['body'] = $invalidBody;
    assertSameValue('', $invalidGeo->resolve(), 'Malformed or excessive provider data is contained.');
}
try {
    $geo->resolve(language: "de\r\nInjected: value");
    throw new RuntimeException('Invalid language accepted.');
} catch (InvalidArgumentException) {
}
fwrite(STDOUT, "Reverse-geocoding cache and Nominatim contracts passed.\n");

// Detail levels are a conservative display policy, not an accuracy guarantee.
foreach ([[20.0, 18], [20.01, 17], [25.0, 17], [100.0, 17], [100.01, 14], [1000.0, 14], [1000.01, 10], [null, 10], [0.0, 10], [-1.0, 10], [NAN, 10], [INF, 10]] as [$accuracy, $zoom]) {
    $precision = $geo->precision($accuracy);
    assertSameValue($zoom, $precision['zoom'], 'Accuracy must cap reverse-geocoding detail at tier boundaries.');
    assertSameValue($zoom !== 18, $precision['approximate'], 'Coarse/unknown positions require an approximate warning.');
}
assertSameValue('unknown', $geo->precision(null)['level'], 'Unknown accuracy is distinct from city-level accuracy.');
assertSameValue(null, $geo->precision(0.0)['accuracy'], 'Zero accuracy must not be advertised as perfect precision.');
$precisionGeo = new ReverseGeocodingHarness();
$precisionGeo->response['body'] = json_encode([
    'display_name' => '7, Example Street, Example Quarter, Example Town, Example State, Example Country',
    'address'      => ['house_number' => '7', 'road' => 'Example Street', 'quarter' => 'Example Quarter',
        'city'                        => 'Example Town', 'state' => 'Example State', 'country' => 'Example Country']
], JSON_THROW_ON_ERROR);
assertSameValue('Example Street 7, Example Town, Example Country', $precisionGeo->resolve(), 'Accurate positions retain the house number in compact order.');
assertSameValue(null, $precisionGeo->cached(zoom: 14), 'An old building result cannot satisfy an area lookup.');
assertSameValue('Example Quarter, Example Town, Example Country', $precisionGeo->resolve(zoom: 14), 'Coarse results must omit house numbers and streets even if the provider ignores zoom.');
assertSameValue('Example Street, Example Town, Example Country', $precisionGeo->resolve(zoom: 17), 'Street results must omit house numbers.');
assertSameValue('Example Town, Example Country', $precisionGeo->resolve(zoom: 10), 'City results must omit streets and neighbourhoods.');
assertSameValue(4, count($precisionGeo->requests), 'Each new detail level needs its own bounded lookup.');
$precisionGeo->resolve(zoom: 14);
assertSameValue(4, count($precisionGeo->requests), 'Repeated coarse results must be cached.');
$precisionGeo->response = ['status' => 503, 'body' => 'unavailable'];
$precisionGeo->resolve(lon: 11.7, zoom: 14);
$precisionGeo->resolve(lon: 11.7, zoom: 17);
assertSameValue(5, count($precisionGeo->requests), 'Provider cooldown applies across all detail levels.');
$missingDetails = new ReverseGeocodingHarness();
$missingDetails->response['body'] = '{"display_name":"Precise text without address fields"}';
assertSameValue('', $missingDetails->resolve(zoom: 14), 'Missing structured fields must never fall back to a precise display_name for a coarse result.');
$legacyCache = new ReverseGeocodingHarness();
$scope = hash('sha256', 'http://nominatim.example:8080/osm|de');
$legacyKey = hash('sha256', $scope . '|48.100000|11.500000');
$legacyCache->attributes['ReverseGeocodingCache'] = json_encode(['entries' => [$legacyKey => ['address' => 'Old precise address', 'expires' => 200000]]], JSON_THROW_ON_ERROR);
assertSameValue(null, $legacyCache->cached(), 'Unversioned old precise cache entries must not bypass the new detail policy.');
foreach ([-1, 19] as $invalidZoom) {
    try {
        $precisionGeo->cached(zoom: $invalidZoom);
        throw new RuntimeException('Invalid detail level accepted.');
    } catch (InvalidArgumentException) {
    }
}
fwrite(STDOUT, "Reverse-geocoding accuracy and precision-cache contracts passed.\n");
