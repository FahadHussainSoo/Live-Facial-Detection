<?php
require_once __DIR__ . '/config/config.php';

// If already logged in, no need to sign up again.
if (isLoggedIn()) {
    redirectTo(APP_URL . '/dashboard.php');
}

$pageTitle = 'Sign Up';

$errors = getFlashErrors('signup');
$old    = getFlashOld('signup');
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="text-center mb-4">
                <i class="bi bi-person-plus-fill" style="font-size: 2.5rem; color: var(--brand-primary);"></i>
                <h3 class="fw-bold mt-2 mb-1">Create Your Account</h3>
                <p class="text-muted small mb-0">Step 1 of your facial authentication setup.</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo APP_URL; ?>/api/signup-process.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="mb-3">
                    <label for="fullName" class="form-label">Full Name</label>
                    <input
                        type="text"
                        class="form-control <?php echo !empty($errors['full_name']) ? 'is-invalid' : ''; ?>"
                        id="fullName"
                        name="full_name"
                        placeholder="Jane Doe"
                        value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>"
                        maxlength="150"
                        required>
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['full_name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>"
                        id="email"
                        name="email"
                        placeholder="jane@example.com"
                        value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
                        maxlength="150"
                        required>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input
                        type="tel"
                        class="form-control <?php echo !empty($errors['phone']) ? 'is-invalid' : ''; ?>"
                        id="phone"
                        name="phone"
                        placeholder="+1 555 123 4567"
                        value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>"
                        maxlength="20"
                        required>
                    <?php if (!empty($errors['phone'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
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
                            placeholder="At least 8 characters, with a letter and a number"
                            required>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                            id="confirmPassword"
                            name="confirm_password"
                            placeholder="Re-enter your password"
                            required>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="confirmPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100 py-2 mt-2">Create Account</button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                Already have an account?
                <a href="<?php echo APP_URL; ?>/signin.php" class="text-decoration-none">Sign in</a>
            </p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
