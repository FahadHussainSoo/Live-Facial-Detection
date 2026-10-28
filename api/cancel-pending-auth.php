<?php
/**
 * api/cancel-pending-auth.php
 *
 * Lets a user abandon a pending (password-verified, face-not-yet-
 * verified) sign-in attempt — e.g. clicking "Use Password" / "Back
 * to Sign In" from face-verify.php — without leaving a pending
 * session sitting around. This never touches $_SESSION['user_id']
 * and cannot grant access to anything; it only clears state.
 */

require_once __DIR__ . '/../config/config.php';

clearPendingFaceAuth();
unset($_SESSION['redirect_after_login']);

redirectTo(APP_URL . '/signin.php');
