<?php

/*
 * Offline page - the one page sw.js keeps in its cache, shown in place of the
 * browser's own error when the installed app (or a browser tab) has no network.
 *
 * It is cached once, when the service worker installs, so it must not show
 * anything about the signed-in user: only the app name and fixed wording.
 * Self-contained (inline style, no script) so that it renders with nothing
 * else in the cache.
 */

if (!file_exists(__DIR__ . "/config.php")) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";

$app_name = isset($config_app_name) && $config_app_name !== '' ? $config_app_name : 'ITFlow';

header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'");

?>
<!DOCTYPE html>
<html lang="<?= escapeHtml(i18n_current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0d6efd">
    <title><?= escapeHtml($app_name) ?> | <?= t('You are offline') ?></title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 16px; box-sizing: border-box; text-align: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fa; color: #212529;
        }
        main { max-width: 360px; }
        img { width: 72px; height: 72px; border-radius: 16px; }
        h1 { font-size: 1.4rem; margin: 20px 0 8px; }
        p { margin: 0 0 24px; line-height: 1.5; color: #6c757d; }
        a {
            display: inline-block; padding: 10px 24px; border-radius: 6px;
            background: #0d6efd; color: #fff; text-decoration: none; font-weight: 600;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #212529; color: #dee2e6; }
            p { color: #adb5bd; }
        }
    </style>
</head>
<body>
<main>
    <img src="/img/pwa/icon-192.png" alt="">
    <h1><?= t('You are offline') ?></h1>
    <p><?= escapeHtml(t('{app} needs a network connection. Check your connection, then try again.', ['{app}' => $app_name])) ?></p>
    <?php /* An empty href reloads the address the agent was trying to open */ ?>
    <a href=""><?= t('Try again') ?></a>
</main>
</body>
</html>
