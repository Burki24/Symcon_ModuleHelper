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

$truncated = $helper->format(str_repeat('ä', 100), 80);
debugAssertTrue(strlen($truncated) <= 80, 'DebugHelper must respect the configured byte limit.');
debugAssertTrue(str_ends_with($truncated, '… [truncated]'), 'Truncated debug output must be clearly marked.');
debugAssertSame(1, preg_match('//u', $truncated), 'Truncation must not split UTF-8 characters.');

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
