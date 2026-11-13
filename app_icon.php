<?php

/*
 * The installable app's icon - the company logo (Admin > Company) on a white square,
 * at the size asked for: app_icon.php?size=192, &maskable=1 for Android's shaped icons.
 * Without a logo, the ITFlow icons in img/pwa.
 *
 * Fetched by phones without the session cookie (manifest, home screen), so it needs no
 * sign-in - the logo is on the login page for anyone to see already.
 */

if (!file_exists(__DIR__ . "/config.php")) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";

$size = intval($_GET['size'] ?? 192);
if (!in_array($size, PWA_ICON_SIZES, true)) {
    $size = 192;
}
$maskable = !empty($_GET['maskable']);

$logo = pwaCompanyLogoPath();
$png = $logo ? pwaRenderIcon($logo, $size, $maskable) : null;

if ($png === null) {
    // The ITFlow icons
    if ($size === 180) {
        $fallback = 'apple-touch-icon.png';
    } elseif ($maskable) {
        $fallback = 'icon-maskable-512.png';
    } else {
        $fallback = $size === 512 ? 'icon-512.png' : 'icon-192.png';
    }
    $png = (string) file_get_contents(__DIR__ . "/img/pwa/$fallback");
}

$etag = '"' . md5($png) . '"';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header("ETag: $etag");

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit();
}

echo $png;
