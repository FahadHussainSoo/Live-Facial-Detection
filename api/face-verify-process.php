<?php
/**
 * api/face-verify-process.php
 *
 * Compares a LIVE facial embedding (generated client-side, just now,
 * from the webcam) against the ALREADY-REGISTERED embedding for the
 * currently signed-in user, and reports one of:
 *
 *   - "match"          the live face matches the account's registered face
 *   - "no_match"       a real face was read, but it does not match
 *   - "not_registered" this account has no registered face yet
 *
 * This is a genuine 1:1 biometric comparison (Euclidean distance
 * between two 128-value face-api.js embeddings) — never a random or
 * hard-coded result, and detecting *a* face is never treated as a
 * pass on its own. Only a live embedding that is numerically close
 * enough to the stored one counts as a match.
 *
 * Security / correctness notes:
 *   - The account to compare against comes ONLY from the server-side
 *     PENDING session ($_SESSION['pending_user_id'], set only after
 *     a correct password) — a user can only ever be verified against
 *     their OWN registered face, never anyone else's.
 *   - A password check alone never creates a full session. This
 *     endpoint is the ONLY place (besides face-register.php's
 *     first-time enrollment path) that is allowed to promote a
 *     pending session into a fully authenticated one, and it only
 *     does so after a genuine embedding match.
 *   - No image/video data is accepted or stored here — only the
 *     numeric embedding array, exactly like registration.
 *   - The match decision is computed entirely on the server, so the
 *     client cannot simply claim "success" — it can only supply the
 *     embedding to be compared.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

const FACE_EMBEDDING_LENGTH = 128;

// Same default distance threshold used by face-api.js's own
// FaceMatcher for "same person" — lower distance = more similar.
const FACE_MATCH_THRESHOLD = 0.6;

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function isValidDescriptor($descriptor): bool
{
    if (!is_array($descriptor) || count($descriptor) !== FACE_EMBEDDING_LENGTH) {
        return false;
    }
    foreach ($descriptor as $value) {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return false;
        }
    }
    return true;
}

function euclideanDistance(array $a, array $b): float
{
    $sumSquares = 0.0;
    $count = count($a);
    for ($i = 0; $i < $count; $i++) {
        $diff = ((float) $a[$i]) - ((float) $b[$i]);
        $sumSquares += $diff * $diff;
    }
    return sqrt($sumSquares);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// This endpoint is reached mid-login: the password has already been
// verified (that's what put the session into the pending state) but
// the final authenticated session does not exist yet. A fully
// logged-in session has no reason to call this endpoint at all.
if (!isPendingFaceAuth()) {
    respond(401, ['success' => false, 'message' => 'Your sign-in attempt has expired. Please sign in again.']);
}

$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($submittedToken)) {
    respond(403, ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload) || !isset($payload['descriptor'])) {
    respond(422, ['success' => false, 'message' => 'Missing or invalid face data.']);
}

$liveDescriptor = $payload['descriptor'];

if (!isValidDescriptor($liveDescriptor)) {
    respond(422, ['success' => false, 'message' => 'Unexpected face data format.']);
}

$liveDescriptor = array_map('floatval', $liveDescriptor);
$userId = (int) $_SESSION['pending_user_id'];
$pendingUserName = (string) ($_SESSION['pending_user_name'] ?? 'User');
$pendingUserEmail = (string) ($_SESSION['pending_user_email'] ?? '');
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $pdo = getDBConnection();

    // PHASE 12 — brute-force / abuse protection: block further face
    // verification attempts for this account once it has racked up
    // too many recent failures. This does not change how a
    // comparison is judged — it only limits how often one can be
    // attempted.
    if (tooManyRecentFailures($pdo, 'face', $userId, $ipAddress, 10, 15)) {
        respond(429, [
            'success' => false,
            'message' => 'Too many failed face verification attempts. Please wait a few minutes, or use your password instead.',
        ]);
    }

    // Only ever look up the CURRENT session's own registered face —
    // there is no code path here that accepts or looks up any other
    // user's id.
    $stmt = $pdo->prepare('SELECT face_embedding FROM face_authentication WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    // Only used to *label* a successful result (name + timestamp) —
    // never part of the actual match decision.
    $userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :user_id');
    $userStmt->execute(['user_id' => $userId]);
    $userRow = $userStmt->fetch();
    $userName = $userRow['full_name'] ?? $pendingUserName;
    $userEmail = $userRow['email'] ?? $pendingUserEmail;

    if ($row === false) {
        respond(200, [
            'success' => true,
            'status'  => 'not_registered',
            'message' => 'No face is registered on this account yet. Please register your face first.',
        ]);
    }

    $storedDescriptor = json_decode($row['face_embedding'], true);

    if (!isValidDescriptor($storedDescriptor)) {
        error_log("Stored face_embedding for user {$userId} is invalid/corrupted.");
        respond(500, ['success' => false, 'message' => 'Stored face data could not be read. Please re-register your face.']);
    }

    $storedDescriptor = array_map('floatval', $storedDescriptor);

    // The actual comparison: a genuine numeric distance between two
    // real embeddings. A smaller distance means the two faces are
    // more alike; only distances at or below the threshold count as
    // the same person.
    $distance = euclideanDistance($liveDescriptor, $storedDescriptor);
    $isMatch = $distance <= FACE_MATCH_THRESHOLD;

    // PHASE 10 — every real comparison attempt (granted or denied) is
    // recorded here. The raw distance is intentionally never logged
    // or returned to the client — only the final success/failed
    // status, exactly like the password-login audit trail.
    $log = $pdo->prepare(
        'INSERT INTO authentication_logs (user_id, authentication_type, status, ip_address)
         VALUES (:user_id, \'face\', :status, :ip_address)'
    );
    $log->execute([
        'user_id'    => $userId,
        'status'     => $isMatch ? 'success' : 'failed',
        'ip_address' => $ipAddress,
    ]);

    if ($isMatch) {
        // ACCESS GRANTED. This is the one and only point where the
        // pending session is promoted into a real, fully
        // authenticated session — password AND live face have both
        // now been verified server-side.
        finalizePendingAuth($userId, $userName, $userEmail);

        $redirectTarget = $_SESSION['redirect_after_login'] ?? (APP_URL . '/dashboard.php');
        unset($_SESSION['redirect_after_login']);

        // Only what's needed for the result screen — name, a
        // server-generated timestamp, and a verified flag. No
        // embeddings, distances, or thresholds are ever exposed.
        respond(200, [
            'success'     => true,
            'status'      => 'match',
            'message'     => 'Face verification successful.',
            'user_name'   => $userName,
            'verified_at' => date('Y-m-d H:i:s'),
            'verified'    => true,
            'redirect'    => $redirectTarget,
        ]);
    }

    // ACCESS DENIED.
    respond(200, [
        'success'  => true,
        'status'   => 'no_match',
        'message'  => 'Face verification failed.',
        'verified' => false,
    ]);
} catch (PDOException $e) {
    error_log('Face verification failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Something went wrong while verifying your face. Please try again.']);
}
