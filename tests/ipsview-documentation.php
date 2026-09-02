<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    $root . '/src/IPSViewControlThemeHelper.php',
    $root . '/src/IPSViewStyleConfigurationHelper.php'
];

$missing = [];
foreach ($targets as $path) {
    $source = (string) file_get_contents($path);
    preg_match_all(
        '/\b(public|protected|private)\s+(?:static\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*?)\)\s*:\s*([^\s{]+)/s',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    foreach ($matches[0] as $index => $declaration) {
        $method = $matches[2][$index][0];
        $parameters = $matches[3][$index][0];
        $returnType = $matches[4][$index][0];
        $prefix = rtrim(substr($source, 0, $declaration[1]));
        if (!str_ends_with($prefix, '*/')) {
            $missing[] = basename($path) . '::' . $method . ' has no direct PHPDoc block.';
            continue;
        }

        $docStart = strrpos($prefix, '/**');
        $commentStart = strrpos($prefix, '/*');
        if ($docStart === false || $commentStart !== $docStart) {
            $missing[] = basename($path) . '::' . $method . ' has no direct PHPDoc block.';
            continue;
        }

        $doc = substr($prefix, $docStart);
        preg_match_all('/\$[A-Za-z_][A-Za-z0-9_]*/', $parameters, $parameterMatches);
        foreach (array_unique($parameterMatches[0]) as $parameter) {
            if (preg_match('/@param\s+[^\r\n]*' . preg_quote($parameter, '/') . '\b/', $doc) !== 1) {
                $missing[] = basename($path) . '::' . $method . ' is missing @param for ' . $parameter . '.';
            }
        }

        if ($returnType === 'void') {
            if (preg_match('/@return\s+void\b/', $doc) !== 1) {
                $missing[] = basename($path) . '::' . $method . ' is missing @return void.';
            }
        } elseif (preg_match('/@return\s+[^\r\n]+/', $doc) !== 1) {
            $missing[] = basename($path) . '::' . $method . ' is missing @return.';
        }
    }
}

$readme = (string) file_get_contents($root . '/README.md');
foreach ([
    '## IPSViewControlThemeHelper',
    '## IPSViewStyleConfigurationHelper',
    '109',
    '15',
    '`ColorView`',
    '`ColorPage`',
    '`IPSViewStyleNativeColorOverrides()`'
] as $requiredDocumentation) {
    if (!str_contains($readme, $requiredDocumentation)) {
        $missing[] = 'README.md is missing documentation marker: ' . $requiredDocumentation;
    }
}

if ($missing !== []) {
    throw new RuntimeException("IPSView documentation contract failed:\n - " . implode("\n - ", $missing));
}

fwrite(STDOUT, "IPSView helper documentation contract passed.\n");
