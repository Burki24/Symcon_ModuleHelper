<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DebugHelper.php';

use Burki24\SymconModuleHelper\DebugHelper;

final class DebugHelperHarness
{
    use DebugHelper;

    /** @var list<array{message: string, data: string, format: int}> */
    public array $debug = [];

    public function send(string $message, mixed $data, int $maxLength = 16_384, array $additionalSensitiveKeys = []): void
    {
        $this->SendSafeDebug($message, $data, $maxLength, $additionalSensitiveKeys);
    }

    public function sendException(string $message, Throwable $exception): void
    {
        $this->SendSafeDebugException($message, $exception);
    }

    public function format(mixed $data, int $maxLength = 16_384, array $additionalSensitiveKeys = []): string
    {
        return $this->FormatSafeDebugData($data, $maxLength, $additionalSensitiveKeys);
    }

    public function SendDebug(string $message, string $data, int $format): void
    {
        $this->debug[] = compact('message', 'data', 'format');
    }
}

function debugAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
}

function debugAssertTrue(bool $actual, string $message): void
{
    debugAssertSame(true, $actual, $message);
}

$helper = new DebugHelperHarness();
$helper->send('Synchronization', [
    'provider'     => 'Microsoft',
    'eventCount'   => 37,
    'accessToken'  => 'secret-access-token',
    'refreshToken' => 'secret-refresh-token',
    'nested'       => [
        'password'   => 'calendar-password',
        'statusCode' => 200,
        'devicePin'  => '4711'
    ]
], 16_384, ['devicePin']);

debugAssertSame(1, count($helper->debug), 'DebugHelper must forward one sanitized message to SendDebug().');
debugAssertSame('Synchronization', $helper->debug[0]['message'], 'DebugHelper must preserve the Symcon debug category.');
debugAssertSame(0, $helper->debug[0]['format'], 'DebugHelper must use Symcon text format 0.');
$structured = json_decode($helper->debug[0]['data'], true, 512, JSON_THROW_ON_ERROR);
debugAssertSame('Microsoft', $structured['provider'], 'Non-sensitive structured values must remain visible.');
debugAssertSame(37, $structured['eventCount'], 'Numeric debug values must be preserved.');
debugAssertSame('***', $structured['accessToken'], 'Access tokens must be masked recursively.');
debugAssertSame('***', $structured['refreshToken'], 'Refresh tokens must be masked recursively.');
debugAssertSame('***', $structured['nested']['password'], 'Nested passwords must be masked recursively.');
debugAssertSame(200, $structured['nested']['statusCode'], 'Ordinary code/status fields must remain visible.');
debugAssertSame('***', $structured['nested']['devicePin'], 'Module-specific sensitive keys must be maskable.');

$nestedPayload = [
    'title'    => 'Living room chart',
    'Password' => 'synthetic-password',
    'nested'   => ['ApiKey' => 'synthetic-api-key', 'value' => 12345]
];
$encodedPayload = json_encode($nestedPayload, JSON_THROW_ON_ERROR);
$nested = json_decode($helper->format([
    'json'   => $encodedPayload,
    'base64' => base64_encode($encodedPayload)
]), true, 512, JSON_THROW_ON_ERROR);
$jsonPayload = json_decode($nested['json'], true, 512, JSON_THROW_ON_ERROR);
$base64Payload = json_decode(base64_decode($nested['base64'], true), true, 512, JSON_THROW_ON_ERROR);
foreach ([$jsonPayload, $base64Payload] as $payload) {
    debugAssertSame('Living room chart', $payload['title'], 'Nested payload text must remain visible.');
    debugAssertSame('***', $payload['Password'], 'Nested JSON passwords must be masked.');
    debugAssertSame('***', $payload['nested']['ApiKey'], 'Nested JSON API keys must be masked.');
    debugAssertSame(12345, $payload['nested']['value'], 'Nested numeric values must remain visible.');
}

$germanPayload = [
    'Passwort'       => 'synthetisches Passwort mit Leerzeichen',
    'nested'         => (object) ['SMTP_PASSWORT' => 'weiteres synthetisches Geheimnis'],
    'PasswortLaenge' => 38,
    'status'         => 'bereit'
];
foreach (['array', 'json', 'base64'] as $encoding) {
    $input = match ($encoding) {
        'json'   => json_encode($germanPayload, JSON_THROW_ON_ERROR),
        'base64' => base64_encode(json_encode($germanPayload, JSON_THROW_ON_ERROR)),
        default  => $germanPayload
    };
    $formatted = $helper->format($input);
    if ($encoding === 'base64') {
        $formatted = base64_decode($formatted, true);
    }
    $safe = json_decode($formatted, true, 512, JSON_THROW_ON_ERROR);
    debugAssertSame('***', $safe['Passwort'], 'German password fields must be masked in ' . $encoding . '.');
    debugAssertSame('***', $safe['nested']['SMTP_PASSWORT'], 'German password suffixes must be masked in ' . $encoding . '.');
    debugAssertSame(38, $safe['PasswortLaenge'], 'Password metadata must remain visible.');
    debugAssertSame('bereit', $safe['status'], 'Non-sensitive status must remain visible.');
}

$textCases = [
    ['password="zwei geheime Worte" status=ok', 'password="***" status=ok'],
    ["Passwort='zwei geheime Worte'; status=ok", "Passwort='***'; status=ok"],
    ['PASSWORT: zwei geheime Worte, status=ok', 'PASSWORT: ***, status=ok'],
    ['password=zwei geheime Worte', 'password=***'],
    ['password=a,b;c&d mit Leerzeichen; status=ok', 'password=***; status=ok'],
    ['password="synthetic \\"quoted\\" secret" status=ok', 'password="***" status=ok'],
    ["passwd='synthetic \\'quoted\\' secret' code=1234", "passwd='***' code=1234"],
    ["password=\"erste Zeile\nzweite Zeile\" status=ok", 'password="***" status=ok'],
    ['password="abgeschnittener geheimer Text', 'password="***"'],
    ["pwd='abgeschnittener geheimer Text\\", "pwd='***'"],
    ['Antwort: {"Passwort": "synthetische geheime Worte", "status": 200}', 'Antwort: {"Passwort": "***", "status": 200}'],
    ['smtp-password="synthetic secret" count=3', 'smtp-password="***" count=3'],
    ['client_secret="synthetic secret" status=ok', 'client_secret="***" status=ok'],
    ['https://example.test/?Passwort=synthetic%20secret&value=1', 'https://example.test/?Passwort=***&value=1'],
    ['PasswortLaenge=38 tokenCount=2 status=ok', 'PasswortLaenge=38 tokenCount=2 status=ok'],
    ['Status: Passwort fehlt; code=1234', 'Status: Passwort fehlt; code=1234'],
    ['password="" status=ok', 'password="***" status=ok']
];
foreach ($textCases as [$input, $expected]) {
    debugAssertSame($expected, $helper->format($input), 'Password text must be fully masked while preserving safe adjacent fields.');
}

debugAssertSame('password="***"', $helper->format('password="' . str_repeat('private ', 20_000) . '"'), 'Large quoted secrets must be masked before output truncation.');
debugAssertSame('password=*** ', $helper->format('password=' . str_repeat('private ', 20_000)), 'Large plain secrets must be masked before output truncation.');
debugAssertSame('https://example.test/?%50asswort=***&value=1', $helper->format('https://example.test/?%50asswort=synthetic%20secret&value=1'), 'Encoded query field names must not bypass masking.');

$backtrackLimit = ini_set('pcre.backtrack_limit', '1');
try {
    $failedSanitization = $helper->format('password="synthetic private text"');
    debugAssertTrue(!str_contains($failedSanitization, 'synthetic'), 'A failed sanitizer must withhold input instead of returning raw secrets.');
} finally {
    ini_set('pcre.backtrack_limit', $backtrackLimit);
}

debugAssertSame(
    'devicePin="***" status=ok',
    $helper->format('devicePin="synthetic private value" status=ok', 16_384, ['devicePin']),
    'Additional sensitive keys must also protect free text.'
);

$inline = $helper->format(
    "Authorization: Bearer abc.def.ghi\n"
    . "Cookie: session=secret\n"
    . "https://user:pass@example.test/path?access_token=abc&value=1\n"
    . "client_secret='secret-value' code=1234"
);
debugAssertTrue(!str_contains($inline, 'abc.def.ghi'), 'Bearer credentials must be removed from text debug output.');
debugAssertTrue(!str_contains($inline, 'session=secret'), 'Cookie values must be removed from text debug output.');
debugAssertTrue(!str_contains($inline, 'user:pass@'), 'URL user-info passwords must be removed from text debug output.');
debugAssertTrue(!str_contains($inline, 'access_token=abc'), 'Sensitive query parameters must be removed from text debug output.');
debugAssertTrue(!str_contains($inline, 'secret-value'), 'Inline client secrets must be removed from text debug output.');
debugAssertTrue(str_contains($inline, 'code=1234'), 'Generic code values must not be treated as credentials.');

$helper->sendException(
    'ProviderError',
    new RuntimeException('Authorization: Bearer top-secret-token', 401)
);
$exception = json_decode($helper->debug[1]['data'], true, 512, JSON_THROW_ON_ERROR);
debugAssertSame(RuntimeException::class, $exception['type'], 'Exception debug output must include the exception type.');
debugAssertSame(401, $exception['code'], 'Exception debug output must include the exception code.');
debugAssertTrue(!str_contains($exception['message'], 'top-secret-token'), 'Exception messages must be sanitized.');
debugAssertTrue(!array_key_exists('trace', $exception), 'Exception debug output must not expose stack arguments.');

$privateError = new DebugHelperHarness();
$privateError->sendException('ProviderError', new RuntimeException('Passwort="synthetische geheime Worte" status=403'));
$safeError = json_decode($privateError->debug[0]['data'], true, 512, JSON_THROW_ON_ERROR);
debugAssertSame('Passwort="***" status=403', $safeError['message'], 'Exception messages must use the same complete password masking.');

debugAssertSame(
    'Passwort="***" status=ok',
    $helper->format(new class() implements Stringable {
        public function __toString(): string
        {
            return 'Passwort="synthetische geheime Worte" status=ok';
        }
    }),
    'Stringable debug values must not bypass password text masking.'
);

$truncated = $helper->format(str_repeat('ä', 100), 80);
debugAssertTrue(strlen($truncated) <= 80, 'DebugHelper must respect the configured byte limit.');
debugAssertTrue(str_ends_with($truncated, '… [truncated]'), 'Truncated debug output must be clearly marked.');
debugAssertSame(1, preg_match('//u', $truncated), 'Truncation must not split UTF-8 characters.');

$longPayload = str_repeat('L', 17_000);
debugAssertSame($longPayload, $helper->format($longPayload, PHP_INT_MAX), 'An explicit unlimited length must preserve the complete payload.');

debugAssertSame('NAN', $helper->format(NAN), 'Non-finite floating-point values must remain debuggable.');

debugAssertSame(
    '[binary data: 2 bytes]',
    $helper->format("\xFF\xFE"),
    'Invalid UTF-8 data must not be forwarded directly to the Symcon debug view.'
);

try {
    $helper->format('too small', 63);
    throw new RuntimeException('DebugHelper accepted an unsafe output length.');
} catch (InvalidArgumentException) {
}

fwrite(STDOUT, "DebugHelper tests passed.\n");
