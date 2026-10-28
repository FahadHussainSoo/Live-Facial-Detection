/**
 * face-detection.js
 * Real-time face presence + position guidance for the live camera preview.
 *
 * Library: face-api.js (via the actively-maintained @vladmandic/face-api
 * fork), which wraps TensorFlow.js and runs entirely client-side in the
 * browser — no frames are ever sent to a server.
 *
 * Phase 7 scope ONLY:
 *   - Detect whether zero, one, or multiple faces are visible.
 *   - Draw a live bounding box / guide over the video preview.
 *   - Give live directional guidance ("Move left", "Move closer", etc.)
 *     based on the detected face's position/size versus the frame.
 *
 * Explicitly OUT of scope for this phase (do not add here):
 *   - Identifying WHO the person is (no face recognition/matching).
 *   - Any "Access Granted" / "Access Denied" decision.
 *   - Capturing, storing, or uploading any image/frame.
 */

/**
 * Pure decision function: given a detected face box (already resized to
 * the displayed canvas size) and the display dimensions, returns the
 * guidance message + a "tone" for styling. Kept separate from the
 * rendering loop below so it's easy to reason about and unit test.
 *
 * NOTE: box.x/box.width are in RAW (unmirrored) video coordinates. The
 * caller is responsible for interpreting horizontal offsets against the
 * mirrored display (see the mirroredCenterXNorm flip inside).
 */
function resolveFaceGuidance(box, displaySize, thresholds, stableFrames) {
    const rawCenterXNorm = (box.x + box.width / 2) / displaySize.width;
    const mirroredCenterXNorm = 1 - rawCenterXNorm;
    const centerYNorm = (box.y + box.height / 2) / displaySize.height;
    const widthRatio = box.width / displaySize.width;

    const offX = mirroredCenterXNorm - 0.5;
    const offY = centerYNorm - 0.5;

    if (widthRatio < thresholds.farWidthRatio) {
        return { message: 'Move closer', tone: 'warn', stableFrames: 0 };
    }
    if (widthRatio > thresholds.closeWidthRatio) {
        return { message: 'Move back', tone: 'warn', stableFrames: 0 };
    }

    const xOff = Math.abs(offX) > thresholds.centerToleranceX;
    const yOff = Math.abs(offY) > thresholds.centerToleranceY;

    if (xOff && yOff) {
        return { message: 'Center your face', tone: 'warn', stableFrames: 0 };
    }
    if (xOff) {
        return { message: offX > 0 ? 'Move left' : 'Move right', tone: 'warn', stableFrames: 0 };
    }
    if (yOff) {
        return { message: offY < 0 ? 'Move down' : 'Move up', tone: 'warn', stableFrames: 0 };
    }

    const nextStableFrames = stableFrames + 1;
    return {
        message: nextStableFrames > thresholds.holdStillFrames ? 'Hold still' : 'Perfect position',
        tone: 'good',
        stableFrames: nextStableFrames,
    };
}

function initFaceDetection(options) {
    const video            = document.getElementById(options.videoId);
    const canvas           = document.getElementById(options.canvasId);
    const faceStatusBadge  = document.getElementById(options.faceStatusBadgeId);
    const faceStatusText   = document.getElementById(options.faceStatusTextId);
    const guidanceBanner   = document.getElementById(options.guidanceBannerId);
    const guidanceText     = document.getElementById(options.guidanceTextId);
    const onUpdate         = typeof options.onUpdate === 'function' ? options.onUpdate : function () {};

    if (!video || !canvas || typeof faceapi === 'undefined') {
        return null;
    }

    const ctx = canvas.getContext('2d');

    // Tiny Face Detector model, served from a public, versioned CDN path.
    const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';
    const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions({
        inputSize: 224,
        scoreThreshold: 0.5,
    });

    // Tolerances for "well positioned" (fraction of frame width/height).
    const CENTER_TOLERANCE_X = 0.12;
    const CENTER_TOLERANCE_Y = 0.14;
    const FAR_WIDTH_RATIO    = 0.16; // face box narrower than this => too far away
    const CLOSE_WIDTH_RATIO  = 0.52; // face box wider than this => too close
    const HOLD_STILL_FRAMES  = 20;   // consecutive good frames before switching the message

    let modelsReady   = false;
    let modelsLoading = false;
    let rafId         = null;
    let running       = false;
    let stableFrames  = 0;

    function setFaceStatus(text, tone) {
        if (faceStatusText) faceStatusText.textContent = text;
        if (faceStatusBadge) {
            faceStatusBadge.hidden = false;
            faceStatusBadge.classList.remove('tone-good', 'tone-warn', 'tone-bad', 'tone-neutral');
            faceStatusBadge.classList.add('tone-' + tone);
        }
    }

    function setGuidance(text, tone) {
        if (guidanceText) guidanceText.textContent = text;
        if (guidanceBanner) {
            guidanceBanner.hidden = !text;
            guidanceBanner.classList.remove('tone-good', 'tone-warn', 'tone-bad', 'tone-neutral');
            guidanceBanner.classList.add('tone-' + tone);
        }
    }

    function hideOverlayText() {
        if (faceStatusBadge) faceStatusBadge.hidden = true;
        if (guidanceBanner) guidanceBanner.hidden = true;
    }

    async function ensureModelsLoaded() {
        if (modelsReady) return true;
        if (modelsLoading) return false;

        modelsLoading = true;
        setFaceStatus('Loading face detection…', 'neutral');
        try {
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            modelsReady = true;
            return true;
        } catch (err) {
            setFaceStatus('Face detection failed to load', 'bad');
            setGuidance('Check your internet connection and reload the page.', 'bad');
            return false;
        } finally {
            modelsLoading = false;
        }
    }

    function resizeCanvasToVideo() {
        const rect = video.getBoundingClientRect();
        if (canvas.width !== rect.width || canvas.height !== rect.height) {
            canvas.width = rect.width;
            canvas.height = rect.height;
        }
    }

    function clearCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function drawGuideZone() {
        const w = canvas.width;
        const h = canvas.height;
        const zoneW = w * (1 - CENTER_TOLERANCE_X * 2);
        const zoneH = h * (1 - CENTER_TOLERANCE_Y * 2) * 0.85;
        const x = (w - zoneW) / 2;
        const y = (h - zoneH) / 2;

        ctx.save();
        ctx.setLineDash([8, 8]);
        ctx.lineWidth = 2;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
        ctx.strokeRect(x, y, zoneW, zoneH);
        ctx.restore();
    }

    function drawFaceBox(box, color) {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
        ctx.restore();
    }

    async function detectFrame() {
        if (!running) return;

        if (video.readyState >= 2 && video.videoWidth > 0) {
            resizeCanvasToVideo();
            clearCanvas();
            drawGuideZone();

            let detections = [];
            try {
                detections = await faceapi.detectAllFaces(video, DETECTOR_OPTIONS);
            } catch (err) {
                // Skip this frame silently (e.g. a transient decode error);
                // the loop just tries again on the next frame.
                detections = [];
            }

            const displaySize = { width: canvas.width, height: canvas.height };
            const resized = faceapi.resizeResults(detections, displaySize);

            if (resized.length === 0) {
                stableFrames = 0;
                setFaceStatus('No face detected', 'bad');
                setGuidance('Position your face inside the frame', 'neutral');
                onUpdate({ faceCount: 0, tone: 'bad', message: 'No face detected' });
            } else if (resized.length > 1) {
                stableFrames = 0;
                resized.forEach(function (d) {
                    drawFaceBox(d.box, '#f97316');
                });
                setFaceStatus('Multiple faces detected', 'warn');
                setGuidance('Only one person should be in frame', 'warn');
                onUpdate({ faceCount: resized.length, tone: 'warn', message: 'Multiple faces detected' });
            } else {
                const box = resized[0].box;
                const displaySize2 = { width: canvas.width, height: canvas.height };

                setFaceStatus('Face detected', 'good');

                const result = resolveFaceGuidance(box, displaySize2, {
                    farWidthRatio: FAR_WIDTH_RATIO,
                    closeWidthRatio: CLOSE_WIDTH_RATIO,
                    centerToleranceX: CENTER_TOLERANCE_X,
                    centerToleranceY: CENTER_TOLERANCE_Y,
                    holdStillFrames: HOLD_STILL_FRAMES,
                }, stableFrames);

                stableFrames = result.stableFrames;

                drawFaceBox(box, result.tone === 'good' ? '#22c55e' : '#f59e0b');
                setGuidance(result.message, result.tone);
                onUpdate({ faceCount: 1, tone: result.tone, message: result.message });
            }
        }

        rafId = requestAnimationFrame(detectFrame);
    }

    async function start() {
        running = true;
        stableFrames = 0;
        const ok = await ensureModelsLoaded();
        if (!running) return; // stopped while models were loading
        if (!ok) return;

        setFaceStatus('Looking for a face…', 'neutral');
        if (rafId) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(detectFrame);
    }

    function stop() {
        running = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        clearCanvas();
        hideOverlayText();
        stableFrames = 0;
        onUpdate({ faceCount: 0, tone: 'neutral', message: '' });
    }

    return { start: start, stop: stop };
}
