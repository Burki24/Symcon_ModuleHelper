<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;

require_once __DIR__ . '/../src/ResponsiveVisualizationHelper.php';

final class ResponsiveVisualizationHelperHarness
{
    use ResponsiveVisualizationHelper;

    public function css(string $rootSelector, string $containerName = 'symcon-visualization'): string
    {
        return $this->ResponsiveVisualizationCSS($rootSelector, $containerName);
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

fwrite(STDOUT, "ResponsiveVisualizationHelper tests passed.\n");
