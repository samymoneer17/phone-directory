<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Configuration
 * International Phone Directory
 * ============================================================
 */

defined('APP_STARTED') || define('APP_STARTED', true);

// ============================================================
// Site Configuration
// ============================================================
define('SITE_NAME', 'دليل الهاتف الدولي');
define('SITE_NAME_EN', 'International Phone Directory');
define('SITE_URL', '/');
define('SITE_DESCRIPTION', 'دليل هاتف دولي شامل للبحث عن الأرقام والتعرف على هواتف مجهولة من جميع دول العالم');
define('SITE_KEYWORDS', 'دليل هاتف, بحث عن رقم, معرفة صاحب الرقم, هاتف دولي, رقم مجهول, Yemen Phone Directory');

// ============================================================
// Vercel Detection
// ============================================================
define('IS_VERCEL', (bool) getenv('VERCEL'));

// ============================================================
// Path Configuration
// ============================================================
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('DATABASE_PATH', IS_VERCEL ? '/tmp' : BASE_PATH . '/database');
define('DB_FILE', DATABASE_PATH . '/app.db');
define('SCHEMA_FILE', BASE_PATH . '/database/schema.sql');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOADS_PATH', IS_VERCEL ? '/tmp/uploads' : BASE_PATH . '/uploads');
define('CACHE_PATH', IS_VERCEL ? '/tmp/cache' : BASE_PATH . '/cache');

// ============================================================
// Site URL
// ============================================================
define('SITE_URL', getenv('VERCEL_URL') ? 'https://' . getenv('VERCEL_URL') : '/');

// ============================================================
// Session Configuration
// ============================================================
define('SESSION_LIFETIME', 7200);       // 2 hours in seconds
define('SESSION_NAME', 'phone_dir_sid');
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SECURE', IS_VERCEL);  // HTTPS on Vercel
define('SESSION_COOKIE_SAMESITE', 'Lax');
define('REMEMBER_ME_LIFETIME', 2592000); // 30 days in seconds

// ============================================================
// Security Settings
// ============================================================
define('BCRYPT_COST', 12);
define('RESET_TOKEN_LENGTH', 32);
define('RESET_TOKEN_EXPIRY', 3600);      // 1 hour in seconds
define('CSRF_TOKEN_LENGTH', 32);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);       // 15 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 128);

// ============================================================
// Rate Limiting
// ============================================================
define('RATE_LIMIT_SEARCH', 30);         // 30 searches per minute
define('RATE_LIMIT_LOGIN', 10);          // 10 login attempts per minute
define('RATE_LIMIT_REGISTER', 5);        // 5 registrations per minute
define('RATE_LIMIT_API', 60);            // 60 API requests per minute
define('RATE_LIMIT_WINDOW', 60);         // 1 minute window

// ============================================================
// Search Configuration
// ============================================================
define('FREE_SEARCH_LIMIT', 10);         // 10 searches per day for free users
define('PRO_SEARCH_LIMIT', 100);         // 100 searches per day for pro users
define('PREMIUM_SEARCH_LIMIT', 99999);   // Unlimited for premium

// ============================================================
// Plan Definitions & Pricing
// ============================================================
define('PLANS', [
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
define('JAIB_FCM_KEY', 'cKaO7jFQ7JgS2G97QovObIlIK4MHMUcrXJzAxOj8WNI=');
define('JAIB_FCM_IV', 'dbCnTfkJJPMYk09L5qMEgA==');
define('JAIB_CLIENT_IV', '1T9yGplidi4rj0NAFdd3Gg==');

// بيانات الجهاز الثابتة
define('JAIB_DEVICE_ID', 'ffffffff-de16-649e-0000-000000000020@ea14e74f-aae2-47e3-b531-57cab72b1436');
define('JAIB_SMS_CODE', '0qDUPl/hH8g');
define('JAIB_TKN_NOT', 'e77Y_8f3TyC2BV7jZxEdR5:APA91bGAG_pizZVfBSIS77pKNPfwKkamj7rwmBtqrTih2urGz8YfpcHrEcyesJWDVH8JkpbNPTnUDSSTPdBoGZiD1mfNYLkMwU6I7ObvUCyFDNXMIdhKWZU');
define('JAIB_INIT_VALUE', 'Cn9dxD4IEqmmTOu4+0VeX5hdY3c7m2WRPXHy93');
define('JAIB_AUTH_HEADER', 'Basic SGF6bWlBZ2VudFNlcnZpY2U6QWRtaW4hI0AyNDU');

// سيرفرات Jaib
define('JAIB_SERVERS', [
    'https://www.w-jaib.com:2074',
    'https://api.jaib.com.ye:1074',
    'https://api3.jaib.com.ye:9974',
    'https://api.e-jaib.com:2174',
    'https://api.ahd.com.ye:6073',
    'https://api.jaib.com.ye:6072',
]);

// إعدادات عامة
define('JAIB_APP_VERSION', '446');
define('JAIB_UPDATE_DATA', '20220329171');
define('JAIB_TIMEOUT', 15); // seconds

// العملة
define('JAIB_CURRENCY', 'YER');

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
