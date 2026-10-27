<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DebugFlowHelper.php';

final class DebugFlowHarness
{
    use \Burki24\SymconModuleHelper\DebugFlowHelper;

    public int $InstanceID = 12345;
    public array $messages = [];
    public bool $failDebug = false;
    public array $writes = [];

    public function write(): mixed
    {
        return $this->DebugFlowWriteValue('BatteryLevel', 42, __FUNCTION__, __LINE__, true, ['Einheit' => 'Prozent', 'Ziel' => 'Darf Ziel nicht ersetzen']);
    }

    public function callback(\Closure $operation): mixed
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, $operation, false, [], ['Auftrag abgelehnt.', 'Auftrag angenommen; Ausführung nicht bestätigt.']);
    }

    public function execute(bool $fail = false): bool
    {
        $result = false;
        $failed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $this->nested();
            if ($fail) {
                throw new RuntimeException('token=must-not-leak');
            }
            return $result = true;
        } catch (Throwable $exception) {
            $failed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $exception);
            throw $exception;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $result, $failed, false);
        }
    }

    protected function SetValue(string $ident, mixed $value): bool
    {
        $this->writes[] = [$ident, $value];
        return false;
    }

    protected function SendDebug(string $message, string $data, int $format): bool
    {
        if ($this->failDebug) {
            throw new RuntimeException('Debug channel unavailable.');
        }
        $this->messages[] = ['category' => $message, 'data' => json_decode($data, true, 512, JSON_THROW_ON_ERROR)];
        return true;
    }

    private function nested(): void
    {
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Wert wird übergeben.', ['Variable' => 23456, 'Wert' => true, 'token' => 'must-not-leak']);
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__);
        }
    }
}

$flowHarness = new DebugFlowHarness();
if (!$flowHarness->execute()) {
    throw new RuntimeException('Tracing changed the result.');
}
$operation = $flowHarness->messages[0]['data']['Vorgang'];
$stack = [];
foreach ($flowHarness->messages as $entry) {
    if ($entry['data']['Vorgang'] !== $operation || $entry['data']['Instanz'] !== 12345) {
        throw new RuntimeException('Nested operations lost their context.');
    }
    $call = $entry['data']['Aufruf'];
    if (str_contains($entry['category'], ' | START | ')) {
        $stack[] = $call;
    } elseif (str_contains($entry['category'], ' | ENDE | ') && array_pop($stack) !== $call) {
        throw new RuntimeException('Unbalanced call trace.');
    }
}
if ($stack !== [] || str_contains(json_encode($flowHarness->messages), 'must-not-leak')) {
    throw new RuntimeException('Trace stack or secret protection failed.');
}
try {
    $flowHarness->execute(true);
    throw new LogicException('Expected the original exception.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'token=must-not-leak') {
        throw $exception;
    }
}
if (str_contains(json_encode($flowHarness->messages), 'must-not-leak')) {
    throw new RuntimeException('Exception text leaked.');
}
$flowHarness->messages = [];
$flowHarness->execute();
if ($flowHarness->messages[0]['data']['Vorgang'] === $operation || $flowHarness->messages[0]['data']['Aufruf'] !== 1) {
    throw new RuntimeException('A new operation did not reset correlation.');
}
$flowHarness->failDebug = true;
if (!$flowHarness->execute()) {
    throw new RuntimeException('Debug failure interfered with the operation.');
}
fwrite(STDOUT, "Debug flow checks passed.\n");

$callbackHarness = new DebugFlowHarness();
$result = ['private' => 'UNLABELLED-SECRET-CONTENT'];
if ($callbackHarness->callback(fn () => $result) !== $result || str_contains(json_encode($callbackHarness->messages), 'UNLABELLED-SECRET-CONTENT')) {
    throw new RuntimeException('A callback result was changed or exposed.');
}
$expected = new RuntimeException('UNLABELLED-SECRET-CONTENT');
try {
    $callbackHarness->callback(static function () use ($expected): void
    { throw $expected; });
    throw new LogicException('Expected callback exception.');
} catch (RuntimeException $exception) {
    if ($exception !== $expected) {
        throw new RuntimeException('Original exception identity changed.');
    }
}
$stack = [];
foreach ($callbackHarness->messages as $entry) {
    $key = $entry['data']['Vorgang'] . ':' . $entry['data']['Aufruf'];
    if (str_contains($entry['category'], ' | START | ')) {
        $stack[] = $key;
    }
    if (str_contains($entry['category'], ' | ENDE | ') && array_pop($stack) !== $key) {
        throw new RuntimeException('Callback trace is unbalanced.');
    }
}
if ($stack !== [] || str_contains(json_encode($callbackHarness->messages), 'UNLABELLED-SECRET-CONTENT')) {
    throw new RuntimeException('Callback exception trace leaked private data or lost its end.');
}
$callbackHarness->callback(fn () => $callbackHarness->callback(fn () => false));
if (!str_contains(json_encode($callbackHarness->messages, JSON_UNESCAPED_UNICODE), 'Auftrag abgelehnt.')) {
    throw new RuntimeException('Boolean outcome lacks its domain meaning.');
}
$callbackHarness->failDebug = true;
if ($callbackHarness->callback(fn () => true) !== true) {
    throw new RuntimeException('Broken channel changed callback result.');
}
fwrite(STDOUT, "Debug callback return, exception, privacy and channel-failure checks passed.\n");

$writeHarness = new DebugFlowHarness();
if ($writeHarness->write() !== false || $writeHarness->writes !== [['BatteryLevel', 42]]) {
    throw new RuntimeException('Debug must retain the native write result and perform exactly one write.');
}
$writeContext = $writeHarness->messages[0]['data']['Bezug'];
if ($writeContext['Ziel'] !== 'BatteryLevel' || $writeContext['Wert'] !== 42 || $writeContext['Einheit'] !== 'Prozent') {
    throw new RuntimeException('Safe write metadata must retain target/value identity and explain units.');
}
$writeHarness->execute();
$completedOperation = end($writeHarness->messages)['data']['Vorgang'];
$writeHarness->write();
if (end($writeHarness->messages)['data']['Vorgang'] === $completedOperation || end($writeHarness->messages)['data']['Aufruf'] !== 0) {
    throw new RuntimeException('A standalone event must not inherit a completed call correlation.');
}
