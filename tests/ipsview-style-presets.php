<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewStylePresetHelper;

require_once __DIR__ . '/../src/IPSViewStylePresetHelper.php';

$expectedIds = [
    IPSViewStylePresetHelper::PRESET_STANDARD,
    IPSViewStylePresetHelper::PRESET_LIGHT,
    IPSViewStylePresetHelper::PRESET_DARK,
    IPSViewStylePresetHelper::PRESET_WARM,
    IPSViewStylePresetHelper::PRESET_COOL,
    IPSViewStylePresetHelper::PRESET_EARTHY,
    IPSViewStylePresetHelper::PRESET_WATER,
    IPSViewStylePresetHelper::PRESET_SUNNY
];
assertSameValue($expectedIds, IPSViewStylePresetHelper::ids(), 'All existing IPSView Assistant presets must be centralized.');
assertSameValue(12, count(IPSViewStylePresetHelper::roles()), 'Every preset must expose the twelve semantic color roles.');
assertSameValue(8, count(IPSViewStylePresetHelper::options()), 'The preset options must expose all eight predefined themes.');

$expectedDefiningColors = [
    IPSViewStylePresetHelper::PRESET_STANDARD => ['#404040', '#007AFF'],
    IPSViewStylePresetHelper::PRESET_LIGHT    => ['#E9EEF5', '#2563EB'],
    IPSViewStylePresetHelper::PRESET_DARK     => ['#111827', '#3B82F6'],
    IPSViewStylePresetHelper::PRESET_WARM     => ['#3B2420', '#F59E0B'],
    IPSViewStylePresetHelper::PRESET_COOL     => ['#0F1B2D', '#38BDF8'],
    IPSViewStylePresetHelper::PRESET_EARTHY   => ['#2D2A20', '#B08968'],
    IPSViewStylePresetHelper::PRESET_WATER    => ['#06283D', '#00B4D8'],
    IPSViewStylePresetHelper::PRESET_SUNNY    => ['#FFF3B0', '#F59E0B']
];

foreach ($expectedDefiningColors as $preset => [$viewBackground, $accent]) {
    $palette = IPSViewStylePresetHelper::palette($preset);
    assertSameValue(12, count($palette), sprintf('%s must define all semantic roles.', $preset));
    assertSameValue(
        $viewBackground,
        $palette[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND],
        sprintf('%s has an unexpected view background.', $preset)
    );
    assertSameValue(
        $accent,
        $palette[IPSViewStylePresetHelper::ROLE_ACCENT],
        sprintf('%s has an unexpected accent color.', $preset)
    );

    foreach ($palette as $color) {
        assertTrueValue(
            preg_match('/^#[0-9A-F]{6}$/', $color) === 1,
            sprintf('%s contains an invalid canonical color.', $preset)
        );
    }
}

assertSameValue('IPSView Standard', IPSViewStylePresetHelper::label('STANDARD'), 'Preset names must normalize case-insensitively.');
assertSameValue('dark', IPSViewStylePresetHelper::normalize(' Dark '), 'Preset identifiers must normalize whitespace.');
assertSameValue('light', IPSViewStylePresetHelper::normalize('unknown', 'light'), 'Unknown presets must support an explicit fallback.');
assertSameValue(null, IPSViewStylePresetHelper::normalize('unknown'), 'Unknown presets without fallback must return null.');
assertFalseValue(IPSViewStylePresetHelper::isValid('custom'), 'Custom is an editor mode and must not become a predefined central preset.');

try {
    IPSViewStylePresetHelper::palette('custom');
    throw new RuntimeException('Custom must not resolve as a predefined central preset.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'not supported'), 'Invalid preset errors must explain the failure.');
}

fwrite(STDOUT, "IPSView style preset tests passed.\n");
