<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Secure Identity. Verified Live.';

// Loaded only on this page via the header/footer hooks — keeps the
// new type system and animation scoped to the landing page.
$extraHeadHTML = '
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="' . APP_URL . '/assets/css/landing.css">
';
$extraBodyHTML = '<script src="' . APP_URL . '/assets/js/landing.js"></script>';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="landing">

    <!-- ================= HERO ================= -->
    <section class="landing-hero">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 hero-copy">
                    <span class="eyebrow">Live Biometric Authentication</span>
                    <h1>Secure Identity.<br>Verified Live.</h1>
                    <p class="lede">Verify your identity securely using live facial authentication.</p>

                    <div class="landing-cta-row">
                        <a href="<?php echo APP_URL; ?>/signup.php" class="btn btn-mint">Create Account</a>
                        <a href="<?php echo APP_URL; ?>/signin.php" class="btn btn-ghost-text">Sign In</a>
                        <a href="<?php echo APP_URL; ?>/<?php echo isLoggedIn() ? 'face-verify.php' : 'signin.php'; ?>" class="btn btn-azure-outline">
                            <i class="bi bi-camera-video me-1"></i>Verify With Face
                        </a>
                    </div>

                    <div class="hero-microtrust">
                        <span><i class="bi bi-lock-fill"></i>Encrypted in transit &amp; at rest</span>
                        <span><i class="bi bi-eye-fill"></i>Liveness-checked</span>
                        <span><i class="bi bi-image"></i>No raw images stored</span>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="viewfinder-wrap">
                        <div class="viewfinder">
                            <span class="vf-preview-tag">Preview</span>
                            <div class="vf-face-icon" aria-hidden="true">
                                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="50" cy="38" r="20" stroke="currentColor" stroke-width="3"/>
                                    <path d="M18 90c4-18 15-28 32-28s28 10 32 28" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="vf-frame">
                                <span class="vf-corner tl"></span>
                                <span class="vf-corner tr"></span>
                                <span class="vf-corner bl"></span>
                                <span class="vf-corner br"></span>
                            </div>
                            <div class="vf-scanline"></div>
                            <div class="vf-status">
                                <span class="vf-badge">
                                    <span class="vf-dot"></span>
                                    <span id="vfStatusText">Awaiting verification</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= TRUST STRIP ================= -->
    <section class="section-pad pt-0">
        <div class="container">
            <div class="trust-strip">
                <div class="trust-item">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <div class="t-label">256-bit encryption</div>
                        <div class="t-sub">data in transit &amp; at rest</div>
                    </div>
                </div>
                <div class="trust-item">
                    <i class="bi bi-person-bounding-box"></i>
                    <div>
                        <div class="t-label">Liveness detection</div>
                        <div class="t-sub">blocks photo &amp; video spoofing</div>
                    </div>
                </div>
                <div class="trust-item">
                    <i class="bi bi-file-earmark-lock2"></i>
                    <div>
                        <div class="t-label">Vector storage only</div>
                        <div class="t-sub">no raw face images kept</div>
                    </div>
                </div>
                <div class="trust-item">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <div class="t-label">Full audit trail</div>
                        <div class="t-sub">every attempt is logged</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="section-pad section-alt">
        <div class="container">
            <div class="text-center mb-5">
                <span class="eyebrow">How It Works</span>
                <h2 class="display-font mt-2" style="font-size: clamp(1.8rem, 3vw, 2.3rem);">
                    Three steps to a verified identity
                </h2>
            </div>

            <div class="row align-items-stretch g-3">
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <span class="step-num">STEP 01</span>
                        <h3>Create your account</h3>
                        <p>Sign up with your name, email, and a password to establish your identity in the system.</p>
                    </div>
                </div>
                <div class="col-lg-1 col-md-12 step-connector d-none d-lg-flex">
                    <i class="bi bi-arrow-right"></i>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <span class="step-num">STEP 02</span>
                        <h3>Register your face</h3>
                        <p>Enroll a live facial scan once. It's converted into an encrypted vector — never stored as a photo.</p>
                    </div>
                </div>
                <div class="col-lg-1 col-md-12 step-connector d-none d-lg-flex">
                    <i class="bi bi-arrow-right"></i>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <span class="step-num">STEP 03</span>
                        <h3>Verify &amp; access</h3>
                        <p>From then on, a quick live check confirms it's really you before you get access — no password to remember.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= SECURITY ================= -->
    <section class="section-pad">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 security-copy">
                    <span class="eyebrow">Security</span>
                    <h2 class="mt-2">Built around a simple rule: never store what doesn't need to be stored</h2>
                    <p class="mb-4">
                        Your face is never saved as an image. During registration, key facial features
                        are converted into a mathematical vector — a string of numbers that can confirm
                        a match but can't be reversed back into a picture. Every sign-in and verification
                        attempt is written to an audit log, so account activity stays fully traceable.
                    </p>
                    <a href="<?php echo APP_URL; ?>/signup.php" class="btn btn-mint">Create Your Secure Account</a>
                </div>
                <div class="col-lg-6">
                    <div class="spec-list mono">
                        <div class="spec-row">
                            <span class="spec-key">storage_format</span>
                            <span class="spec-val">encrypted_vector</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-key">raw_image_retention</span>
                            <span class="spec-val">none</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-key">password_storage</span>
                            <span class="spec-val">hashed</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-key">liveness_check</span>
                            <span class="spec-val">required</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-key">activity_logging</span>
                            <span class="spec-val">enabled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FACIAL VERIFICATION EXPLAINED ================= -->
    <section class="section-pad section-alt">
        <div class="container">
            <div class="text-center mb-5">
                <span class="eyebrow">Facial Verification, Explained</span>
                <h2 class="display-font mt-2" style="font-size: clamp(1.8rem, 3vw, 2.3rem);">
                    What actually happens during a face check
                </h2>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="explain-card">
                        <div class="explain-icon"><i class="bi bi-person-check"></i></div>
                        <h3>1. Liveness</h3>
                        <p>The system first confirms a real, present person is being verified — not a static photo, screen, or recording.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="explain-card">
                        <div class="explain-icon"><i class="bi bi-braces-asterisk"></i></div>
                        <h3>2. Encoding</h3>
                        <p>Distinctive facial features are converted into an encrypted numeric vector — the "embedding" — instead of a saved image.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="explain-card">
                        <div class="explain-icon"><i class="bi bi-shuffle"></i></div>
                        <h3>3. Matching</h3>
                        <p>That vector is compared against your registered vector. A close-enough match grants access; a mismatch is logged and denied.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA BAND ================= -->
    <section class="section-pad">
        <div class="container">
            <div class="cta-band">
                <span class="eyebrow">Get Started</span>
                <h2 class="mt-2">Ready to verify your identity?</h2>
                <p>Create an account in under a minute — face registration comes right after.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="<?php echo APP_URL; ?>/signup.php" class="btn btn-mint">Create Account</a>
                    <a href="<?php echo APP_URL; ?>/signin.php" class="btn btn-ghost-text">Sign In</a>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
