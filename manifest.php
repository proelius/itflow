<?php

/*
 * Web app manifest - what lets a phone install ITFlow on its home screen
 * (Progressive Web App). Linked from includes/header.php and login.php.
 *
 * Browsers fetch the manifest without the session cookie, so nothing here may
 * depend on who is signed in: only the app name from config.php and the
 * labels in the instance's language.
 */

if (!file_exists(__DIR__ . "/config.php")) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";

$app_name = isset($config_app_name) && $config_app_name !== '' ? $config_app_name : 'ITFlow';

$manifest = [
    'id' => '/',
    'name' => $app_name,
    'short_name' => $app_name,
    'lang' => i18n_current_language(),
    // index.php sends a signed-in agent to their start page, everyone else to the login
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#ffffff',
    'theme_color' => '#0d6efd',
    'icons' => [
        ['src' => '/img/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/img/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/img/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    // Long-press on the home screen icon
    'shortcuts' => [
        ['name' => t('Tickets'), 'url' => '/agent/tickets.php', 'icons' => [['src' => '/img/pwa/icon-192.png', 'sizes' => '192x192']]],
        ['name' => t('Clients'), 'url' => '/agent/clients.php', 'icons' => [['src' => '/img/pwa/icon-192.png', 'sizes' => '192x192']]],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
