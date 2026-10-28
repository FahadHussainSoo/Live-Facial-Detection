<?php
require_once __DIR__ . '/config/config.php';

// Only signed-in users may view the dashboard.
requireLogin();

$pageTitle = 'Dashboard';
$userId = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------
// PHASE 11 — Real dashboard data.
// Everything below is read-only reporting on top of the existing
// users / authentication_logs tables. No facial recognition logic
// is touched here.
// ---------------------------------------------------------------
$user = [
    'full_name'       => $_SESSION['user_name'] ?? 'there',
    'email'           => $_SESSION['user_email'] ?? '',
    'status'          => 'active',
    'face_registered' => false,
    'created_at'      => null,
];
$lastVerification  = null; // ['created_at' => ..., 'status' => ...]
$successCount      = 0;
$failedCount       = 0;
$recentHistory     = [];

try {
    $pdo = getDBConnection();

    $userStmt = $pdo->prepare(
        'SELECT full_name, email, status, face_registered, created_at
         FROM users WHERE id = :id'
    );
    $userStmt->execute(['id' => $userId]);
    $userRow = $userStmt->fetch();
    if ($userRow !== false) {
        $user = [
            'full_name'       => $userRow['full_name'],
            'email'           => $userRow['email'],
            'status'          => $userRow['status'],
            'face_registered' => !empty($userRow['face_registered']),
            'created_at'      => $userRow['created_at'],
        ];
    }

    $lastVerifyStmt = $pdo->prepare(
        "SELECT created_at, status FROM authentication_logs
         WHERE user_id = :id AND authentication_type = 'face'
         ORDER BY created_at DESC LIMIT 1"
    );
    $lastVerifyStmt->execute(['id' => $userId]);
    $lastVerifyRow = $lastVerifyStmt->fetch();
    if ($lastVerifyRow !== false) {
        $lastVerification = $lastVerifyRow;
    }

    $countsStmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total FROM authentication_logs
         WHERE user_id = :id AND authentication_type = 'face'
         GROUP BY status"
    );
    $countsStmt->execute(['id' => $userId]);
    foreach ($countsStmt->fetchAll() as $countRow) {
        if ($countRow['status'] === 'success') {
            $successCount = (int) $countRow['total'];
        } elseif ($countRow['status'] === 'failed') {
            $failedCount = (int) $countRow['total'];
        }
    }

    $historyStmt = $pdo->prepare(
        'SELECT created_at, authentication_type, status FROM authentication_logs
         WHERE user_id = :id ORDER BY created_at DESC LIMIT 6'
    );
    $historyStmt->execute(['id' => $userId]);
    $recentHistory = $historyStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard data load failed: ' . $e->getMessage());
}

/**
 * Small display helpers kept local to this page.
 */
function methodLabel(string $type): string
{
    switch ($type) {
        case 'face':
            return 'Face Verification';
        case 'password_face':
            return 'Password + Face';
        case 'password':
        default:
            return 'Password';
    }
}

function methodIcon(string $type): string
{
    switch ($type) {
        case 'face':
            return 'bi-camera-video';
        case 'password_face':
            return 'bi-shield-lock';
        case 'password':
        default:
            return 'bi-key';
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5 dashboard-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Dashboard</h2>
            <p class="text-muted mb-0">
                Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>.
                Overview of your account and facial authentication status.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo APP_URL; ?>/face-verify.php" class="btn btn-brand">
                <i class="bi bi-shield-check me-1"></i> Verify Identity
            </a>
            <a href="<?php echo APP_URL; ?>/history.php" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i> View Full History
            </a>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-person-badge"></i></div>
                <h6 class="text-muted mb-1">User</h6>
                <h4 class="fw-bold mb-0 text-truncate" title="<?php echo htmlspecialchars($user['full_name']); ?>">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </h4>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-person-check"></i></div>
                <h6 class="text-muted mb-1">Account Status</h6>
                <h4 class="fw-bold mb-0">
                    <span class="badge-pill badge-<?php echo $user['status'] === 'active' ? 'good' : 'bad'; ?>">
                        <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                    </span>
                </h4>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-camera-video"></i></div>
                <h6 class="text-muted mb-1">Face Registration</h6>
                <h4 class="fw-bold mb-0">
                    <?php if ($user['face_registered']): ?>
                        <span class="badge-pill badge-good">Registered</span>
                    <?php else: ?>
                        <span class="badge-pill badge-warn">Not Set Up</span>
                    <?php endif; ?>
                </h4>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap"><i class="bi bi-clock-history"></i></div>
                <h6 class="text-muted mb-1">Last Verification</h6>
                <?php if ($lastVerification): ?>
                    <h4 class="fw-bold mb-0 fs-6">
                        <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($lastVerification['created_at']))); ?>
                    </h4>
                    <span class="badge-pill badge-<?php echo $lastVerification['status'] === 'success' ? 'good' : 'bad'; ?> mt-2">
                        <?php echo $lastVerification['status'] === 'success' ? 'Verified' : 'Failed'; ?>
                    </span>
                <?php else: ?>
                    <h4 class="fw-bold mb-0">&mdash;</h4>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap icon-wrap-good"><i class="bi bi-check-circle"></i></div>
                <h6 class="text-muted mb-1">Successful Verifications</h6>
                <h4 class="fw-bold mb-0"><?php echo (int) $successCount; ?></h4>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card stat-card p-4 h-100">
                <div class="icon-wrap icon-wrap-bad"><i class="bi bi-x-circle"></i></div>
                <h6 class="text-muted mb-1">Failed Verifications</h6>
                <h4 class="fw-bold mb-0"><?php echo (int) $failedCount; ?></h4>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick actions / nav shortcuts -->
        <div class="col-lg-4">
            <div class="card stat-card p-4 h-100">
                <h5 class="fw-semibold mb-3">Quick Links</h5>
                <div class="d-flex flex-column gap-2 dashboard-quicklinks">
                    <a href="<?php echo APP_URL; ?>/profile.php" class="quicklink-item">
                        <i class="bi bi-person-circle"></i> Profile
                    </a>
                    <a href="<?php echo APP_URL; ?>/security.php" class="quicklink-item">
                        <i class="bi bi-shield-lock"></i> Security
                    </a>
                    <a href="<?php echo APP_URL; ?>/face-verify.php" class="quicklink-item">
                        <i class="bi bi-camera-video"></i> Face Verification
                    </a>
                    <a href="<?php echo APP_URL; ?>/history.php" class="quicklink-item">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                    <a href="<?php echo APP_URL; ?>/api/logout.php" class="quicklink-item quicklink-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
                <?php if (!$user['face_registered']): ?>
                    <a href="<?php echo APP_URL; ?>/face-register.php" class="btn btn-brand w-100 mt-3">
                        <i class="bi bi-camera-video me-1"></i> Register Your Face
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent authentication history -->
        <div class="col-lg-8">
            <div class="card stat-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-semibold mb-0">Recent Activity</h5>
                    <a href="<?php echo APP_URL; ?>/history.php" class="small">View all</a>
                </div>

                <?php if (empty($recentHistory)): ?>
                    <p class="text-muted mb-0">No authentication activity yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table history-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentHistory as $entry): ?>
                                    <?php $ts = strtotime($entry['created_at']); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('M j, Y', $ts)); ?></td>
                                        <td><?php echo htmlspecialchars(date('g:i A', $ts)); ?></td>
                                        <td>
                                            <i class="bi <?php echo methodIcon($entry['authentication_type']); ?> me-1 text-muted"></i>
                                            <?php echo htmlspecialchars(methodLabel($entry['authentication_type'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge-pill badge-<?php echo $entry['status'] === 'success' ? 'good' : 'bad'; ?>">
                                                <?php echo $entry['status'] === 'success' ? 'Success' : 'Failed'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
