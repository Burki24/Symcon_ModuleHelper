<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\HelperTranslationHelper;

require_once __DIR__ . '/../src/HelperTranslationHelper.php';

final class HelperTranslationHelperHarness
{
    use HelperTranslationHelper;

    public function translate(string $key, string $fallback, ?string $language = null): string
    {
        return $this->TranslateHelperText('IPSViewStyleHelper', $key, $fallback, $language);
    }

    public function normalize(string $language): string
    {
        return $this->NormalizeHelperTranslationLanguage($language);
    }
}

$harness = new HelperTranslationHelperHarness();

assertSameValue(
    'Flächentransparenz',
    $harness->translate('section.surface_transparency', 'Surface transparency', 'de_DE.UTF-8'),
    'Helper catalogs must provide their German translation without consumer locale entries.'
);
assertSameValue(
    'Surface transparency',
    $harness->translate('section.surface_transparency', 'Surface transparency', 'en_US'),
    'English helper text must remain the catalog fallback.'
);
assertSameValue(
    'Surface transparency',
    $harness->translate('section.surface_transparency', 'Surface transparency', 'fr_FR'),
    'Unsupported languages must fall back to English.'
);
assertSameValue(
    'Fallback text',
    $harness->translate('missing.translation.key', 'Fallback text', 'de'),
    'Missing catalog entries must never break a consumer form.'
);
assertSameValue('de', $harness->normalize('de_DE.UTF-8'), 'Locale variants must normalize to their language code.');
assertSameValue('en', $harness->normalize(''), 'An empty language must normalize to English.');
