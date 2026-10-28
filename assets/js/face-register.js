/**
 * face-register.js
 * Drives the Phase 8 registration flow on top of the existing camera
 * (camera.js) and live face detection (face-detection.js):
 *
 *   Start camera → detect exactly one face → guide into position →
 *   user clicks "Register My Face" → generate a REAL facial embedding
 *   client-side with face-api.js → send ONLY that embedding (128
 *   numbers, never an image/video frame) to the server → server saves
 *   it for the signed-in user and sets face_registered = 1.
 *
 * Explicitly NOT done here:
 *   - No raw video/image is ever captured, stored, or uploaded.
 *   - No made-up/placeholder embedding is ever sent — if a real
 *     descriptor can't be computed at capture time, registration is
 *     aborted and the user is asked to try again.
 *   - The account being registered is decided entirely by the
 *     server-side session, never by anything sent from this script.
 */

function initFaceRegistration(options) {
    const video          = document.getElementById(options.videoId);
    const registerBtn    = document.getElementById(options.registerBtnId);
    const regStatusText  = document.getElementById(options.regStatusTextId);
    const successAlert   = document.getElementById(options.successAlertId);
    const errorAlert     = document.getElementById(options.errorAlertId);
    const csrfToken       = options.csrfToken;
    const apiUrl          = options.apiUrl;

    if (!video || !registerBtn) {
        return null;
    }

    const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';

    let modelsReady    = false;
    let modelsLoading  = false;
    let isRegistering  = false;
    let alreadyDone    = false;
    let currentState   = { faceCount: 0, tone: 'neutral', message: '' };

    function setRegStatus(text) {
        if (regStatusText) regStatusText.textContent = text;
    }

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

    function showSuccess(message) {
        if (!successAlert) return;
        successAlert.textContent = message;
        successAlert.hidden = false;
    }

    function isWellPositioned() {
        return currentState.faceCount === 1 && currentState.tone === 'good';
    }

    function updateButtonState() {
        registerBtn.disabled = alreadyDone || isRegistering || !isWellPositioned();
    }

    async function ensureModelsLoaded() {
        if (modelsReady) return true;
        if (modelsLoading) {
            // Wait for the in-flight load rather than starting a second one.
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
     * Called by face-detection.js on every processed frame so this
     * module always knows the current live face state without running
     * its own separate detection loop.
     */
    function onDetectionUpdate(state) {
        currentState = state;

        if (!alreadyDone && !isRegistering) {
            if (state.faceCount !== 1) {
                setRegStatus('Position your face');
            } else if (state.tone === 'good') {
                setRegStatus('Hold still');
            } else {
                setRegStatus('Face detected');
            }
        }

        updateButtonState();
    }

    async function handleRegisterClick() {
        if (alreadyDone || isRegistering || !isWellPositioned()) {
            return;
        }

        clearError();
        isRegistering = true;
        updateButtonState();
        setRegStatus('Registering...');

        const modelsOk = await ensureModelsLoaded();
        if (!modelsOk) {
            isRegistering = false;
            setRegStatus('Hold still');
            updateButtonState();
            return;
        }

        try {
            // Compute a REAL embedding from the current live frame.
            // Nothing here writes an image anywhere — `detection.descriptor`
            // is a Float32Array of 128 numbers describing the face.
            const detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks(true)
                .withFaceDescriptor();

            if (!detection || !detection.descriptor) {
                showError('Could not get a clear reading of your face just then. Hold still and try again.');
                isRegistering = false;
                setRegStatus('Hold still');
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
                alreadyDone = true;
                setRegStatus('Face registered successfully');
                showSuccess(result.message || 'Your face has been registered successfully.');
                registerBtn.hidden = true;

                // Only set for a login-time (pending) first-time
                // enrollment — that live capture just completed
                // sign-in, so move on to the destination automatically.
                if (result.redirect) {
                    setTimeout(function () {
                        window.location.href = result.redirect;
                    }, 900);
                }
            } else {
                showError(result.message || 'Face registration failed. Please try again.');
                isRegistering = false;
                setRegStatus('Hold still');
            }
        } catch (err) {
            showError('A network error occurred while registering your face. Please try again.');
            isRegistering = false;
            setRegStatus('Hold still');
        }

        updateButtonState();
    }

    registerBtn.addEventListener('click', handleRegisterClick);
    setRegStatus('Position your face');
    updateButtonState();

    return { onDetectionUpdate: onDetectionUpdate };
}
