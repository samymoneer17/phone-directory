<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Configuration
 * International Phone Directory
 * ============================================================
 */

defined('APP_STARTED') || define('APP_STARTED', true);
// Guard: prevent re-definition warnings on Vercel
if (defined('CONFIG_LOADED')) return;
define('CONFIG_LOADED', true);

// ============================================================
// Site Configuration
// ============================================================
defined('SITE_NAME') or define('SITE_NAME', 'دليل الهاتف الدولي');
defined('SITE_NAME_EN') or define('SITE_NAME_EN', 'International Phone Directory');
defined('SITE_DESCRIPTION') or define('SITE_DESCRIPTION', 'دليل هاتف دولي شامل للبحث عن الأرقام والتعرف على هواتف مجهولة من جميع دول العالم');
defined('SITE_KEYWORDS') or define('SITE_KEYWORDS', 'دليل هاتف, بحث عن رقم, معرفة صاحب الرقم, هاتف دولي, رقم مجهول, Yemen Phone Directory');

// ============================================================
// Vercel Detection
// ============================================================
defined('IS_VERCEL') or define('IS_VERCEL', (bool) getenv('VERCEL'));

// ============================================================
// Path Configuration
// ============================================================
defined('BASE_PATH') or define('BASE_PATH', dirname(__DIR__));
defined('INCLUDES_PATH') or define('INCLUDES_PATH', BASE_PATH . '/includes');
defined('DATABASE_PATH') or define('DATABASE_PATH', IS_VERCEL ? '/tmp' : BASE_PATH . '/database');
defined('DB_FILE') or define('DB_FILE', DATABASE_PATH . '/app.db');
defined('SCHEMA_FILE') or define('SCHEMA_FILE', BASE_PATH . '/database/schema.sql');
defined('PUBLIC_PATH') or define('PUBLIC_PATH', BASE_PATH . '/public');
defined('UPLOADS_PATH') or define('UPLOADS_PATH', IS_VERCEL ? '/tmp/uploads' : BASE_PATH . '/uploads');
defined('CACHE_PATH') or define('CACHE_PATH', IS_VERCEL ? '/tmp/cache' : BASE_PATH . '/cache');

// ============================================================
// Site URL
// ============================================================
defined('SITE_URL') or define('SITE_URL', getenv('VERCEL_URL') ? 'https://' . getenv('VERCEL_URL') : '/');

// ============================================================
// Session Configuration
// ============================================================
defined('SESSION_LIFETIME') or define('SESSION_LIFETIME', 7200);
defined('SESSION_NAME') or define('SESSION_NAME', 'phone_dir_sid');
defined('SESSION_COOKIE_HTTPONLY') or define('SESSION_COOKIE_HTTPONLY', true);
defined('SESSION_COOKIE_SECURE') or define('SESSION_COOKIE_SECURE', IS_VERCEL);
defined('SESSION_COOKIE_SAMESITE') or define('SESSION_COOKIE_SAMESITE', 'Lax');
defined('REMEMBER_ME_LIFETIME') or define('REMEMBER_ME_LIFETIME', 2592000);

// ============================================================
// Security Settings
// ============================================================
defined('BCRYPT_COST') or define('BCRYPT_COST', 12);
defined('RESET_TOKEN_LENGTH') or define('RESET_TOKEN_LENGTH', 32);
defined('RESET_TOKEN_EXPIRY') or define('RESET_TOKEN_EXPIRY', 3600);
defined('CSRF_TOKEN_LENGTH') or define('CSRF_TOKEN_LENGTH', 32);
defined('CSRF_SECRET') or define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'phone_dir_csrf_hmac_secret_2024_xK9mQ3vR7wZ');
defined('CSRF_TOKEN_TTL') or define('CSRF_TOKEN_TTL', 3600); // 1 hour
defined('MAX_LOGIN_ATTEMPTS') or define('MAX_LOGIN_ATTEMPTS', 5);
defined('LOGIN_LOCKOUT_TIME') or define('LOGIN_LOCKOUT_TIME', 900);
defined('PASSWORD_MIN_LENGTH') or define('PASSWORD_MIN_LENGTH', 8);
defined('PASSWORD_MAX_LENGTH') or define('PASSWORD_MAX_LENGTH', 128);

// ============================================================
// Advanced Security Settings
// ============================================================
defined('ADMIN_SESSION_TIMEOUT') or define('ADMIN_SESSION_TIMEOUT', 28800);
defined('AUTO_BLOCK_VIOLATIONS') or define('AUTO_BLOCK_VIOLATIONS', 20);
defined('IP_BLOCK_DEFAULT_DURATION') or define('IP_BLOCK_DEFAULT_DURATION', 3600);
defined('MAX_UPLOAD_SIZE') or define('MAX_UPLOAD_SIZE', 5242880);
defined('HONEYPOT_FIELD_NAME') or define('HONEYPOT_FIELD_NAME', 'website');

// ============================================================
// Vercel Cron Secret (optional, for scheduled tasks)
// ============================================================
defined('VERCEL_CRON_SECRET') or define('VERCEL_CRON_SECRET', getenv('VERCEL_CRON_SECRET') ?: '');

// ============================================================
// Rate Limiting
// ============================================================
defined('RATE_LIMIT_SEARCH') or define('RATE_LIMIT_SEARCH', 30);
defined('RATE_LIMIT_LOGIN') or define('RATE_LIMIT_LOGIN', 10);
defined('RATE_LIMIT_REGISTER') or define('RATE_LIMIT_REGISTER', 5);
defined('RATE_LIMIT_API') or define('RATE_LIMIT_API', 60);
defined('RATE_LIMIT_WINDOW') or define('RATE_LIMIT_WINDOW', 60);

// ============================================================
// Search Configuration
// ============================================================
defined('FREE_SEARCH_LIMIT') or define('FREE_SEARCH_LIMIT', 10);
defined('PRO_SEARCH_LIMIT') or define('PRO_SEARCH_LIMIT', 100);
defined('PREMIUM_SEARCH_LIMIT') or define('PREMIUM_SEARCH_LIMIT', 99999);

// ============================================================
// Plan Definitions & Pricing
// ============================================================
defined('PLANS') or define('PLANS', [
    'FREE' => [
        'name' => 'مجاني',
        'name_en' => 'Free',
        'price' => 0,
        'currency' => 'YER',
        'duration' => 0,               // Unlimited (no expiry)
        'duration_text' => 'دائم',
        'search_limit' => FREE_SEARCH_LIMIT,
        'features' => [
            '10 عمليات بحث يومياً',
            'نتائج أساسية',
            'البحث بالاسم والرقم',
        ],
        'can_hide_phone' => false,
        'priority_support' => false,
    ],
    'PRO' => [
        'name' => 'احترافي',
        'name_en' => 'Pro',
        'price' => 2000,
        'currency' => 'YER',
        'duration' => 30,              // 30 days
        'duration_text' => '30 يوم',
        'search_limit' => PRO_SEARCH_LIMIT,
        'features' => [
            '100 عملية بحث يومياً',
            'نتائج تفصيلية',
            'إخفاء رقم الهاتف',
            'سجل البحث الكامل',
            'دعم ذو أولوية',
        ],
        'can_hide_phone' => true,
        'priority_support' => true,
    ],
    'PREMIUM' => [
        'name' => 'مميز',
        'name_en' => 'Premium',
        'price' => 5000,
        'currency' => 'YER',
        'duration' => 30,              // 30 days
        'duration_text' => '30 يوم',
        'search_limit' => PREMIUM_SEARCH_LIMIT,
        'features' => [
            'بحث غير محدود',
            'نتائج تفصيلية كاملة',
            'إخفاء رقم الهاتف',
            'سجل البحث الكامل',
            'دعم ذو أولوية VIP',
            'واجهة خالية من الإعلانات',
            'تحميل التقارير PDF',
        ],
        'can_hide_phone' => true,
        'priority_support' => true,
    ],
]);

// ============================================================
// Jaib Payment System - المفاتيح والتكوين (مضمنة في الكود)
// مأخوذة من تحليل تطبيق Jaib الرسمي
// ============================================================

// مفاتيح التشفير (AES-256-CBC)
defined('JAIB_FCM_KEY') or define('JAIB_FCM_KEY', 'cKaO7jFQ7JgS2G97QovObIlIK4MHMUcrXJzAxOj8WNI=');
defined('JAIB_FCM_IV') or define('JAIB_FCM_IV', 'dbCnTfkJJPMYk09L5qMEgA==');
defined('JAIB_CLIENT_IV') or define('JAIB_CLIENT_IV', '1T9yGplidi4rj0NAFdd3Gg==');

// بيانات الجهاز الثابتة
defined('JAIB_DEVICE_ID') or define('JAIB_DEVICE_ID', 'ffffffff-de16-649e-0000-000000000020@ea14e74f-aae2-47e3-b531-57cab72b1436');
defined('JAIB_SMS_CODE') or define('JAIB_SMS_CODE', '0qDUPl/hH8g');
defined('JAIB_TKN_NOT') or define('JAIB_TKN_NOT', 'e77Y_8f3TyC2BV7jZxEdR5:APA91bGAG_pizZVfBSIS77pKNPfwKkamj7rwmBtqrTih2urGz8YfpcHrEcyesJWDVH8JkpbNPTnUDSSTPdBoGZiD1mfNYLkMwU6I7ObvUCyFDNXMIdhKWZU');
defined('JAIB_INIT_VALUE') or define('JAIB_INIT_VALUE', 'Cn9dxD4IEqmmTOu4+0VeX5hdY3c7m2WRPXHy93');
defined('JAIB_AUTH_HEADER') or define('JAIB_AUTH_HEADER', 'Basic SGF6bWlBZ2VudFNlcnZpY2U6QWRtaW4hI0AyNDU');

// سيرفرات Jaib
defined('JAIB_SERVERS') or define('JAIB_SERVERS', [
    'https://www.w-jaib.com:2074',
    'https://api.jaib.com.ye:1074',
    'https://api3.jaib.com.ye:9974',
    'https://api.e-jaib.com:2174',
    'https://api.ahd.com.ye:6073',
    'https://api.jaib.com.ye:6072',
]);

// إعدادات عامة
defined('JAIB_APP_VERSION') or define('JAIB_APP_VERSION', '446');
defined('JAIB_UPDATE_DATA') or define('JAIB_UPDATE_DATA', '20220329171');
defined('JAIB_TIMEOUT') or define('JAIB_TIMEOUT', 15);
defined('JAIB_CURRENCY') or define('JAIB_CURRENCY', 'YER');

// حساب جيب المستقبل (الذي يحول عليه المستخدمون)
defined('JAIB_RECEIVER_ACCOUNT') or define('JAIB_RECEIVER_ACCOUNT', '523416');
defined('JAIB_RECEIVER_NAME') or define('JAIB_RECEIVER_NAME', 'دليل الهاتف الدولي');

// بيانات تسجيل دخول جيب للتحقق من المعاملات
defined('JAIB_ADMIN_PHONE') or define('JAIB_ADMIN_PHONE', '777189801');
defined('JAIB_ADMIN_PASSWORD') or define('JAIB_ADMIN_PASSWORD', '700181334');

// ============================================================
// Error Reporting
// ============================================================
if (IS_VERCEL) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', '/tmp/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/error.log');
}

// ============================================================
// Timezone
// ============================================================
date_default_timezone_set('Asia/Aden');

// ============================================================
// Auto-load Include Files
// ============================================================
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================
// Helper: Get config value
// ============================================================
function config(string $key, $default = null)
{
    $keys = explode('.', $key);
    $value = null;

    switch ($keys[0]) {
        case 'site':
            $map = [
                'name' => SITE_NAME,
                'name_en' => SITE_NAME_EN,
                'url' => SITE_URL,
                'description' => SITE_DESCRIPTION,
                'keywords' => SITE_KEYWORDS,
            ];
            $value = $map[$keys[1] ?? ''] ?? $default;
            break;
        case 'plan':
            $planName = strtoupper($keys[1] ?? 'FREE');
            $field = $keys[2] ?? 'name';
            $value = PLANS[$planName][$field] ?? $default;
            break;
        case 'security':
            $map = [
                'bcrypt_cost' => BCRYPT_COST,
                'reset_token_length' => RESET_TOKEN_LENGTH,
                'reset_token_expiry' => RESET_TOKEN_EXPIRY,
                'max_login_attempts' => MAX_LOGIN_ATTEMPTS,
                'login_lockout_time' => LOGIN_LOCKOUT_TIME,
                'password_min' => PASSWORD_MIN_LENGTH,
                'password_max' => PASSWORD_MAX_LENGTH,
            ];
            $value = $map[$keys[1] ?? ''] ?? $default;
            break;
    }

    return $value ?? $default;
}

// ============================================================
// Ensure required directories exist
// ============================================================
foreach ([UPLOADS_PATH, CACHE_PATH, DATABASE_PATH, BASE_PATH . '/logs'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
