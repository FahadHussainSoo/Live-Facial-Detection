<?php
/**
 * Global Application Configuration
 * Live Facial Authentication System
 */

// ---------------------------------------------------------------
// Secure session setup
// ---------------------------------------------------------------
// Cookie params must be set BEFORE session_start(). httponly stops
// JS from reading the cookie (mitigates XSS session theft), samesite
// Lax mitigates CSRF on cross-site navigations, and "secure" is only
// forced when the request is already HTTPS (so local XAMPP over
// plain HTTP during development still works).
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Base app settings
define('APP_NAME', 'Live Facial Authentication');
define('APP_URL', 'http://localhost/Live-Facial-Detection'); // must match your XAMPP htdocs folder name exactly

// Error reporting: always LOG everything, but never DISPLAY raw
// errors/stack traces in the browser — a leaked stack trace can
// expose file paths, query fragments, or other internals to an
// attacker. Application code still shows the user a friendly
// message and writes the real error via error_log().
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Pull in the database connection helper.
require_once __DIR__ . '/database.php';

/**
 * Checks whether a user is currently logged in (i.e. has a valid
 * authenticated session).
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Guards a page so only authenticated users can view it. Call at the
 * very top of any protected page, right after config.php is required.
 * Unauthenticated visitors are bounced to signin.php, and the page
 * they originally wanted is remembered so we can send them back
 * after a successful login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        // Only ever remember a same-site relative path (starts with a
        // single '/', never '//' which browsers can treat as
        // protocol-relative). Anything else falls back to the
        // dashboard, so this can never be turned into an open redirect.
        if (is_string($requestUri) && preg_match('#^/[^/\\\\]#', $requestUri)) {
            $_SESSION['redirect_after_login'] = $requestUri;
        } else {
            $_SESSION['redirect_after_login'] = APP_URL . '/dashboard.php';
        }
        redirectTo(APP_URL . '/signin.php');
    }
}

/**
 * Redirect helper.
 */
function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * ---------------------------------------------------------------
 * Two-factor "pending" authentication state
 * ---------------------------------------------------------------
 * A correct password does NOT log a user in on its own. It only
 * moves the session into a PENDING state that identifies who is
 * attempting to sign in, without granting access to anything
 * protected by requireLogin(). Only a subsequent, server-verified
 * face match (see api/face-verify-process.php) is allowed to
 * promote a pending session into a fully authenticated one.
 *
 * This is enforced entirely server-side via session state — it
 * cannot be bypassed by editing JavaScript, URL parameters, or any
 * other client-controlled input, because isLoggedIn() (used by
 * requireLogin()) only ever looks at $_SESSION['user_id'], which
 * nothing in the pending flow ever sets.
 */
// A pending (password-verified, face-not-yet-verified) state is only
// good for a few minutes — long enough to complete the face step,
// short enough that an abandoned attempt can't be resumed later
// without re-entering the password.
const PENDING_FACE_AUTH_TTL_SECONDS = 300;

function isPendingFaceAuth(): bool
{
    if (isLoggedIn() || empty($_SESSION['pending_user_id'])) {
        return false;
    }

    $startedAt = (int) ($_SESSION['pending_started_at'] ?? 0);
    if ($startedAt === 0 || (time() - $startedAt) > PENDING_FACE_AUTH_TTL_SECONDS) {
        clearPendingFaceAuth();
        return false;
    }

    return true;
}

/**
 * Begins the pending state right after a correct password check.
 * Deliberately does NOT set $_SESSION['user_id'] — that only
 * happens once the face matches (finalizePendingAuth()).
 */
function startPendingFaceAuth(int $userId, string $fullName, string $email): void
{
    // Rotate the session ID now (password stage succeeded) without
    // yet granting authenticated status.
    session_regenerate_id(true);

    $_SESSION['pending_user_id']    = $userId;
    $_SESSION['pending_user_name']  = $fullName;
    $_SESSION['pending_user_email'] = $email;
    $_SESSION['pending_started_at'] = time();

    regenerateCsrfToken();
}

/**
 * Guards pages that require the password step to have already
 * succeeded (e.g. face-verify.php). A fully authenticated user is
 * sent straight to the dashboard; anyone with no pending state at
 * all is sent back to sign in with a password first.
 */
function requirePendingFaceAuth(): void
{
    if (isLoggedIn()) {
        redirectTo(APP_URL . '/dashboard.php');
    }

    if (!isPendingFaceAuth()) {
        redirectTo(APP_URL . '/signin.php');
    }
}

/**
 * Promotes a pending session to a fully authenticated one. This is
 * the ONLY place (besides signup) that ever sets
 * $_SESSION['user_id'], and it is only ever called after the server
 * itself has confirmed a genuine live face match (or, for a brand
 * new account with no face on file yet, a fresh live registration).
 */
function finalizePendingAuth(int $userId, string $fullName, string $email): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']    = $userId;
    $_SESSION['user_name']  = $fullName;
    $_SESSION['user_email'] = $email;

    unset(
        $_SESSION['pending_user_id'],
        $_SESSION['pending_user_name'],
        $_SESSION['pending_user_email'],
        $_SESSION['pending_started_at']
    );

    regenerateCsrfToken();
}

/**
 * Guards face-register.php. Registration is normally done by a
 * fully signed-in user (right after signup). It is ALSO allowed for
 * a pending (password-verified) session that has no face on file
 * yet — an existing account simply enrolling its face for the first
 * time at login. It is never allowed just because a request claims
 * some user id; both paths are still tied to server-side session
 * state only. Returns the effective user id to register against, or
 * redirects away if neither condition is met.
 */
function requireFaceRegistrationAccess(): int
{
    if (isLoggedIn()) {
        return (int) $_SESSION['user_id'];
    }

    if (isPendingFaceAuth()) {
        return (int) $_SESSION['pending_user_id'];
    }

    redirectTo(APP_URL . '/signin.php');
}

/**
 * Clears a pending session without granting access (used when the
 * user backs out to try signing in again from scratch).
 */
function clearPendingFaceAuth(): void
{
    unset(
        $_SESSION['pending_user_id'],
        $_SESSION['pending_user_name'],
        $_SESSION['pending_user_email'],
        $_SESSION['pending_started_at']
    );
}

/**
 * ---------------------------------------------------------------
 * CSRF protection
 * ---------------------------------------------------------------
 * A single token is kept per session and embedded as a hidden field
 * in every state-changing form. It is verified with a timing-safe
 * comparison before any processing happens.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rotates the CSRF token. Call after a successful state-changing
 * action (e.g. after registration succeeds) so the old token can't
 * be replayed.
 */
function regenerateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * ---------------------------------------------------------------
 * Rate limiting (brute-force protection)
 * ---------------------------------------------------------------
 * Counts recent FAILED attempts already recorded in
 * authentication_logs and blocks further attempts once a threshold
 * is hit within a rolling time window. Re-uses the existing audit
 * log instead of a new table, and fails OPEN (never blocks a
 * legitimate user) if the check itself errors out.
 */
function tooManyRecentFailures(
    PDO $pdo,
    string $authType,
    ?int $userId,
    string $ipAddress,
    int $limit,
    int $windowMinutes
): bool {
    try {
        if ($userId !== null) {
            // Known account: rate-limit by that account specifically.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM authentication_logs
                 WHERE authentication_type = :type
                   AND status = 'failed'
                   AND user_id = :user_id
                   AND created_at >= (NOW() - INTERVAL :minutes MINUTE)"
            );
            $stmt->bindValue(':type', $authType);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':minutes', $windowMinutes, PDO::PARAM_INT);
        } else {
            // Unknown/unauthenticated identifier (e.g. sign-in with a
            // wrong email): fall back to rate-limiting by IP so an
            // attacker can't just try a different email every request.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM authentication_logs
                 WHERE authentication_type = :type
                   AND status = 'failed'
                   AND ip_address = :ip_address
                   AND created_at >= (NOW() - INTERVAL :minutes MINUTE)"
            );
            $stmt->bindValue(':type', $authType);
            $stmt->bindValue(':ip_address', $ipAddress);
            $stmt->bindValue(':minutes', $windowMinutes, PDO::PARAM_INT);
        }
        $stmt->execute();
        $total = (int) ($stmt->fetch()['total'] ?? 0);
        return $total >= $limit;
    } catch (PDOException $e) {
        error_log('Rate limit check failed (failing open): ' . $e->getMessage());
        return false;
    }
}

/**
 * ---------------------------------------------------------------
 * Flash data (validation errors + old form input)
 * ---------------------------------------------------------------
 * Stored in the session for exactly one request/redirect cycle so a
 * form page can show errors and repopulate fields after a
 * redirect-back from its processing script.
 */
function setFlashErrors(string $formKey, array $errors, array $oldInput = []): void
{
    $_SESSION['flash_errors'][$formKey] = $errors;
    $_SESSION['flash_old'][$formKey] = $oldInput;
}

function getFlashErrors(string $formKey): array
{
    $errors = $_SESSION['flash_errors'][$formKey] ?? [];
    unset($_SESSION['flash_errors'][$formKey]);
    return $errors;
}

function getFlashOld(string $formKey): array
{
    $old = $_SESSION['flash_old'][$formKey] ?? [];
    unset($_SESSION['flash_old'][$formKey]);
    return $old;
}

function setFlashSuccess(string $message): void
{
    $_SESSION['flash_success'] = $message;
}

function getFlashSuccess(): ?string
{
    $message = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);
    return $message;
}
