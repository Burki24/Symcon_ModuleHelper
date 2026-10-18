<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DebugFlowHelper.php';

final class DebugFlowHarness
{
    use \Burki24\SymconModuleHelper\DebugFlowHelper;

    public int $InstanceID = 12345;
    public array $messages = [];
    public bool $failDebug = false;

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
