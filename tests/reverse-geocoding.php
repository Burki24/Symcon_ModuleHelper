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
    public array $response = ['status' => 200, 'body' => '{"display_name":"Example Street 7, Example Town"}'];
    public int $now = 100000;

    public function __construct()
    {
        $this->RegisterReverseGeocodingCache();
    }

    public function cached(string $server = 'http://nominatim.example:8080/osm', float $lat = 48.1, float $lon = 11.5, string $language = 'de'): ?string
    {
        return $this->ReadNominatimAddress($server, $lat, $lon, $language);
    }

    public function resolve(string $server = 'http://nominatim.example:8080/osm', float $lat = 48.1, float $lon = 11.5, string $language = 'de'): string
    {
        return $this->ResolveNominatimAddress($server, $lat, $lon, $language);
    }

    public function server(string $url): string
    {
        return $this->NormalizeNominatimServer($url);
    }

    public function http(string $url): array
    {
        return $this->ActualRequestNominatim($url);
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
assertSameValue(['lat', 'lon', 'format', 'addressdetails', 'accept-language'], array_keys($query), 'Only coordinates and format/language options leave Symcon.');
assertSameValue('jsonv2', $query['format'], 'API format must be explicit.');
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
$geo->response = ['status' => 200, 'body' => '{"display_name":"<script>literal text</script>"}'];
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
