<?php
/**
 * Reusable Navbar
 * Relies on isLoggedIn() from config.php to adjust the menu.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?php echo APP_URL; ?>/index.php">
            <i class="bi bi-shield-lock-fill me-2"></i><?php echo htmlspecialchars(APP_NAME); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>"
                       href="<?php echo APP_URL; ?>/index.php">Home</a>
                </li>

                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'security.php' ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/security.php">Security</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo in_array($currentPage, ['face-verify.php', 'face-register.php'], true) ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/face-verify.php">Face Verification</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'history.php' ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/history.php">History</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-light btn-sm" href="<?php echo APP_URL; ?>/api/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'signin.php' ? 'active' : ''; ?>"
                           href="<?php echo APP_URL; ?>/signin.php">Sign In</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-light btn-sm fw-semibold" href="<?php echo APP_URL; ?>/signup.php">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
