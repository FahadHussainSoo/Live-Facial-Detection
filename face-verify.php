<?php
require_once __DIR__ . '/config/config.php';

// Comparing a live face against "the account's registered embedding"
// only makes sense once we know who is attempting to sign in — but
// crucially, only the PASSWORD stage needs to have succeeded, not a
// full login. A fully authenticated session is never required (or
// even possible) to reach this page as part of a normal sign-in.
requirePendingFaceAuth();

$pendingUserId = (int) $_SESSION['pending_user_id'];

// Check up front whether this account has a face on file yet. If
// not, we don't pretend to run a comparison against nothing — we
// send the user to register a face instead, and DO NOT grant access
// in the meantime.
$hasRegisteredFace = false;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT 1 FROM face_authentication WHERE user_id = :id');
    $stmt->execute(['id' => $pendingUserId]);
    $hasRegisteredFace = $stmt->fetch() !== false;
} catch (PDOException $e) {
    error_log('Could not check registered-face status: ' . $e->getMessage());
}

$csrfToken = generateCsrfToken();

$pageTitle = 'Face Verify';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold mb-1">Face Verification</h2>
        <p class="text-muted">Verify your identity using your registered face.</p>
    </div>

    <?php if (!$hasRegisteredFace): ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Face verification is not available for this account.
            </div>
            <p class="text-muted small">
                This account doesn't have a face registered yet. You'll need to register one
                before you can sign in with facial verification.
            </p>
            <div class="d-grid gap-2">
                <a href="<?php echo APP_URL; ?>/face-register.php" class="btn btn-brand">
                    <i class="bi bi-camera-video me-1"></i> Register My Face
                </a>
                <a href="<?php echo APP_URL; ?>/api/cancel-pending-auth.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="camera-box" id="cameraBox">
                <video id="cameraVideo" autoplay playsinline muted hidden></video>
                <canvas class="face-overlay-canvas" id="faceOverlayCanvas" hidden></canvas>

                <div class="camera-placeholder" id="cameraPlaceholder">
                    <i class="bi bi-camera-video-off"></i>
                    <h5 class="fw-semibold mb-1">Camera Preview</h5>
                    <p class="mb-0 small">
                        Click "Allow Camera Access" below to enable your webcam.
                        Nothing is recorded, captured, or uploaded — this is a live, local preview only.
                    </p>
                </div>

                <div class="camera-status-badge" id="cameraStatusBadge" hidden>
                    <span class="status-dot"></span>
                    <span id="cameraStatusText">Camera active</span>
                </div>

                <div class="face-status-badge" id="faceStatusBadge" hidden>
                    <span id="faceStatusText">No face detected</span>
                </div>

                <div class="face-guidance-banner tone-neutral" id="faceGuidanceBanner" hidden>
                    <span id="faceGuidanceText"></span>
                </div>
            </div>

            <div class="alert alert-danger mt-3" id="cameraErrorAlert" role="alert" hidden></div>

            <div class="d-grid gap-2 mt-4">
                <button type="button" class="btn btn-brand" id="startCameraBtn">
                    <i class="bi bi-camera-video me-1"></i>
                    <span id="startCameraBtnLabel">Allow Camera Access</span>
                </button>
                <button type="button" class="btn btn-outline-danger" id="stopCameraBtn" disabled hidden>
                    <i class="bi bi-camera-video-off me-1"></i> Stop Camera
                </button>
            </div>

            <div class="text-center mt-3">
                <span class="status-pill" id="verifyStatusBadge">
                    <i class="bi bi-shield-lock me-1"></i>
                    <span id="verifyStatusText">Position your face</span>
                </span>
            </div>

            <div class="alert alert-success mt-3" id="verifySuccessAlert" role="alert" hidden></div>
            <div class="alert alert-warning mt-3" id="verifyWarningAlert" role="alert" hidden></div>
            <div class="alert alert-danger mt-3" id="verifyErrorAlert" role="alert" hidden></div>

            <div class="d-grid mt-3">
                <button type="button" class="btn btn-brand" id="verifyFaceBtn" disabled hidden>
                    <i class="bi bi-shield-check me-1"></i> Verify My Face
                </button>
            </div>

            <p class="text-center text-muted small mt-2" id="verifyHint">
                A live embedding is compared to your account's registered face. Detecting a face is not the same
                as a match — only a genuine comparison result is shown.
            </p>

            <!-- PHASE 10 — ACCESS GRANTED -->
            <div class="result-panel result-panel-granted mt-3" id="resultGrantedPanel" hidden>
                <div class="result-icon"><i class="bi bi-check-circle-fill"></i></div>
                <h4 class="fw-bold mb-1">ACCESS GRANTED</h4>
                <p class="mb-3">Face verification successful.</p>
                <ul class="list-unstyled result-details">
                    <li><span class="label">User</span><span id="resultUserName"></span></li>
                    <li><span class="label">Verification time</span><span id="resultVerifiedTime"></span></li>
                    <li><span class="label">Status</span><span class="badge-verified" id="resultVerifiedStatus">Verified</span></li>
                </ul>
                <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-brand" id="continueToDashboardBtn">
                    <i class="bi bi-speedometer2 me-1"></i> Continue to Dashboard
                </a>
            </div>

            <!-- PHASE 10 — ACCESS DENIED -->
            <div class="result-panel result-panel-denied mt-3" id="resultDeniedPanel" hidden>
                <div class="result-icon"><i class="bi bi-x-circle-fill"></i></div>
                <h4 class="fw-bold mb-1">ACCESS DENIED</h4>
                <p class="mb-3">Face verification failed.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" id="tryAgainBtn">
                        <i class="bi bi-arrow-repeat me-1"></i> Try Again
                    </button>
                    <a href="<?php echo APP_URL; ?>/api/cancel-pending-auth.php" class="btn btn-brand" id="usePasswordBtn">
                        <i class="bi bi-key me-1"></i> Use Password
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php if ($hasRegisteredFace): ?>
<!-- face-api.js (TensorFlow.js-based, client-side only — no frames leave the browser) -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/camera.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/face-detection.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/face-verify.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const verifyBtn = document.getElementById('verifyFaceBtn');

        // Assigned once initLiveCamera runs (see below); referenced by
        // the onGranted callback closure so the camera can be shut off
        // the moment ACCESS GRANTED is shown.
        let cameraController = null;

        const faceVerification = initFaceVerification({
            videoId: 'cameraVideo',
            verifyBtnId: 'verifyFaceBtn',
            statusTextId: 'verifyStatusText',
            successAlertId: 'verifySuccessAlert',
            warningAlertId: 'verifyWarningAlert',
            errorAlertId: 'verifyErrorAlert',
            csrfToken: <?php echo json_encode($csrfToken); ?>,
            apiUrl: <?php echo json_encode(APP_URL . '/api/face-verify-process.php'); ?>,
            hintId: 'verifyHint',
            resultGrantedPanelId: 'resultGrantedPanel',
            resultDeniedPanelId: 'resultDeniedPanel',
            resultUserNameId: 'resultUserName',
            resultVerifiedTimeId: 'resultVerifiedTime',
            tryAgainBtnId: 'tryAgainBtn',
            continueBtnId: 'continueToDashboardBtn',
            onGranted: function () {
                if (cameraController) cameraController.stop();
            },
        });

        const faceDetection = initFaceDetection({
            videoId: 'cameraVideo',
            canvasId: 'faceOverlayCanvas',
            faceStatusBadgeId: 'faceStatusBadge',
            faceStatusTextId: 'faceStatusText',
            guidanceBannerId: 'faceGuidanceBanner',
            guidanceTextId: 'faceGuidanceText',
            onUpdate: function (state) {
                if (faceVerification) faceVerification.onDetectionUpdate(state);
            },
        });
        const faceOverlayCanvas = document.getElementById('faceOverlayCanvas');

        cameraController = initLiveCamera({
            videoId: 'cameraVideo',
            startBtnId: 'startCameraBtn',
            startBtnLabelId: 'startCameraBtnLabel',
            stopBtnId: 'stopCameraBtn',
            placeholderId: 'cameraPlaceholder',
            statusBadgeId: 'cameraStatusBadge',
            statusTextId: 'cameraStatusText',
            errorAlertId: 'cameraErrorAlert',
            onStart: function () {
                if (faceOverlayCanvas) faceOverlayCanvas.hidden = false;
                if (verifyBtn) verifyBtn.hidden = false;
                if (faceDetection) faceDetection.start();
            },
            onStop: function () {
                if (faceDetection) faceDetection.stop();
                if (faceOverlayCanvas) faceOverlayCanvas.hidden = true;
                if (verifyBtn) verifyBtn.hidden = true;
            },
        });
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
