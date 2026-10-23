<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/StatusWidgetHelper.php';

final class StatusWidgetImageHarness
{
    use \Burki24\SymconModuleHelper\StatusWidgetHelper;

    public function image(int $id): string
    {
        return $this->StatusWidgetImage($id);
    }
}

$imageMedia = ['MediaType' => 1, 'MediaIsAvailable' => true, 'MediaSize' => 34];
$imageContent = 'R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=';
function IPS_MediaExists(int $id): bool
{
    return $id === 1;
}
function IPS_GetMedia(int $id): array
{
    return $GLOBALS['imageMedia'];
}
function IPS_GetMediaContent(int $id): string
{
    return $GLOBALS['imageContent'];
}
$widget = new StatusWidgetImageHarness();
assertSameValue('', $widget->image(0), 'No picture uses initials.');
assertSameValue('', $widget->image(2), 'Deleted media uses initials.');
assertSameValue('data:image/gif;base64,' . $imageContent, $widget->image(1), 'Supported image content is embedded.');
$imageMedia['MediaType'] = 5;
assertSameValue('', $widget->image(1), 'Document media cannot be embedded.');
$imageMedia['MediaType'] = 1;
$imageMedia['MediaSize'] = 131073;
assertSameValue('', $widget->image(1), 'Oversized media is rejected before content reads.');
$imageMedia['MediaSize'] = 100;
$imageContent = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
assertSameValue('', $widget->image(1), 'SVG cannot inject executable content.');
$imageContent = base64_encode('https://example.invalid/private-image');
assertSameValue('', $widget->image(1), 'Remote URLs are never loaded.');
$imageContent = 'not base64';
assertSameValue('', $widget->image(1), 'Malformed media fails safely.');
echo "Status widget image validation passed.\n";
