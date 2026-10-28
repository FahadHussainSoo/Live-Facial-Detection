<?php
require_once __DIR__ . '/config/config.php';

// Only signed-in users may view their own profile.
requireLogin();

$pageTitle = 'Profile';
$userId = (int) $_SESSION['user_id'];

$user = [
    'full_name'       => $_SESSION['user_name'] ?? '',
    'email'           => $_SESSION['user_email'] ?? '',
    'phone'           => null,
    'status'          => 'active',
    'face_registered' => false,
    'created_at'      => null,
];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        'SELECT full_name, email, phone, status, face_registered, created_at
         FROM users WHERE id = :id'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if ($row !== false) {
        $user = [
            'full_name'       => $row['full_name'],
            'email'           => $row['email'],
            'phone'           => $row['phone'],
            'status'          => $row['status'],
            'face_registered' => !empty($row['face_registered']),
            'created_at'      => $row['created_at'],
        ];
    }
} catch (PDOException $e) {
    error_log('Profile load failed: ' . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5 profile-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Profile</h2>
            <p class="text-muted mb-0">Your account details.</p>
        </div>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card stat-card p-4 text-center h-100">
                <div class="profile-avatar mx-auto mb-3">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="badge-pill badge-<?php echo $user['status'] === 'active' ? 'good' : 'bad'; ?> mx-auto">
                    <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                </span>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card stat-card p-4 h-100">
                <h5 class="fw-semibold mb-3">Account Details</h5>
                <ul class="list-unstyled detail-list mb-0">
                    <li>
                        <span class="label">Full Name</span>
                        <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </li>
                    <li>
                        <span class="label">Email</span>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </li>
                    <li>
                        <span class="label">Phone</span>
                        <span><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></span>
                    </li>
                    <li>
                        <span class="label">Account Status</span>
                        <span class="badge-pill badge-<?php echo $user['status'] === 'active' ? 'good' : 'bad'; ?>">
                            <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                        </span>
                    </li>
                    <li>
                        <span class="label">Face Registration</span>
                        <?php if ($user['face_registered']): ?>
                            <span class="badge-pill badge-good">Registered</span>
                        <?php else: ?>
                            <span class="badge-pill badge-warn">Not Set Up</span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="label">Member Since</span>
                        <span>
                            <?php echo $user['created_at']
                                ? htmlspecialchars(date('F j, Y', strtotime($user['created_at'])))
                                : '—'; ?>
                        </span>
                    </li>
                </ul>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="<?php echo APP_URL; ?>/face-register.php" class="btn btn-brand">
                        <i class="bi bi-camera-video me-1"></i>
                        <?php echo $user['face_registered'] ? 'Update Registered Face' : 'Register Your Face'; ?>
                    </a>
                    <a href="<?php echo APP_URL; ?>/security.php" class="btn btn-outline-secondary">
                        <i class="bi bi-shield-lock me-1"></i> Security Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
