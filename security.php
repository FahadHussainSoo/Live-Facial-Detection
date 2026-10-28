<?php
require_once __DIR__ . '/config/config.php';

// Only signed-in users may view their own security overview.
requireLogin();

$pageTitle = 'Security';
$userId = (int) $_SESSION['user_id'];

$user = [
    'status'          => 'active',
    'face_registered' => false,
];
$successCount = 0;
$failedCount  = 0;
$lastPassword = null;
$lastFace     = null;

try {
    $pdo = getDBConnection();

    $userStmt = $pdo->prepare('SELECT status, face_registered FROM users WHERE id = :id');
    $userStmt->execute(['id' => $userId]);
    $userRow = $userStmt->fetch();
    if ($userRow !== false) {
        $user['status']          = $userRow['status'];
        $user['face_registered'] = !empty($userRow['face_registered']);
    }

    $countsStmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total FROM authentication_logs
         WHERE user_id = :id AND authentication_type = 'face'
         GROUP BY status"
    );
    $countsStmt->execute(['id' => $userId]);
    foreach ($countsStmt->fetchAll() as $row) {
        if ($row['status'] === 'success') {
            $successCount = (int) $row['total'];
        } elseif ($row['status'] === 'failed') {
            $failedCount = (int) $row['total'];
        }
    }

    $lastPasswordStmt = $pdo->prepare(
        "SELECT created_at, status FROM authentication_logs
         WHERE user_id = :id AND authentication_type = 'password'
         ORDER BY created_at DESC LIMIT 1"
    );
    $lastPasswordStmt->execute(['id' => $userId]);
    $lastPasswordRow = $lastPasswordStmt->fetch();
    if ($lastPasswordRow !== false) {
        $lastPassword = $lastPasswordRow;
    }

    $lastFaceStmt = $pdo->prepare(
        "SELECT created_at, status FROM authentication_logs
         WHERE user_id = :id AND authentication_type = 'face'
         ORDER BY created_at DESC LIMIT 1"
    );
    $lastFaceStmt->execute(['id' => $userId]);
    $lastFaceRow = $lastFaceStmt->fetch();
    if ($lastFaceRow !== false) {
        $lastFace = $lastFaceRow;
    }
} catch (PDOException $e) {
    error_log('Security overview load failed: ' . $e->getMessage());
}

function fmtEvent(?array $event): string
{
    if (!$event) {
        return 'No record yet';
    }
    return date('M j, Y g:i A', strtotime($event['created_at'])) . ' — ' . ($event['status'] === 'success' ? 'Success' : 'Failed');
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5 security-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Security</h2>
            <p class="text-muted mb-0">Overview of how your account is protected.</p>
        </div>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-key"></i></div>
                <h6 class="text-muted mb-1">Password</h6>
                <h5 class="fw-bold mb-2">Protected</h5>
                <p class="text-muted small mb-0">Last password sign-in: <?php echo htmlspecialchars(fmtEvent($lastPassword)); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-camera-video"></i></div>
                <h6 class="text-muted mb-1">Face Verification</h6>
                <h5 class="fw-bold mb-2">
                    <?php if ($user['face_registered']): ?>
                        <span class="badge-pill badge-good">Enrolled</span>
                    <?php else: ?>
                        <span class="badge-pill badge-warn">Not Enrolled</span>
                    <?php endif; ?>
                </h5>
                <p class="text-muted small mb-0">Last verification: <?php echo htmlspecialchars(fmtEvent($lastFace)); ?></p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card stat-card p-4 h-100">
                <h5 class="fw-semibold mb-3">Verification Summary</h5>
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="summary-tile summary-good">
                            <div class="summary-number"><?php echo (int) $successCount; ?></div>
                            <div class="summary-label">Successful</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="summary-tile summary-bad">
                            <div class="summary-number"><?php echo (int) $failedCount; ?></div>
                            <div class="summary-label">Failed</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo APP_URL; ?>/history.php" class="btn btn-outline-secondary w-100 mt-4">
                    <i class="bi bi-clock-history me-1"></i> View Full Authentication History
                </a>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card stat-card p-4 h-100">
                <h5 class="fw-semibold mb-3">Account Status</h5>
                <p class="mb-2">
                    <span class="badge-pill badge-<?php echo $user['status'] === 'active' ? 'good' : 'bad'; ?>">
                        <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                    </span>
                </p>
                <p class="text-muted small mb-0">
                    Your account is secured with a password
                    <?php echo $user['face_registered'] ? 'and a registered face for biometric verification.' : '. Register your face to enable biometric verification.'; ?>
                </p>
                <?php if (!$user['face_registered']): ?>
                    <a href="<?php echo APP_URL; ?>/face-register.php" class="btn btn-brand w-100 mt-3">
                        <i class="bi bi-camera-video me-1"></i> Register Your Face
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
