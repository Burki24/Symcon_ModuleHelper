<?php

declare(strict_types=1);

// Test the real cURL boundary against a loopback-only synthetic service.
$socket = stream_socket_server('tcp://127.0.0.1:0');
assertTrueValue(is_resource($socket), 'Loopback fixture must reserve a port.');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$router = tempnam(sys_get_temp_dir(), 'nominatim-router-');
$log = tempnam(sys_get_temp_dir(), 'nominatim-request-');
$script = <<<'PHP'
<?php
file_put_contents(getenv('NOMINATIM_TEST_LOG'), json_encode($_SERVER) . "\n", FILE_APPEND);
header('Content-Type: application/json');
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/redirect':
        header('Location: /must-not-follow', true, 302);
        echo '{}';
        break;
    case '/oversized':
        echo str_repeat('x', 70000);
        break;
    default:
        echo '{"display_name":"Example Street, Example Town"}';
}
PHP;
file_put_contents($router, $script);
$output = tmpfile();
$process = proc_open([PHP_BINARY, '-S', $address, $router], [0 => ['pipe', 'r'], 1 => $output, 2 => $output], $pipes, null, array_merge(getenv(), ['NOMINATIM_TEST_LOG' => $log]));
assertTrueValue(is_resource($process), 'Synthetic Nominatim HTTP fixture starts.');
try {
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(20000);
    }
    assertTrueValue($ready, 'Loopback fixture must be ready.');
    $client = new ReverseGeocodingHarness();
    $success = $client->http('http://' . $address . '/reverse?lat=0&lon=0&format=jsonv2');
    assertSameValue(200, $success['status'], 'Real cURL boundary reads HTTP status.');
    assertSameValue('Example Street, Example Town', json_decode($success['body'], true)['display_name'], 'Response body reaches the parser.');
    assertSameValue(302, $client->http('http://' . $address . '/redirect')['status'], 'Redirect must not be followed.');
    assertSameValue(0, $client->http('http://' . $address . '/oversized')['status'], 'Oversized responses must abort.');
    $requests = array_map(static fn ($line): array => json_decode($line, true), file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    assertSameValue(3, count($requests), 'Redirects must never create another outgoing request.');
    assertTrueValue(str_contains($requests[0]['HTTP_USER_AGENT'], 'Symcon-ModuleHelper/ReverseGeocoding'), 'Request identifies the application.');
    assertSameValue('application/json', $requests[0]['HTTP_ACCEPT'], 'Request accepts the documented JSON format.');
    assertFalseValue(isset($requests[0]['HTTP_AUTHORIZATION']) || isset($requests[0]['HTTP_COOKIE']), 'No account credentials or cookies are forwarded.');
} finally {
    proc_terminate($process);
    proc_close($process);
    fclose($output);
    unlink($router);
    unlink($log);
}
fwrite(STDOUT, "Nominatim loopback HTTP boundary passed.\n");
