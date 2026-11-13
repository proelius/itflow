<?php

// Installable app (Progressive Web App) - the home screen icon, made from the company logo
// See manifest.php, app_icon.php, includes/pwa_head.php and sw.js

/** The sizes app_icon.php draws: Android (192, 512) and the iPhone home screen (180). */
const PWA_ICON_SIZES = [180, 192, 512];

/**
 * The company logo (Admin > Company) on disk, or null when there is none - then the
 * app keeps the ITFlow icons in img/pwa. Read once per request.
 */
function pwaCompanyLogoPath(): ?string
{
    static $path = false;
    if ($path !== false) {
        return $path;
    }

    global $mysqli;
    $path = null;
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        return $path;
    }

    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_logo FROM companies WHERE company_id = 1"));
    } catch (Throwable $e) {
        return $path;
    }

    // basename: the name comes from the database, the file must still be one in uploads/settings
    $logo = basename((string) ($row['company_logo'] ?? ''));
    $file = dirname(__DIR__) . "/uploads/settings/$logo";
    if ($logo !== '' && is_file($file)) {
        $path = $file;
    }

    return $path;
}

/**
 * The address of the app icon at one size - /app_icon.php?size=192&v=... The v changes
 * when the logo does, so phones and browsers fetch the new icon instead of a kept copy.
 */
function pwaIconUrl(int $size, bool $maskable = false): string
{
    $logo = pwaCompanyLogoPath();
    $version = $logo ? substr(md5(basename($logo) . '|' . @filemtime($logo)), 0, 10) : 'itflow';

    return "/app_icon.php?size=$size" . ($maskable ? '&maskable=1' : '') . "&v=$version";
}

/**
 * Draw the logo centred on a white square, as a PNG. The margins around the logo are
 * trimmed first, so a wide logo uses all the room it can. A maskable icon keeps the logo
 * inside the circle Android may cut it to (the middle 80%). Null when GD cannot read it.
 */
function pwaRenderIcon(string $logo_file, int $size, bool $maskable): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $info = @getimagesize($logo_file);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $info[0] > 6000 || $info[1] > 6000) {
        return null;
    }

    $logo = @imagecreatefromstring((string) file_get_contents($logo_file));
    if (!$logo) {
        return null;
    }

    // Transparent or white margins around the logo
    imagesavealpha($logo, true);
    $cropped = @imagecropauto($logo, IMG_CROP_SIDES);
    if ($cropped) {
        imagedestroy($logo);
        $logo = $cropped;
    }

    $logo_width = imagesx($logo);
    $logo_height = imagesy($logo);

    // The box the logo may fill: 80% of the icon, 56% when maskable (a box that still fits the 80% circle)
    $box = $size * ($maskable ? 0.56 : 0.80);
    $scale = min($box / $logo_width, $box / $logo_height);
    $width = max(1, (int) round($logo_width * $scale));
    $height = max(1, (int) round($logo_height * $scale));

    $icon = imagecreatetruecolor($size, $size);
    imagefill($icon, 0, 0, imagecolorallocate($icon, 255, 255, 255));
    imagealphablending($icon, true);
    imagecopyresampled($icon, $logo, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, $logo_width, $logo_height);
    imagedestroy($logo);

    ob_start();
    imagepng($icon, null, 9);
    imagedestroy($icon);

    return (string) ob_get_clean();
}
