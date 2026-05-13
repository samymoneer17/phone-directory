<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Forgot Password Page
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'نسيت كلمة المرور - ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect(getPageUrl('dashboard.php'));
    exit;
}

$flashSuccess = getFlash('success');
$flashError = getFlash('error');
$formError = '';
$formSuccess = false;
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $formError = 'طلب غير صالح. يرجى المحاولة مرة أخرى.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $formEmail = $email;

        $result = Auth::forgotPassword($email);

        if ($result['success']) {
            $formSuccess = true;
        } else {
            $formError = $result['message'];
        }
    }
}

$csrfToken = Security::getCSRFToken();
?>

<!-- Auth Container -->
<div class="auth-container" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 6rem 1rem 2rem; background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%); position: relative; overflow: hidden;">
    <div style="position:absolute; top:-15%; right:-15%; width:400px; height:400px; background:radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

    <div class="auth-card" style="max-width: 440px;">
        <!-- Logo -->
        <div class="auth-header">
            <a href="<?php echo getPageUrl('index.php'); ?>" class="auth-logo" style="text-decoration:none;">
                <span class="auth-logo-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </span>
                <span class="auth-logo-text">دليل الهاتف الدولي</span>
            </a>
            <h1 class="auth-title">نسيت كلمة المرور؟</h1>
            <p class="auth-subtitle">أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة تعيين كلمة المرور</p>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($flashSuccess)): ?>
        <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; margin-bottom: 1.25rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($flashSuccess); ?></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1.25rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($flashError); ?></div></div>
        </div>
        <?php endif; ?>

        <?php if ($formSuccess): ?>
        <!-- Success State -->
        <div style="text-align: center; padding: 2rem 0;">
            <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: #D1FAE5; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #059669;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.75rem;">تم إرسال رابط الاستعادة!</h3>
            <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 0.25rem;">
                تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني
            </p>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem;">
                يرجى التحقق من صندوق الوارد والبريد غير المرغوب فيه
            </p>
            <a href="<?php echo getPageUrl('login.php'); ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                العودة لتسجيل الدخول
            </a>
        </div>
        <?php else: ?>
        <!-- Error Message -->
        <?php if (!empty($formError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1.25rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($formError); ?></div></div>
        </div>
        <?php endif; ?>

        <!-- Forgot Password Form -->
        <form method="POST" action="" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Email -->
            <div class="form-group">
                <label class="label label-required">البريد الإلكتروني</label>
                <div class="input-icon">
                    <span class="input-icon-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" name="email" class="input" placeholder="أدخل بريدك الإلكتروني" value="<?php echo sanitizeOutput($formEmail); ?>" required autocomplete="email" dir="ltr" style="text-align:right;">
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary auth-submit">
                إرسال رابط الاستعادة
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>

        <!-- Back to Login -->
        <div class="auth-footer">
            <a href="<?php echo getPageUrl('login.php'); ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline; vertical-align:middle; margin-left:0.25rem;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                العودة لتسجيل الدخول
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
