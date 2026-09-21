<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ChunkedJsonTransferHelper.php';

use Burki24\SymconModuleHelper\ChunkedJsonTransferHelper;

$GLOBALS['transferTestLocks'] = [];
$GLOBALS['transferTestLockDenied'] = false;
function IPS_SemaphoreEnter(string $name, int $timeout): bool
{
    if ($GLOBALS['transferTestLockDenied'] || isset($GLOBALS['transferTestLocks'][$name])) {
        return false;
    }
    $GLOBALS['transferTestLocks'][$name] = true;
    return true;
}
function IPS_SemaphoreLeave(string $name): void
{
    unset($GLOBALS['transferTestLocks'][$name]);
}

final class ChunkedJsonTransferHarness
{
    use ChunkedJsonTransferHelper;
    public int $peakBufferBytes = 0;
    public int $InstanceID;

    /** @var array<string,string> */
    private array $buffers = [];

    private int $timestamp = 1_000;
    private static int $nextInstanceID = 10_000;

    public function __construct(?int $instanceID = null)
    {
        $this->InstanceID = $instanceID ?? ++self::$nextInstanceID;
    }

    public function restoreBuffers(array $buffers): void
    {
        $this->buffers = $buffers;
    }

    public function seedBuffer(string $name, string $value): void
    {
        $this->SetBuffer($name, $value);
    }

    public function storageFiles(): array
    {
        $files = [];
        foreach ($this->buffers as $name => $value) {
            if (str_ends_with($name, ':Metadata') && $value !== '') {
                $metadata = json_decode($value, true);
                if (isset($metadata['storage']['file'])) {
                    $files[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $metadata['storage']['file'];
                }
            }
        }
        return $files;
    }

    public function ownedFiles(): array
    {
        $owner = substr(hash('sha256', getmypid() . ':' . $this->InstanceID), 0, 24);
        return glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'symcon-transfer-' . $owner . '-*.bin') ?: [];
    }

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
            if (str_contains($name, ':' . $token . ':Metadata') && $value !== '') {
                $metadata = json_decode($value, true);
                $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $metadata['storage']['file'];
                $file = fopen($path, 'r+b');
                fseek($file, 4);
                $byte = fread($file, 1);
                fseek($file, 4);
                fwrite($file, chr(ord($byte) ^ 1));
                fclose($file);
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
        $next = $this->buffers;
        $next[$name] = $value;
        if (array_sum(array_map('strlen', $next)) > 1_048_576) {
            throw new RuntimeException('Symcon total instance buffer hard limit exceeded.');
        }
        $this->peakBufferBytes = max($this->peakBufferBytes, array_sum(array_map('strlen', $next)));
        $this->buffers[$name] = $value;
    }

    protected function GetBufferList(): array
    {
        return array_keys($this->buffers);
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
    $activeFiles = $transfer->ownedFiles();
    $transfer->create('Oversized', [str_repeat('A', 600), str_repeat('X', 2_000)], 1024);
    throw new RuntimeException('An item larger than one page must fail.');
} catch (UnexpectedValueException) {
    assertSameValue(
        $activePages,
        $transfer->activePageBufferCount(),
        'A failed transfer must remove pages written before the oversized item.'
    );
    assertSameValue($activeFiles, $transfer->ownedFiles(), 'A failed transfer must remove its temporary file.');
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
        str_contains($exception->getMessage(), 'authentication'),
        'Tampered ciphertext must fail authentication.'
    );
}

$transfer->clear('CalendarEvents', $metadata['Token']);
$transfer->clear('Corrupt', $corrupt['Token']);

// An incompressible transfer well above the hard limit must not fill instance buffers.
$large = new ChunkedJsonTransferHarness();
$large->seedBuffer('OtherHelper', str_repeat('X', 64 * 1024));
$largeItems = [];
for ($i = 0; $i < 400; ++$i) {
    $largeItems[] = ['id' => $i, 'description' => bin2hex(random_bytes(2048))];
}
$one = $large->create('Large', $largeItems);
$two = $large->create('SecondScope', $largeItems);
assertTrueValue($large->peakBufferBytes < 80 * 1024, 'Two large transfers must retain only small metadata alongside foreign buffers.');
assertTrueValue(count($large->storageFiles()) === 2, 'Each transfer must use one encrypted file.');
$paths = $large->storageFiles();
assertFalseValue(str_contains(file_get_contents($paths[0]), $largeItems[0]['description']), 'Files must not expose calendar plaintext.');
$reloaded = new ChunkedJsonTransferHarness($large->InstanceID);
$reloaded->restoreBuffers($large->buffers());
$received = [];
for ($page = 0; $page < $one['PageCount']; ++$page) {
    $payload = $reloaded->page('Large', $one['Token'], $page);
    array_push($received, ...$payload['Items']);
}
assertSameValue($largeItems, $received, 'A reloaded module object must reconstruct all pages losslessly.');
assertSameValue($largeItems, array_merge(...array_map(
    fn (int $page): array => $reloaded->page('SecondScope', $two['Token'], $page)['Items'],
    range(0, $two['PageCount'] - 1)
)), 'Parallel transfers must remain independent.');
assertSameValue($reloaded->page('Large', $one['Token'], 0), $reloaded->page('Large', $one['Token'], 0), 'Retries must be stable.');
$alien = new ChunkedJsonTransferHarness();
$alien->restoreBuffers($large->buffers());
try {
    $alien->page('Large', $one['Token'], 0);
    throw new RuntimeException('Another instance must not read a copied transfer.');
} catch (UnexpectedValueException) {
}
$reloaded->clear('Large', $one['Token']);
assertFalseValue(is_file($paths[0]), 'Finish must delete the encrypted file.');
assertTrueValue(is_file($paths[1]), 'Finish must preserve another active transfer.');
$reloaded->setTimestamp(1400);
assertSameValue(1, $reloaded->cleanup('SecondScope'), 'TTL must clean an abandoned transfer.');
assertFalseValue(is_file($paths[1]), 'TTL must remove the file.');
assertSameValue(str_repeat('X', 64 * 1024), $reloaded->buffers()['OtherHelper'], 'Other helper buffers must remain unchanged.');

$budget = new ChunkedJsonTransferHarness();
$budget->seedBuffer('ExistingData', str_repeat('F', 250 * 1024));
try {
    $budget->create('NoBudget', $largeItems);
    throw new RuntimeException('Insufficient total budget must fail before writing metadata.');
} catch (RuntimeException $error) {
    assertTrueValue(str_contains($error->getMessage(), 'budget'), 'Buffer admission must explain exhaustion.');
}
assertSameValue(250 * 1024, strlen($budget->buffers()['ExistingData']), 'Budget rejection must preserve foreign data.');
assertSameValue([], $budget->storageFiles(), 'Rejected transfer must not leave live metadata.');
assertSameValue([], $budget->ownedFiles(), 'Rejected transfer must remove its encrypted file.');

// Restart drops buffers and keys, but expiry still reclaims this instance's files.
$beforeRestart = new ChunkedJsonTransferHarness();
$orphan = $beforeRestart->create('Restart', [['id' => 1]], 1024, 5);
$orphanPath = $beforeRestart->ownedFiles()[0];
$afterRestart = new ChunkedJsonTransferHarness($beforeRestart->InstanceID);
try {
    $afterRestart->page('Restart', $orphan['Token'], 0);
    throw new RuntimeException('Restart must invalidate old transfer tokens.');
} catch (UnexpectedValueException) {
}
$afterRestart->setTimestamp(1006);
$fresh = $afterRestart->create('Restart', []);
assertFalseValue(is_file($orphanPath), 'A subsequent creation must remove expired restart orphans.');
$afterRestart->clear('Restart', $fresh['Token']);

// The limit spans scopes, not just each registry.
$limited = new ChunkedJsonTransferHarness();
$active = [];
for ($i = 0; $i < 16; ++$i) {
    $active['Scope' . $i] = $limited->create('Scope' . $i, []);
}
try {
    $limited->create('OneTooMany', []);
    throw new RuntimeException('The instance-wide active-transfer limit must be enforced.');
} catch (RuntimeException $error) {
    assertTrueValue(str_contains($error->getMessage(), 'Too many'), 'Transfer quota must reject without overwriting existing transfers.');
}
foreach ($active as $scope => $meta) {
    assertSameValue([], $limited->page($scope, $meta['Token'], 0)['Items'], 'A quota rejection must preserve active transfers.');
    $limited->clear($scope, $meta['Token']);
}
assertSameValue([], $limited->ownedFiles(), 'Finishing all transfers must leave no files.');

// In-flight transfers from v1.0.0 remain readable and removable during an update.
$legacy = new ChunkedJsonTransferHarness();
$legacyToken = str_repeat('a', 32);
$legacyPrefix = 'ChunkedJsonTransfer:' . substr(hash('sha256', 'Legacy'), 0, 16);
$legacy->seedBuffer($legacyPrefix . ':Registry', json_encode([$legacyToken => 2000]));
$legacy->seedBuffer($legacyPrefix . ':' . $legacyToken . ':Metadata', json_encode([
    'token' => $legacyToken, 'pageCount' => 1, 'itemCount' => 1, 'expiresAt' => 2000, 'pageBytes' => 1024
]));
$legacy->seedBuffer($legacyPrefix . ':' . $legacyToken . ':Page:0', '[{"id":42}]');
assertSameValue([['id' => 42]], $legacy->page('Legacy', $legacyToken, 0)['Items'], 'Legacy buffer pages must survive a helper update.');
$legacy->clear('Legacy', $legacyToken);
assertSameValue(0, array_sum(array_map('strlen', $legacy->buffers())), 'Legacy buffers must be completely cleared.');

$bounded = new ChunkedJsonTransferHarness();
try {
    $bounded->create('StorageLimit', array_fill(0, 350, str_repeat('X', 196000)));
    throw new RuntimeException('A transfer above the disk quota must be rejected.');
} catch (UnexpectedValueException $error) {
    assertTrueValue(str_contains($error->getMessage(), 'storage limit'), 'The disk quota must fail explicitly.');
}
assertSameValue([], $bounded->ownedFiles(), 'Partial files must be deleted after a disk-quota failure.');
assertSameValue(0, array_sum(array_map('strlen', $bounded->buffers())), 'Failed transfers must not leave metadata.');

$missing = $bounded->create('MissingFile', [['id' => 1]]);
unlink($bounded->storageFiles()[0]);
try {
    $bounded->page('MissingFile', $missing['Token'], 0);
    throw new RuntimeException('Missing files must not be interpreted as an empty successful transfer.');
} catch (UnexpectedValueException $error) {
    assertTrueValue(str_contains($error->getMessage(), 'missing'), 'Missing files must fail explicitly.');
}
$bounded->clear('MissingFile', $missing['Token']);
$GLOBALS['transferTestLockDenied'] = true;
try {
    $bounded->create('Busy', []);
    throw new RuntimeException('A busy store must not be modified.');
} catch (RuntimeException $error) {
    assertTrueValue(str_contains($error->getMessage(), 'busy'), 'A denied semaphore must stop the operation.');
} finally {
    $GLOBALS['transferTestLockDenied'] = false;
}
assertSameValue([], $bounded->ownedFiles(), 'Lock rejection must not create files.');
assertSameValue([], $GLOBALS['transferTestLocks'], 'All success and failure paths must release their locks.');

fwrite(STDOUT, "ChunkedJsonTransferHelper tests passed (large transfers, aggregate budget, reload, isolation, encryption, cleanup, quotas, locks).\n");
