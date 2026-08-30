<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewFontCatalogHelper;
use Burki24\SymconModuleHelper\IPSViewStylePresetHelper;

/** @return array<string,mixed>|null */
function ipsViewStyleCustomCopyFindControl(array $items, string $caption): ?array
{
    foreach ($items as $item) {
        if (($item['caption'] ?? '') === $caption) {
            return $item;
        }
        if (is_array($item['items'] ?? null)) {
            $result = ipsViewStyleCustomCopyFindControl($item['items'], $caption);
            if ($result !== null) {
                return $result;
            }
        }
    }

    return null;
}

$copyHarness = new IPSViewStyleHelperHarness();
$copyHarness->register();
$copyProperties = $copyHarness->properties();

assertSameValue(
    IPSViewFontCatalogHelper::STYLE_REGULAR,
    $copyProperties['IPSViewStyleFontStyle'] ?? null,
    'The custom style must default to the regular font cut.'
);

$copyHarness->setProperty('IPSViewStyleViewBackgroundColor', 0x010203);
$copyHarness->setProperty(
    'IPSViewStyleSource',
    IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_PRESET_WATER
);
$waterPalette = IPSViewStylePresetHelper::palette(IPSViewStylePresetHelper::PRESET_WATER);
$waterViewColor = (int) hexdec(substr($waterPalette[IPSViewStylePresetHelper::ROLE_VIEW_BACKGROUND], 1));
$waterForm = $copyHarness->formItems();
$viewControl = ipsViewStyleCustomCopyFindControl($waterForm, 'View background');
assertSameValue(false, $viewControl['enabled'] ?? null, 'Preset color values must be read-only.');
assertSameValue($waterViewColor, $viewControl['value'] ?? null, 'Preset colors must show their effective values.');
assertFalseValue(isset($viewControl['name']), 'Read-only preset previews must not be bound to custom properties.');
assertSameValue(
    0x010203,
    $copyHarness->properties()['IPSViewStyleViewBackgroundColor'],
    'Displaying a preset must not overwrite the stored custom style.'
);

$mediaControl = ipsViewStyleCustomCopyFindControl($waterForm, 'IPSView media object');
$profileControl = ipsViewStyleCustomCopyFindControl($waterForm, 'Style profile media object');
assertSameValue(false, $mediaControl['visible'] ?? null, 'The IPSView media selector must be hidden for presets.');
assertSameValue(false, $profileControl['visible'] ?? null, 'The profile media selector must be hidden for presets.');

$copyButton = ipsViewStyleCustomCopyFindControl($waterForm, 'Copy to custom style');
assertSameValue('Button', $copyButton['type'] ?? null, 'Preset sources must offer a copy-to-custom action.');
assertTrueValue(is_array($copyButton['onClick'] ?? null), 'The copy action must use a multi-line script.');
$copyScript = implode("\n", $copyButton['onClick']);
assertTrueValue(
    str_contains($copyScript, "IPS_SetProperty(\$id, 'IPSViewStyleViewBackgroundColor', " . $waterViewColor . ');'),
    'The copy action must transfer the effective preset colors.'
);
assertTrueValue(
    str_contains($copyScript, "IPS_SetProperty(\$id, 'IPSViewStyleFontStyle', 'regular');"),
    'The copy action must transfer the effective font cut.'
);
assertTrueValue(
    str_contains($copyScript, "IPS_SetProperty(\$id, 'IPSViewStyleSource', 0);"),
    'The copy action must switch to the custom source.'
);
assertTrueValue(str_contains($copyScript, 'IPS_ApplyChanges($id);'), 'The copy action must apply the copied style.');

$copyHarness->setProperty('IPSViewStyleSource', IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_CUSTOM);
$customForm = $copyHarness->formItems();
$customViewControl = ipsViewStyleCustomCopyFindControl($customForm, 'View background');
assertSameValue(
    'IPSViewStyleViewBackgroundColor',
    $customViewControl['name'] ?? null,
    'The custom source must keep its editable property binding.'
);
assertFalseValue(isset($customViewControl['enabled']), 'Custom color controls must remain editable.');
assertSameValue(null, ipsViewStyleCustomCopyFindControl($customForm, 'Copy to custom style'), 'The copy action must be hidden for the custom source.');

$copyHarness->setProperty('IPSViewStyleFontFamily', 'Roboto');
$copyHarness->setProperty('IPSViewStyleFontStyle', IPSViewFontCatalogHelper::STYLE_BOLD);
assertSameValue(
    IPSViewFontCatalogHelper::STYLE_BOLD,
    $copyHarness->style()['FontStyle'],
    'The custom source must preserve a valid selected font cut.'
);
assertTrueValue(
    str_contains($copyHarness->css(), '--ipsview-font-weight: 700;'),
    'A copied or selected bold font cut must reach the generated CSS.'
);

$copyHarness->setProperty('IPSViewStyleFontFamily', 'Dancing Script');
$copyHarness->setProperty('IPSViewStyleFontStyle', IPSViewFontCatalogHelper::STYLE_ITALIC);
assertSameValue(
    IPSViewFontCatalogHelper::STYLE_REGULAR,
    $copyHarness->style()['FontStyle'],
    'Unsupported font cuts must fall back safely to regular.'
);

fwrite(STDOUT, "IPSView active-style preview and custom-copy tests passed.\n");
