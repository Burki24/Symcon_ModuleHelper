<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\HttpResponseHelper;

require_once __DIR__ . '/../src/HttpResponseHelper.php';

final class HttpResponseHelperHarness
{
    use HttpResponseHelper;

    public function sendText(int $statusCode, string $message): void
    {
        $this->SendPlainTextResponse($statusCode, $message);
    }
}

$helper = new HttpResponseHelperHarness();

ob_start();
$helper->sendText(418, 'Helper HTTP response');
$output = ob_get_clean();

if ($output !== 'Helper HTTP response') {
    throw new RuntimeException('Plain-text response body was not emitted correctly.');
}

if (http_response_code() !== 418) {
    throw new RuntimeException('HTTP response status was not set correctly.');
}

$source = file_get_contents(__DIR__ . '/../src/HttpResponseHelper.php');
if (!is_string($source)) {
    throw new RuntimeException('HttpResponseHelper source could not be read.');
}

foreach ([
    "header('Content-Type: text/plain; charset=utf-8');",
    "header('Cache-Control: no-store, max-age=0');",
    "header('X-Content-Type-Options: nosniff');",
] as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException('Missing secure HTTP response header: ' . $marker);
    }
}

http_response_code(200);

fwrite(STDOUT, "HttpResponseHelper tests passed.\n");
