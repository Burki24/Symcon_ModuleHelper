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

    public function sendHtmlText(int $statusCode, string $message): void
    {
        $this->SendHtmlTextResponse($statusCode, $message);
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
    throw new RuntimeException('Plain-text HTTP response status was not set correctly.');
}

$htmlMessage = '<strong title="OAuth">München & 東京\'s</strong>';
ob_start();
$helper->sendHtmlText(400, $htmlMessage);
$htmlOutput = ob_get_clean();

$expectedHtml = htmlspecialchars($htmlMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if ($htmlOutput !== $expectedHtml) {
    throw new RuntimeException('HTML text response was not escaped correctly.');
}

if (http_response_code() !== 400) {
    throw new RuntimeException('HTML HTTP response status was not set correctly.');
}

$source = file_get_contents(__DIR__ . '/../src/HttpResponseHelper.php');
if (!is_string($source)) {
    throw new RuntimeException('HttpResponseHelper source could not be read.');
}

foreach ([
    "'text/plain'",
    "'text/html'",
    "htmlspecialchars(\$message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')",
    "header('Content-Type: ' . \$contentType . '; charset=utf-8');",
    "header('Cache-Control: no-store, max-age=0');",
    "header('X-Content-Type-Options: nosniff');",
] as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException('Missing HTTP response implementation marker: ' . $marker);
    }
}

http_response_code(200);

fwrite(STDOUT, "HttpResponseHelper tests passed.\n");
