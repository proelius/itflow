/*
 * Registers the service worker (sw.js) that makes ITFlow installable as an
 * app on a phone or desktop. Loaded by includes/header.php and login.php.
 * Service workers only run over HTTPS (or on localhost); elsewhere this does nothing.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (error) {
            console.warn('ITFlow: service worker registration failed', error);
        });
    });
}
