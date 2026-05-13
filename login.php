<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Login Page
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'تسجيل الدخول - ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect(getPageUrl('dashboard.php'));
    exit;
}

// Process login
$loginError = '';
$loginEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $loginError = 'طلب غير صالح. يرجى المحاولة مرة أخرى.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        $result = Auth::login($email, $password);

        if ($result['success']) {
            if ($remember) {
                // Set remember me cookie
                $token = Security::randomString(64);
                setcookie('remember_token', $token, time() + REMEMBER_ME_LIFETIME, '/', '', false, true);
            }

            $intendedUrl = $_SESSION['intended_url'] ?? getPageUrl('dashboard.php');
            unset($_SESSION['intended_url']);
            redirect($intendedUrl);
            exit;
        } else {
            $loginError = $result['message'];
            $loginEmail = $email;
        }
    }
}

$csrfToken = Security::getCSRFToken();
?>

<!-- Auth Container -->
<div class="auth-container" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 6rem 1rem 2rem; background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%); position: relative; overflow: hidden;">
    <!-- Decorative -->
    <div style="position:absolute; top:-20%; right:-10%; width:500px; height:500px; background:radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

    <div class="auth-card">
        <!-- Logo -->
        <div class="auth-header">
            <a href="<?php echo getPageUrl('index.php'); ?>" class="auth-logo" style="text-decoration:none;">
                <span class="auth-logo-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </span>
                <span class="auth-logo-text">دليل الهاتف الدولي</span>
            </a>
            <h1 class="auth-title">تسجيل الدخول</h1>
            <p class="auth-subtitle">أدخل بياناتك للوصول إلى حسابك</p>
        </div>

        <!-- Error Message -->
        <?php if (!empty($loginError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1.25rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($loginError); ?></div></div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="auth-form" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Email -->
            <div class="form-group">
                <label class="label label-required">البريد الإلكتروني</label>
                <div class="input-icon">
                    <span class="input-icon-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" name="email" class="input <?php echo (!empty($loginError) && empty($loginEmail)) ? 'input-error' : ''; ?>" placeholder="example@email.com" value="<?php echo sanitizeOutput($loginEmail); ?>" required autocomplete="email" dir="ltr" style="text-align:right;">
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="label label-required">كلمة المرور</label>
                <div class="password-toggle">
                    <input type="password" name="password" class="input" placeholder="أدخل كلمة المرور" required minlength="8" autocomplete="current-password" id="loginPassword">
                    <button type="button" class="password-toggle-btn" onclick="togglePassword('loginPassword', this)" aria-label="إظهار كلمة المرور">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <!-- Remember & Forgot -->
            <div class="auth-remember">
                <label class="checkbox">
                    <input type="checkbox" name="remember" id="rememberMe">
                    <span class="checkbox-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="checkbox-label">تذكرني</span>
                </label>
                <a href="<?php echo getPageUrl('forgot-password.php'); ?>" class="auth-forgot">نسيت كلمة المرور؟</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary auth-submit" id="loginSubmitBtn">
                تسجيل الدخول
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </button>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
            ليس لديك حساب؟ <a href="<?php echo getPageUrl('register.php'); ?>">سجل الآن</a>
        </div>
    </div>
</div>

<script src="<?php echo getPageUrl('assets/js/auth.js'); ?>"></script>
<script>
function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var eyeIcon = btn.querySelector('.eye-icon');
    var eyeOffIcon = btn.querySelector('.eye-off-icon');
    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.style.display = 'none';
        eyeOffIcon.style.display = 'block';
    } else {
        input.type = 'password';
        eyeIcon.style.display = 'block';
        eyeOffIcon.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
