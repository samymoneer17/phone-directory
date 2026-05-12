<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin Dashboard
 * International Phone Directory
 * ============================================================
 */

$pageTitle = 'لوحة التحكم';
$adminPage = 'index';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin
Auth::requireAdmin();

$currentUser = Auth::getCurrentUser();
$csrfToken = Security::getCSRFToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --admin-bg: #f8fafc; --admin-sidebar-bg: #0f172a; --admin-sidebar-w: 260px;
            --admin-card-bg: #ffffff; --admin-text: #0f172a; --admin-text-sec: #475569; --admin-text-muted: #94a3b8;
            --admin-border: #e2e8f0; --admin-accent: #10b981; --admin-accent-hover: #059669;
            --admin-danger: #ef4444; --admin-warning: #f59e0b; --admin-info: #3b82f6;
            --admin-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --admin-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.05);
            --admin-radius: 12px;
        }
        [data-theme="dark"] {
            --admin-bg: #0b1120; --admin-sidebar-bg: #060d1a; --admin-card-bg: #1e293b;
            --admin-text: #f1f5f9; --admin-text-sec: #94a3b8; --admin-text-muted: #64748b;
            --admin-border: #1e293b; --admin-shadow: 0 4px 6px rgba(0,0,0,0.3);
            --admin-shadow-lg: 0 10px 15px rgba(0,0,0,0.4);
        }
        body { font-family: 'Cairo', sans-serif; background: var(--admin-bg); color: var(--admin-text); min-height: 100vh; }

        /* Sidebar */
        .admin-sidebar {
            position: fixed; top: 0; right: 0; bottom: 0; width: var(--admin-sidebar-w);
            background: var(--admin-sidebar-bg); z-index: 100; display: flex; flex-direction: column;
            border-left: 1px solid rgba(255,255,255,0.05); transition: transform 0.3s ease;
        }
        .admin-sidebar-logo {
            padding: 1.5rem; display: flex; align-items: center; gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .admin-sidebar-logo-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, var(--admin-accent), #06b6d4);
            display: flex; align-items: center; justify-content: center; color: #fff;
        }
        .admin-sidebar-logo h2 { color: #fff; font-size: 1.1rem; font-weight: 700; }
        .admin-sidebar-nav { flex: 1; padding: 1rem 0.75rem; overflow-y: auto; }
        .admin-sidebar-link {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
            border-radius: 10px; color: rgba(255,255,255,0.6); text-decoration: none;
            font-size: 0.9rem; font-weight: 500; transition: all 0.2s ease; margin-bottom: 0.25rem;
        }
        .admin-sidebar-link:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.9); }
        .admin-sidebar-link.active {
            background: linear-gradient(135deg, var(--admin-accent), #06b6d4);
            color: #fff; font-weight: 600; box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }
        .admin-sidebar-link svg { width: 20px; height: 20px; flex-shrink: 0; }
        .admin-sidebar-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 0.75rem 1rem; }
        .admin-sidebar-footer {
            padding: 1rem 0.75rem; border-top: 1px solid rgba(255,255,255,0.08);
        }
        .admin-sidebar-footer .admin-sidebar-link { color: rgba(239,68,68,0.7); }
        .admin-sidebar-footer .admin-sidebar-link:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

        /* Mobile sidebar toggle */
        .admin-mobile-toggle {
            display: none; position: fixed; top: 1rem; right: 1rem; z-index: 200;
            width: 44px; height: 44px; border-radius: 10px; background: var(--admin-sidebar-bg);
            color: #fff; border: none; cursor: pointer; align-items: center; justify-content: center;
            box-shadow: var(--admin-shadow-lg);
        }
        .admin-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 90; }

        /* Main content */
        .admin-main {
            margin-right: var(--admin-sidebar-w); padding: 2rem; min-height: 100vh;
            transition: margin-right 0.3s ease;
        }
        .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .admin-header h1 { font-size: 1.75rem; font-weight: 800; }
        .admin-header-sub { color: var(--admin-text-sec); font-size: 0.9rem; }
        .admin-header-actions { display: flex; gap: 0.5rem; align-items: center; }
        .admin-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: var(--admin-accent); color: #fff; }

        /* Stats grid */
        .admin-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem; }
        .admin-stat {
            background: var(--admin-card-bg); border: 1px solid var(--admin-border); border-radius: var(--admin-radius);
            padding: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;
            box-shadow: var(--admin-shadow); transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .admin-stat:hover { transform: translateY(-2px); box-shadow: var(--admin-shadow-lg); }
        .admin-stat-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .admin-stat-icon.green { background: rgba(16,185,129,0.1); color: var(--admin-accent); }
        .admin-stat-icon.blue { background: rgba(59,130,246,0.1); color: var(--admin-info); }
        .admin-stat-icon.gold { background: rgba(245,158,11,0.1); color: var(--admin-warning); }
        .admin-stat-icon.purple { background: rgba(168,85,247,0.1); color: #a855f7; }
        .admin-stat-icon svg { width: 24px; height: 24px; }
        .admin-stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1.2; }
        .admin-stat-label { color: var(--admin-text-sec); font-size: 0.85rem; font-weight: 500; }

        /* Cards grid */
        .admin-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .admin-card {
            background: var(--admin-card-bg); border: 1px solid var(--admin-border); border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow); overflow: hidden;
        }
        .admin-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--admin-border);
        }
        .admin-card-title { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .admin-card-title svg { width: 20px; height: 20px; color: var(--admin-accent); }
        .admin-card-body { padding: 0; }
        .admin-card-body.no-data { padding: 2rem; text-align: center; color: var(--admin-text-muted); }

        /* Tables */
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .admin-table th {
            padding: 0.75rem 1rem; text-align: right; font-weight: 600;
            color: var(--admin-text-muted); font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.03em; background: var(--admin-bg);
            border-bottom: 1px solid var(--admin-border);
        }
        .admin-table td {
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-sec); vertical-align: middle;
        }
        .admin-table tbody tr:hover { background: rgba(16,185,129,0.03); }
        .admin-table tr:last-child td { border-bottom: none; }

        /* System info */
        .admin-system-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .admin-system-item {
            display: flex; flex-direction: column; gap: 0.25rem; padding: 1rem;
            background: var(--admin-bg); border-radius: 8px; border: 1px solid var(--admin-border);
        }
        .admin-system-label { font-size: 0.75rem; color: var(--admin-text-muted); font-weight: 600; text-transform: uppercase; }
        .admin-system-value { font-size: 0.9rem; font-weight: 600; color: var(--admin-text); }

        /* Badges */
        .plan-badge-admin {
            display: inline-flex; padding: 0.15rem 0.5rem; border-radius: 9999px;
            font-size: 0.7rem; font-weight: 700;
        }
        .plan-badge-admin.free { background: rgba(16,185,129,0.1); color: var(--admin-accent); }
        .plan-badge-admin.pro { background: rgba(59,130,246,0.1); color: var(--admin-info); }
        .plan-badge-admin.premium { background: rgba(168,85,247,0.1); color: #a855f7; }
        .status-badge-admin {
            display: inline-flex; padding: 0.15rem 0.5rem; border-radius: 9999px;
            font-size: 0.7rem; font-weight: 700;
        }
        .status-badge-admin.approved { background: rgba(16,185,129,0.1); color: var(--admin-accent); }
        .status-badge-admin.pending { background: rgba(245,158,11,0.1); color: var(--admin-warning); }
        .status-badge-admin.rejected { background: rgba(239,68,68,0.1); color: var(--admin-danger); }

        /* Loading spinner */
        .admin-spinner { display: flex; justify-content: center; padding: 3rem; }
        .admin-spinner::after {
            content: ''; width: 32px; height: 32px; border: 3px solid var(--admin-border);
            border-top-color: var(--admin-accent); border-radius: 50%; animation: admin-spin 0.7s linear infinite;
        }
        @keyframes admin-spin { to { transform: rotate(360deg); } }

        /* Action badge colors for logs */
        .action-badge { display: inline-flex; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .action-badge.login { background: rgba(59,130,246,0.1); color: var(--admin-info); }
        .action-badge.register { background: rgba(16,185,129,0.1); color: var(--admin-accent); }
        .action-badge.payment { background: rgba(245,158,11,0.1); color: var(--admin-warning); }
        .action-badge.search { background: rgba(6,182,212,0.1); color: #06b6d4; }
        .action-badge.error { background: rgba(239,68,68,0.1); color: var(--admin-danger); }
        .action-badge.admin { background: rgba(168,85,247,0.1); color: #a855f7; }
        .action-badge.default { background: rgba(148,163,184,0.1); color: #94a3b8; }

        /* User avatar in table */
        .user-avatar-sm {
            width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--admin-accent); flex-shrink: 0;
        }
        .user-cell { display: flex; align-items: center; gap: 0.75rem; }
        .user-cell-name { font-weight: 600; color: var(--admin-text); }
        .user-cell-email { font-size: 0.78rem; color: var(--admin-text-muted); }

        /* Toast notifications */
        .admin-toast-container { position: fixed; top: 1rem; left: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .admin-toast {
            padding: 0.875rem 1.25rem; border-radius: 10px; font-size: 0.875rem; font-weight: 500;
            box-shadow: var(--admin-shadow-lg); animation: toastIn 0.3s ease;
            display: flex; align-items: center; gap: 0.5rem; max-width: 380px;
        }
        .admin-toast.success { background: var(--admin-accent); color: #fff; }
        .admin-toast.error { background: var(--admin-danger); color: #fff; }
        .admin-toast.info { background: var(--admin-info); color: #fff; }
        @keyframes toastIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-cards-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .admin-sidebar { transform: translateX(100%); }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-overlay.show { display: block; }
            .admin-mobile-toggle { display: flex; }
            .admin-main { margin-right: 0; padding: 1rem; padding-top: 4rem; }
            .admin-stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
            .admin-stat { padding: 1rem; }
            .admin-stat-value { font-size: 1.35rem; }
            .admin-header h1 { font-size: 1.35rem; }
        }
        @media (max-width: 480px) {
            .admin-stats-grid { grid-template-columns: 1fr; }
            .admin-system-grid { grid-template-columns: 1fr 1fr; }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--admin-text-muted); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--admin-text-sec); }
    </style>
    <script>
        (function(){ var t=localStorage.getItem('theme')||'dark'; document.documentElement.setAttribute('data-theme',t); })();
    </script>
</head>
<body>
    <!-- Mobile Toggle -->
    <button class="admin-mobile-toggle" id="sidebarToggle" aria-label="فتح القائمة">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
    <div class="admin-overlay" id="adminOverlay"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-logo">
            <div class="admin-sidebar-logo-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h2>لوحة التحكم</h2>
        </div>
        <nav class="admin-sidebar-nav">
            <a href="index.php" class="admin-sidebar-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                الرئيسية
            </a>
            <a href="users.php" class="admin-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                المستخدمين
            </a>
            <a href="payments.php" class="admin-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                المدفوعات
            </a>
            <a href="logs.php" class="admin-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                السجلات
            </a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-divider"></div>
            <a href="../index.php" class="admin-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                العودة للموقع
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1>مرحباً، <?php echo sanitizeOutput($currentUser['name']); ?> 👋</h1>
                <p class="admin-header-sub">إليك ملخص نظام دليل الهاتف الدولي</p>
            </div>
            <div class="admin-header-actions">
                <span class="admin-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    مدير النظام
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="admin-stats-grid" id="statsGrid">
            <div class="admin-stat">
                <div class="admin-stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div class="admin-stat-value" id="statTotalUsers">-</div>
                    <div class="admin-stat-label">إجمالي المستخدمين</div>
                </div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div>
                    <div class="admin-stat-value" id="statActiveUsers">-</div>
                    <div class="admin-stat-label">المستخدمين النشطين</div>
                </div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-icon gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
                <div>
                    <div class="admin-stat-value" id="statMonthlyPayments">-</div>
                    <div class="admin-stat-label">المدفوعات الشهرية</div>
                </div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <div>
                    <div class="admin-stat-value" id="statTodaySearches">-</div>
                    <div class="admin-stat-label">عمليات البحث اليومية</div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="admin-cards-grid">
            <!-- Recent Users -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <div class="admin-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        آخر المستخدمين المسجلين
                    </div>
                    <a href="users.php" class="btn btn-ghost btn-sm" style="font-size:0.78rem;">عرض الكل</a>
                </div>
                <div class="admin-card-body">
                    <div id="recentUsersTable"><div class="admin-spinner"></div></div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <div class="admin-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        آخر المدفوعات
                    </div>
                    <a href="payments.php" class="btn btn-ghost btn-sm" style="font-size:0.78rem;">عرض الكل</a>
                </div>
                <div class="admin-card-body">
                    <div id="recentPaymentsTable"><div class="admin-spinner"></div></div>
                </div>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="admin-card" style="margin-bottom: 2rem;">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    آخر الأنشطة
                </div>
                <a href="logs.php" class="btn btn-ghost btn-sm" style="font-size:0.78rem;">عرض الكل</a>
            </div>
            <div class="admin-card-body">
                <div id="recentLogsTable"><div class="admin-spinner"></div></div>
            </div>
        </div>

        <!-- System Info -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    معلومات النظام
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-system-grid" id="systemInfoGrid"><div class="admin-spinner"></div></div>
            </div>
        </div>
    </main>

    <!-- Toast Container -->
    <div class="admin-toast-container" id="toastContainer"></div>

    <script>
        // CSRF token
        const CSRF = '<?php echo $csrfToken; ?>';
        const API_URL = '../api/admin.php';

        // Sidebar toggle
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('adminOverlay');
        const toggle = document.getElementById('sidebarToggle');

        toggle.addEventListener('click', () => { sidebar.classList.add('open'); overlay.classList.add('show'); });
        overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });

        // Toast notification
        function showToast(msg, type = 'success') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'admin-toast ' + type;
            t.textContent = msg;
            c.appendChild(t);
            setTimeout(() => { t.remove(); }, 4000);
        }

        // Format number
        function fmtNum(n) { return Number(n).toLocaleString('ar-EG'); }

        // Get action badge class
        function actionClass(action) {
            if (!action) return 'default';
            if (action.includes('login') || action.includes('logout')) return 'login';
            if (action.includes('register')) return 'register';
            if (action.includes('payment')) return 'payment';
            if (action.includes('search')) return 'search';
            if (action.includes('error') || action.includes('fail') || action.includes('blocked')) return 'error';
            if (action.includes('admin')) return 'admin';
            return 'default';
        }

        // Plan badge HTML
        function planBadge(plan) {
            plan = (plan || 'FREE').toLowerCase();
            const labels = { free: 'مجاني', pro: 'احترافي', premium: 'مميز' };
            return `<span class="plan-badge-admin ${plan}">${labels[plan] || plan}</span>`;
        }

        // Status badge HTML
        function statusBadge(status) {
            const labels = { pending: 'معلق', approved: 'مقبول', rejected: 'مرفوض' };
            return `<span class="status-badge-admin ${(status||'').toLowerCase()}">${labels[(status||'').toLowerCase()] || status}</span>`;
        }

        // Avatar HTML
        function avatarHtml(name, size) {
            size = size || 32;
            const initial = (name || '?').charAt(0).toUpperCase();
            const colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63','#00bcd4','#ff5722'];
            let hash = 0;
            for (let i = 0; i < (name||'').length; i++) hash = (name||'').charCodeAt(i) + ((hash << 5) - hash);
            const bg = colors[Math.abs(hash) % colors.length];
            return `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${bg};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:${size*0.38}px;flex-shrink:0;">${initial}</div>`;
        }

        // Time ago (Arabic)
        function timeAgo(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - d) / 1000);
            if (diff < 60) return 'الآن';
            if (diff < 3600) return 'منذ ' + Math.floor(diff/60) + ' دقيقة';
            if (diff < 86400) return 'منذ ' + Math.floor(diff/3600) + ' ساعة';
            if (diff < 2592000) return 'منذ ' + Math.floor(diff/86400) + ' يوم';
            return 'منذ ' + Math.floor(diff/2592000) + ' شهر';
        }

        // API call
        async function adminApi(action, data = {}) {
            data.action = action;
            data.csrf_token = CSRF;
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                return await res.json();
            } catch(e) {
                return { success: false, message: 'خطأ في الاتصال بالخادم' };
            }
        }

        // Load dashboard
        async function loadDashboard() {
            const res = await adminApi('stats/dashboard');
            if (!res.success) { showToast(res.message, 'error'); return; }
            const d = res.data;

            // Stats
            document.getElementById('statTotalUsers').textContent = fmtNum(d.total_users);
            document.getElementById('statActiveUsers').textContent = fmtNum(d.active_users);
            document.getElementById('statMonthlyPayments').textContent = fmtNum(d.monthly_payments) + ' ر.ي';
            document.getElementById('statTodaySearches').textContent = fmtNum(d.today_searches);

            // Recent Users
            const usersHtml = d.recent_users.length === 0
                ? '<div class="admin-card-body no-data">لا يوجد مستخدمين حتى الآن</div>'
                : `<table class="admin-table"><thead><tr><th>#</th><th>المستخدم</th><th>الخطة</th><th>التاريخ</th></tr></thead><tbody>${
                    d.recent_users.map((u, i) => `<tr>
                        <td>${i + 1}</td>
                        <td><div class="user-cell">${avatarHtml(u.name, 32)}<div><div class="user-cell-name">${u.name}</div><div class="user-cell-email">${u.email}</div></div></div></td>
                        <td>${planBadge(u.plan)}</td>
                        <td style="white-space:nowrap;font-size:0.78rem;">${timeAgo(u.created_at)}</td>
                    </tr>`).join('')
                }</tbody></table>`;
            document.getElementById('recentUsersTable').innerHTML = usersHtml;

            // Recent Payments
            const payHtml = d.recent_payments.length === 0
                ? '<div class="admin-card-body no-data">لا يوجد مدفوعات حتى الآن</div>'
                : `<table class="admin-table"><thead><tr><th>#</th><th>المستخدم</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>${
                    d.recent_payments.map((p, i) => `<tr>
                        <td>${i + 1}</td>
                        <td style="font-weight:600;color:var(--admin-text);">${p.user_name || '-'}</td>
                        <td style="font-weight:600;">${fmtNum(p.amount)} ر.ي</td>
                        <td>${statusBadge(p.status)}</td>
                        <td style="white-space:nowrap;font-size:0.78rem;">${timeAgo(p.created_at)}</td>
                    </tr>`).join('')
                }</tbody></table>`;
            document.getElementById('recentPaymentsTable').innerHTML = payHtml;

            // Recent Logs
            const logsHtml = d.recent_logs.length === 0
                ? '<div class="admin-card-body no-data">لا يوجد سجلات حتى الآن</div>'
                : `<table class="admin-table"><thead><tr><th>#</th><th>المستخدم</th><th>الإجراء</th><th>عنوان IP</th><th>التاريخ</th></tr></thead><tbody>${
                    d.recent_logs.map((l, i) => `<tr>
                        <td>${i + 1}</td>
                        <td style="font-weight:600;color:var(--admin-text);">${l.user_name || 'زائر'}</td>
                        <td><span class="action-badge ${actionClass(l.action)}">${l.action}</span></td>
                        <td style="font-size:0.78rem;direction:ltr;text-align:right;">${l.ip_address || '-'}</td>
                        <td style="white-space:nowrap;font-size:0.78rem;">${timeAgo(l.created_at)}</td>
                    </tr>`).join('')
                }</tbody></table>`;
            document.getElementById('recentLogsTable').innerHTML = logsHtml;

            // System Info
            const sys = d.system_info;
            document.getElementById('systemInfoGrid').innerHTML = `
                <div class="admin-system-item"><span class="admin-system-label">إصدار PHP</span><span class="admin-system-value">${sys.php_version}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">حجم قاعدة البيانات</span><span class="admin-system-value">${sys.db_size_formatted}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">خادم الويب</span><span class="admin-system-value">${sys.server_software}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">المنطقة الزمنية</span><span class="admin-system-value">${sys.timezone}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">استخدام الذاكرة</span><span class="admin-system-value">${sys.memory_usage}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">أقصى استخدام للذاكرة</span><span class="admin-system-value">${sys.memory_peak}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">إجمالي عمليات البحث</span><span class="admin-system-value">${fmtNum(d.total_searches)}</span></div>
                <div class="admin-system-item"><span class="admin-system-label">إجمالي السجلات</span><span class="admin-system-value">${fmtNum(d.total_logs)}</span></div>
            `;
        }

        // Init
        document.addEventListener('DOMContentLoaded', loadDashboard);
    </script>
</body>
</html>
