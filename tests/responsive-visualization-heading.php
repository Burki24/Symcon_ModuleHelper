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
    assertSameValue(['tileHeaderVisible' => true, 'tileHeaderInset' => 58, 'tileMaximizeVisible' => true, 'tileActionInset' => 0], $headingHarness->layout(), 'Visible or missing title metadata must reserve the shared inset.');
}
foreach ([true, false] as $hideTitle) {
    foreach ([true, false] as $hideMaximize) {
        $headingObject = ['ObjectIsHiddenTitle' => $hideTitle, 'ObjectIsHiddenMaximize' => $hideMaximize];
        $before = $headingLookups;
        assertSameValue([
            'tileHeaderVisible'   => !$hideTitle,
            'tileHeaderInset'     => $hideTitle ? 0 : 58,
            'tileMaximizeVisible' => !$hideMaximize,
            'tileActionInset'     => $hideTitle && !$hideMaximize ? 44 : 0
        ], $headingHarness->layout(), 'Only a hidden title and visible maximize control require an inline toolbar reservation.');
        assertSameValue($before + 1, $headingLookups, 'Both flags must come from the same object snapshot.');
    }
}
$headingObject = ['ObjectIsHiddenTitle' => true];
assertSameValue(44, $headingHarness->layout()['tileActionInset'], 'A missing maximize flag conservatively reserves the control beside a hidden title.');
$headingObject = null;
assertSameValue(['tileHeaderVisible' => true, 'tileHeaderInset' => 58, 'tileMaximizeVisible' => true, 'tileActionInset' => 0], $headingHarness->layout(), 'Failed native lookups must retain the conservative heading inset.');
$lookupsBeforeIPSView = $headingLookups;
assertSameValue(['tileHeaderVisible' => false, 'tileHeaderInset' => 0, 'tileMaximizeVisible' => false, 'tileActionInset' => 0], $headingHarness->layout(false), 'IPSView has neither a native heading nor a native maximize control.');
assertSameValue($lookupsBeforeIPSView, $headingLookups, 'IPSView must not access the native object API.');
