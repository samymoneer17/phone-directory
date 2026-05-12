<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Payment API
 * International Phone Directory - Payment API Endpoints
 * ============================================================
 * Handles: process (Jaib payment), check-status,
 *          toggle-phone-hide
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/jaib-payment.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Require authentication for all payment actions
Auth::requireAuth();

// CSRF token verification
if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
    jsonResponse(['success' => false, 'error' => 'رمز التحقق غير صالح'], 403);
}

$action = post('action');

try {
    $currentUser = Auth::getCurrentUser();

    switch ($action) {

        // =====================================================
        // معالجة الدفع
        // =====================================================
        case 'process':
            $userId        = $currentUser['id'];
            $plan          = Security::sanitizeInput(post('plan'));
            $transactionId = Security::sanitizeInput(post('transaction_id'));

            // Validate plan
            if (!validatePlan($plan)) {
                jsonResponse(['success' => false, 'error' => 'خطة غير صالحة']);
            }

            // Validate transaction ID
            if (empty($transactionId) || strlen($transactionId) < 8) {
                jsonResponse(['success' => false, 'error' => 'رقم العملية غير صالح (8 أحرف على الأقل)']);
            }

            // Check for duplicate transaction
            $jaib = new JaibPayment();
            if ($jaib->checkDuplicateTransaction($transactionId)) {
                jsonResponse([
                    'success' => false,
                    'error'   => 'رقم العملية مستخدم مسبقاً. لا يمكن استخدام نفس رقم العملية مرتين.',
                ]);
            }

            // Rate limit check — checkRateLimit() returns {allowed, remaining, resetIn}
            $ip = Security::getClientIP();
            $rateCheck = Security::checkRateLimit($ip, 'payment', 5, 300);

            if (!$rateCheck['allowed']) {
                jsonResponse([
                    'success'   => false,
                    'error'     => 'تم تجاوز عدد المحاولات. حاول بعد 5 دقائق.',
                    'remaining' => $rateCheck['remaining'],
                    'resetIn'   => $rateCheck['resetIn'],
                ], 429);
            }

            // Process payment — JaibPayment::processPayment(int $userId, string $plan, string $transactionId)
            // Returns: {success, message, subscription?} where subscription has {expires_at, ...}
            $result = $jaib->processPayment($userId, $plan, $transactionId);

            if ($result['success']) {
                Security::logActivity($userId, 'payment', "اشتراك {$plan} - عملية {$transactionId}");
                jsonResponse([
                    'success'    => true,
                    'message'    => $result['message'],
                    'plan'       => $plan,
                    'expires_at' => $result['subscription']['expires_at'] ?? null,
                ]);
            } else {
                Security::logActivity(
                    $userId,
                    'payment_failed',
                    "فشل {$plan} - عملية {$transactionId}: " . ($result['message'] ?? '')
                );
                jsonResponse(['success' => false, 'error' => $result['message'] ?? 'فشل التحقق من الدفع']);
            }
            break;

        // =====================================================
        // التحقق من حالة الاشتراك
        // =====================================================
        case 'check-status':
            $userId = $currentUser['id'];

            // Auth::checkSubscription() returns {active, plan, expiresAt}
            $subCheck = Auth::checkSubscription($userId);

            jsonResponse([
                'success'         => true,
                'plan'            => $currentUser['plan'],
                'is_active'       => $subCheck['active'],
                'expires_at'      => $currentUser['subscription_expires_at'],
                'is_phone_hidden' => (bool) ($currentUser['is_phone_hidden'] ?? 0),
                'can_hide_phone'  => Auth::canHidePhone($userId),
            ]);
            break;

        // =====================================================
        // تفعيل/إلغاء إخفاء الرقم
        // =====================================================
        case 'toggle-phone-hide':
            $userId = $currentUser['id'];

            if (!Auth::canHidePhone($userId)) {
                jsonResponse([
                    'success' => false,
                    'error'   => 'هذه الميزة متاحة فقط للمشتركين في خطط مدفوعة',
                ]);
            }

            $newState = post('hide') === 'true' ? 1 : 0;

            // update() signature: update(string $table, array $data, string $where, array $whereParams = [])
            update('users', ['is_phone_hidden' => $newState], 'id = :id', [':id' => $userId]);

            // Update session cache — session uses 'user_data' key
            if (isset($_SESSION['user_data'])) {
                $_SESSION['user_data']['is_phone_hidden'] = $newState;
            }

            Security::logActivity(
                $userId,
                'settings',
                $newState ? 'تفعيل إخفاء الرقم' : 'إلغاء إخفاء الرقم'
            );

            jsonResponse([
                'success' => true,
                'message' => $newState ? 'تم تفعيل إخفاء رقمك' : 'تم إلغاء إخفاء رقمك',
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
    Security::logActivity($userId, 'error', 'Payment API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'حدث خطأ في الخادم'], 500);
}
