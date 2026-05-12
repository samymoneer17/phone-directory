<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Authentication API
 * International Phone Directory - Auth API Endpoints
 * ============================================================
 * Handles: login, register, forgot-password, reset-password,
 *          logout
 * 
 * Accepts both JSON and form-data POST requests
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers (must be set before any other logic)
$origin = SITE_URL;
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $parsed = parse_url($_SERVER['HTTP_ORIGIN']);
    $siteParsed = parse_url(SITE_URL);
    if ($parsed['host'] === $siteParsed['host']) {
        $origin = $_SERVER['HTTP_ORIGIN'];
    }
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Initialize secure session for user login state
// CSRF tokens are now stateless (HMAC-based) — no session dependency
Security::secureSession();

// ============================================================
// Read input: support both JSON body and form-data ($_POST)
// ============================================================
$input = Security::getJsonInput();
if ($input === null) {
    // Fallback to $_POST for form-data submissions
    $input = $_POST;
}

if (empty($input)) {
    jsonResponse(['success' => false, 'error' => 'No data received'], 400);
}

// Get action
$action = $input['action'] ?? '';

// CSRF check for all actions except login (to allow initial load)
if ($action !== 'login') {
    $csrfToken = $input['csrf_token'] ?? '';
    if (empty($csrfToken) || !Security::verifyCSRFToken($csrfToken)) {
        jsonResponse(['success' => false, 'error' => 'رمز التحقق غير صالح'], 403);
    }
}

try {
    switch ($action) {

        // =====================================================
        // تسجيل الدخول
        // =====================================================
        case 'login':
            // Rate limit check
            $ip = Security::getClientIP();
            $rateCheck = Security::checkRateLimit($ip, 'login', 5, 300);

            if (!$rateCheck['allowed']) {
                jsonResponse([
                    'success'   => false,
                    'error'     => 'تم تجاوز عدد المحاولات. حاول بعد 5 دقائق.',
                    'remaining' => $rateCheck['remaining'],
                    'resetIn'   => $rateCheck['resetIn'],
                ], 429);
            }

            $email    = Security::sanitizeInput($input['email'] ?? '');
            $password = $input['password'] ?? '';

            if (empty($email) || empty($password)) {
                jsonResponse(['success' => false, 'error' => 'البريد وكلمة المرور مطلوبان']);
            }

            $result = Auth::login($email, $password);

            if ($result['success']) {
                Security::logActivity($result['user']['id'], 'login_api', 'تسجيل دخول ناجح');
                jsonResponse([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'redirect' => 'dashboard.html',
                    'user'    => [
                        'id'    => $result['user']['id'],
                        'name'  => $result['user']['name'],
                        'email' => $result['user']['email'],
                        'plan'  => $result['user']['plan'],
                        'phone' => $result['user']['phone'] ?? '',
                        'created_at' => $result['user']['created_at'] ?? '',
                        'search_count' => (int) ($result['user']['search_count'] ?? 0),
                    ],
                    'auth_token' => $result['auth_token'] ?? '',
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // إنشاء حساب جديد
        // =====================================================
        case 'register':
            // Rate limit
            $ip = Security::getClientIP();
            $rateCheck = Security::checkRateLimit($ip, 'register', 3, 300);

            if (!$rateCheck['allowed']) {
                jsonResponse([
                    'success'   => false,
                    'error'     => 'تم تجاوز عدد المحاولات. حاول بعد 5 دقائق.',
                    'remaining' => $rateCheck['remaining'],
                    'resetIn'   => $rateCheck['resetIn'],
                ], 429);
            }

            $data = [
                'name'     => Security::sanitizeInput($input['name'] ?? ''),
                'email'    => Security::sanitizeInput($input['email'] ?? ''),
                'password' => $input['password'] ?? '',
                'phone'    => Security::sanitizeInput($input['phone'] ?? ''),
            ];

            // Validation
            if (empty($data['name']) || strlen($data['name']) < 3) {
                jsonResponse(['success' => false, 'error' => 'الاسم مطلوب (3 أحرف على الأقل)']);
            }
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'error' => 'بريد إلكتروني غير صالح']);
            }
            if (empty($data['password']) || strlen($data['password']) < 8) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل']);
            }

            $result = Auth::register($data);

            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'تم إنشاء الحساب بنجاح',
                    'redirect' => 'dashboard.html',
                    'user'    => [
                        'id'    => $result['user']['id'] ?? null,
                        'name'  => $result['user']['name'] ?? $data['name'],
                        'email' => $result['user']['email'] ?? $data['email'],
                        'plan'  => $result['user']['plan'] ?? 'FREE',
                        'phone' => $result['user']['phone'] ?? '',
                        'created_at' => $result['user']['created_at'] ?? '',
                        'search_count' => (int) ($result['user']['search_count'] ?? 0),
                    ],
                    'auth_token' => $result['auth_token'] ?? '',
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // استعادة كلمة المرور (إرسال رابط)
        // =====================================================
        case 'forgot-password':
            $email = Security::sanitizeInput($input['email'] ?? '');

            if (empty($email)) {
                jsonResponse(['success' => false, 'error' => 'البريد الإلكتروني مطلوب']);
            }

            $result = Auth::forgotPassword($email);

            // Always return same message to prevent email enumeration
            jsonResponse([
                'success' => true,
                'message' => 'إذا كان البريد مسجلاً، سيتم إرسال رابط الاستعادة',
            ]);
            break;

        // =====================================================
        // إعادة تعيين كلمة المرور
        // =====================================================
        case 'reset-password':
            $token    = $input['token'] ?? '';
            $password = $input['password'] ?? '';
            $confirm  = $input['confirm_password'] ?? '';

            if (empty($token) || empty($password)) {
                jsonResponse(['success' => false, 'error' => 'بيانات غير مكتملة']);
            }
            if ($password !== $confirm) {
                jsonResponse(['success' => false, 'error' => 'كلمتا المرور غير متطابقتين']);
            }
            if (strlen($password) < 6) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
            }

            $result = Auth::resetPassword($token, $password);

            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'تم تغيير كلمة المرور بنجاح',
                    'redirect' => 'login.html',
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // تسجيل الخروج
        // =====================================================
        case 'logout':
            $userId = null;
            // Revoke auth token from request
            $authToken = $input['auth_token'] ?? '';
            if (!empty($authToken)) {
                Auth::revokeAuthToken($authToken);
            }
            if (Auth::isLoggedIn()) {
                $user = Auth::getCurrentUser();
                $userId = $user['id'] ?? null;
            }
            Auth::logout();
            jsonResponse([
                'success'  => true,
                'message'  => 'تم تسجيل الخروج',
                'redirect' => 'index.html',
            ]);
            break;

        // =====================================================
        // إجراء غير معروف
        // =====================================================
        default:
            jsonResponse(['success' => false, 'error' => 'إجراء غير معروف'], 400);
    }
} catch (Exception $e) {
    $userId = null;
    if (Auth::isLoggedIn()) {
        $user = Auth::getCurrentUser();
        $userId = $user['id'] ?? null;
    }
    Security::logActivity($userId, 'error', 'Auth API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'حدث خطأ في الخادم'], 500);
}
