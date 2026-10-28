<?php
/**
 * api/logout.php
 *
 * Logs the current user out by fully clearing and destroying the
 * session, then removing the session cookie from the browser, and
 * finally redirecting to the sign-in page.
 */

require_once __DIR__ . '/../config/config.php';

// Wipe all session data.
$_SESSION = [];

// Remove the session cookie itself (not just the server-side data),
// so the browser doesn't keep sending a now-dead session ID.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

redirectTo(APP_URL . '/signin.php');
