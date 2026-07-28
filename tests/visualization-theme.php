<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\VisualizationThemeHelper;

require_once __DIR__ . '/../src/VisualizationThemeHelper.php';

final class VisualizationThemeHelperHarness
{
    use VisualizationThemeHelper;

    public function themeCSS(): string
    {
        return $this->VisualizationThemeCSS();
    }
}

$visualizationThemeHarness = new VisualizationThemeHelperHarness();
$visualizationThemeCSS = $visualizationThemeHarness->themeCSS();

assertTrueValue(str_contains($visualizationThemeCSS, '--symc-text:'), 'Theme must expose the text token.');
assertTrueValue(str_contains($visualizationThemeCSS, '--symc-background:'), 'Theme must expose the background token.');
assertTrueValue(str_contains($visualizationThemeCSS, '--symc-accent:'), 'Theme must expose the accent token.');
assertTrueValue(str_contains($visualizationThemeCSS, 'var(--content-color'), 'Theme must prefer the Symcon content color.');
assertTrueValue(str_contains($visualizationThemeCSS, 'var(--card-color'), 'Theme must prefer the Symcon card color.');
assertTrueValue(str_contains($visualizationThemeCSS, 'var(--accent-color'), 'Theme must prefer the Symcon accent color.');
assertFalseValue(str_contains($visualizationThemeCSS, 'prefers-color-scheme'), 'Theme must use token fallbacks without duplicating media-query palettes.');

fwrite(STDOUT, "VisualizationThemeHelper tests passed.\n");
