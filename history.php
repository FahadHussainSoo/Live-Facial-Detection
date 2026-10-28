<?php
require_once __DIR__ . '/config/config.php';

// Only signed-in users may view their own authentication history.
requireLogin();

$pageTitle = 'History';
$userId = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------
// PHASE 11 — Authentication history.
// Every row here comes straight from authentication_logs, scoped
// ONLY to the current session's own user_id — never another
// account's data. No facial recognition logic is touched here.
// ---------------------------------------------------------------
const HISTORY_PAGE_SIZE = 20;

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * HISTORY_PAGE_SIZE;

$typeFilter = $_GET['type'] ?? 'all';
$allowedTypes = ['all', 'face', 'password', 'password_face'];
if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = 'all';
}

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'success', 'failed'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$entries = [];
$totalCount = 0;

try {
    $pdo = getDBConnection();

    $where = 'WHERE user_id = :id';
    $params = ['id' => $userId];

    if ($typeFilter !== 'all') {
        $where .= ' AND authentication_type = :type';
        $params['type'] = $typeFilter;
    }
    if ($statusFilter !== 'all') {
        $where .= ' AND status = :status';
        $params['status'] = $statusFilter;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM authentication_logs {$where}");
    $countStmt->execute($params);
    $totalCount = (int) ($countStmt->fetch()['total'] ?? 0);

    $listStmt = $pdo->prepare(
        "SELECT created_at, authentication_type, status, ip_address
         FROM authentication_logs {$where}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $listStmt->bindValue(':' . $key, $value);
    }
    $listStmt->bindValue(':limit', HISTORY_PAGE_SIZE, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $entries = $listStmt->fetchAll();
} catch (PDOException $e) {
    error_log('History load failed: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($totalCount / HISTORY_PAGE_SIZE));

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

function buildQuery(array $overrides = []): string
{
    global $typeFilter, $statusFilter, $page;
    $params = array_merge([
        'type'   => $typeFilter,
        'status' => $statusFilter,
        'page'   => $page,
    ], $overrides);
    return '?' . http_build_query($params);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5 history-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Authentication History</h2>
            <p class="text-muted mb-0">Every sign-in and face verification attempt on your account.</p>
        </div>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="card stat-card p-3 p-md-4 mb-4">
        <form method="get" class="row g-2 align-items-end history-filters">
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Method</label>
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All methods</option>
                    <option value="face" <?php echo $typeFilter === 'face' ? 'selected' : ''; ?>>Face Verification</option>
                    <option value="password" <?php echo $typeFilter === 'password' ? 'selected' : ''; ?>>Password</option>
                    <option value="password_face" <?php echo $typeFilter === 'password_face' ? 'selected' : ''; ?>>Password + Face</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                    <option value="success" <?php echo $statusFilter === 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="col-12 col-md-6 text-md-end text-muted small">
                <?php echo (int) $totalCount; ?> total record<?php echo $totalCount === 1 ? '' : 's'; ?>
            </div>
        </form>
    </div>

    <div class="card stat-card p-3 p-md-4">
        <?php if (empty($entries)): ?>
            <p class="text-muted mb-0 py-4 text-center">No matching authentication activity found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table history-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Authentication Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <?php $ts = strtotime($entry['created_at']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('M j, Y', $ts)); ?></td>
                                <td><?php echo htmlspecialchars(date('g:i:s A', $ts)); ?></td>
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

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4" aria-label="History pagination">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildQuery(['page' => max(1, $page - 1)]); ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildQuery(['page' => $p]); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildQuery(['page' => min($totalPages, $page + 1)]); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
