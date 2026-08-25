<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ipsview-style.php';

/** @return list<int> */
function ipsViewStyleLayoutRowSizes(array $items, string $type): array
{
    $sizes = [];
    foreach ($items as $item) {
        if (($item['type'] ?? '') !== 'RowLayout' || !is_array($item['items'] ?? null)) {
            continue;
        }

        $matchingItems = array_filter(
            $item['items'],
            static fn (array $child): bool => ($child['type'] ?? '') === $type
        );
        if ($matchingItems !== []) {
            $sizes[] = count($matchingItems);
        }
    }

    return $sizes;
}

/** @return list<int> */
function ipsViewStyleOpacityRowSizes(array $items): array
{
    $sizes = [];
    foreach ($items as $item) {
        if (($item['type'] ?? '') !== 'RowLayout' || !is_array($item['items'] ?? null)) {
            continue;
        }

        $matchingItems = array_filter(
            $item['items'],
            static fn (array $child): bool => str_ends_with((string) ($child['name'] ?? ''), 'Opacity')
                && ($child['name'] ?? '') !== 'IPSViewStyleDisabledOpacity'
        );
        if ($matchingItems !== []) {
            $sizes[] = count($matchingItems);
        }
    }

    return $sizes;
}

/** @return array<string,mixed>|null */
function ipsViewStyleFindControl(array $items, string $name): ?array
{
    foreach ($items as $item) {
        if (($item['name'] ?? '') === $name) {
            return $item;
        }
        if (is_array($item['items'] ?? null)) {
            $result = ipsViewStyleFindControl($item['items'], $name);
            if ($result !== null) {
                return $result;
            }
        }
    }

    return null;
}

$layoutHarness = new IPSViewStyleHelperHarness();
$defaultForm = $layoutHarness->formItems();
assertSameValue(
    [3, 3, 3, 3, 3, 3, 3],
    ipsViewStyleLayoutRowSizes($defaultForm, 'SelectColor'),
    'The default IPSView style form must use three color controls per row so captions stay readable.'
);

$defaultJSON = json_encode($defaultForm, JSON_THROW_ON_ERROR);
assertFalseValue(
    preg_match('/"width":"[0-9.]+%"/', $defaultJSON) === 1,
    'The default IPSView style form must avoid percentage control widths inside RowLayout.'
);
assertTrueValue(
    str_contains($defaultJSON, '"width":"180px"')
        && str_contains($defaultJSON, '"width":"220px"')
        && str_contains($defaultJSON, '"width":"240px"')
        && str_contains($defaultJSON, '"width":"260px"')
        && str_contains($defaultJSON, '"width":"300px"')
        && str_contains($defaultJSON, '"width":"320px"'),
    'The default IPSView style form must use caption-safe fixed control widths.'
);

assertSameValue(
    [3, 3, 3, 3],
    ipsViewStyleOpacityRowSizes($defaultForm),
    'The surface opacity controls must use three caption-safe controls per row.'
);

foreach ([
    'IPSViewStyleViewBackgroundOpacity',
    'IPSViewStyleControlInactiveBackgroundOpacity',
    'IPSViewStylePopupShadowOpacity'
] as $name) {
    $control = ipsViewStyleFindControl($defaultForm, $name);
    assertSameValue('260px', $control['width'] ?? null, sprintf('%s must keep a readable caption width.', $name));
}

foreach ([
    'IPSViewStyleBaseFontSize',
    'IPSViewStyleBorderRadius',
    'IPSViewStyleBorderWidth',
    'IPSViewStyleLineWidth',
    'IPSViewStyleShadowBlur',
    'IPSViewStyleShadowSpread',
    'IPSViewStyleShadowOffsetX',
    'IPSViewStyleShadowOffsetY',
    'IPSViewStyleDisabledOpacity',
    'IPSViewStyleGradientStrength'
] as $name) {
    $control = ipsViewStyleFindControl($defaultForm, $name);
    assertSameValue('220px', $control['width'] ?? null, sprintf('%s must keep a readable caption width.', $name));
}

$customForm = $layoutHarness->formItems('230px');
assertSameValue(
    [3, 3, 3, 3, 3, 3, 3],
    ipsViewStyleLayoutRowSizes($customForm, 'SelectColor'),
    'Explicit color widths must retain the three-column color layout.'
);
assertTrueValue(
    str_contains(json_encode($customForm, JSON_THROW_ON_ERROR), '230px'),
    'Explicit color widths must still be forwarded to color controls.'
);

fwrite(STDOUT, "IPSViewStyleHelper caption-safe layout tests passed.\n");
