<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests = [
    'persistent-json-cache.php',
    'configuration-form.php',
    'data-flow.php',
    'chunked-json-transfer.php',
    'debug-helper.php',
    'variable-presentation.php',
    'parent-connection.php',
    'visualization-asset.php',
    'helper-translation.php',
    'ipsview-html-page.php',
    'visualization-theme.php',
    'ipsview-color-palette.php',
    'ipsview-font-catalog.php',
    'ipsview-style-profile.php',
    'ipsview-style-profile-source.php',
    'ipsview-style.php',
    'ipsview-style-font-catalog.php',
    'ipsview-style-layout.php',
    'http-response.php',
    'symcon-oauth.php',
    'variable-helper.php',
    'date-helper.php'
];

foreach ($tests as $test) {
    require_once __DIR__ . '/' . $test;
}

fwrite(STDOUT, "All helper tests passed.\n");
