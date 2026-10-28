<?php
/**
 * api/signin-process.php
 *
 * Handles POST submissions from signin.php.
 * - Accepts either an email address OR a phone number as the identifier
 * - Verifies the CSRF token
 * - Looks up the matching user with a prepared statement
 * - Checks the password with password_verify() against the stored hash
 * - On success: regenerates the session ID, stores the user in session,
 *   rotates the CSRF token, logs the attempt, and redirects to the
 *   dashboard (or wherever the user originally tried to go)
 * - On failure: shows one generic "invalid login" message that does not
 *   reveal whether the identifier or the password was wrong, and logs
 *   the failed attempt
 *
 * No facial recognition logic is implemented here.
 */

require_once __DIR__ . '/../config/config.php';

// This endpoint only accepts POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo(APP_URL . '/signin.php');
}

$submittedToken = $_POST['csrf_token'] ?? null;

$oldInput = [
    'identifier' => trim((string) ($_POST['identifier'] ?? '')),
];

$errors = [];

// ---------------------------------------------------------------
// CSRF check — fail fast, don't leak which field was wrong.
// ---------------------------------------------------------------
if (!verifyCsrfToken($submittedToken)) {
    $errors['general'] = 'Your session has expired or the form was tampered with. Please try again.';
    setFlashErrors('signin', $errors, $oldInput);
    redirectTo(APP_URL . '/signin.php');
}

$identifier = $oldInput['identifier'];
$password   = (string) ($_POST['password'] ?? '');

// ---------------------------------------------------------------
// Basic presence validation
// ---------------------------------------------------------------
if ($identifier === '') {
    $errors['identifier'] = 'Please enter your email or phone number.';
}
if ($password === '') {
    $errors['password'] = 'Please enter your password.';
}

if (!empty($errors)) {
    setFlashErrors('signin', $errors, $oldInput);
    redirectTo(APP_URL . '/signin.php');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/**
 * Records an attempt in the audit log. Failures here are swallowed
 * (logged to the PHP error log) so a logging problem never blocks
 * or breaks the actual sign-in flow.
 */
$logAttempt = function (?int $userId, string $status) use ($ipAddress): void {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO authentication_logs (user_id, authentication_type, status, ip_address)
             VALUES (:user_id, \'password\', :status, :ip_address)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'status'     => $status,
            'ip_address' => $ipAddress,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write authentication log: ' . $e->getMessage());
    }
};

// ---------------------------------------------------------------
// Look up the user by email OR phone (prepared statement).
// ---------------------------------------------------------------
$pdo = getDBConnection();

// Generic failure message. Deliberately identical whether the
// identifier doesn't exist or the password is wrong, so an attacker
// can't use the response to enumerate valid accounts.
$invalidMessage = 'Invalid email/phone or password.';

try {
    // PHASE 12 — brute-force protection: block sign-in attempts from
    // an IP that has racked up too many recent failures, before we
    // even touch the password check.
    if (tooManyRecentFailures($pdo, 'password', null, $ipAddress, 10, 15)) {
        $errors['general'] = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';
        setFlashErrors('signin', $errors, $oldInput);
        redirectTo(APP_URL . '/signin.php');
    }

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, password_hash, status
         FROM users
         WHERE email = :identifier_email OR phone = :identifier_phone
         LIMIT 1'
    );
    $stmt->execute([
        'identifier_email' => $identifier,
        'identifier_phone' => $identifier,
    ]);
    $user = $stmt->fetch();

    // Also block further attempts against this specific account once
    // it has too many recent failures, even from a different IP.
    if ($user !== false && tooManyRecentFailures($pdo, 'password', (int) $user['id'], $ipAddress, 10, 15)) {
        $errors['general'] = 'Too many failed sign-in attempts on this account. Please wait a few minutes and try again.';
        setFlashErrors('signin', $errors, $oldInput);
        redirectTo(APP_URL . '/signin.php');
    }

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        $logAttempt($user['id'] ?? null, 'failed');
        $errors['general'] = $invalidMessage;
        setFlashErrors('signin', $errors, $oldInput);
        redirectTo(APP_URL . '/signin.php');
    }

    if ($user['status'] !== 'active') {
        $logAttempt((int) $user['id'], 'failed');
        $errors['general'] = 'This account is not active. Please contact support.';
        setFlashErrors('signin', $errors, $oldInput);
        redirectTo(APP_URL . '/signin.php');
    }

    // -------------------------------------------------------
    // Password correct — but this is only step 1 of 2.
    //
    // SECURITY: we deliberately do NOT create the final
    // authenticated session here. $_SESSION['user_id'] (what
    // isLoggedIn()/requireLogin() check) is never set on a
    // password match alone. Instead we move the session into a
    // PENDING state that only identifies who is attempting to
    // sign in. The final session is only created after a live
    // face match succeeds, in api/face-verify-process.php. This
    // is enforced server-side, so it cannot be skipped by editing
    // JavaScript, the URL, or session values from the client.
    // -------------------------------------------------------
    startPendingFaceAuth((int) $user['id'], $user['full_name'], $user['email']);

    // The password stage itself is logged as usual — this is not
    // the final login decision, just an audit entry for this step.
    $logAttempt((int) $user['id'], 'success');

    // Remember where the user originally wanted to go so face-verify
    // can send them there once (and only once) face verification
    // also succeeds. Falls back to the dashboard.
    if (empty($_SESSION['redirect_after_login'])) {
        $_SESSION['redirect_after_login'] = APP_URL . '/dashboard.php';
    }

    redirectTo(APP_URL . '/face-verify.php');

} catch (PDOException $e) {
    error_log('Sign-in failed: ' . $e->getMessage());
    $errors['general'] = 'Something went wrong while signing you in. Please try again.';
    setFlashErrors('signin', $errors, $oldInput);
    redirectTo(APP_URL . '/signin.php');
}
