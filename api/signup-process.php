<?php
/**
 * api/signup-process.php
 *
 * Handles POST submissions from signup.php.
 * - Validates all fields server-side (required, email format, password match)
 * - Verifies the CSRF token
 * - Checks for a duplicate email using a prepared statement
 * - Hashes the password with password_hash() — never stored in plain text
 * - Inserts the new user with a prepared statement
 * - Logs the new user in (session) and redirects to face-register.php
 *
 * No webcam / facial recognition logic is implemented here.
 */

require_once __DIR__ . '/../config/config.php';

// This endpoint only accepts POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo(APP_URL . '/signup.php');
}

$submittedToken = $_POST['csrf_token'] ?? null;

// Collect raw input for redisplay (never includes passwords).
$oldInput = [
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'email'     => trim((string) ($_POST['email'] ?? '')),
    'phone'     => trim((string) ($_POST['phone'] ?? '')),
];

$errors = [];

// ---------------------------------------------------------------
// CSRF check — fail fast, don't leak which field was wrong.
// ---------------------------------------------------------------
if (!verifyCsrfToken($submittedToken)) {
    $errors['general'] = 'Your session has expired or the form was tampered with. Please try again.';
    setFlashErrors('signup', $errors, $oldInput);
    redirectTo(APP_URL . '/signup.php');
}

// ---------------------------------------------------------------
// Gather + validate input
// ---------------------------------------------------------------
$fullName        = $oldInput['full_name'];
$email           = $oldInput['email'];
$phone           = $oldInput['phone'];
$password        = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

// Full name
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
} elseif (mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (mb_strlen($fullName) > 150) {
    $errors['full_name'] = 'Full name must be 150 characters or fewer.';
}

// Email
if ($email === '') {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (mb_strlen($email) > 150) {
    $errors['email'] = 'Email address must be 150 characters or fewer.';
}

// Phone
if ($phone === '') {
    $errors['phone'] = 'Phone number is required.';
} elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number (7–20 digits, may include + - ( ) spaces).';
}

// Password
if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (mb_strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
} elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'Password must include at least one letter and one number.';
}

// Confirm password
if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
} elseif ($password !== '' && $password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

// Stop here if any basic validation failed — no need to touch the database.
if (!empty($errors)) {
    setFlashErrors('signup', $errors, $oldInput);
    redirectTo(APP_URL . '/signup.php');
}

// ---------------------------------------------------------------
// Duplicate email check (prepared statement)
// ---------------------------------------------------------------
$pdo = getDBConnection();

try {
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch() !== false) {
        $errors['email'] = 'An account with this email address already exists.';
        setFlashErrors('signup', $errors, $oldInput);
        redirectTo(APP_URL . '/signup.php');
    }

    // -------------------------------------------------------
    // Hash the password (never store plain text) and insert.
    // -------------------------------------------------------
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash, face_registered, status)
         VALUES (:full_name, :email, :phone, :password_hash, 0, \'active\')'
    );
    $insertStmt->execute([
        'full_name'      => $fullName,
        'email'          => $email,
        'phone'          => $phone,
        'password_hash'  => $passwordHash,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    // ---------------------------------------------------
    // Log the new user in and rotate session/CSRF token.
    // ---------------------------------------------------
    session_regenerate_id(true);
    $_SESSION['user_id']    = $newUserId;
    $_SESSION['user_name']  = $fullName;
    $_SESSION['user_email'] = $email;
    regenerateCsrfToken();

    setFlashSuccess('Account created successfully. Let\'s register your face next.');
    redirectTo(APP_URL . '/face-register.php');

} catch (PDOException $e) {
    // Handles a race condition where two identical emails are submitted
    // at nearly the same time (unique index violation, MySQL error 1062).
    if ((int) $e->getCode() === 23000) {
        $errors['email'] = 'An account with this email address already exists.';
    } else {
        error_log('Signup failed: ' . $e->getMessage());
        $errors['general'] = 'Something went wrong while creating your account. Please try again.';
    }

    setFlashErrors('signup', $errors, $oldInput);
    redirectTo(APP_URL . '/signup.php');
}
