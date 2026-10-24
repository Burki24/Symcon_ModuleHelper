<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/RasterImageHelper.php';

$imageTool = new class() {
    use \Burki24\SymconModuleHelper\RasterImageHelper;

    public function prepare(string $content): array
    {
        return $this->PrepareRasterImage($content);
    }
};
$assertImage = static function (bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$gif = 'R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=';
$assertImage($imageTool->prepare($gif)['content'] === $gif, 'Small valid pictures remain unchanged.');
foreach (['invalid', base64_encode('<svg onload="alert(1)"/>'), base64_encode('https://example.invalid/image')] as $bad) {
    $assertImage($imageTool->prepare($bad)['error'] === 'format', 'No SVG, paths or URLs may reach the image decoder.');
}
$assertImage($imageTool->prepare(base64_encode(str_repeat('x', 10485761)))['error'] === 'input_size', 'Input bytes are bounded before decoding.');
$bomb = substr_replace(base64_decode($gif), pack('vv', 6000, 6000), 6, 4);
$assertImage($imageTool->prepare(base64_encode($bomb))['error'] === 'pixels', 'Pixel allocation is bounded before GD.');
if (!extension_loaded('gd')) {
    throw new RuntimeException('GD is required to verify real raster conversion.');
}
$source = imagecreatetruecolor(1600, 800);
imagealphablending($source, false);
imagesavealpha($source, true);
imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
imagefilledrectangle($source, 400, 200, 1200, 600, imagecolorallocatealpha($source, 220, 40, 50, 0));
ob_start();
imagepng($source);
$raw = ob_get_clean();
// Valid PNG with harmless trailing padding reproduces the reported file size.
$result = $imageTool->prepare(base64_encode($raw . str_repeat("\0", 203100)));
$assertImage($result['error'] === '' && $result['changed'], 'A real raster larger than 203 kB must be converted.');
$assertImage($result['width'] === 512 && $result['height'] === 256, 'Keep aspect ratio without cropping.');
$bytes = base64_decode($result['content']);
$assertImage(strlen($bytes) <= 131072 && getimagesizefromstring($bytes)[0] === 512, 'Return a real decodable picture below 128 KiB.');
$decoded = imagecreatefromstring($bytes);
$assertImage(imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha'] >= 120, 'Transparent corners survive conversion.');
$assertImage($imageTool->prepare($result['content'])['content'] === $result['content'], 'Repeated preparation must be stable.');
$noise = imagecreatetruecolor(512, 512);
for ($y = 0; $y < 512; $y++) {
    for ($x = 0; $x < 512; $x++) {
        // Stable high-frequency detail forces a real byte-budget reduction.
        imagesetpixel($noise, $x, $y, (($x * 7919 + $y * 104729) ^ ($x * $y * 31337)) & 0xFFFFFF);
    }
}
ob_start();
imagejpeg($noise, null, 100);
$jpeg = ob_get_clean();
$assertImage(strlen($jpeg) > 131072, 'The compression fixture must exceed the output budget.');
$compact = $imageTool->prepare(base64_encode($jpeg));
$assertImage($compact['error'] === '' && strlen(base64_decode($compact['content'])) <= 131072, 'Detailed JPEGs must meet the byte budget as well as dimensions.');
if (function_exists('exif_read_data')) {
    // Little-endian TIFF with one Orientation=6 entry, in a JPEG APP1 segment.
    $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVVV', 0x112, 3, 1, 6, 0);
    ob_start();
    imagejpeg($source);
    $plain = ob_get_clean();
    $turned = substr($plain, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($plain, 2);
    $upright = $imageTool->prepare(base64_encode($turned));
    $assertImage($upright['width'] === 256 && $upright['height'] === 512, 'Phone camera orientation is applied before scaling.');
}
$script = 'require ' . var_export(__DIR__ . '/../src/RasterImageHelper.php', true) . ';'
    . '$tool = new class {use \\Burki24\\SymconModuleHelper\\RasterImageHelper; public function run($s) {return $this->PrepareRasterImage($s);}};'
    . 'echo $tool->run(' . var_export(base64_encode($raw), true) . ')["error"];';
$pipes = [];
$process = proc_open([PHP_BINARY, '-n', '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$withoutGD = stream_get_contents($pipes[1]);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
$assertImage(proc_close($process) === 0 && $withoutGD === 'unavailable', 'A server without GD reports an explicit capability error.');
fwrite(STDOUT, "Raster image sizing, compression, alpha, bounds and idempotency passed.\n");
