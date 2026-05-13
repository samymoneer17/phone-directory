<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Register Page
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'إنشاء حساب - ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect(getPageUrl('dashboard.php'));
    exit;
}

$registerError = '';
$formData = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $registerError = 'طلب غير صالح. يرجى المحاولة مرة أخرى.';
    } else {
        $formData = [
            'name'     => Security::sanitizeInput($_POST['name'] ?? ''),
            'email'    => strtolower(trim($_POST['email'] ?? '')),
            'phone'    => Security::sanitizeInput($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ];

        $termsAccepted = isset($_POST['terms']);

        if (!$termsAccepted) {
            $registerError = 'يجب الموافقة على شروط الاستخدام وسياسة الخصوصية';
        } else {
            $result = Auth::register($formData);
            if ($result['success']) {
                flash('success', 'تم إنشاء حسابك بنجاح! مرحباً بك في دليل الهاتف الدولي');
                redirect(getPageUrl('dashboard.php'));
                exit;
            } else {
                $registerError = $result['message'];
            }
        }
    }
}

$csrfToken = Security::getCSRFToken();
?>

<!-- Auth Container -->
<div class="auth-container" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 5rem 1rem 2rem; background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%); position: relative; overflow: hidden;">
    <div style="position:absolute; top:-20%; left:-10%; width:500px; height:500px; background:radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

    <div class="auth-card" style="max-width: 460px;">
        <!-- Logo -->
        <div class="auth-header">
            <a href="<?php echo getPageUrl('index.php'); ?>" class="auth-logo" style="text-decoration:none;">
                <span class="auth-logo-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </span>
                <span class="auth-logo-text">دليل الهاتف الدولي</span>
            </a>
            <h1 class="auth-title">إنشاء حساب جديد</h1>
            <p class="auth-subtitle">أنشئ حسابك للبدء في البحث عن الأرقام</p>
        </div>

        <!-- Error Message -->
        <?php if (!empty($registerError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1.25rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($registerError); ?></div></div>
        </div>
        <?php endif; ?>

        <!-- Register Form -->
        <form method="POST" action="" class="auth-form" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Name -->
            <div class="form-group">
                <label class="label label-required">الاسم الكامل</label>
                <div class="input-icon">
                    <span class="input-icon-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text" name="name" class="input" placeholder="أدخل اسمك الكامل" value="<?php echo sanitizeOutput($formData['name']); ?>" required minlength="2" maxlength="100" autocomplete="name">
                </div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="label label-required">البريد الإلكتروني</label>
                <div class="input-icon">
                    <span class="input-icon-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" name="email" class="input" placeholder="example@email.com" value="<?php echo sanitizeOutput($formData['email']); ?>" required autocomplete="email" dir="ltr" style="text-align:right;">
                </div>
            </div>

            <!-- Phone -->
            <div class="form-group">
                <label class="label">رقم الهاتف (اختياري)</label>
                <div class="input-icon">
                    <span class="input-icon-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </span>
                    <input type="tel" name="phone" class="input" placeholder="967512345678" value="<?php echo sanitizeOutput($formData['phone']); ?>" autocomplete="tel" dir="ltr" style="text-align:right;">
                </div>
                <div class="form-hint">أدخل رقم الهاتف مع رمز الدولة (مثال: 967512345678)</div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="label label-required">كلمة المرور</label>
                <div class="password-toggle">
                    <input type="password" name="password" class="input" placeholder="أدخل كلمة مرور قوية" required minlength="8" maxlength="128" autocomplete="new-password" id="regPassword" oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="password-toggle-btn" onclick="toggleRegPassword(this)" aria-label="إظهار كلمة المرور">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="password-strength-bar">
                        <div class="password-strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="password-strength-text" id="strengthText"></div>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="label label-required">تأكيد كلمة المرور</label>
                <div class="password-toggle">
                    <input type="password" name="confirm_password" class="input" placeholder="أعد كتابة كلمة المرور" required minlength="8" autocomplete="new-password" id="regConfirmPassword">
                    <button type="button" class="password-toggle-btn" onclick="toggleRegPassword(this)" aria-label="إظهار كلمة المرور">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <!-- Terms -->
            <div class="form-group">
                <label class="checkbox">
                    <input type="checkbox" name="terms" id="termsCheckbox" required>
                    <span class="checkbox-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="checkbox-label">أوافق على <a href="#" style="color: var(--accent); font-weight: 600;">شروط الاستخدام</a> و<a href="#" style="color: var(--accent); font-weight: 600;">سياسة الخصوصية</a></span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary auth-submit" id="registerSubmitBtn">
                إنشاء حساب
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </button>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
            لديك حساب؟ <a href="<?php echo getPageUrl('login.php'); ?>">سجل دخولك</a>
        </div>
    </div>
</div>

<script src="<?php echo getPageUrl('assets/js/auth.js'); ?>"></script>
<script>
function toggleRegPassword(btn) {
    var input = btn.parentElement.querySelector('input');
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

function checkPasswordStrength(password) {
    var fill = document.getElementById('strengthFill');
    var text = document.getElementById('strengthText');
    var strength = 0;

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    fill.className = 'password-strength-fill';
    if (strength <= 2) {
        fill.classList.add('weak');
        text.textContent = 'كلمة مرور ضعيفة';
        text.style.color = '#EF4444';
    } else if (strength <= 3) {
        fill.classList.add('fair');
        text.textContent = 'كلمة مرور متوسطة';
        text.style.color = '#F59E0B';
    } else {
        fill.classList.add('strong');
        text.textContent = 'كلمة مرور قوية';
        text.style.color = '#10B981';
    }

    if (password.length === 0) {
        fill.className = 'password-strength-fill';
        text.textContent = '';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
