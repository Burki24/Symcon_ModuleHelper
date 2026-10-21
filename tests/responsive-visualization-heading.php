<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/ResponsiveVisualizationHelper.php';

$headingObject = [];
$headingLookups = 0;
function IPS_GetObject(int $instanceID): array
{
    global $headingObject, $headingLookups;
    $headingLookups++;
    assertSameValue(123, $instanceID, 'The current module object must determine heading visibility.');
    if ($headingObject === null) {
        throw new RuntimeException('Object unavailable');
    }

    return $headingObject;
}

$headingHarness = new class() {
    use Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;

    public int $InstanceID = 123;

    public function layout(bool $nativeTile = true): array
    {
        return $this->ResponsiveVisualizationLayoutOptions($nativeTile);
    }
};
foreach ([[], ['ObjectIsHiddenTitle' => false]] as $headingObject) {
    assertSameValue(['tileHeaderVisible' => true, 'tileHeaderInset' => 58], $headingHarness->layout(), 'Visible or missing title metadata must reserve the shared inset.');
}
$headingObject = ['ObjectIsHiddenTitle' => true];
assertSameValue(['tileHeaderVisible' => false, 'tileHeaderInset' => 0], $headingHarness->layout(), 'Hidden native headings must not waste viewport height.');
$headingObject = null;
assertSameValue(['tileHeaderVisible' => true, 'tileHeaderInset' => 58], $headingHarness->layout(), 'Failed native lookups must retain the conservative heading inset.');
$lookupsBeforeIPSView = $headingLookups;
assertSameValue(['tileHeaderVisible' => false, 'tileHeaderInset' => 0], $headingHarness->layout(false), 'IPSView has no native heading.');
assertSameValue($lookupsBeforeIPSView, $headingLookups, 'IPSView must not access the native object API.');
