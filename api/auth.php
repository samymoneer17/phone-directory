<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Authentication API
 * International Phone Directory - Auth API Endpoints
 * ============================================================
 * Handles: login, register, forgot-password, reset-password,
 *          logout
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get action
$action = post('action');

// CSRF check for all actions except login (to allow initial load)
if ($action !== 'login') {
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        jsonResponse(['success' => false, 'error' => 'رمز التحقق غير صالح'], 403);
    }
}

try {
    switch ($action) {

        // =====================================================
        // تسجيل الدخول
        // =====================================================
        case 'login':
            // Rate limit check — checkRateLimit() returns {allowed, remaining, resetIn}
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

            $email    = Security::sanitizeInput(post('email'));
            $password = post('password');

            if (empty($email) || empty($password)) {
                jsonResponse(['success' => false, 'error' => 'البريد وكلمة المرور مطلوبان']);
            }

            // Auth::login() signature: login(string $email, string $password)
            $result = Auth::login($email, $password);

            if ($result['success']) {
                Security::logActivity($result['user']['id'], 'login_api', 'تسجيل دخول ناجح');
                jsonResponse([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'redirect' => 'dashboard.php',
                    'user'    => [
                        'id'    => $result['user']['id'],
                        'name'  => $result['user']['name'],
                        'email' => $result['user']['email'],
                        'plan'  => $result['user']['plan'],
                    ],
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
                'name'     => Security::sanitizeInput(post('name')),
                'email'    => Security::sanitizeInput(post('email')),
                'password' => post('password'),
                'phone'    => Security::sanitizeInput(post('phone')),
            ];

            // Validation
            if (empty($data['name']) || strlen($data['name']) < 3) {
                jsonResponse(['success' => false, 'error' => 'الاسم مطلوب (3 أحرف على الأقل)']);
            }
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'error' => 'بريد إلكتروني غير صالح']);
            }
            if (empty($data['password']) || strlen($data['password']) < 6) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
            }

            // Auth::register() signature: register(array $data)
            // Returns: {success, message, user?}
            $result = Auth::register($data);

            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'تم إنشاء الحساب بنجاح',
                    'redirect' => 'dashboard.php',
                    'user'    => [
                        'id'    => $result['user']['id'] ?? null,
                        'name'  => $result['user']['name'] ?? $data['name'],
                        'email' => $result['user']['email'] ?? $data['email'],
                    ],
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // استعادة كلمة المرور (إرسال رابط)
        // =====================================================
        case 'forgot-password':
            $email = Security::sanitizeInput(post('email'));

            if (empty($email)) {
                jsonResponse(['success' => false, 'error' => 'البريد الإلكتروني مطلوب']);
            }

            // Auth::forgotPassword() signature: forgotPassword(string $email)
            // Always returns success to prevent email enumeration
            $result = Auth::forgotPassword($email);

            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'إذا كان البريد مسجلاً، سيتم إرسال رابط الاستعادة',
                ]);
            } else {
                jsonResponse([
                    'success' => true,
                    'message' => 'إذا كان البريد مسجلاً، سيتم إرسال رابط الاستعادة',
                ]);
            }
            break;

        // =====================================================
        // إعادة تعيين كلمة المرور
        // =====================================================
        case 'reset-password':
            $token    = post('token');
            $password = post('password');
            $confirm  = post('confirm_password');

            if (empty($token) || empty($password)) {
                jsonResponse(['success' => false, 'error' => 'بيانات غير مكتملة']);
            }
            if ($password !== $confirm) {
                jsonResponse(['success' => false, 'error' => 'كلمتا المرور غير متطابقتين']);
            }
            if (strlen($password) < 6) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
            }

            // Auth::resetPassword() signature: resetPassword(string $token, string $newPassword)
            // Returns: {success, message}
            $result = Auth::resetPassword($token, $password);

            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'تم تغيير كلمة المرور بنجاح',
                    'redirect' => 'login.php',
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
            if (Auth::isLoggedIn()) {
                $user = Auth::getCurrentUser();
                $userId = $user['id'] ?? null;
            }
            Auth::logout();
            jsonResponse([
                'success'  => true,
                'message'  => 'تم تسجيل الخروج',
                'redirect' => 'index.php',
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
