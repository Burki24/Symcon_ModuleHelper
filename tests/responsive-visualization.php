<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;

require_once __DIR__ . '/../src/ResponsiveVisualizationHelper.php';

final class ResponsiveVisualizationHelperHarness
{
    use ResponsiveVisualizationHelper;

    public int $InstanceID = 1;

    public function layout(bool $nativeTile = true): array
    {
        return $this->ResponsiveVisualizationLayoutOptions($nativeTile);
    }

    public function css(string $rootSelector, string $containerName = 'symcon-visualization', bool $fillViewport = false): string
    {
        return $this->ResponsiveVisualizationCSS($rootSelector, $containerName, $fillViewport);
    }
}

$responsiveVisualizationHarness = new ResponsiveVisualizationHelperHarness();
$responsiveVisualizationCSS = $responsiveVisualizationHarness->css('#calendar-app');

assertTrueValue(str_contains($responsiveVisualizationCSS, 'container-name: symcon-visualization;'), 'Responsive CSS must name the query container.');
assertTrueValue(str_contains($responsiveVisualizationCSS, 'container-type: inline-size;'), 'Responsive CSS must query the available tile width.');
assertTrueValue(str_contains($responsiveVisualizationCSS, '--symc-responsive-touch-target: 44px;'), 'Responsive CSS must expose the shared touch target size.');

try {
    $responsiveVisualizationHarness->css('.calendar-app');
    throw new RuntimeException('Class selectors must be rejected for the responsive root.');
} catch (InvalidArgumentException) {
}

try {
    $responsiveVisualizationHarness->css('#calendar-app', 'invalid name');
    throw new RuntimeException('Invalid responsive container names must be rejected.');
} catch (InvalidArgumentException) {
}

assertFalseValue(str_contains($responsiveVisualizationCSS, 'html, body'), 'Existing consumers must not opt into viewport CSS implicitly.');
$responsiveFrame = $responsiveVisualizationHarness->css('#map-app', 'map-view', true);
assertTrueValue(str_contains($responsiveFrame, 'height: 100%;'), 'The opted-in frame must fill the available height.');
assertTrueValue(str_contains($responsiveFrame, 'min-height: 0;'), 'The frame must allow shrinking below its content height.');
assertTrueValue(str_contains($responsiveFrame, 'padding-top: calc(var(--symc-responsive-padding, 12px) + var(--symc-tile-header-inset, 0px));'), 'The frame must reserve the canonical heading inset once.');
assertFalseValue(str_contains($responsiveFrame, '--symc-tile-header-inset:'), 'The frame must inherit the dynamic heading inset from an ancestor without shadowing it.');
assertTrueValue(str_contains($responsiveFrame, 'overflow: hidden;'), 'Scrolling belongs to module content rather than the outer page.');
assertSameValue(['tileHeaderVisible' => false, 'tileHeaderInset' => 0], $responsiveVisualizationHarness->layout(false), 'IPSView must never reserve the native heading.');
assertSameValue(['tileHeaderVisible' => true, 'tileHeaderInset' => 58], $responsiveVisualizationHarness->layout(), 'Unavailable object lookup must conservatively reserve the visible heading.');

// Run object API fixtures in isolation so they cannot affect the shared test process.
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/responsive-visualization-heading.php'), $responsiveHeadingExitCode);
assertSameValue(0, $responsiveHeadingExitCode, 'Native heading API fixtures must pass.');

fwrite(STDOUT, "ResponsiveVisualizationHelper tests passed.\n");
