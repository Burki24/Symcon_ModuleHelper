<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SVGPreviewHelper.php';

use Burki24\SymconModuleHelper\SVGPreviewHelper;

$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text>München &amp; 東京</text></svg>';
$dataUri = SVGPreviewHelper::dataUri($svg);
assertTrueValue(
    str_starts_with($dataUri, 'data:image/svg+xml;base64,'),
    'SVG previews must use the expected Base64 data-URI prefix.'
);
assertSameValue(
    $svg,
    base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true),
    'SVG preview data URIs must preserve the original UTF-8 markup.'
);

$xmlSvg = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>";
assertTrueValue(
    str_starts_with(SVGPreviewHelper::dataUri($xmlSvg), 'data:image/svg+xml;base64,'),
    'SVG previews may contain a standard XML declaration.'
);

assertSameValue(
    'Wind &amp; &lt;weather&gt; &quot;outside&quot; &apos;now&apos;',
    SVGPreviewHelper::escape('Wind & <weather> "outside" \'now\''),
    'SVG preview text and attribute values must be XML-escaped.'
);

foreach (['', 'not svg', '<html></html>'] as $invalidSvg) {
    try {
        SVGPreviewHelper::dataUri($invalidSvg);
        throw new RuntimeException('Invalid SVG preview markup must be rejected.');
    } catch (InvalidArgumentException $exception) {
        assertTrueValue(
            str_contains($exception->getMessage(), 'SVG'),
            'Invalid preview markup errors must identify the SVG contract.'
        );
    }
}

$form = [
    'elements' => [
        [
            'type'  => 'ExpansionPanel',
            'name'  => 'PreviewPanel',
            'items' => [
                [
                    'type'  => 'Image',
                    'name'  => 'PreviewImage',
                    'image' => ''
                ]
            ]
        ]
    ],
    'actions' => [],
    'status'  => []
];
$updatedForm = SVGPreviewHelper::withImage($form, 'PreviewImage', $svg);
assertSameValue('', $form['elements'][0]['items'][0]['image'], 'SVG form injection must not mutate the input form.');
assertSameValue(
    $dataUri,
    $updatedForm['elements'][0]['items'][0]['image'],
    'SVG form injection must update the named Image element at any nesting depth.'
);

foreach (
    [
        'MissingImage' => $form,
        'PreviewImage' => [
            'elements' => [
                ['type' => 'Image', 'name' => 'PreviewImage', 'image' => ''],
                ['type' => 'Image', 'name' => 'PreviewImage', 'image' => '']
            ]
        ]
    ] as $fieldName => $invalidForm
) {
    try {
        SVGPreviewHelper::withImage($invalidForm, $fieldName, $svg);
        throw new RuntimeException('Missing or duplicate SVG preview fields must be rejected.');
    } catch (UnexpectedValueException $exception) {
        assertTrueValue(
            str_contains($exception->getMessage(), $fieldName),
            'SVG preview form errors must identify the affected field.'
        );
    }
}

try {
    SVGPreviewHelper::withImage($form, '', $svg);
    throw new RuntimeException('An empty SVG preview field name must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(
        str_contains($exception->getMessage(), 'field name'),
        'An empty preview field error must identify the field-name contract.'
    );
}

fwrite(STDOUT, "SVGPreviewHelper tests passed.\n");
