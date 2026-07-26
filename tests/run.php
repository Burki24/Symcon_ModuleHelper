<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests = [
    'persistent-json-cache.php',
    'configuration-form.php',
    'variable-presentation.php',
    'parent-connection.php',
    'visualization-asset.php',
    'http-response.php',
    'variable-helper.php',
    'date-helper.php',
];

foreach ($tests as $test) {
    require_once __DIR__ . '/' . $test;
}

fwrite(STDOUT, "All helper tests passed.\n");
