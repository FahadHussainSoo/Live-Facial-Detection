<?php
require_once __DIR__ . '/config/config.php';

// Face registration is reachable in two situations:
//   1. A fully signed-in user (normal case: right after signup).
//   2. A PENDING session (password just verified at login) whose
//      account has no face on file yet — first-time enrollment.
// Either way, the user id always comes from server-side session
// state, never from anything the client sends.
$registerForUserId = requireFaceRegistrationAccess();
$isPendingEnrollment = !isLoggedIn(); // true only for case 2 above

$successMessage = getFlashSuccess();
$csrfToken = generateCsrfToken();

// Just for the friendly notice below — doesn't change what the flow does.
$alreadyRegistered = false;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT face_registered FROM users WHERE id = :id');
    $stmt->execute(['id' => $registerForUserId]);
    $row = $stmt->fetch();
    $alreadyRegistered = !empty($row['face_registered']);
} catch (PDOException $e) {
    error_log('Could not check face_registered status: ' . $e->getMessage());
}

// SECURITY: a pending (password-only) session must NEVER be allowed
// to re-register/replace a face that already exists — that would let
// anyone who merely knows the password overwrite the second factor
// and grant themselves access. If this account already has a face on
// file, a pending session can only reach it through real
// verification (face-verify.php), never through this page.
if ($isPendingEnrollment && $alreadyRegistered) {
    redirectTo(APP_URL . '/face-verify.php');
}

$pageTitle = 'Face Register';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold mb-1">Face Registration</h2>
        <p class="text-muted">Enroll your face for authentication using your live webcam.</p>
    </div>

    <?php if ($successMessage): ?>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="alert alert-success" role="alert" data-auto-dismiss>
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($alreadyRegistered): ?>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    You already have a face registered on this account. Registering again will replace it.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isPendingEnrollment): ?>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    This account doesn't have a face on file yet, so we can't run facial verification.
                    Register your face now to finish signing in and enable it going forward.
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                <span class="reg-status-pill" id="regStatusBadge">
                    <i class="bi bi-person-bounding-box me-1"></i>
                    <span id="regStatusText">Position your face</span>
                </span>
            </div>

            <div class="alert alert-success mt-3" id="regSuccessAlert" role="alert" hidden></div>
            <div class="alert alert-danger mt-3" id="regErrorAlert" role="alert" hidden></div>

            <div class="d-grid mt-3">
                <button type="button" class="btn btn-success" id="registerFaceBtn" disabled hidden>
                    <i class="bi bi-person-check-fill me-1"></i> Register My Face
                </button>
            </div>

            <div class="d-grid mt-3" id="regContinueActions" hidden>
                <a href="<?php echo APP_URL; ?>/signin.php" class="btn btn-brand">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
                </a>
            </div>

            <p class="text-center text-muted small mt-2">
                Only a numeric face embedding is generated and saved — no photo or video is ever stored or uploaded.
            </p>
        </div>
    </div>
</main>

<!-- face-api.js (TensorFlow.js-based, client-side only — no frames leave the browser) -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/camera.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/face-detection.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/face-register.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const registerBtn = document.getElementById('registerFaceBtn');

        const faceRegistration = initFaceRegistration({
            videoId: 'cameraVideo',
            registerBtnId: 'registerFaceBtn',
            regStatusTextId: 'regStatusText',
            successAlertId: 'regSuccessAlert',
            errorAlertId: 'regErrorAlert',
            continueActionsId: 'regContinueActions',
            csrfToken: <?php echo json_encode($csrfToken); ?>,
            apiUrl: <?php echo json_encode(APP_URL . '/api/face-register-process.php'); ?>,
        });

        const faceDetection = initFaceDetection({
            videoId: 'cameraVideo',
            canvasId: 'faceOverlayCanvas',
            faceStatusBadgeId: 'faceStatusBadge',
            faceStatusTextId: 'faceStatusText',
            guidanceBannerId: 'faceGuidanceBanner',
            guidanceTextId: 'faceGuidanceText',
            onUpdate: function (state) {
                if (faceRegistration) faceRegistration.onDetectionUpdate(state);
            },
        });
        const faceOverlayCanvas = document.getElementById('faceOverlayCanvas');

        initLiveCamera({
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
                if (registerBtn) registerBtn.hidden = false;
                if (faceDetection) faceDetection.start();
            },
            onStop: function () {
                if (faceDetection) faceDetection.stop();
                if (faceOverlayCanvas) faceOverlayCanvas.hidden = true;
                if (registerBtn) registerBtn.hidden = true;
            },
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>