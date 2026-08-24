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

$layoutHarness = new IPSViewStyleHelperHarness();
$responsiveForm = $layoutHarness->formItems('19%');
assertSameValue(
    [5, 5, 5, 5, 1],
    ipsViewStyleLayoutRowSizes($responsiveForm, 'SelectColor'),
    'The responsive IPSView style form must use five color controls per complete row.'
);

$responsiveJSON = json_encode($responsiveForm, JSON_THROW_ON_ERROR);
assertFalseValue(
    preg_match('/"width":"[0-9.]+px"/', $responsiveJSON) === 1,
    'The responsive IPSView style form must not use fixed pixel widths inside its generated rows.'
);
assertTrueValue(
    str_contains($responsiveJSON, '"width":"24%"')
        && str_contains($responsiveJSON, '"width":"34%"')
        && str_contains($responsiveJSON, '"width":"19%"'),
    'The responsive IPSView style form must distribute controls with percentage widths.'
);

$opacityRows = 0;
foreach ($responsiveForm as $item) {
    if (($item['type'] ?? '') !== 'RowLayout' || !is_array($item['items'] ?? null)) {
        continue;
    }

    $opacityControls = array_filter(
        $item['items'],
        static fn (array $child): bool => str_starts_with((string) ($child['name'] ?? ''), 'IPSViewStyle')
            && str_ends_with((string) ($child['name'] ?? ''), 'Opacity')
            && ($child['width'] ?? '') === '24%'
    );
    if (count($opacityControls) === 4) {
        ++$opacityRows;
    }
}
assertSameValue(3, $opacityRows, 'The surface opacity controls must use four controls per row.');

$legacyForm = $layoutHarness->formItems('230px');
assertSameValue(
    [3, 3, 3, 3, 3, 3, 3],
    ipsViewStyleLayoutRowSizes($legacyForm, 'SelectColor'),
    'Explicit legacy color widths must retain the previous three-column color layout.'
);
assertTrueValue(
    str_contains(json_encode($legacyForm, JSON_THROW_ON_ERROR), '230px'),
    'Explicit legacy color widths must still be forwarded to color controls.'
);

fwrite(STDOUT, "IPSViewStyleHelper responsive layout tests passed.\n");
