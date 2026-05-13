<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - User Dashboard
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'لوحة التحكم - ' . SITE_NAME;

Auth::requireAuth();

$user = Auth::getCurrentUser();
if (!$user) {
    redirect(getPageUrl('login.php'));
    exit;
}

$subscription = Auth::checkSubscription($user['id']);
$plan = $user['plan'] ?? 'FREE';
$planConfig = PLANS[$plan] ?? PLANS['FREE'];

// Statistics
$totalSearches = (int) ($user['search_count'] ?? 0);
$today = date('Y-m-d');
$todayCount = fetch(
    "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND date(created_at) = :today",
    [':uid' => $user['id'], ':today' => $today]
);
$todaySearches = (int) ($todayCount['cnt'] ?? 0);

$monthStart = date('Y-m-01');
$monthCount = fetch(
    "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND created_at >= :month",
    [':uid' => $user['id'], ':month' => $monthStart]
);
$monthSearches = (int) ($monthCount['cnt'] ?? 0);

// Recent searches
$recentSearches = fetchAll(
    "SELECT * FROM search_history WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10",
    [':uid' => $user['id']]
);

// Activity log
$activities = fetchAll(
    "SELECT * FROM activity_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5",
    [':uid' => $user['id']]
);

// Active subscription
$activeSub = fetch(
    "SELECT * FROM subscriptions WHERE user_id = :uid AND is_active = 1 AND expires_at > :now ORDER BY expires_at DESC LIMIT 1",
    [':uid' => $user['id'], ':now' => date('Y-m-d H:i:s')]
);

$avatarData = generateAvatar($user['name'], 80);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-layout" style="display: flex; min-height: calc(100vh - 72px); padding-top: 0;">
    <!-- Sidebar -->
    <aside class="sidebar" style="width: 260px; position: fixed; top: 72px; right: 0; bottom: 0; background: var(--bg-card); border-left: 1px solid var(--border-color); padding: 1.5rem 0; overflow-y: auto; z-index: 40;">
        <!-- User Info -->
        <div style="padding: 0 1.25rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo sanitizeOutput($user['avatar']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent);">
                <?php else: ?>
                <img src="<?php echo $avatarData; ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--accent);">
                <?php endif; ?>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo sanitizeOutput($user['name']); ?></div>
                    <span class="plan-badge plan-badge-<?php echo strtolower($plan); ?>" style="margin-top: 0.25rem;"><?php echo $planConfig['name']; ?></span>
                </div>
            </div>
        </div>

        <div class="sidebar-title" style="padding: 0 1.25rem;">القائمة الرئيسية</div>
        <nav class="sidebar-nav" style="display: flex; flex-direction: column; gap: 0.25rem; padding: 0 0.75rem;">
            <a href="<?php echo getPageUrl('dashboard.php'); ?>" class="sidebar-link active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-4-4m0 0 18 0M9 15h6"/></svg>
                الرئيسية
            </a>
            <a href="<?php echo getPageUrl('search.php'); ?>" class="sidebar-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                البحث
            </a>
            <a href="<?php echo getPageUrl('plans.php'); ?>" class="sidebar-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                خطتي
            </a>
            <a href="<?php echo getPageUrl('account.php'); ?>" class="sidebar-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                الإعدادات
            </a>

            <div class="sidebar-divider" style="height: 1px; background: var(--border-light); margin: 0.75rem 1.25rem;"></div>

            <a href="<?php echo getPageUrl('logout.php'); ?>" class="sidebar-link" style="color: var(--danger);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 13 12 13 8 17 5 17 21"/></svg>
                تسجيل الخروج
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-content" style="flex: 1; margin-right: 260px; padding: 2rem;">
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1>مرحباً، <?php echo sanitizeOutput(explode(' ', $user['name'])[0] ?? $user['name']); ?> 👋</h1>
                <p>إليك ملخص نشاطك في المنصة</p>
            </div>
            <a href="<?php echo getPageUrl('search.php'); ?>" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                بحث سريع
            </a>
        </div>

        <!-- Quick Search -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" class="input" placeholder="ابحث سريع عن رقم أو اسم..." style="flex:1; border: none; background: transparent; font-size: 0.95rem;" onfocus="this.parentElement.parentElement.style.boxShadow='0 4px 12px rgba(16,185,129,0.15)'" onblur="this.parentElement.parentElement.style.boxShadow=''" onkeypress="if(event.key==='Enter') window.location.href='<?php echo getPageUrl('search.php'); ?>?q='+encodeURIComponent(this.value)">
            </div>
        </div>

        <!-- Stats Row -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-card-icon blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
                <div class="stat-card-info">
                    <div class="stat-card-label">إجمالي البحث</div>
                    <div class="stat-card-value"><?php echo number_format($totalSearches); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/><path d="M6 20V10"/></svg>
                </div>
                <div class="stat-card-info">
                    <div class="stat-card-label">بحث اليوم</div>
                    <div class="stat-card-value"><?php echo $todaySearches; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"/></svg>
                </div>
                <div class="stat-card-info">
                    <div class="stat-card-label">خطتك</div>
                    <div class="stat-card-value" style="font-size: 1.25rem;"><?php echo $planConfig['name']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon <?php echo $subscription['active'] ? 'green' : 'red'; ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="stat-card-info">
                    <div class="stat-card-label">حالة الاشتراك</div>
                    <div class="stat-card-value" style="font-size: 1.25rem;"><?php echo $subscription['active'] ? 'فعّال' : 'غير مشترك'; ?></div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Recent Searches -->
            <div class="card" style="padding: 1.5rem;">
                <div class="card-header" style="margin-bottom: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">آخر عمليات البحث</h3>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo count($recentSearches); ?> بحث</span>
                </div>
                <?php if (!empty($recentSearches)): ?>
                <div class="table-wrapper" style="border: 1px solid var(--border-color);">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الاستعلام</th>
                                <th>النوع</th>
                                <th>النتائج</th>
                                <th>الوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSearches as $item): ?>
                            <tr>
                                <td style="direction: ltr; font-family: 'Inter'; font-size: 0.85rem;"><?php echo sanitizeOutput($item['query']); ?></td>
                                <td><span class="badge <?php echo $item['query_type'] === 'NUMBER' ? 'badge-blue' : 'badge-purple'; ?>"><?php echo $item['query_type'] === 'NUMBER' ? 'رقم' : 'اسم'; ?></span></td>
                                <td style="font-family: 'Inter'; direction: ltr;"><?php echo (int) ($item['results_count'] ?? 0); ?></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;"><?php echo timeAgo($item['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--border-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.75rem;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <p style="margin: 0;">لا توجد عمليات بحث بعد</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Activity & Subscription -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Activity Log -->
                <div class="card" style="padding: 1.25rem;">
                    <div class="card-header" style="margin-bottom: 0.75rem;">
                        <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0;">النشاط الأخير</h3>
                    </div>
                    <?php if (!empty($activities)): ?>
                    <?php
                    $activityColors = [
                        'login' => 'green', 'register' => 'blue',
                        'logout' => 'red', 'search' => 'blue', 'payment_success' => 'green',
                        'profile_updated' => 'blue', 'password_changed' => 'yellow',
                        'login_failed' => 'red',
                    ];
                    $activityLabels = [
                        'login' => 'تسجيل دخول',
                        'register' => 'تسجيل حساب جديد',
                        'logout' => 'تسجيل خروج',
                        'search' => 'بحث جديد',
                        'payment_success' => 'عملية دفع ناجحة',
                        'profile_updated' => 'تحديث الملف الشخصي',
                        'password_changed' => 'تغيير كلمة المرور',
                        'login_failed' => 'محاولة دخول فاشلة',
                    ];
                    ?>
                    <div>
                        <?php foreach ($activities as $act): ?>
                        <div class="activity-item">
                            <div class="activity-dot <?php echo $activityColors[$act['action']] ?? 'blue'; ?>"></div>
                            <div class="activity-info">
                                <div class="activity-text"><?php echo sanitizeOutput($activityLabels[$act['action']] ?? $act['action']); ?></div>
                                <div class="activity-time"><?php echo timeAgo($act['created_at']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem;">لا يوجد نشاط بعد</p>
                    <?php endif; ?>
                </div>

                <!-- Subscription Status -->
                <?php if ($activeSub): ?>
                <div class="card" style="padding: 1.25rem; border: 1px solid rgba(16,185,129,0.3); background: linear-gradient(135deg, var(--bg-card), rgba(16,185,129,0.03));">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: #059669;">اشتراك فعّال</h3>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                        <div>الباقة: <strong style="color: var(--text-primary);"><?php echo PLANS[$activeSub['plan']]['name'] ?? ''; ?></strong></div>
                        <div>تنتهي في: <strong style="color: var(--text-primary);" dir="ltr"><?php echo sanitizeOutput($activeSub['expires_at']); ?></strong></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card" style="padding: 1.25rem;">
                    <div style="text-align: center; padding: 0.5rem 0;">
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.75rem;">لا يوجد اشتراك فعّال</p>
                        <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center;">اشترك الآن</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
    @media (max-width: 1024px) {
        .sidebar { display: none !important; }
        .dashboard-content { margin-right: 0 !important; }
        .stat-card-value { font-size: 1.25rem !important; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
