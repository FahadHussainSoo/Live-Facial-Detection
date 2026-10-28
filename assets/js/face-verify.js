/**
 * face-verify.js
 * Drives the verification flow on top of the existing camera
 * (camera.js) and live face detection (face-detection.js):
 *
 *   Detect exactly one, well-positioned face → user clicks
 *   "Verify My Face" → generate a REAL live embedding client-side →
 *   send ONLY that embedding to the server → server compares it to
 *   the signed-in user's registered embedding and returns a genuine
 *   match/no-match result.
 *
 * IMPORTANT: detecting a face is never treated as success by itself.
 * The button only becomes available once a single face is well
 * positioned, but the actual pass/fail decision always comes back
 * from the server's real embedding comparison — this script never
 * invents or assumes a result.
 *
 * PHASE 10 — ACCESS RESULT
 *   - Match      → full "ACCESS GRANTED" panel (name, verification
 *                  time, verified status) with a link into the
 *                  protected dashboard.
 *   - No match   → full "ACCESS DENIED" panel with "Try Again" /
 *                  "Use Password" actions.
 *   - Multiple faces present → "Only one person should be visible."
 *                  and verification is never attempted.
 *   - Every real comparison attempt is logged server-side as
 *     success/failed; no comparison internals are ever shown here.
 */

function initFaceVerification(options) {
    const video          = document.getElementById(options.videoId);
    const verifyBtn      = document.getElementById(options.verifyBtnId);
    const statusText     = document.getElementById(options.statusTextId);
    const successAlert   = document.getElementById(options.successAlertId);
    const warningAlert   = document.getElementById(options.warningAlertId);
    const errorAlert     = document.getElementById(options.errorAlertId);
    const csrfToken       = options.csrfToken;
    const apiUrl          = options.apiUrl;

    const hint              = options.hintId ? document.getElementById(options.hintId) : null;
    const grantedPanel      = options.resultGrantedPanelId ? document.getElementById(options.resultGrantedPanelId) : null;
    const deniedPanel       = options.resultDeniedPanelId ? document.getElementById(options.resultDeniedPanelId) : null;
    const resultUserName    = options.resultUserNameId ? document.getElementById(options.resultUserNameId) : null;
    const resultVerifiedTime = options.resultVerifiedTimeId ? document.getElementById(options.resultVerifiedTimeId) : null;
    const tryAgainBtn       = options.tryAgainBtnId ? document.getElementById(options.tryAgainBtnId) : null;
    const continueBtn       = options.continueBtnId ? document.getElementById(options.continueBtnId) : null;
    const onGranted          = typeof options.onGranted === 'function' ? options.onGranted : function () {};

    if (!video || !verifyBtn) {
        return null;
    }

    const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';

    let modelsReady   = false;
    let modelsLoading = false;
    let isVerifying   = false;
    let resultShown   = false; // true once ACCESS GRANTED/DENIED is on screen
    let currentState  = { faceCount: 0, tone: 'neutral', message: '' };

    function setStatus(text) {
        if (statusText) statusText.textContent = text;
    }

    function formatVerificationTime(isoLike) {
        // Server sends 'Y-m-d H:i:s' (server local time). Fall back to
        // "now" if it's ever missing so the panel is never blank.
        const date = isoLike ? new Date(isoLike.replace(' ', 'T')) : new Date();
        if (isNaN(date.getTime())) {
            return new Date().toLocaleString();
        }
        return date.toLocaleString();
    }

    /**
     * Swaps the live camera/verify UI for one of the two full result
     * panels. Only ever called with a genuine server response.
     */
    function showResultPanel(kind, data) {
        resultShown = true;
        clearAlerts();
        if (verifyBtn) verifyBtn.hidden = true;
        if (hint) hint.hidden = true;

        if (kind === 'granted') {
            if (deniedPanel) deniedPanel.hidden = true;
            if (resultUserName) resultUserName.textContent = (data && data.user_name) || '';
            if (resultVerifiedTime) resultVerifiedTime.textContent = formatVerificationTime(data && data.verified_at);
            if (continueBtn && data && data.redirect) continueBtn.setAttribute('href', data.redirect);
            if (grantedPanel) grantedPanel.hidden = false;
            // Verification is complete — the camera is no longer needed,
            // so shut it off now rather than leaving it running until the
            // user navigates away.
            onGranted();
        } else if (kind === 'denied') {
            if (grantedPanel) grantedPanel.hidden = true;
            if (deniedPanel) deniedPanel.hidden = false;
        }
    }

    function hideResultPanels() {
        resultShown = false;
        if (grantedPanel) grantedPanel.hidden = true;
        if (deniedPanel) deniedPanel.hidden = true;
        if (hint) hint.hidden = false;
    }

    function clearAlerts() {
        [successAlert, warningAlert, errorAlert].forEach(function (el) {
            if (el) {
                el.hidden = true;
                el.textContent = '';
            }
        });
    }

    function showSuccess(message) {
        clearAlerts();
        if (successAlert) {
            successAlert.textContent = message;
            successAlert.hidden = false;
        }
    }

    function showWarning(message) {
        clearAlerts();
        if (warningAlert) {
            warningAlert.textContent = message;
            warningAlert.hidden = false;
        }
    }

    function showError(message) {
        clearAlerts();
        if (errorAlert) {
            errorAlert.textContent = message;
            errorAlert.hidden = false;
        }
    }

    function isWellPositioned() {
        return currentState.faceCount === 1 && currentState.tone === 'good';
    }

    function updateButtonState() {
        verifyBtn.disabled = isVerifying || !isWellPositioned();
    }

    async function ensureModelsLoaded() {
        if (modelsReady) return true;
        if (modelsLoading) {
            while (modelsLoading) {
                await new Promise(function (resolve) { setTimeout(resolve, 150); });
            }
            return modelsReady;
        }

        modelsLoading = true;
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);
            modelsReady = true;
            return true;
        } catch (err) {
            showError('Could not load the face recognition model. Check your connection and reload the page.');
            return false;
        } finally {
            modelsLoading = false;
        }
    }

    /**
     * Called by face-detection.js on every processed frame.
     */
    function onDetectionUpdate(state) {
        currentState = state;

        // A result panel (ACCESS GRANTED/DENIED) is already showing —
        // don't let live frame updates overwrite it or the alerts.
        if (resultShown) {
            updateButtonState();
            return;
        }

        if (!isVerifying) {
            if (state.faceCount === 0) {
                setStatus('No face detected');
                clearAlerts();
            } else if (state.faceCount > 1) {
                // PHASE 10: multiple people in frame — never authenticate.
                setStatus('Multiple faces detected');
                showWarning('Only one person should be visible.');
            } else if (state.tone === 'good') {
                setStatus('Hold still');
                clearAlerts();
            } else {
                setStatus('Face detected');
                clearAlerts();
            }
        }

        updateButtonState();
    }

    async function handleVerifyClick() {
        if (isVerifying || resultShown) {
            return;
        }

        // PHASE 10: multiple people in frame — refuse to even attempt
        // authentication, no matter how this got triggered.
        if (currentState.faceCount > 1) {
            setStatus('Multiple faces detected');
            showWarning('Only one person should be visible.');
            return;
        }

        if (!isWellPositioned()) {
            return;
        }

        clearAlerts();
        isVerifying = true;
        updateButtonState();
        setStatus('Verifying...');

        const modelsOk = await ensureModelsLoaded();
        if (!modelsOk) {
            isVerifying = false;
            setStatus('Hold still');
            updateButtonState();
            return;
        }

        try {
            // A fresh, real embedding from the current live frame —
            // never a stored, cached, or fabricated value.
            const detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks(true)
                .withFaceDescriptor();

            if (!detection || !detection.descriptor) {
                showError('Could not get a clear reading of your face just then. Hold still and try again.');
                isVerifying = false;
                setStatus('Hold still');
                updateButtonState();
                return;
            }

            const descriptor = Array.from(detection.descriptor);

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ descriptor: descriptor }),
            });

            let result;
            try {
                result = await response.json();
            } catch (parseErr) {
                result = { success: false, message: 'Unexpected server response.' };
            }

            if (response.ok && result.success) {
                if (result.status === 'match') {
                    // ACCESS GRANTED
                    setStatus('Registered face');
                    showResultPanel('granted', result);
                    isVerifying = false;
                    updateButtonState();
                    return;
                } else if (result.status === 'no_match') {
                    // ACCESS DENIED
                    setStatus('Different face');
                    showResultPanel('denied', result);
                    isVerifying = false;
                    updateButtonState();
                    return;
                } else if (result.status === 'not_registered') {
                    setStatus('No face registered');
                    showWarning(result.message || 'No face is registered on this account yet.');
                } else {
                    showError('Unexpected verification result. Please try again.');
                    setStatus('Hold still');
                }
            } else {
                showError(result.message || 'Face verification failed. Please try again.');
                setStatus('Hold still');
            }
        } catch (err) {
            showError('A network error occurred while verifying your face. Please try again.');
            setStatus('Hold still');
        }

        isVerifying = false;
        updateButtonState();
    }

    function handleTryAgain() {
        hideResultPanels();
        clearAlerts();
        isVerifying = false;
        setStatus(isWellPositioned() ? 'Hold still' : 'Position your face');
        updateButtonState();
        if (verifyBtn) verifyBtn.hidden = false;
    }

    verifyBtn.addEventListener('click', handleVerifyClick);
    if (tryAgainBtn) tryAgainBtn.addEventListener('click', handleTryAgain);
    setStatus('Position your face');
    updateButtonState();

    return { onDetectionUpdate: onDetectionUpdate };
}
