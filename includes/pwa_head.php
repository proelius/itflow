<?php /* Installable app (Progressive Web App): manifest.php describes it, sw.js
         is its service worker, js/pwa.js registers it. Shared by
         includes/header.php and login.php, so the app can be installed from the
         login page as well as once signed in. */ ?>
    <link rel="manifest" href="/manifest.php">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= escapeHtml(isset($config_app_name) && $config_app_name !== '' ? $config_app_name : 'ITFlow') ?>">
    <link rel="apple-touch-icon" href="<?= escapeHtml(pwaIconUrl(180)) ?>">
    <script src="<?= staticFileUrl('/js/pwa.js') ?>" defer></script>
