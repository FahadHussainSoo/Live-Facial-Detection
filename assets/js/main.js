/**
 * main.js
 * Global front-end behavior for Live Facial Authentication.
 *
 * Phase 1 note: No webcam / facial recognition logic lives here yet.
 * This file currently only wires up small, safe UI enhancements.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Enable Bootstrap tooltips globally, if any are present on a page.
    var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Auto-dismiss alert messages (e.g. flash messages) after a few seconds.
    var alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });

    // Show/hide password toggle buttons. Any button with
    // data-toggle-password="fieldId" flips that field between
    // type="password" and type="text" and swaps its own icon.
    var toggleButtons = document.querySelectorAll('[data-toggle-password]');
    toggleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-toggle-password');
            var field = document.getElementById(targetId);
            if (!field) {
                return;
            }
            var icon = btn.querySelector('i');
            var isHidden = field.getAttribute('type') === 'password';
            field.setAttribute('type', isHidden ? 'text' : 'password');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            }
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
});
