<?php

declare(strict_types=1);

/** @param mixed $actual */
function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    assertSameValue(true, $actual, $message);
}

function assertFalseValue(bool $actual, string $message): void
{
    assertSameValue(false, $actual, $message);
}
