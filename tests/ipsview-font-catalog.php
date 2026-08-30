<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewFontCatalogHelper;

require_once __DIR__ . '/../src/IPSViewFontCatalogHelper.php';

$expectedFamilies = [
    IPSViewFontCatalogHelper::FONT_ROBOTO,
    IPSViewFontCatalogHelper::FONT_ROBOTO_MONO,
    IPSViewFontCatalogHelper::FONT_DANCING_SCRIPT,
    IPSViewFontCatalogHelper::FONT_INDIE_FLOWER,
    IPSViewFontCatalogHelper::FONT_OPEN_SANS,
    IPSViewFontCatalogHelper::FONT_PT_SANS,
    IPSViewFontCatalogHelper::FONT_BEBAS_NEUE,
    IPSViewFontCatalogHelper::FONT_SEGMENT_7
];

assertSameValue($expectedFamilies, IPSViewFontCatalogHelper::families(), 'The shared catalogue must expose all verified IPSView fonts.');
assertSameValue(8, count(IPSViewFontCatalogHelper::catalog()), 'The shared catalogue must contain exactly eight verified IPSView fonts.');
assertSameValue(8, count(IPSViewFontCatalogHelper::options()), 'Every verified IPSView font must have one Select option.');

assertSameValue('Roboto Mono', IPSViewFontCatalogHelper::label('RobotoMono'), 'Canonical family names must expose readable labels.');
assertSameValue('Open Sans', IPSViewFontCatalogHelper::label('open sans'), 'Readable family aliases must normalize to canonical IPSView names.');
assertSameValue('PTSans', IPSViewFontCatalogHelper::normalizeFamily('PT Sans'), 'Spaced family aliases must normalize to document values.');
assertSameValue('DancingScript', IPSViewFontCatalogHelper::normalizeFamily('dancingscript'), 'Family normalization must be case-insensitive.');
assertSameValue(null, IPSViewFontCatalogHelper::normalizeFamily('Unknown Font'), 'Unknown families must not be silently converted.');
assertSameValue('Roboto', IPSViewFontCatalogHelper::normalizeFamily('Unknown Font', 'Roboto'), 'Known fallbacks must be returned for unknown families.');
assertSameValue(null, IPSViewFontCatalogHelper::normalizeFamily('Unknown Font', 'Unknown Fallback'), 'Unknown fallbacks must remain invalid.');

assertTrueValue(IPSViewFontCatalogHelper::isValidFamily('Bebas Neue'), 'Known display names must be accepted as font aliases.');
assertFalseValue(IPSViewFontCatalogHelper::isValidFamily('Arial'), 'Fonts outside the verified IPSView catalogue must not be reported as native fonts.');

assertSameValue(
    ['bold' => true, 'italic' => true, 'boldItalic' => true],
    IPSViewFontCatalogHelper::capabilities('Roboto'),
    'Roboto must expose all verified cuts.'
);
assertSameValue(
    ['bold' => true, 'italic' => false, 'boldItalic' => false],
    IPSViewFontCatalogHelper::capabilities('Dancing Script'),
    'Dancing Script must expose only Regular and Bold.'
);
assertSameValue(
    ['bold' => false, 'italic' => false, 'boldItalic' => false],
    IPSViewFontCatalogHelper::capabilities('Segment7'),
    'Segment7 must expose only the Regular cut.'
);
assertSameValue(null, IPSViewFontCatalogHelper::capabilities('Arial'), 'Unknown/custom fonts must leave capability handling to the consumer.');

assertSameValue(
    [
        IPSViewFontCatalogHelper::STYLE_REGULAR,
        IPSViewFontCatalogHelper::STYLE_BOLD,
        IPSViewFontCatalogHelper::STYLE_ITALIC,
        IPSViewFontCatalogHelper::STYLE_BOLD_ITALIC
    ],
    IPSViewFontCatalogHelper::styles('Open Sans'),
    'Open Sans must expose all four verified cuts.'
);
assertSameValue(
    [IPSViewFontCatalogHelper::STYLE_REGULAR, IPSViewFontCatalogHelper::STYLE_BOLD],
    IPSViewFontCatalogHelper::styles('Dancing Script'),
    'Dancing Script must expose only Regular and Bold.'
);
assertSameValue(
    [IPSViewFontCatalogHelper::STYLE_REGULAR],
    IPSViewFontCatalogHelper::styles('BebasNeue'),
    'Bebas Neue must expose only Regular.'
);
assertSameValue([], IPSViewFontCatalogHelper::styles('Arial'), 'Unknown families must not invent font cuts.');

assertTrueValue(IPSViewFontCatalogHelper::isValidStyle('Roboto', 'Bold Italic'), 'Readable Bold Italic aliases must validate for capable fonts.');
assertTrueValue(IPSViewFontCatalogHelper::isValidStyle('PT Sans', 'normal'), 'Normal must be accepted as a Regular alias.');
assertFalseValue(IPSViewFontCatalogHelper::isValidStyle('DancingScript', 'italic'), 'Unavailable cuts must be rejected.');
assertFalseValue(IPSViewFontCatalogHelper::isValidStyle('Segment7', 'bold'), 'Segment7 must reject unsupported Bold formatting.');
assertFalseValue(IPSViewFontCatalogHelper::isValidStyle('Arial', 'regular'), 'Unknown families must not validate native cuts.');

assertSameValue(
    IPSViewFontCatalogHelper::STYLE_BOLD_ITALIC,
    IPSViewFontCatalogHelper::normalizeStyle('Roboto Mono', 'bold italic'),
    'Known cut aliases must normalize to the central style identifier.'
);
assertSameValue(
    IPSViewFontCatalogHelper::STYLE_REGULAR,
    IPSViewFontCatalogHelper::normalizeStyle('Segment7', 'italic'),
    'Unsupported cuts must fall back to Regular by default.'
);
assertSameValue(
    null,
    IPSViewFontCatalogHelper::normalizeStyle('Segment7', 'italic', null),
    'Consumers must be able to reject unsupported cuts without a fallback.'
);

fwrite(STDOUT, "IPSView font catalogue tests passed.\n");
