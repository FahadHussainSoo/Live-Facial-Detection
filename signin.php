<?php
require_once __DIR__ . '/config/config.php';

// If already logged in, no need to sign in again.
if (isLoggedIn()) {
    redirectTo(APP_URL . '/dashboard.php');
}

$pageTitle = 'Sign In';

$errors = getFlashErrors('signin');
$old    = getFlashOld('signin');
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem; color: var(--brand-primary);"></i>
                <h3 class="fw-bold mt-2 mb-1">Welcome Back</h3>
                <p class="text-muted small mb-0">Sign in with your credentials, then verify with your face.</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo APP_URL; ?>/api/signin-process.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="mb-3">
                    <label for="identifier" class="form-label">Email or Phone Number</label>
                    <input
                        type="text"
                        class="form-control <?php echo !empty($errors['identifier']) ? 'is-invalid' : ''; ?>"
                        id="identifier"
                        name="identifier"
                        placeholder="jane@example.com or +1 555 123 4567"
                        value="<?php echo htmlspecialchars($old['identifier'] ?? ''); ?>"
                        autocomplete="username"
                        required>
                    <?php if (!empty($errors['identifier'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['identifier']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100 py-2 mt-2">Sign In</button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Sign in with your password first — you'll then verify with your live face before you can access your dashboard.
            </p>

            <p class="text-center text-muted small mt-4 mb-0">
                Don't have an account?
                <a href="<?php echo APP_URL; ?>/signup.php" class="text-decoration-none">Sign up</a>
            </p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
