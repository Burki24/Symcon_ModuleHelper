<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;

require_once __DIR__ . '/bootstrap.php';

if (!defined('VARIABLETYPE_STRING')) {
    define('VARIABLETYPE_STRING', 3);
}
if (!defined('VARIABLE_PRESENTATION_WEB_CONTENT')) {
    define('VARIABLE_PRESENTATION_WEB_CONTENT', '{9DE1D610-5106-97FB-714D-1AADEDF8377A}');
}

require_once __DIR__ . '/../src/IPSViewHTMLPageHelper.php';

final class IPSViewHTMLPageHelperHarness
{
    use IPSViewHTMLPageHelper;

    /** @var list<array{message:string,data:mixed,format:int}> */
    private array $debugMessages = [];

    /** @var array<string,bool|string> */
    private array $properties = [];

    /** @var array<string,string> */
    private array $attributes = [];

    /** @var array<string,array{caption:string,type:int,presentation:array<string,mixed>,position:int}> */
    private array $variables = [];

    /** @var array<string,string> */
    private array $values = [];

    /** @var list<array{ident:string,caption:string,type:int,presentation:array<string,mixed>,position:int,maintain:bool}> */
    private array $maintainCalls = [];

    private string $helperLanguage = 'en';

    public function registerPageProperties(): void
    {
        $this->RegisterIPSViewHTMLPageProperties();
    }

    /** @return array<int,array<string,mixed>> */
    public function pageFormItems(string $description = ''): array
    {
        return $this->IPSViewHTMLPageFormItems($description);
    }

    /** @param array<int,array<string,mixed>> $elements */
    public function insertPageFormItems(
        array &$elements,
        string $markerCaption = 'Configure optional IPSView HTML output.',
        string $description = ''
    ): bool {
        return $this->InsertIPSViewHTMLPageFormItems($elements, $markerCaption, $description);
    }

    public function enabled(): bool
    {
        return $this->IsIPSViewHTMLPageEnabled();
    }

    public function maintainPageVariable(
        string $ident,
        string $caption,
        int $position,
        string $initialHtml = '',
        ?bool $padding = null
    ): bool {
        return $this->MaintainIPSViewHTMLVariable($ident, $caption, $position, $initialHtml, $padding);
    }

    public function updatePageVariable(string $ident, string $html): bool
    {
        return $this->UpdateIPSViewHTMLVariable($ident, $html);
    }

    public function setProperty(string $name, bool|string $value): void
    {
        $this->properties[$name] = $value;
    }

    public function setHelperLanguage(string $language): void
    {
        $this->helperLanguage = $language;
    }

    /** @return array<string,bool|string> */
    public function properties(): array
    {
        return $this->properties;
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /** @return array<string,array{caption:string,type:int,presentation:array<string,mixed>,position:int}> */
    public function variables(): array
    {
        return $this->variables;
    }

    /** @return array<string,string> */
    public function values(): array
    {
        return $this->values;
    }

    /** @return list<array{ident:string,caption:string,type:int,presentation:array<string,mixed>,position:int,maintain:bool}> */
    public function maintainCalls(): array
    {
        return $this->maintainCalls;
    }

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

    protected function RegisterPropertyBoolean(string $name, bool $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function RegisterPropertyString(string $name, string $default): void
    {
        $this->properties[$name] = $default;
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return (bool) ($this->properties[$name] ?? false);
    }

    protected function ReadPropertyString(string $name): string
    {
        return (string) ($this->properties[$name] ?? '');
    }

    protected function RegisterAttributeString(string $name, string $default): void
    {
        $this->attributes[$name] = $default;
    }

    protected function ReadAttributeString(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @param array<string,mixed> $presentation */
    protected function MaintainVariable(
        string $ident,
        string $caption,
        int $type,
        array $presentation,
        int $position,
        bool $maintain
    ): bool {
        $this->maintainCalls[] = [
            'ident'        => $ident,
            'caption'      => $caption,
            'type'         => $type,
            'presentation' => $presentation,
            'position'     => $position,
            'maintain'     => $maintain
        ];

        if (!$maintain) {
            unset($this->variables[$ident], $this->values[$ident]);

            return false;
        }

        $created = !array_key_exists($ident, $this->variables);
        $this->variables[$ident] = [
            'caption'      => $caption,
            'type'         => $type,
            'presentation' => $presentation,
            'position'     => $position
        ];

        return $created;
    }

    protected function VariableExists(string $ident): bool
    {
        return array_key_exists($ident, $this->variables);
    }

    protected function UnregisterVariable(string $ident): void
    {
        unset($this->variables[$ident], $this->values[$ident]);
    }

    protected function SetValue(string $ident, mixed $value): void
    {
        if (!array_key_exists($ident, $this->variables)) {
            throw new RuntimeException('Unknown variable: ' . $ident);
        }
        if (!is_string($value)) {
            throw new RuntimeException('The test harness expects String values.');
        }

        $this->values[$ident] = $value;
    }

    protected function HelperTranslationLanguageOverride(): string
    {
        return $this->helperLanguage;
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

final class IPSViewHTMLPageMissingVariableFallbackHarness
{
    use IPSViewHTMLPageHelper;

    /** @return array<int,array<string,mixed>> */
    public function pageFormItems(): array
    {
        return $this->IPSViewHTMLPageFormItems();
    }

    protected function ReadPropertyBoolean(string $name): bool
    {
        return false;
    }

    protected function ReadAttributeString(string $name): string
    {
        return $name === 'IPSViewHTMLVariableRegistry'
            ? '{"IPSViewAlarm":"IPSView alarm"}'
            : '{}';
    }

    protected function GetIDForIdent(string $ident): int
    {
        trigger_error('Object with ident ' . $ident . ' was not found.', E_USER_WARNING);

        return 0;
    }

    protected function HelperTranslationLanguageOverride(): string
    {
        return 'en';
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
    $fallbackHelper = new IPSViewHTMLPageMissingVariableFallbackHarness();
    $unsuppressedMissingVariableWarnings = 0;
    set_error_handler(
        static function (int $severity) use (&$unsuppressedMissingVariableWarnings): bool
        {
            if ((error_reporting() & $severity) !== 0) {
                $unsuppressedMissingVariableWarnings++;
            }

            return true;
        }
    );
    try {
        $fallbackFormItems = $fallbackHelper->pageFormItems();
    } finally {
        restore_error_handler();
    }
    assertSameValue(
        0,
        $unsuppressedMissingVariableWarnings,
        'A missing optional IPSView variable must not emit a warning while loading the configuration form.'
    );
    assertSameValue(
        2,
        count($fallbackFormItems),
        'A missing optional IPSView variable must not create retained-variable deletion controls.'
    );

    $helper = new IPSViewHTMLPageHelperHarness();

    $helper->registerPageProperties();
    assertSameValue(
        [
            'EnableIPSView'            => false,
            'IPSViewHTMLDeleteRequest' => ''
        ],
        $helper->properties(),
        'The optional IPSView output properties must use safe defaults.'
    );
    assertSameValue(
        [
            'IPSViewHTMLVariableRegistry' => '[]',
            'IPSViewHTMLDeleteState'      => '{}'
        ],
        $helper->attributes(),
        'The optional IPSView output attributes must use empty defaults.'
    );
    assertFalseValue($helper->enabled(), 'IPSView output must be disabled by default.');

    $englishFormItems = $helper->pageFormItems();
    assertSameValue('CheckBox', $englishFormItems[0]['type'], 'The first form item must be a checkbox.');
    assertSameValue('EnableIPSView', $englishFormItems[0]['name'], 'The checkbox must use the shared property.');
    assertSameValue(
        'Provide IPSView HTML output',
        $englishFormItems[0]['caption'],
        'The English helper-owned checkbox caption must be available.'
    );
    assertTrueValue(
        str_contains((string) $englishFormItems[1]['caption'], 'additional String variables'),
        'The generic hint must explain that additional String variables are created.'
    );
    assertTrueValue(
        str_contains((string) $englishFormItems[1]['caption'], 'explicitly deletes them'),
        'The generic hint must explain that disabling does not delete variables automatically.'
    );
    assertSameValue(2, count($englishFormItems), 'No deletion controls may be shown without retained variables.');

    $helper->setHelperLanguage('de_DE.UTF-8');
    $germanFormItems = $helper->pageFormItems();
    assertSameValue(
        'IPSView-HTML-Ausgabe bereitstellen',
        $germanFormItems[0]['caption'],
        'The helper must provide its German checkbox caption without consumer locale entries.'
    );
    assertTrueValue(
        str_contains((string) $germanFormItems[1]['caption'], 'String-Variablen'),
        'The German helper hint must describe the optional variables.'
    );
    assertTrueValue(
        str_contains((string) $germanFormItems[1]['caption'], 'ausdrücklich löscht'),
        'The German helper hint must describe explicit deletion.'
    );
    assertSameValue(
        'Modulspezifischer Hinweis',
        $helper->pageFormItems(' Modulspezifischer Hinweis ')[1]['caption'],
        'Consumers must be able to replace the generic description.'
    );

    $form = [
        [
            'type'    => 'ExpansionPanel',
            'caption' => 'IPSView',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'Configure optional IPSView HTML output.'
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'After marker'
                ]
            ]
        ]
    ];
    assertTrueValue($helper->insertPageFormItems($form), 'Nested IPSView form markers must be replaced.');
    assertSameValue('CheckBox', $form[0]['items'][0]['type'], 'The marker must be replaced by the checkbox.');
    assertSameValue('Label', $form[0]['items'][1]['type'], 'The helper hint must follow the checkbox.');
    assertSameValue('After marker', $form[0]['items'][2]['caption'], 'Following form items must be retained.');

    assertFalseValue(
        $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100, '<p>Initial</p>'),
        'Disabled output must not create the optional variable.'
    );
    assertSameValue([], $helper->variables(), 'No IPSView variable may exist while the switch is disabled.');
    assertSameValue(
        '{"IPSViewExample":"IPSView example"}',
        $helper->attributes()['IPSViewHTMLVariableRegistry'],
        'Known optional variables must be registered for later deletion controls.'
    );

    $helper->setProperty('EnableIPSView', true);
    assertTrueValue($helper->enabled(), 'The common switch must be readable through the helper.');
    assertTrueValue(
        $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100, '<p>Initial</p>'),
        'Enabled output must create the optional variable.'
    );
    assertSameValue(
        [
            'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
            'HTML_TYPE'    => 0
        ],
        $helper->variables()['IPSViewExample']['presentation'],
        'The optional variable must use the WebContent presentation in HTML mode.'
    );
    assertSameValue(
        VARIABLETYPE_STRING,
        $helper->variables()['IPSViewExample']['type'],
        'The optional IPSView output must be stored in a String variable.'
    );
    assertSameValue(
        '<p>Initial</p>',
        $helper->values()['IPSViewExample'],
        'New optional variables must receive their initial HTML.'
    );
    assertFalseValue(
        $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100, '<p>Overwrite</p>'),
        'Maintaining an existing optional variable must not report a new creation.'
    );
    assertSameValue(
        '<p>Initial</p>',
        $helper->values()['IPSViewExample'],
        'Existing values must not be overwritten by the initial HTML.'
    );
    assertTrueValue(
        $helper->updatePageVariable('IPSViewExample', '<p>Updated</p>'),
        'Enabled existing IPSView variables must be updateable centrally.'
    );
    assertSameValue('<p>Updated</p>', $helper->values()['IPSViewExample'], 'The updated HTML must be stored.');
    assertFalseValue(
        $helper->updatePageVariable('MissingIPSViewVariable', '<p>Missing</p>'),
        'Missing IPSView variables must be ignored.'
    );

    assertTrueValue(
        $helper->maintainPageVariable('IPSViewPadded', 'IPSView padded', 110, '', false),
        'A second IPSView variable must be maintainable.'
    );
    assertSameValue(
        false,
        $helper->variables()['IPSViewPadded']['presentation']['PADDING'],
        'Consumers must be able to set the WebContent padding explicitly.'
    );

    $helper->setProperty('EnableIPSView', false);
    assertFalseValue(
        $helper->updatePageVariable('IPSViewExample', '<p>Disabled</p>'),
        'Disabled output must not update optional variables.'
    );
    $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100);
    $helper->maintainPageVariable('IPSViewPadded', 'IPSView padded', 110);
    assertSameValue(
        ['IPSViewExample', 'IPSViewPadded'],
        array_keys($helper->variables()),
        'Disabling output must retain existing optional variables.'
    );
    assertSameValue(
        '<p>Updated</p>',
        $helper->values()['IPSViewExample'],
        'Retained variables must keep their last HTML value.'
    );
    foreach ($helper->maintainCalls() as $maintainCall) {
        assertTrueValue(
            $maintainCall['maintain'],
            'The helper must never pass false to MaintainVariable() when IPSView is disabled.'
        );
    }

    $retainedFormItems = $helper->pageFormItems();
    assertSameValue(4, count($retainedFormItems), 'Disabled retained variables must add a warning and delete action.');
    assertSameValue('PopupButton', $retainedFormItems[3]['type'], 'Deletion must use a confirmation popup.');
    assertSameValue(
        'IPSView-Variablen löschen...',
        $retainedFormItems[3]['caption'],
        'The German delete action must be helper-owned.'
    );
    assertSameValue(
        'Variablen behalten',
        $retainedFormItems[3]['popup']['closeCaption'],
        'Closing the confirmation must explicitly keep the variables.'
    );
    assertSameValue(
        3,
        count($retainedFormItems[3]['popup']['items']),
        'The confirmation popup must list every retained variable.'
    );
    $deleteScript = implode("\n", $retainedFormItems[3]['popup']['buttons'][0]['onClick']);
    assertTrueValue(
        str_contains($deleteScript, "IPS_SetProperty(\$id, 'IPSViewHTMLDeleteRequest'"),
        'The confirmation action must create a new one-shot deletion request.'
    );
    assertTrueValue(
        str_contains($deleteScript, 'IPS_ApplyChanges($id);'),
        'The confirmation action must apply the deletion request immediately.'
    );

    $helper->setProperty('IPSViewHTMLDeleteRequest', 'request-1');
    $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100);
    $helper->maintainPageVariable('IPSViewPadded', 'IPSView padded', 110);
    assertSameValue([], $helper->variables(), 'Confirmed deletion must remove every optional IPSView variable.');
    assertSameValue(
        '{"IPSViewExample":"request-1","IPSViewPadded":"request-1"}',
        $helper->attributes()['IPSViewHTMLDeleteState'],
        'Each variable must remember the deletion request it already processed.'
    );
    assertSameValue(2, count($helper->pageFormItems()), 'Deletion controls must disappear after removal.');

    $helper->setProperty('EnableIPSView', true);
    assertTrueValue(
        $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100, '<p>Recreated</p>'),
        'Re-enabling IPSView must recreate a previously deleted variable.'
    );
    $helper->setProperty('EnableIPSView', false);
    $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100);
    assertTrueValue(
        array_key_exists('IPSViewExample', $helper->variables()),
        'A handled deletion request must never delete a recreated variable without new confirmation.'
    );

    $helper->setProperty('IPSViewHTMLDeleteRequest', 'request-2');
    $helper->maintainPageVariable('IPSViewExample', 'IPSView example', 100);
    assertSameValue([], $helper->variables(), 'A new explicit request must delete recreated variables.');

    $invalidVariableIdentRejected = false;
    try {
        $helper->maintainPageVariable('Invalid ident', 'Invalid', 1);
    } catch (InvalidArgumentException) {
        $invalidVariableIdentRejected = true;
    }
    assertTrueValue($invalidVariableIdentRejected, 'Invalid IPSView variable idents must be rejected.');

    $emptyMarkerRejected = false;
    try {
        $helper->insertPageFormItems($form, '');
    } catch (InvalidArgumentException) {
        $emptyMarkerRejected = true;
    }
    assertTrueValue($emptyMarkerRejected, 'Empty IPSView form markers must be rejected.');

    $helper->setHelperLanguage('en');

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
