<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

/**
 * Provides reusable HTTP response helpers for Symcon module WebHooks.
 *
 * Intended for classes derived from IPSModule/IPSModuleStrict. The helper
 * sends small HTTP responses with explicit status codes and secure default
 * headers without depending on module-specific state.
 *
 * @version 1.0.0
 */
trait HttpResponseHelper
{
    /**
     * Sends a plain-text HTTP response.
     *
     * Sets the HTTP status, UTF-8 content type, cache protection and the
     * nosniff header before writing the response body.
     *
     * @param int    $statusCode HTTP status code to send.
     * @param string $message    Plain-text response body.
     *
     * @return void No return value.
     */
    protected function SendPlainTextResponse(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $message;
    }
}
