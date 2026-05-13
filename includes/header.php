<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - HTML Header
 * International Phone Directory
 * ============================================================
 * Complete HTML head, navigation header with glassmorphism,
 * theme toggle, and mobile-responsive hamburger menu.
 */

// Ensure session and security are initialized
if (!defined('APP_STARTED')) {
    require_once __DIR__ . '/config.php';
}
if (!class_exists('Security')) {
    require_once __DIR__ . '/security.php';
}
if (!class_exists('Auth')) {
    require_once __DIR__ . '/auth.php';
}

// Apply security headers
Security::applySecurityHeaders();

// Initialize secure session
Security::secureSession();

// Check session expiry
if (Auth::isLoggedIn() && Auth::isSessionExpired()) {
    Auth::logout();
    flash('warning', 'انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
}

// Refresh session if active
if (Auth::isLoggedIn()) {
    Auth::refreshSession();
}

// Get current user data
$currentUser = Auth::getCurrentUser();
$isLoggedIn = Auth::isLoggedIn();
$isAdmin = $currentUser && $currentUser['role'] === 'ADMIN';

// Get flash messages for header display
$flashMessages = getAllFlash();

// Current page detection
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$isActive = function ($page) use ($currentPage) {
    return $currentPage === $page ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo sanitizeOutput(SITE_DESCRIPTION); ?>">
    <meta name="keywords" content="<?php echo sanitizeOutput(SITE_KEYWORDS); ?>">
    <meta name="author" content="<?php echo sanitizeOutput(SITE_NAME); ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo sanitizeOutput(SITE_NAME); ?>">
    <meta property="og:description" content="<?php echo sanitizeOutput(SITE_DESCRIPTION); ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_YE">
    <title><?php echo sanitizeOutput($pageTitle ?? SITE_NAME); ?></title>

    <!-- Google Fonts: Cairo (Arabic) + Inter (Latin) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Lucide Icons (via CDN) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- Inline Critical CSS (Prevent flash) -->
    <style>
        /* CSS Reset & Base */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.72);
            --bg-glass-border: rgba(255, 255, 255, 0.3);
            --bg-glass-hover: rgba(255, 255, 255, 0.85);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-light: rgba(16, 185, 129, 0.1);
            --accent-glow: rgba(16, 185, 129, 0.25);
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --success: #10b981;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.2s ease;
        }

        [data-theme="dark"] {
            --bg-primary: #0b1120;
            --bg-secondary: #111827;
            --bg-card: #1e293b;
            --bg-glass: rgba(15, 23, 42, 0.75);
            --bg-glass-border: rgba(51, 65, 85, 0.4);
            --bg-glass-hover: rgba(30, 41, 59, 0.85);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #1e293b;
            --border-light: #1e293b;
            --accent: #34d399;
            --accent-hover: #10b981;
            --accent-light: rgba(52, 211, 153, 0.1);
            --accent-glow: rgba(52, 211, 153, 0.2);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -2px rgba(0,0,0,0.2);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.3), 0 4px 6px -4px rgba(0,0,0,0.2);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.4), 0 8px 10px -6px rgba(0,0,0,0.3);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Cairo', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.7;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s ease, color 0.3s ease;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Navigation Glassmorphism */
        .nav-glass {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1000;
            background: var(--bg-glass);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--bg-glass-border);
            transition: var(--transition);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .nav-logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            border-radius: var(--radius-sm);
            color: white;
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        .nav-logo-text {
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.925rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent);
            background: var(--accent-light);
        }

        .nav-link.active {
            font-weight: 600;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }

        .nav-btn-ghost:hover {
            color: var(--text-primary);
            background: var(--bg-glass-hover);
        }

        .nav-btn-primary {
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            color: white;
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        .nav-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .nav-btn-outline {
            background: transparent;
            color: var(--accent);
            border: 1.5px solid var(--accent);
        }

        .nav-btn-outline:hover {
            background: var(--accent-light);
        }

        /* Theme Toggle */
        .theme-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            color: var(--accent);
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .theme-toggle .icon-sun, [data-theme="light"] .theme-toggle .icon-moon { display: none; }
        [data-theme="light"] .theme-toggle .icon-sun, .theme-toggle .icon-moon { display: block; }

        /* User Menu Dropdown */
        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-sm);
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .user-menu-btn:hover {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
        }

        .user-avatar-placeholder {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            min-width: 200px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.96);
            transition: all 0.2s ease;
            z-index: 1001;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
        }

        .user-dropdown-item:hover {
            background: var(--accent-light);
            color: var(--accent);
        }

        .user-dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.375rem 0;
        }

        .user-dropdown-item.danger { color: var(--danger); }
        .user-dropdown-item.danger:hover { background: rgba(239, 68, 68, 0.1); }

        /* Plan Badge */
        .plan-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-badge-free { background: var(--border-color); color: var(--text-muted); }
        .plan-badge-pro { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .plan-badge-premium { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

        /* Mobile Hamburger */
        .hamburger {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: transparent;
            border: none;
            cursor: pointer;
            gap: 5px;
            padding: 8px;
        }

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }

        /* Mobile Menu Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-overlay.show { opacity: 1; }

        /* Mobile Nav Panel */
        .mobile-nav {
            display: none;
            position: fixed;
            top: 72px;
            right: 0;
            bottom: 0;
            width: 280px;
            max-width: 85vw;
            background: var(--bg-card);
            border-left: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            z-index: 999;
            overflow-y: auto;
            padding: 1.5rem;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .mobile-nav.show { transform: translateX(0); }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: 0.25rem;
        }

        .mobile-nav-link:hover, .mobile-nav-link.active {
            background: var(--accent-light);
            color: var(--accent);
        }

        .mobile-nav-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1rem 0;
        }

        .mobile-nav-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .mobile-nav-actions .nav-btn {
            justify-content: center;
            width: 100%;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-top: 72px;
        }

        /* Flash Messages */
        .flash-container {
            position: fixed;
            top: 84px;
            right: 1.5rem;
            left: 1.5rem;
            z-index: 997;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }

        .flash-message {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            font-size: 0.925rem;
            font-weight: 500;
            pointer-events: auto;
            animation: flashSlideIn 0.3s ease, flashSlideOut 0.3s ease 4.7s forwards;
            max-width: 500px;
            margin-left: auto;
        }

        .flash-success { border-right: 4px solid var(--success); color: var(--success); }
        .flash-error { border-right: 4px solid var(--danger); color: var(--danger); }
        .flash-warning { border-right: 4px solid var(--warning); color: var(--warning); }
        .flash-info { border-right: 4px solid var(--info); color: var(--info); }

        .flash-close {
            margin-right: auto;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1.1rem;
            line-height: 1;
        }

        @keyframes flashSlideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes flashSlideOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-20px); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links, .user-menu { display: none; }
            .hamburger { display: flex; }
            .mobile-nav, .mobile-overlay { display: block; }
            .nav-container { height: 64px; }
            .main-content { margin-top: 64px; }
            .flash-container { top: 76px; }
        }

        @media (max-width: 480px) {
            .nav-container { padding: 0 1rem; }
            .nav-logo-text { font-size: 1.05rem; }
        }

        /* Utility classes */
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }
        .container-sm { max-width: 640px; margin: 0 auto; padding: 0 1.5rem; }
        .container-md { max-width: 896px; margin: 0 auto; padding: 0 1.5rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    </style>

    <!-- Early Theme Script (prevent flash of wrong theme) -->
    <script>
        (function() {
            var stored = localStorage.getItem('theme');
            var preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            var theme = stored || preferred;
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <!-- Additional page-specific CSS can be loaded here -->
    <?php if (isset($additionalCSS)): ?>
        <link rel="stylesheet" href="<?php echo sanitizeOutput($additionalCSS); ?>">
    <?php endif; ?>
</head>
<body>
    <!-- ============================================================
         Navigation Header
         ============================================================ -->
    <header class="nav-glass" role="banner">
        <nav class="nav-container" aria-label="القائمة الرئيسية">
            <!-- Logo -->
            <a href="<?php echo getPageUrl('index.php'); ?>" class="nav-logo" aria-label="الصفحة الرئيسية">
                <span class="nav-logo-icon">
                    <i data-lucide="phone" style="width:20px;height:20px;"></i>
                </span>
                <span class="nav-logo-text">دليل الهاتف الدولي</span>
            </a>

            <!-- Desktop Nav Links -->
            <ul class="nav-links">
                <li>
                    <a href="<?php echo getPageUrl('index.php'); ?>" class="nav-link <?php echo $isActive('index.php'); ?>">
                        <i data-lucide="home" style="width:16px;height:16px;"></i>
                        الرئيسية
                    </a>
                </li>
                <li>
                    <a href="<?php echo getPageUrl('search.php'); ?>" class="nav-link <?php echo $isActive('search.php'); ?>">
                        <i data-lucide="search" style="width:16px;height:16px;"></i>
                        البحث
                    </a>
                </li>
                <li>
                    <a href="<?php echo getPageUrl('plans.php'); ?>" class="nav-link <?php echo $isActive('plans.php'); ?>">
                        <i data-lucide="crown" style="width:16px;height:16px;"></i>
                        الباقات
                    </a>
                </li>
                <li>
                    <a href="<?php echo getPageUrl('payment.php'); ?>" class="nav-link <?php echo $isActive('payment.php'); ?>">
                        <i data-lucide="credit-card" style="width:16px;height:16px;"></i>
                        الدفع
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li>
                    <a href="<?php echo getPageUrl('admin/dashboard.php'); ?>" class="nav-link <?php echo $isActive('dashboard.php'); ?>">
                        <i data-lucide="shield" style="width:16px;height:16px;"></i>
                        لوحة التحكم
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Nav Actions -->
            <div class="nav-actions">
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي" title="تبديل الوضع">
                    <i data-lucide="moon" class="icon-moon" style="width:18px;height:18px;"></i>
                    <i data-lucide="sun" class="icon-sun" style="width:18px;height:18px;"></i>
                </button>

                <?php if ($isLoggedIn && $currentUser): ?>
                    <!-- User Menu (Logged In) -->
                    <div class="user-menu" id="userMenu">
                        <button class="user-menu-btn" id="userMenuBtn" aria-expanded="false" aria-haspopup="true">
                            <?php if (!empty($currentUser['avatar'])): ?>
                                <img src="<?php echo sanitizeOutput($currentUser['avatar']); ?>" alt="" class="user-avatar">
                            <?php else: ?>
                                <span class="user-avatar-placeholder">
                                    <?php echo mb_substr($currentUser['name'], 0, 1, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                            <span><?php echo sanitizeOutput(truncateText($currentUser['name'], 15)); ?></span>
                            <span class="plan-badge plan-badge-<?php echo strtolower($currentUser['plan']); ?>">
                                <?php echo PLANS[$currentUser['plan']]['name'] ?? 'مجاني'; ?>
                            </span>
                            <i data-lucide="chevron-down" style="width:14px;height:14px;"></i>
                        </button>

                        <div class="user-dropdown" id="userDropdown" role="menu">
                            <a href="<?php echo getPageUrl('profile.php'); ?>" class="user-dropdown-item" role="menuitem">
                                <i data-lucide="user" style="width:16px;height:16px;"></i>
                                الملف الشخصي
                            </a>
                            <a href="<?php echo getPageUrl('search-history.php'); ?>" class="user-dropdown-item" role="menuitem">
                                <i data-lucide="history" style="width:16px;height:16px;"></i>
                                سجل البحث
                            </a>
                            <a href="<?php echo getPageUrl('subscription.php'); ?>" class="user-dropdown-item" role="menuitem">
                                <i data-lucide="credit-card" style="width:16px;height:16px;"></i>
                                الاشتراك والمدفوعات
                            </a>
                            <a href="<?php echo getPageUrl('settings.php'); ?>" class="user-dropdown-item" role="menuitem">
                                <i data-lucide="settings" style="width:16px;height:16px;"></i>
                                الإعدادات
                            </a>
                            <?php if ($isAdmin): ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="<?php echo getPageUrl('admin/dashboard.php'); ?>" class="user-dropdown-item" role="menuitem">
                                <i data-lucide="shield" style="width:16px;height:16px;"></i>
                                لوحة التحكم
                            </a>
                            <?php endif; ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="<?php echo getPageUrl('logout.php'); ?>" class="user-dropdown-item danger" role="menuitem">
                                <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                                تسجيل الخروج
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login/Register Buttons -->
                    <a href="<?php echo getPageUrl('login.php'); ?>" class="nav-btn nav-btn-ghost">
                        <i data-lucide="log-in" style="width:16px;height:16px;"></i>
                        تسجيل الدخول
                    </a>
                    <a href="<?php echo getPageUrl('register.php'); ?>" class="nav-btn nav-btn-primary">
                        <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
                        إنشاء حساب
                    </a>
                <?php endif; ?>

                <!-- Hamburger (Mobile) -->
                <button class="hamburger" id="hamburgerBtn" aria-label="فتح القائمة" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </nav>
    </header>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Mobile Navigation Panel -->
    <nav class="mobile-nav" id="mobileNav" aria-label="القائمة الجانبية">
        <?php if ($isLoggedIn && $currentUser): ?>
            <!-- Mobile User Info -->
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border-color);">
                <?php if (!empty($currentUser['avatar'])): ?>
                    <img src="<?php echo sanitizeOutput($currentUser['avatar']); ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);">
                <?php else: ?>
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#06b6d4);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;">
                        <?php echo mb_substr($currentUser['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="font-weight:600;font-size:0.95rem;"><?php echo sanitizeOutput($currentUser['name']); ?></div>
                    <span class="plan-badge plan-badge-<?php echo strtolower($currentUser['plan']); ?>">
                        <?php echo PLANS[$currentUser['plan']]['name'] ?? 'مجاني'; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <a href="<?php echo getPageUrl('index.php'); ?>" class="mobile-nav-link <?php echo $isActive('index.php'); ?>">
            <i data-lucide="home" style="width:18px;height:18px;"></i>
            الرئيسية
        </a>
        <a href="<?php echo getPageUrl('search.php'); ?>" class="mobile-nav-link <?php echo $isActive('search.php'); ?>">
            <i data-lucide="search" style="width:18px;height:18px;"></i>
            البحث
        </a>
        <a href="<?php echo getPageUrl('plans.php'); ?>" class="mobile-nav-link <?php echo $isActive('plans.php'); ?>">
            <i data-lucide="crown" style="width:18px;height:18px;"></i>
            الباقات
        </a>
        <a href="<?php echo getPageUrl('payment.php'); ?>" class="mobile-nav-link <?php echo $isActive('payment.php'); ?>">
            <i data-lucide="credit-card" style="width:18px;height:18px;"></i>
            الدفع
        </a>

        <?php if ($isLoggedIn && $currentUser): ?>
            <div class="mobile-nav-divider"></div>
            <a href="<?php echo getPageUrl('profile.php'); ?>" class="mobile-nav-link">
                <i data-lucide="user" style="width:18px;height:18px;"></i>
                الملف الشخصي
            </a>
            <a href="<?php echo getPageUrl('search-history.php'); ?>" class="mobile-nav-link">
                <i data-lucide="history" style="width:18px;height:18px;"></i>
                سجل البحث
            </a>
            <a href="<?php echo getPageUrl('subscription.php'); ?>" class="mobile-nav-link">
                <i data-lucide="credit-card" style="width:18px;height:18px;"></i>
                الاشتراك والمدفوعات
            </a>
            <a href="<?php echo getPageUrl('settings.php'); ?>" class="mobile-nav-link">
                <i data-lucide="settings" style="width:18px;height:18px;"></i>
                الإعدادات
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?php echo getPageUrl('admin/dashboard.php'); ?>" class="mobile-nav-link">
                <i data-lucide="shield" style="width:18px;height:18px;"></i>
                لوحة التحكم
            </a>
            <?php endif; ?>

            <div class="mobile-nav-actions">
                <a href="<?php echo getPageUrl('logout.php'); ?>" class="nav-btn nav-btn-ghost" style="color:var(--danger);">
                    <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                    تسجيل الخروج
                </a>
            </div>
        <?php else: ?>
            <div class="mobile-nav-divider"></div>
            <div class="mobile-nav-actions">
                <a href="<?php echo getPageUrl('login.php'); ?>" class="nav-btn nav-btn-outline">
                    <i data-lucide="log-in" style="width:16px;height:16px;"></i>
                    تسجيل الدخول
                </a>
                <a href="<?php echo getPageUrl('register.php'); ?>" class="nav-btn nav-btn-primary">
                    <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
                    إنشاء حساب
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Flash Messages Container -->
    <?php if (!empty($flashMessages)): ?>
    <div class="flash-container" aria-live="polite">
        <?php foreach ($flashMessages as $type => $message): ?>
        <div class="flash-message flash-<?php echo sanitizeOutput($type); ?>">
            <?php
            $icons = [
                'success' => 'check-circle',
                'error' => 'x-circle',
                'warning' => 'alert-triangle',
                'info' => 'info',
            ];
            $iconName = $icons[$type] ?? 'info';
            ?>
            <i data-lucide="<?php echo $iconName; ?>" style="width:18px;height:18px;flex-shrink:0;"></i>
            <span style="color:var(--text-primary);flex:1;"><?php echo sanitizeOutput($message); ?></span>
            <button class="flash-close" onclick="this.parentElement.remove();" aria-label="إغلاق">&times;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <main class="main-content" role="main">

    <!-- ============================================================
         Navigation JavaScript
         ============================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // === Theme Toggle ===
            var themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    var current = document.documentElement.getAttribute('data-theme');
                    var next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);

                    // Re-initialize icons after theme change (for icon swaps)
                    if (typeof lucide !== 'undefined') {
                        setTimeout(function() { lucide.createIcons(); }, 50);
                    }
                });
            }

            // === User Menu Dropdown ===
            var userMenuBtn = document.getElementById('userMenuBtn');
            var userDropdown = document.getElementById('userDropdown');
            var userMenu = document.getElementById('userMenu');

            if (userMenuBtn && userDropdown) {
                userMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isOpen = userDropdown.classList.contains('show');
                    userDropdown.classList.toggle('show');
                    userMenuBtn.setAttribute('aria-expanded', !isOpen);
                });

                document.addEventListener('click', function(e) {
                    if (userMenu && !userMenu.contains(e.target)) {
                        userDropdown.classList.remove('show');
                        userMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        userDropdown.classList.remove('show');
                        userMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // === Mobile Hamburger Menu ===
            var hamburgerBtn = document.getElementById('hamburgerBtn');
            var mobileNav = document.getElementById('mobileNav');
            var mobileOverlay = document.getElementById('mobileOverlay');

            function openMobileMenu() {
                hamburgerBtn.classList.add('active');
                mobileNav.classList.add('show');
                mobileOverlay.classList.add('show');
                hamburgerBtn.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileMenu() {
                hamburgerBtn.classList.remove('active');
                mobileNav.classList.remove('show');
                mobileOverlay.classList.remove('show');
                hamburgerBtn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            if (hamburgerBtn && mobileNav && mobileOverlay) {
                hamburgerBtn.addEventListener('click', function() {
                    var isOpen = mobileNav.classList.contains('show');
                    if (isOpen) {
                        closeMobileMenu();
                    } else {
                        openMobileMenu();
                    }
                });

                mobileOverlay.addEventListener('click', closeMobileMenu);

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeMobileMenu();
                    }
                });
            }

            // === Auto-dismiss flash messages ===
            var flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(function(msg) {
                setTimeout(function() {
                    if (msg.parentElement) {
                        msg.remove();
                    }
                }, 5000);
            });
        });
    </script>
