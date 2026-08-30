<?php

declare(strict_types=1);

/** @return array<string,mixed>|null */
function ipsViewStyleFontFindControl(array $items, string $name): ?array
{
    foreach ($items as $item) {
        if (($item['name'] ?? '') === $name) {
            return $item;
        }
        if (is_array($item['items'] ?? null)) {
            $result = ipsViewStyleFontFindControl($item['items'], $name);
            if ($result !== null) {
                return $result;
            }
        }
    }

    return null;
}

$fontHarness = new IPSViewStyleHelperHarness();
$fontHarness->register();

assertSameValue(
    '',
    $fontHarness->properties()['IPSViewStyleFontFamily'] ?? null,
    'The existing FontFamily property default must remain unchanged.'
);

$fontControl = ipsViewStyleFontFindControl($fontHarness->formItems(), 'IPSViewStyleFontFamily');
assertSameValue('Select', $fontControl['type'] ?? null, 'The shared FontFamily field must use a Select control.');
assertSameValue(9, count($fontControl['options'] ?? []), 'The font selector must expose system default plus eight IPSView fonts.');
assertSameValue('', $fontControl['options'][0]['value'] ?? null, 'The system-default option must preserve the empty legacy value.');
assertTrueValue(
    in_array(['caption' => 'Open Sans', 'value' => 'OpenSans'], $fontControl['options'] ?? [], true),
    'The font selector must use canonical values from IPSViewFontCatalogHelper.'
);

$defaultFontStyle = $fontHarness->style();
assertSameValue(
    '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    $defaultFontStyle['FontFamily'],
    'Empty existing FontFamily values must retain the system-font fallback.'
);

$fontHarness->setProperty('IPSViewStyleFontFamily', 'Open Sans');
assertSameValue(
    'OpenSans',
    $fontHarness->style()['FontFamily'],
    'Known historic aliases must normalize to the canonical IPSView font family.'
);

$fontHarness->setProperty('IPSViewStyleFontFamily', 'My Existing Web Font');
assertSameValue(
    'My Existing Web Font',
    $fontHarness->style()['FontFamily'],
    'Safe historic custom font values must remain readable.'
);
$legacyControl = ipsViewStyleFontFindControl($fontHarness->formItems(), 'IPSViewStyleFontFamily');
assertTrueValue(
    in_array(
        ['caption' => 'My Existing Web Font (Legacy/custom)', 'value' => 'My Existing Web Font'],
        $legacyControl['options'] ?? [],
        true
    ),
    'Safe historic custom font values must remain selectable until changed.'
);

$fontHarness->setProperty('IPSViewStyleFontFamily', 'bad;font');
assertSameValue(
    '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    $fontHarness->style()['FontFamily'],
    'Unsafe historic font values must use the system-font fallback.'
);
$unsafeControl = ipsViewStyleFontFindControl($fontHarness->formItems(), 'IPSViewStyleFontFamily');
assertFalseValue(
    in_array('bad;font', array_column($unsafeControl['options'] ?? [], 'value'), true),
    'Unsafe historic font values must not be exposed as selectable options.'
);

$fontHarness->setProperty('IPSViewStyleSource', IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_MEDIA);
$fontDocument = json_encode(
    [
        'DefaultFontFamily' => 'Roboto Mono',
        'DefaultFontSize'   => 14
    ],
    JSON_THROW_ON_ERROR
);
$mediaFontStyle = $fontHarness->style($fontDocument);
assertSameValue(
    'RobotoMono',
    $mediaFontStyle['FontFamily'],
    'IPSView media font aliases must normalize through the shared catalogue.'
);
assertSameValue(14.0, $mediaFontStyle['FontSize'], 'Media font-size import must remain unchanged.');

$fontHarness->setProperty('IPSViewStyleSource', IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_CUSTOM);
$fontHarness->setProperty('IPSViewStyleFontFamily', 'PT Sans');
assertTrueValue(
    str_contains($fontHarness->css(), '--ipsview-font-family: PTSans;'),
    'CSS output must use the canonical shared font-family value.'
);

$fontHarness->setTranslationLanguage('de_DE.UTF-8');
$germanFontControl = ipsViewStyleFontFindControl($fontHarness->formItems(), 'IPSViewStyleFontFamily');
assertSameValue(
    'Systemstandard',
    $germanFontControl['options'][0]['caption'] ?? null,
    'The system-font option must be translated by the shared helper.'
);

assertSameValue(0, IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_CUSTOM, 'CUSTOM source ID must remain stable.');
assertSameValue(1, IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_MEDIA, 'MEDIA source ID must remain stable.');
assertSameValue(2, IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_LIGHT, 'LIGHT source ID must remain stable.');
assertSameValue(3, IPSViewStyleHelperHarness::IPSVIEW_STYLE_SOURCE_DARK, 'DARK source ID must remain stable.');

fwrite(STDOUT, "IPSViewStyleHelper shared-font integration tests passed.\n");
