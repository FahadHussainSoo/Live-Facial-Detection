    <footer class="app-footer mt-auto py-4">
        <div class="container text-center">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All rights reserved.</p>
            <p class="mb-0 small text-muted">Secure. Simple. Passwordless — powered by facial authentication.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Scripts -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>

    <?php if (isset($extraBodyHTML)) { echo $extraBodyHTML; } ?>
</body>
</html>
