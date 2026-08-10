<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ChunkedJsonTransferHelper.php';

use Burki24\SymconModuleHelper\ChunkedJsonTransferHelper;

final class ChunkedJsonTransferHarness
{
    use ChunkedJsonTransferHelper;

    /** @var array<string,string> */
    private array $buffers = [];

    private int $timestamp = 1_000;

    /**
     * @param list<mixed> $items
     * @return array{Token:string,PageCount:int,ItemCount:int,ExpiresAt:int}
     */
    public function create(string $scope, array $items, int $pageBytes = 196_608, int $ttlSeconds = 300): array
    {
        return $this->CreateChunkedJsonTransfer($scope, $items, $pageBytes, $ttlSeconds);
    }

    /** @return array{Token:string,Page:int,PageCount:int,ItemCount:int,Complete:bool,Items:list<mixed>} */
    public function page(string $scope, string $token, int $page): array
    {
        return $this->ReadChunkedJsonTransferPage($scope, $token, $page);
    }

    public function clear(string $scope, string $token): bool
    {
        return $this->ClearChunkedJsonTransfer($scope, $token);
    }

    public function cleanup(string $scope): int
    {
        return $this->CleanupExpiredChunkedJsonTransfers($scope);
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /** @return array<string,string> */
    public function buffers(): array
    {
        return $this->buffers;
    }

    public function corruptFirstPage(string $token): void
    {
        foreach ($this->buffers as $name => $value) {
            if (str_contains($name, ':' . $token . ':Page:') && $value !== '') {
                $this->buffers[$name] = '{invalid';
                return;
            }
        }

        throw new RuntimeException('No transfer page is available to corrupt.');
    }

    public function activePageBufferCount(): int
    {
        return count(array_filter(
            $this->buffers,
            static fn (string $value, string $name): bool => str_contains($name, ':Page:') && $value !== '',
            ARRAY_FILTER_USE_BOTH
        ));
    }

    protected function GetBuffer(string $name): string
    {
        return $this->buffers[$name] ?? '';
    }

    protected function SetBuffer(string $name, string $value): void
    {
        $this->buffers[$name] = $value;
    }

    protected function GetChunkedJsonTransferTimestamp(): int
    {
        return $this->timestamp;
    }
}

$transfer = new ChunkedJsonTransferHarness();
$empty = $transfer->create('CalendarEvents', []);
assertSameValue(1, $empty['PageCount'], 'An empty transfer must contain one page.');
assertSameValue(0, $empty['ItemCount'], 'An empty transfer must report zero items.');
$emptyPage = $transfer->page('CalendarEvents', $empty['Token'], 0);
assertSameValue([], $emptyPage['Items'], 'An empty transfer page must contain an empty list.');
assertTrueValue($emptyPage['Complete'], 'The only empty transfer page must be complete.');
assertTrueValue($transfer->clear('CalendarEvents', $empty['Token']), 'An existing transfer must be cleared.');
assertFalseValue($transfer->clear('CalendarEvents', $empty['Token']), 'A cleared transfer must no longer exist.');

$items = [
    ['id' => 1, 'title' => str_repeat('A', 600)],
    ['id' => 2, 'title' => str_repeat('B', 600)],
    ['id' => 3, 'title' => 'München/東京', 'ratio' => 1.0],
];
$metadata = $transfer->create('CalendarEvents', $items, 1024, 60);
assertSameValue(2, $metadata['PageCount'], 'The size limit must split items into multiple pages.');
assertSameValue(3, $metadata['ItemCount'], 'Transfer metadata must report the complete item count.');
assertSameValue(1_060, $metadata['ExpiresAt'], 'Transfer metadata must report the expiration timestamp.');

$received = [];
for ($page = 0; $page < $metadata['PageCount']; ++$page) {
    $payload = $transfer->page('CalendarEvents', $metadata['Token'], $page);
    assertSameValue($page, $payload['Page'], 'Each page must report its zero-based page number.');
    assertSameValue($metadata['PageCount'], $payload['PageCount'], 'Each page must report the total page count.');
    assertSameValue($page === $metadata['PageCount'] - 1, $payload['Complete'], 'Only the last page is complete.');
    array_push($received, ...$payload['Items']);
}
assertSameValue($items, $received, 'Paged items must retain order, Unicode and numeric types.');

foreach ($transfer->buffers() as $name => $value) {
    if (str_contains($name, ':Page:') && $value !== '') {
        assertTrueValue(strlen($value) <= 1024, 'Every encoded page must respect the byte limit.');
    }
}

try {
    $transfer->page('CalendarEvents', $metadata['Token'], $metadata['PageCount']);
    throw new RuntimeException('Reading beyond the available page range must fail.');
} catch (InvalidArgumentException) {
}

try {
    $activePages = $transfer->activePageBufferCount();
    $transfer->create('Oversized', [str_repeat('A', 600), str_repeat('X', 2_000)], 1024);
    throw new RuntimeException('An item larger than one page must fail.');
} catch (UnexpectedValueException) {
    assertSameValue(
        $activePages,
        $transfer->activePageBufferCount(),
        'A failed transfer must remove pages written before the oversized item.'
    );
}

try {
    $transfer->create('CalendarEvents', ['invalid' => 'not-a-list']);
    throw new RuntimeException('An associative root must fail.');
} catch (InvalidArgumentException) {
}

try {
    $transfer->create('Invalid scope!', []);
    throw new RuntimeException('An invalid transfer scope must fail.');
} catch (InvalidArgumentException) {
}

try {
    $transfer->create('CalendarEvents', [], 1023);
    throw new RuntimeException('A page size below the supported minimum must fail.');
} catch (InvalidArgumentException) {
}

try {
    $transfer->create('CalendarEvents', [], 245_761);
    throw new RuntimeException('A page size above the supported maximum must fail.');
} catch (InvalidArgumentException) {
}

try {
    $transfer->page('CalendarEvents', 'invalid', 0);
    throw new RuntimeException('An invalid transfer token must fail.');
} catch (InvalidArgumentException) {
}

$expiring = $transfer->create('Expiring', [['id' => 1]], 1024, 5);
$transfer->setTimestamp(1_005);
assertSameValue(1, $transfer->cleanup('Expiring'), 'Cleanup must remove an expired transfer.');
try {
    $transfer->page('Expiring', $expiring['Token'], 0);
    throw new RuntimeException('An expired and cleaned transfer must no longer be readable.');
} catch (UnexpectedValueException) {
}

$transfer->setTimestamp(2_000);
$corrupt = $transfer->create('Corrupt', [['id' => 1]], 1024, 60);
$transfer->corruptFirstPage($corrupt['Token']);
try {
    $transfer->page('Corrupt', $corrupt['Token'], 0);
    throw new RuntimeException('Invalid page JSON must fail.');
} catch (UnexpectedValueException $exception) {
    assertTrueValue(
        str_contains($exception->getMessage(), 'invalid JSON'),
        'Invalid page JSON must produce a meaningful exception.'
    );
}

fwrite(STDOUT, "ChunkedJsonTransferHelper tests passed.\n");
