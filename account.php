<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Account Settings Page
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'إعدادات الحساب - ' . SITE_NAME;

// Require authentication
Auth::requireAuth();

$user = Auth::getCurrentUser();
if (!$user) {
    redirect(getPageUrl('login.php'));
    exit;
}

$subscription = Auth::checkSubscription($user['id']);
$canHide = Auth::canHidePhone($user['id']);

// Process profile update
$profileSuccess = '';
$profileError = '';
$passwordSuccess = '';
$passwordError = '';
$privacySuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $profileError = 'طلب غير صالح. يرجى المحاولة مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $result = Auth::updateProfile($user['id'], [
                'name'  => $_POST['name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
            ]);
            if ($result['success']) {
                $profileSuccess = $result['message'];
                $user = Auth::getCurrentUser();
            } else {
                $profileError = $result['message'];
            }
        }

        if ($action === 'update_password') {
            $result = Auth::updatePassword(
                $user['id'],
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? ''
            );
            if ($result['success']) {
                $passwordSuccess = $result['message'];
            } else {
                $passwordError = $result['message'];
            }
        }

        if ($action === 'toggle_phone_hide') {
            $hideValue = (int) ($_POST['is_phone_hidden'] ?? 0);
            $result = Auth::updateProfile($user['id'], ['is_phone_hidden' => $hideValue]);
            if ($result['success']) {
                $privacySuccess = 'تم تحديث إعدادات الخصوصية';
                $user = Auth::getCurrentUser();
                $canHide = Auth::canHidePhone($user['id']);
            } else {
                $profileError = $result['message'];
            }
        }
    }
}

// Get search statistics
$today = date('Y-m-d');
$totalSearches = (int) ($user['search_count'] ?? 0);
$monthStart = date('Y-m-01');
$monthSearches = fetch(
    "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND created_at >= :month_start",
    [':uid' => $user['id'], ':month_start' => $monthStart]
);
$monthSearchesCount = (int) ($monthSearches['cnt'] ?? 0);

$recentSearches = fetchAll(
    "SELECT query, query_type, country_code, results_count, created_at FROM search_history WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10",
    [':uid' => $user['id']]
);

$avatarData = generateAvatar($user['name'], 80);
$csrfToken = Security::getCSRFToken();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 900px; padding: 2rem 1rem 4rem;">
    <!-- Page Title -->
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 0.25rem;">إعدادات الحساب</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">إدارة حسابك وتخصيص إعداداتك</p>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Profile Settings Card -->
        <div class="card" style="grid-column: span 2; padding: 2rem;">
            <div class="card-header" style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    معلومات الحساب
                </h3>
            </div>

            <?php if ($profileSuccess): ?>
            <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; margin-bottom: 1rem;">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div class="alert-content"><div><?php echo sanitizeOutput($profileSuccess); ?></div></div>
            </div>
            <?php endif; ?>

            <?php if ($profileError): ?>
            <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1rem;">
                <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <div class="alert-content"><div><?php echo sanitizeOutput($profileError); ?></div></div>
            </div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem;">
                <!-- Avatar -->
                <img src="<?php echo $avatarData; ?>" alt="" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid var(--accent); flex-shrink: 0;">
                <div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);"><?php echo sanitizeOutput($user['name']); ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;"><?php echo sanitizeOutput($user['email']); ?></div>
                    <span class="badge badge-<?php echo strtolower($user['plan']); ?>" style="margin-top: 0.5rem; display: inline-flex;">
                        <?php echo PLANS[$user['plan']]['name'] ?? 'مجاني'; ?>
                    </span>
                </div>
            </div>

            <form method="POST" action="" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="label">الاسم الكامل</label>
                        <input type="text" name="name" class="input" value="<?php echo sanitizeOutput($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="label">رقم الهاتف</label>
                        <input type="tel" name="phone" class="input" value="<?php echo sanitizeOutput($user['phone'] ?? ''); ?>" placeholder="967512345678" dir="ltr" style="text-align:right;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="label">البريد الإلكتروني</label>
                    <input type="email" class="input" value="<?php echo sanitizeOutput($user['email']); ?>" readonly style="opacity: 0.6; cursor: not-allowed;">
                    <div class="form-hint">لا يمكن تغيير البريد الإلكتروني. تواصل مع الدعم الفني إذا لزم الأمر.</div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    حفظ التغييرات
                </button>
            </form>
        </div>

        <!-- Change Password Card -->
        <div class="card" style="padding: 2rem;">
            <div class="card-header" style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    تغيير كلمة المرور
                </h3>
            </div>

            <?php if ($passwordSuccess): ?>
            <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; margin-bottom: 1rem;">
                <div><?php echo sanitizeOutput($passwordSuccess); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($passwordError): ?>
            <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-bottom: 1rem;">
                <div><?php echo sanitizeOutput($passwordError); ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label class="label">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" class="input" placeholder="أدخل كلمة المرور الحالية" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="label">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="input" placeholder="أدخل كلمة المرور الجديدة" required minlength="8" id="accountNewPassword" oninput="checkAccountPwdStrength(this.value)">
                    <div class="password-strength" style="margin-top: 0.5rem;">
                        <div class="password-strength-bar"><div class="password-strength-fill" id="accountStrengthFill"></div></div>
                        <div class="password-strength-text" id="accountStrengthText"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" name="confirm_new_password" class="input" placeholder="أعد كتابة كلمة المرور الجديدة" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">تغيير كلمة المرور</button>
            </form>
        </div>

        <!-- Privacy Card -->
        <div class="card" style="padding: 2rem;">
            <div class="card-header" style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    الخصوصية
                </h3>
            </div>

            <?php if ($privacySuccess): ?>
            <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; margin-bottom: 1rem;">
                <div><?php echo sanitizeOutput($privacySuccess); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($canHide): ?>
            <form method="POST" action="" id="privacyForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="toggle_phone_hide">

                <label class="checkbox" style="margin-bottom: 1rem;">
                    <input type="checkbox" name="is_phone_hidden" id="phoneHideCheck" value="1" <?php echo ($user['is_phone_hidden'] ?? 0) ? 'checked' : ''; ?> onchange="this.form.submit();">
                    <span class="checkbox-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="checkbox-label">إخفاء رقمي من نتائج البحث</span>
                </label>

                <div style="font-size: 0.825rem; color: var(--text-muted); line-height: 1.6; padding: 0.75rem; background: var(--bg-secondary); border-radius: 0.5rem;">
                    عند تفعيل هذا الخيار، لن يظهر رقم هاتفك في نتائج البحث التي يراها المستخدمون الآخرون. يمكنك إلغاء التفعيل في أي وقت.
                </div>
            </form>
            <?php else: ?>
            <div style="text-align: center; padding: 1.5rem;">
                <div style="width: 48px; height: 48px; margin: 0 auto 1rem; background: #FEF3C7; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">هذه الميزة متاحة فقط للباقات المدفوعة</p>
                <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-warning btn-sm" style="width: 100%; justify-content: center;">
                    ترقية الباقة
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Search Statistics Card -->
        <div class="card" style="padding: 2rem;">
            <div class="card-header" style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                    إحصائيات البحث
                </h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 0.75rem; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--accent); font-family: 'Inter'; direction: ltr; display: inline-block;"><?php echo number_format($totalSearches); ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">إجمالي البحث</div>
                </div>
                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 0.75rem; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--accent); font-family: 'Inter'; direction: ltr; display: inline-block;"><?php echo number_format($monthSearchesCount); ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">بحث هذا الشهر</div>
                </div>
            </div>

            <?php if (!empty($recentSearches)): ?>
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">آخر عمليات البحث</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($recentSearches as $item): ?>
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" dir="ltr"><?php echo sanitizeOutput($item['query']); ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo timeAgo($item['created_at']); ?></div>
                    </div>
                    <span class="badge badge-gray"><?php echo $item['results_count']; ?> نتيجة</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem;">لا توجد عمليات بحث بعد</p>
            <?php endif; ?>
        </div>

        <!-- Subscription Info Card -->
        <div class="card" style="padding: 2rem;">
            <div class="card-header" style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V8H6a2 2 0 0 1-2-2v-4h18a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H10a2 2 0 0 1 0-4h14V12Z"/><path d="M20 12v8H6"/></svg>
                    الاشتراك
                </h3>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <div>
                    <span class="badge badge-<?php echo strtolower($subscription['plan']); ?>" style="font-size: 0.85rem; padding: 0.3rem 0.75rem;">
                        <?php echo PLANS[$subscription['plan']]['name'] ?? 'مجاني'; ?>
                    </span>
                </div>
                <?php if ($subscription['active'] && $subscription['expiresAt']): ?>
                <span style="font-size: 0.8rem; color: var(--text-muted);" dir="ltr"><?php echo sanitizeOutput($subscription['expiresAt']); ?></span>
                <?php endif; ?>
            </div>

            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.25rem; line-height: 1.7;">
                <?php
                $planFeatures = PLANS[$subscription['plan']]['features'] ?? [];
                foreach ($planFeatures as $feature):
                    echo '<div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.375rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' . sanitizeOutput($feature) . '</div>';
                endforeach;
                ?>
            </div>

            <?php if ($subscription['plan'] === 'FREE'): ?>
            <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">ترقية الباقة</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function checkAccountPwdStrength(password) {
    var fill = document.getElementById('accountStrengthFill');
    var text = document.getElementById('accountStrengthText');
    var strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    fill.className = 'password-strength-fill';
    if (strength <= 2) { fill.classList.add('weak'); text.textContent = 'ضعيفة'; text.style.color = '#EF4444'; }
    else if (strength <= 3) { fill.classList.add('fair'); text.textContent = 'متوسطة'; text.style.color = '#F59E0B'; }
    else { fill.classList.add('strong'); text.textContent = 'قوية'; text.style.color = '#10B981'; }
    if (!password.length) { fill.className = 'password-strength-fill'; text.textContent = ''; }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
