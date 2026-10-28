<?php
/**
 * api/face-register-process.php
 *
 * Receives a facial embedding (a fixed-length array of numbers produced
 * client-side by face-api.js from the LIVE webcam) and stores it for the
 * currently signed-in user.
 *
 * Security / correctness notes:
 *   - The user is taken ONLY from server-side session state — either
 *     a full session ($_SESSION['user_id']) or, for first-time
 *     enrollment during login, a PENDING session
 *     ($_SESSION['pending_user_id']) — never from anything the
 *     client sends — so it is impossible for a request to register a
 *     face against a different account than the one actually
 *     signed in / mid-login.
 *   - A pending session may only register a face for an account that
 *     doesn't already have one. If the account already has a
 *     registered face, this endpoint refuses — otherwise anyone who
 *     merely knows the password could overwrite the second factor
 *     and grant themselves access without ever proving a face match.
 *   - Only a numeric embedding is accepted. No image, video, or base64
 *     photo data is read, stored, or expected here.
 *   - The embedding is validated (must be an array of exactly 128
 *     finite numbers, which is what face-api.js's recognition model
 *     always produces) before anything touches the database — this
 *     also rules out placeholder/fake data being saved.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

const FACE_EMBEDDING_LENGTH = 128;

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$isPendingEnrollment = false;
if (isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} elseif (isPendingFaceAuth()) {
    $userId = (int) $_SESSION['pending_user_id'];
    $isPendingEnrollment = true;
} else {
    respond(401, ['success' => false, 'message' => 'You must be signed in to register a face.']);
}

// CSRF check — sent as a custom header by the fetch() call in face-register.js.
$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($submittedToken)) {
    respond(403, ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload) || !isset($payload['descriptor']) || !is_array($payload['descriptor'])) {
    respond(422, ['success' => false, 'message' => 'Missing or invalid face data.']);
}

$descriptor = $payload['descriptor'];

// The embedding must be exactly the length face-api.js's recognition
// model produces, and every value must be a finite number — this is
// what stops garbage/placeholder data from ever being saved.
if (count($descriptor) !== FACE_EMBEDDING_LENGTH) {
    respond(422, ['success' => false, 'message' => 'Unexpected face data format.']);
}

foreach ($descriptor as $value) {
    if (!is_numeric($value) || !is_finite((float) $value)) {
        respond(422, ['success' => false, 'message' => 'Unexpected face data format.']);
    }
}

$descriptor = array_map('floatval', $descriptor);
$encodedEmbedding = json_encode($descriptor);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Portable upsert (works the same on MySQL/XAMPP and elsewhere,
    // rather than relying on MySQL-only "ON DUPLICATE KEY" syntax).
    $existing = $pdo->prepare('SELECT id FROM face_authentication WHERE user_id = :user_id');
    $existing->execute(['user_id' => $userId]);
    $alreadyHasFace = $existing->fetch() !== false;

    // SECURITY: a pending (password-only) session must never be able
    // to replace an existing face. That would let anyone who just
    // knows the password strip out the real second factor.
    if ($isPendingEnrollment && $alreadyHasFace) {
        $pdo->rollBack();
        respond(409, [
            'success' => false,
            'message' => 'This account already has a registered face. Please verify with your face instead.',
        ]);
    }

    if ($alreadyHasFace) {
        $stmt = $pdo->prepare(
            'UPDATE face_authentication
             SET face_embedding = :embedding, updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO face_authentication (user_id, face_embedding)
             VALUES (:user_id, :embedding)'
        );
    }
    $stmt->execute(['user_id' => $userId, 'embedding' => $encodedEmbedding]);

    $updateUser = $pdo->prepare('UPDATE users SET face_registered = 1 WHERE id = :id');
    $updateUser->execute(['id' => $userId]);

    $log = $pdo->prepare(
        'INSERT INTO authentication_logs (user_id, authentication_type, status, ip_address)
         VALUES (:user_id, \'face\', \'success\', :ip_address)'
    );
    $log->execute(['user_id' => $userId, 'ip_address' => $ipAddress]);

    $pdo->commit();

    $redirectTarget = null;
    if ($isPendingEnrollment) {
        // A brand-new face was just captured live for an account that
        // had none on file — that live capture, on top of the
        // already-verified password, is what completes sign-in here.
        // This is the only other place (besides face-verify-process.php)
        // allowed to promote a pending session to a full one.
        $nameStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id');
        $nameStmt->execute(['id' => $userId]);
        $nameRow = $nameStmt->fetch();
        $fullName = $nameRow['full_name'] ?? ($_SESSION['pending_user_name'] ?? 'User');
        $email = $nameRow['email'] ?? ($_SESSION['pending_user_email'] ?? '');

        finalizePendingAuth($userId, $fullName, $email);

        $redirectTarget = $_SESSION['redirect_after_login'] ?? (APP_URL . '/dashboard.php');
        unset($_SESSION['redirect_after_login']);
    }

    respond(200, [
        'success'  => true,
        'message'  => 'Your face has been registered successfully.',
        'redirect' => $redirectTarget,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Face registration failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Something went wrong while saving your face data. Please try again.']);
}
