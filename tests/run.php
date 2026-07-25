<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/PersistentJsonCacheHelper.php';

use Burki24\SymconModuleHelper\PersistentJsonCacheHelper;

final class PersistentJsonCacheHarness
{
    use PersistentJsonCacheHelper;

    /** @var array<string,string> */
    private array $attributes = [];

    private int $writeCount = 0;

    public function register(string $name, array $default = []): void
    {
        $this->RegisterPersistentJsonCache($name, $default);
    }

    public function read(string $name): array
    {
        return $this->ReadPersistentJsonCache($name);
    }

    public function write(string $name, array $data): bool
    {
        return $this->WritePersistentJsonCache($name, $data);
    }

    public function clear(string $name): bool
    {
        return $this->ClearPersistentJsonCache($name);
    }

    public function raw(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    public function setRaw(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function writes(): int
    {
        return $this->writeCount;
    }

    protected function RegisterAttributeString(string $name, string $default): void
    {
        if (!array_key_exists($name, $this->attributes)) {
            $this->attributes[$name] = $default;
        }
    }

    protected function ReadAttributeString(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
        ++$this->writeCount;
    }
}

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

$cache = new PersistentJsonCacheHarness();
$cache->register('Cache');
assertSameValue([], $cache->read('Cache'), 'A newly registered cache must be empty.');
assertSameValue('[]', $cache->raw('Cache'), 'The empty default must be stored as JSON.');

$default = ['enabled' => true, 'count' => 2];
$cache->register('WithDefault', $default);
assertSameValue($default, $cache->read('WithDefault'), 'Registered defaults must round-trip.');

$data = [
    'name'  => 'München/東京',
    'ratio' => 1.0,
    'items' => [1, 2, 3],
];
assertTrueValue($cache->write('Cache', $data), 'First write must report a change.');
assertSameValue($data, $cache->read('Cache'), 'Written data must round-trip.');
assertSameValue(1, $cache->writes(), 'First changed write must write exactly once.');
assertTrueValue(str_contains($cache->raw('Cache'), 'München/東京'), 'Unicode must remain unescaped.');
assertTrueValue(str_contains($cache->raw('Cache'), '1.0'), 'Float precision must be preserved.');

assertFalseValue($cache->write('Cache', $data), 'Identical content must not be written again.');
assertSameValue(1, $cache->writes(), 'Identical content must not increase the write count.');

assertTrueValue($cache->clear('Cache'), 'Clearing a non-empty cache must report a change.');
assertSameValue([], $cache->read('Cache'), 'Cleared cache must be empty.');
assertFalseValue($cache->clear('Cache'), 'Clearing an already empty cache must report no change.');

$cache->setRaw('Broken', '{invalid');
try {
    $cache->read('Broken');
    throw new RuntimeException('Invalid JSON must throw UnexpectedValueException.');
} catch (UnexpectedValueException $exception) {
    assertTrueValue(
        str_contains($exception->getMessage(), 'Broken'),
        'Invalid JSON exception must identify the affected cache.'
    );
}

$cache->setRaw('Scalar', '42');
try {
    $cache->read('Scalar');
    throw new RuntimeException('A scalar JSON value must be rejected.');
} catch (UnexpectedValueException $exception) {
    assertTrueValue(
        str_contains($exception->getMessage(), 'Scalar'),
        'Scalar JSON exception must identify the affected cache.'
    );
}

fwrite(STDOUT, "PersistentJsonCacheHelper tests passed.\n");
