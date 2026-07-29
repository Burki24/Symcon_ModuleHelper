<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/IPSViewHTMLPageHelper.php';

final class IPSViewHTMLPageHelperHarness
{
    use IPSViewHTMLPageHelper;

    /** @var list<array{message:string,data:mixed,format:int}> */
    private array $debugMessages = [];

    /** @param array<string,mixed> $configuration */
    public function render(bool $ipsView, array $configuration = []): string
    {
        return $this->RenderVisualizationHTMLPage($ipsView, $configuration);
    }

    public function encode(mixed $payload): string
    {
        return $this->EncodeVisualizationHTMLJSON($payload);
    }

    /** @param list<string> $additionalKeys */
    public function translationsFromLocale(array $additionalKeys = []): array
    {
        return $this->IPSViewTranslationsFromLocale($additionalKeys);
    }

    /** @param list<string> $keys */
    public function translationsFor(array $keys): array
    {
        return $this->IPSViewTranslationsFor($keys);
    }

    /** @return list<array{message:string,data:mixed,format:int}> */
    public function debugMessages(): array
    {
        return $this->debugMessages;
    }

    protected function VisualizationAsset(string $filename): string
    {
        $path = __DIR__ . '/visualization/' . $filename;
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->SendDebug(__FUNCTION__, 'Visualization asset could not be loaded: ' . $path, 0);

            return '';
        }

        return $content;
    }

    protected function Translate(string $text): string
    {
        return 'translated:' . $text;
    }

    protected function SendDebug(string $message, mixed $data, int $format): void
    {
        $this->debugMessages[] = [
            'message' => $message,
            'data'    => $data,
            'format'  => $format
        ];
    }
}

$visualizationDirectory = __DIR__ . '/visualization';
$templatePath = $visualizationDirectory . '/index.html';
$stylePath = $visualizationDirectory . '/style.css';
$scriptPath = $visualizationDirectory . '/app.js';
$localePath = __DIR__ . '/locale.json';

$template = <<<'HTML'
<!DOCTYPE html>
<html lang="{{HTML_LANGUAGE}}" class="{{HTML_CLASSES}}" style="font-size: {{ROOT_FONT_SIZE}};">
<head>
    <meta name="viewport" content="{{VIEWPORT_CONTENT}}">
    <title>{{DOCUMENT_TITLE}}</title>
    <style>{{VISUALIZATION_THEME}}
{{MODULE_STYLE}}
{{IPSVIEW_STYLE}}</style>
</head>
<body>
    <main>{{MODULE_CONTENT}}</main>
    <script>window.SYMC_VISUALIZATION = {{BOOTSTRAP_JSON}};</script>
    <script>{{MODULE_SCRIPT}}</script>
</body>
</html>
HTML;

if (!is_dir($visualizationDirectory) && !mkdir($visualizationDirectory) && !is_dir($visualizationDirectory)) {
    throw new RuntimeException('Visualization test directory could not be created.');
}

file_put_contents($templatePath, $template);
file_put_contents($stylePath, '.module { display: block; }');
file_put_contents($scriptPath, 'window.moduleStarted = true;');
file_put_contents(
    $localePath,
    json_encode(
        [
            'translations' => [
                'de' => [
                    'Hello' => 'Hallo',
                    'Close' => 'Schließen'
                ],
                'en' => [
                    'Module title' => 'Module title'
                ]
            ]
        ],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);

try {
    $helper = new IPSViewHTMLPageHelperHarness();

    $nativeHtml = $helper->render(false, [
        'language'           => 'de-DE',
        'classes'            => ['module-page', 'module-page'],
        'rootFontSize'       => '18px',
        'title'              => 'Module & Status',
        'visualizationTheme' => ':root { --native: 1; }',
        'ipsViewStyle'       => ':root { --ipsview: 1; }',
        'state'              => ['label' => '</script><strong>Alarm & Test</strong>', 'value' => 1.0],
        'runtime'            => null,
        'translations'       => ['Close' => 'Schließen'],
        'options'            => ['compact' => false],
        'replacements'       => ['{{MODULE_CONTENT}}' => '<section id="module"></section>']
    ]);

    assertTrueValue(str_contains($nativeHtml, 'lang="de-DE"'), 'The page language must be rendered.');
    assertTrueValue(str_contains($nativeHtml, 'class="module-page"'), 'HTML classes must be normalized.');
    assertTrueValue(str_contains($nativeHtml, 'font-size: 18px'), 'The root font size must be rendered.');
    assertTrueValue(str_contains($nativeHtml, 'Module &amp; Status'), 'The document title must be HTML escaped.');
    assertTrueValue(str_contains($nativeHtml, '--native: 1'), 'The native theme must be rendered in native mode.');
    assertFalseValue(str_contains($nativeHtml, '--ipsview: 1'), 'The IPSView style must be omitted in native mode.');
    assertTrueValue(str_contains($nativeHtml, '.module { display: block; }'), 'The module stylesheet must be embedded.');
    assertTrueValue(str_contains($nativeHtml, 'window.moduleStarted = true;'), 'The module script must be embedded.');
    assertTrueValue(str_contains($nativeHtml, '<section id="module"></section>'), 'Custom placeholders must be rendered.');
    assertTrueValue(str_contains($nativeHtml, '"contractVersion":1'), 'The bootstrap contract version must be present.');
    assertTrueValue(str_contains($nativeHtml, '"mode":"symcon"'), 'Native mode must be encoded in the bootstrap.');
    assertTrueValue(
        str_contains($nativeHtml, '\u003C/script\u003E\u003Cstrong\u003EAlarm \u0026 Test\u003C/strong\u003E'),
        'Bootstrap JSON must be safe for script embedding.'
    );
    assertTrueValue(str_contains($nativeHtml, '"value":1.0'), 'Float fractions must be preserved.');

    $ipsViewHtml = $helper->render(true, [
        'ipsViewStyle' => ':root { --ipsview: 1; }',
        'state'        => [],
        'replacements' => ['{{MODULE_CONTENT}}' => 'IPSView']
    ]);
    assertTrueValue(str_contains($ipsViewHtml, 'class="ipsview-mode"'), 'IPSView mode must add its default class.');
    assertTrueValue(str_contains($ipsViewHtml, 'maximum-scale=1'), 'IPSView mode must use the compact viewport.');
    assertTrueValue(str_contains($ipsViewHtml, '--ipsview: 1'), 'The IPSView style must be rendered in IPSView mode.');
    assertTrueValue(str_contains($ipsViewHtml, '"mode":"ipsview"'), 'IPSView mode must be encoded in the bootstrap.');

    assertSameValue(
        '{"html":"\u003C/script\u003E\u0026","fraction":2.0}',
        $helper->encode(['html' => '</script>&', 'fraction' => 2.0]),
        'The shared JSON encoder must apply the common safe encoding flags.'
    );

    assertSameValue(
        [
            'Close'        => 'translated:Close',
            'Hello'        => 'translated:Hello',
            'Module title' => 'translated:Module title',
            'Runtime only' => 'translated:Runtime only'
        ],
        $helper->translationsFromLocale(['Runtime only']),
        'Locale translations and additional runtime keys must use the active translator.'
    );
    assertSameValue(
        [
            'Close' => 'translated:Close',
            'Hello' => 'translated:Hello'
        ],
        $helper->translationsFor(['Hello', 'Close']),
        'Explicit translation keys must be sorted and translated consistently.'
    );

    $invalidConfigurationRejected = false;
    try {
        $helper->render(true, ['unknown' => true]);
    } catch (InvalidArgumentException) {
        $invalidConfigurationRejected = true;
    }
    assertTrueValue($invalidConfigurationRejected, 'Unknown page configuration keys must be rejected.');

    $reservedReplacementRejected = false;
    try {
        $helper->render(true, [
            'replacements' => [
                '{{MODULE_CONTENT}}' => 'Content',
                '{{MODULE_STYLE}}'   => 'Override'
            ]
        ]);
    } catch (InvalidArgumentException) {
        $reservedReplacementRejected = true;
    }
    assertTrueValue($reservedReplacementRejected, 'Core placeholders must not be overridden.');

    $invalidClassRejected = false;
    try {
        $helper->render(true, [
            'classes'      => ['valid', 'invalid class'],
            'replacements' => ['{{MODULE_CONTENT}}' => 'Content']
        ]);
    } catch (InvalidArgumentException) {
        $invalidClassRejected = true;
    }
    assertTrueValue($invalidClassRejected, 'Invalid HTML class names must be rejected.');

    file_put_contents($templatePath, str_replace('{{MODULE_SCRIPT}}', '', $template));
    $missingPlaceholderRejected = false;
    try {
        $helper->render(true, ['replacements' => ['{{MODULE_CONTENT}}' => 'Content']]);
    } catch (RuntimeException) {
        $missingPlaceholderRejected = true;
    }
    assertTrueValue($missingPlaceholderRejected, 'Missing core placeholders must be rejected.');

    file_put_contents($templatePath, $template . '{{UNRESOLVED_MODULE_VALUE}}');
    $unresolvedPlaceholderRejected = false;
    try {
        $helper->render(true, ['replacements' => ['{{MODULE_CONTENT}}' => 'Content']]);
    } catch (RuntimeException) {
        $unresolvedPlaceholderRejected = true;
    }
    assertTrueValue($unresolvedPlaceholderRejected, 'Unresolved module placeholders must be rejected.');

    file_put_contents($templatePath, $template);
    unlink($scriptPath);
    assertSameValue(
        '',
        $helper->render(true, ['replacements' => ['{{MODULE_CONTENT}}' => 'Content']]),
        'A missing required visualization asset must return an empty string.'
    );
    assertTrueValue(count($helper->debugMessages()) >= 1, 'Missing assets must be reported through SendDebug().');
} finally {
    foreach ([$templatePath, $stylePath, $scriptPath, $localePath] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($visualizationDirectory)) {
        rmdir($visualizationDirectory);
    }
}

fwrite(STDOUT, "IPSViewHTMLPageHelper tests passed.\n");
