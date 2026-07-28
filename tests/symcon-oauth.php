<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\SymconOAuthClient;
use Burki24\SymconModuleHelper\SymconOAuthException;

require_once __DIR__ . '/../src/SymconOAuthHelper.php';

/** @var list<array{statusCode:int,body:string}|Throwable> $responses */
$responses = [
    [
        'statusCode' => 200,
        'body'       => json_encode([
            'access_token'  => 'initial-access',
            'refresh_token' => 'initial-refresh',
            'expires_in'    => 3600,
            'token_type'    => 'Bearer'
        ], JSON_THROW_ON_ERROR)
    ],
    [
        'statusCode' => 200,
        'body'       => json_encode([
            'access_token' => 'refreshed-access',
            'expires_in'   => '1800',
            'token_type'   => 'bearer'
        ], JSON_THROW_ON_ERROR)
    ]
];
/** @var list<array{method:string,url:string,headers:array<string,string>,body:string}> $requests */
$requests = [];
$transport = static function (string $method, string $url, array $headers, string $body) use (
    &$responses,
    &$requests
): array {
    $requests[] = compact('method', 'url', 'headers', 'body');
    if ($responses === []) {
        throw new RuntimeException('No fake OAuth response was queued.');
    }

    $response = array_shift($responses);
    if ($response instanceof Throwable) {
        throw $response;
    }

    return $response;
};

$client = new SymconOAuthClient($transport, 'example_oauth', 'Example Provider');
$authorizationUrl = $client->getAuthorizationUrl(' user+oauth@example.com ');
$authorizationQuery = [];
parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $authorizationQuery);
assertSameValue(
    'https://oauth.ipmagic.de/authorize/example_oauth?username=user%2Boauth%40example.com',
    $authorizationUrl,
    'Authorization URL must use the fixed Symcon OAuth service and RFC3986 encoding.'
);
assertSameValue(
    ['username' => 'user+oauth@example.com'],
    $authorizationQuery,
    'Authorization URL must route callbacks through the supplied license account.'
);
assertFalseValue(
    str_contains($authorizationUrl, 'client_id') || str_contains($authorizationUrl, 'client_secret'),
    'Provider client credentials must never appear in the authorization URL.'
);

$exchangeStartedAt = time();
$tokens = $client->exchangeAuthorizationCode(' auth-code ');
assertSameValue('initial-access', $tokens['accessToken'], 'Authorization exchange must return the access token.');
assertSameValue('initial-refresh', $tokens['refreshToken'], 'Authorization exchange must require a refresh token.');
assertTrueValue(
    $tokens['expiresAt'] >= $exchangeStartedAt + 3599 && $tokens['expiresAt'] <= time() + 3601,
    'Authorization exchange must normalize the access-token expiration timestamp.'
);
assertSameValue('POST', $requests[0]['method'], 'Token exchange must use POST.');
assertSameValue(
    'https://oauth.ipmagic.de/access_token/example_oauth',
    $requests[0]['url'],
    'Token exchange must use the registered Symcon OAuth endpoint.'
);
assertSameValue('application/json', $requests[0]['headers']['Accept'], 'Token exchange must request JSON.');
$exchangeBody = [];
parse_str($requests[0]['body'], $exchangeBody);
assertSameValue(['code' => 'auth-code'], $exchangeBody, 'Token exchange must only forward the authorization code.');

$tokens = $client->refreshAccessToken(' existing-refresh ');
assertSameValue('refreshed-access', $tokens['accessToken'], 'Refresh must return the renewed access token.');
assertSameValue(
    'existing-refresh',
    $tokens['refreshToken'],
    'Refresh must retain the existing token when the provider does not rotate it.'
);
$refreshBody = [];
parse_str($requests[1]['body'], $refreshBody);
assertSameValue(
    ['refresh_token' => 'existing-refresh'],
    $refreshBody,
    'Refresh must only forward the delegated refresh token.'
);

foreach ([
    ['', 'Provider', 'The Symcon OAuth identifier is invalid.'],
    ['Invalid-Identifier', 'Provider', 'The Symcon OAuth identifier is invalid.'],
    ['valid_identifier', ' ', 'The OAuth provider name is missing.']
] as [$identifier, $providerName, $expectedMessage]) {
    try {
        new SymconOAuthClient($transport, $identifier, $providerName);
        throw new RuntimeException('Invalid OAuth client configuration was accepted.');
    } catch (SymconOAuthException $exception) {
        assertSameValue($expectedMessage, $exception->getMessage(), 'OAuth configuration error is incorrect.');
    }
}

foreach ([
    ['authorization', static fn (): string => $client->getAuthorizationUrl(''), 'The Symcon license account is unavailable.'],
    ['code', static fn (): array => $client->exchangeAuthorizationCode(''), 'The authorization code is missing.'],
    ['refresh', static fn (): array => $client->refreshAccessToken(''), 'Example Provider is not connected yet.']
] as [$case, $operation, $expectedMessage]) {
    try {
        $operation();
        throw new RuntimeException('Invalid OAuth ' . $case . ' input was accepted.');
    } catch (SymconOAuthException $exception) {
        assertSameValue($expectedMessage, $exception->getMessage(), 'OAuth input error is incorrect.');
    }
}

$errorClient = new SymconOAuthClient(
    static fn (): array => [
        'statusCode' => 400,
        'body'       => '{"error":"invalid_grant","error_description":"The authorization has expired."}'
    ],
    'example_oauth',
    'Example Provider'
);
try {
    $errorClient->exchangeAuthorizationCode('expired-code');
    throw new RuntimeException('OAuth provider error was not raised.');
} catch (SymconOAuthException $exception) {
    assertSameValue(
        'The authorization has expired.',
        $exception->getMessage(),
        'OAuth provider error description must be preserved.'
    );
}

$invalidJsonClient = new SymconOAuthClient(
    static fn (): array => ['statusCode' => 200, 'body' => '{invalid'],
    'example_oauth',
    'Example Provider'
);
try {
    $invalidJsonClient->exchangeAuthorizationCode('code');
    throw new RuntimeException('Invalid OAuth JSON was accepted.');
} catch (SymconOAuthException $exception) {
    assertSameValue(
        'Symcon OAuth returned an invalid token response for Example Provider.',
        $exception->getMessage(),
        'Invalid OAuth JSON error is incorrect.'
    );
}

$invalidTransportClient = new SymconOAuthClient(
    static fn (): array => ['statusCode' => '200', 'body' => '{}'],
    'example_oauth',
    'Example Provider'
);
try {
    $invalidTransportClient->exchangeAuthorizationCode('code');
    throw new RuntimeException('Invalid OAuth transport response was accepted.');
} catch (SymconOAuthException $exception) {
    assertSameValue(
        'Symcon OAuth returned an invalid transport response for Example Provider.',
        $exception->getMessage(),
        'Invalid OAuth transport error is incorrect.'
    );
}

$missingRefreshClient = new SymconOAuthClient(
    static fn (): array => [
        'statusCode' => 200,
        'body'       => '{"access_token":"access","token_type":"Bearer"}'
    ],
    'example_oauth',
    'Example Provider'
);
try {
    $missingRefreshClient->exchangeAuthorizationCode('code');
    throw new RuntimeException('Initial OAuth response without refresh token was accepted.');
} catch (SymconOAuthException $exception) {
    assertSameValue(
        'Example Provider did not return a refresh token. Disconnect the account and connect it again.',
        $exception->getMessage(),
        'Missing refresh-token error is incorrect.'
    );
}

fwrite(STDOUT, "SymconOAuthHelper tests passed.\n");
