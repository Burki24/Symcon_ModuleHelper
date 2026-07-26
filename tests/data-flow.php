<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DataFlowHelper.php';

use Burki24\SymconModuleHelper\DataFlowHelper;

final class DataFlowHelperHarness
{
    use DataFlowHelper;

    /** @param array<string|int,mixed> $payload */
    public function encode(string $dataID, array $payload = []): string
    {
        return $this->EncodeDataFlowMessage($dataID, $payload);
    }

    /** @return array<string,mixed> */
    public function decode(string $json, ?string $expectedDataID = null): array
    {
        return $this->DecodeDataFlowMessage($json, $expectedDataID);
    }
}

$helper = new DataFlowHelperHarness();
$dataID = '{A3E591AB-BA98-EC5E-5698-65B05B4787C0}';

$encoded = $helper->encode($dataID, [
    'Operation' => 'GetData',
    'Url'       => 'https://example.test/ä',
    'Value'     => 1.0,
    'Nested'    => ['Enabled' => true]
]);
assertTrueValue(str_contains($encoded, '"DataID":"' . $dataID . '"'), 'DataFlowHelper must prepend DataID.');
assertTrueValue(str_contains($encoded, 'https://example.test/ä'), 'DataFlowHelper must keep Unicode and slashes unescaped.');
assertTrueValue(str_contains($encoded, '"Value":1.0'), 'DataFlowHelper must preserve zero fractions.');

$decoded = $helper->decode($encoded, strtolower($dataID));
assertSameValue($dataID, $decoded['DataID'], 'DataFlowHelper must preserve DataID.');
assertSameValue('GetData', $decoded['Operation'], 'DataFlowHelper must preserve payload fields.');
assertSameValue(['Enabled' => true], $decoded['Nested'], 'DataFlowHelper must decode nested objects as arrays.');

$emptyPayload = $helper->decode($helper->encode($dataID), $dataID);
assertSameValue(['DataID' => $dataID], $emptyPayload, 'DataFlowHelper must support an empty payload.');

try {
    $helper->encode('', []);
    throw new RuntimeException('DataFlowHelper must reject an empty DataID while encoding.');
} catch (InvalidArgumentException) {
}

try {
    $helper->encode($dataID, ['dataid' => 'duplicate']);
    throw new RuntimeException('DataFlowHelper must reject payload-owned DataID fields.');
} catch (InvalidArgumentException) {
}

try {
    $helper->decode('[]');
    throw new RuntimeException('DataFlowHelper must reject a JSON list root.');
} catch (InvalidArgumentException) {
}

try {
    $helper->decode('{}');
    throw new RuntimeException('DataFlowHelper must require DataID.');
} catch (UnexpectedValueException) {
}

try {
    $helper->decode('{invalid');
    throw new RuntimeException('DataFlowHelper must propagate invalid JSON as JsonException.');
} catch (JsonException) {
}

try {
    $helper->decode($encoded, '{00000000-0000-0000-0000-000000000000}');
    throw new RuntimeException('DataFlowHelper must reject an unexpected DataID.');
} catch (UnexpectedValueException) {
}

fwrite(STDOUT, "DataFlowHelper tests passed.\n");
