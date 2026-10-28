/**
 * camera.js
 * Reusable live-camera controller for face-register.php and face-verify.php.
 *
 * Phase 6 scope ONLY:
 *   - Ask for camera permission and show a live local preview.
 *   - The camera is NEVER started automatically — only in direct
 *     response to a user clicking the "Allow Camera Access" /
 *     "Start Camera" button.
 *   - Nothing is captured, recorded, or uploaded. No frames are ever
 *     sent anywhere; the video element is a live passthrough only.
 *   - All tracks are explicitly stopped when the user clicks
 *     "Stop Camera", and also on page unload, so the camera light
 *     never stays on longer than the user asked for.
 *
 * Facial recognition / capture logic is intentionally NOT implemented
 * here — that comes in a later phase.
 */

function initLiveCamera(options) {
    const video          = document.getElementById(options.videoId);
    const startBtn        = document.getElementById(options.startBtnId);
    const startBtnLabel   = document.getElementById(options.startBtnLabelId);
    const stopBtn         = document.getElementById(options.stopBtnId);
    const placeholder      = document.getElementById(options.placeholderId);
    const statusBadge      = document.getElementById(options.statusBadgeId);
    const statusText       = document.getElementById(options.statusTextId);
    const errorAlert       = document.getElementById(options.errorAlertId);
    const onCameraStart    = typeof options.onStart === 'function' ? options.onStart : function () {};
    const onCameraStop     = typeof options.onStop === 'function' ? options.onStop : function () {};

    if (!video || !startBtn || !stopBtn) {
        return; // Markup for this page isn't present — nothing to wire up.
    }

    let activeStream = null;
    let permissionEverGranted = false;

    function showError(message) {
        if (!errorAlert) return;
        errorAlert.textContent = message;
        errorAlert.hidden = false;
    }

    function clearError() {
        if (!errorAlert) return;
        errorAlert.hidden = true;
        errorAlert.textContent = '';
    }

    function setStatus(text) {
        if (statusText) statusText.textContent = text;
    }

    function isBrowserSupported() {
        return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
    }

    function enterActiveState() {
        video.hidden = false;
        if (placeholder) placeholder.hidden = true;
        if (statusBadge) statusBadge.hidden = false;
        setStatus('Camera active');

        startBtn.hidden = true;
        stopBtn.disabled = false;
        stopBtn.hidden = false;
    }

    function enterIdleState() {
        video.hidden = true;
        if (placeholder) placeholder.hidden = false;
        if (statusBadge) statusBadge.hidden = true;

        startBtn.hidden = false;
        startBtn.disabled = false;
        if (startBtnLabel) {
            startBtnLabel.textContent = permissionEverGranted ? 'Start Camera' : 'Allow Camera Access';
        }
        stopBtn.disabled = true;
        stopBtn.hidden = true;
    }

    function stopCamera() {
        if (activeStream) {
            activeStream.getTracks().forEach(function (track) {
                track.stop();
            });
            activeStream = null;
        }
        video.srcObject = null;
        enterIdleState();
        onCameraStop();
    }

    async function startCamera() {
        clearError();

        if (!isBrowserSupported()) {
            showError('Your browser does not support camera access. Please try a recent version of Chrome, Firefox, Edge, or Safari.');
            startBtn.disabled = true;
            return;
        }

        startBtn.disabled = true;
        setStatus('Requesting camera access…');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });

            activeStream = stream;
            permissionEverGranted = true;
            video.srcObject = stream;
            await video.play().catch(function () {
                /* Autoplay-with-user-gesture should always succeed here since
                   this only runs inside a click handler, but ignore benign
                   AbortError races if the user immediately hits Stop. */
            });

            enterActiveState();
            onCameraStart(video);
        } catch (err) {
            startBtn.disabled = false;

            switch (err && err.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                    showError('Camera access was denied. Please allow camera permissions for this site in your browser settings, then try again.');
                    break;
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    showError('No camera was found on this device. Connect a webcam and try again.');
                    break;
                case 'NotReadableError':
                case 'TrackStartError':
                    showError('Your camera could not be started — it may already be in use by another application.');
                    break;
                case 'OverconstrainedError':
                    showError('No camera on this device meets the required constraints.');
                    break;
                case 'SecurityError':
                    showError('Camera access requires a secure (HTTPS) connection.');
                    break;
                default:
                    showError('Something went wrong while trying to access your camera. Please try again.');
            }

            enterIdleState();
        }
    }

    startBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', stopCamera);

    // Safety net: never let the camera stay on past the current page view.
    window.addEventListener('pagehide', stopCamera);
    window.addEventListener('beforeunload', stopCamera);

    // Initial state: camera OFF until the user explicitly asks for it.
    enterIdleState();

    if (!isBrowserSupported()) {
        showError('Your browser does not support camera access. Please try a recent version of Chrome, Firefox, Edge, or Safari.');
        startBtn.disabled = true;
    }

    // Exposed so a calling page can shut the camera off proactively
    // (e.g. immediately after a successful verification, rather than
    // waiting for page unload) or query whether it's currently on.
    return {
        stop: stopCamera,
        isActive: function () { return !!activeStream; },
    };
}
