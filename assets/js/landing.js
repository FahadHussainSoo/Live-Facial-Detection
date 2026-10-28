/**
 * landing.js
 * Purely decorative behavior for the homepage hero "viewfinder" graphic.
 * This does NOT access the camera or any media device — it only cycles
 * a short status label to suggest what live verification will feel like
 * once the feature is implemented in a later phase.
 */
document.addEventListener('DOMContentLoaded', function () {
    var statusEl = document.getElementById('vfStatusText');
    if (!statusEl) {
        return;
    }

    var messages = [
        'Awaiting verification',
        'Positioning guide active',
        'Liveness check ready',
        'Encrypted channel ready'
    ];
    var i = 0;

    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        return;
    }

    setInterval(function () {
        i = (i + 1) % messages.length;
        statusEl.textContent = messages[i];
    }, 3200);
});
