<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Settings API
 * International Phone Directory - Settings API Endpoints
 * ============================================================
 * Handles: update-profile, update-password, get-stats, get-profile
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

// Require authentication for all settings actions
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
        // تحديث الملف الشخصي
        // =====================================================
        case 'update-profile':
            $userId = $currentUser['id'];

            $name  = Security::sanitizeInput(post('name'));
            $phone = Security::sanitizeInput(post('phone'));

            if (empty($name) || strlen($name) < 3) {
                jsonResponse(['success' => false, 'error' => 'الاسم مطلوب (3 أحرف على الأقل)']);
            }

            if (!empty($phone) && !Security::validatePhone($phone)) {
                jsonResponse(['success' => false, 'error' => 'رقم الهاتف غير صالح']);
            }

            // Auth::updateProfile(int $userId, array $data)
            // Returns: {success, message}
            $result = Auth::updateProfile($userId, ['name' => $name, 'phone' => $phone]);

            if ($result['success']) {
                Security::logActivity($userId, 'settings', 'تحديث الملف الشخصي');
                // Refresh user data after update
                $updatedUser = Auth::getCurrentUser();
                jsonResponse([
                    'success' => true,
                    'message' => 'تم تحديث البيانات بنجاح',
                    'user'    => [
                        'id'    => $updatedUser['id'],
                        'name'  => $updatedUser['name'],
                        'email' => $updatedUser['email'],
                        'phone' => $updatedUser['phone'],
                        'plan'  => $updatedUser['plan'],
                    ],
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // تغيير كلمة المرور
        // =====================================================
        case 'update-password':
            $userId = $currentUser['id'];

            $oldPassword    = post('current_password');
            $newPassword    = post('new_password');
            $confirmPassword = post('confirm_password');

            if (empty($oldPassword) || empty($newPassword)) {
                jsonResponse(['success' => false, 'error' => 'جميع الحقول مطلوبة']);
            }
            if (strlen($newPassword) < 6) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل']);
            }
            if ($newPassword !== $confirmPassword) {
                jsonResponse(['success' => false, 'error' => 'كلمة المرور الجديدة غير متطابقة']);
            }

            // Auth::updatePassword(int $userId, string $oldPassword, string $newPassword)
            // Returns: {success, message}
            $result = Auth::updatePassword($userId, $oldPassword, $newPassword);

            if ($result['success']) {
                Security::logActivity($userId, 'settings', 'تغيير كلمة المرور');
                jsonResponse(['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح']);
            } else {
                jsonResponse(['success' => false, 'error' => $result['message']]);
            }
            break;

        // =====================================================
        // إحصائيات المستخدم
        // =====================================================
        case 'get-stats':
            $userId = $currentUser['id'];

            // Total searches from user record
            $totalSearches = (int) ($currentUser['search_count'] ?? 0);

            // This month searches — countRecords(string $table, string $where, array $params)
            $monthStart    = date('Y-m-01 00:00:00');
            $monthSearches = countRecords(
                'search_history',
                'user_id = ? AND created_at >= ?',
                [$userId, $monthStart]
            );

            // Recent searches (last 10)
            $recentSearches = fetchAll(
                "SELECT * FROM search_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
                [$userId]
            );

            // Format recent searches with Arabic labels
            foreach ($recentSearches as &$s) {
                $s['time_ago']         = timeAgo($s['created_at']);
                $s['query_type_label'] = $s['query_type'] === 'NAME' ? 'اسم' : 'رقم';
            }
            unset($s); // Break the reference

            jsonResponse([
                'success'                  => true,
                'total_searches'           => $totalSearches,
                'month_searches'           => $monthSearches,
                'recent_searches'          => $recentSearches,
                'plan'                     => $currentUser['plan'],
                'subscription_expires_at'  => $currentUser['subscription_expires_at'],
            ]);
            break;

        // =====================================================
        // بيانات الملف الشخصي
        // =====================================================
        case 'get-profile':
            // Auth::getCurrentUser() returns ?array (already checked via requireAuth)
            $user = Auth::getCurrentUser();

            // Never send password or reset token
            unset($user['password']);
            unset($user['reset_token']);
            unset($user['reset_token_expires_at']);

            jsonResponse([
                'success' => true,
                'user'    => $user,
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
    Security::logActivity($userId, 'error', 'Settings API: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'حدث خطأ في الخادم'], 500);
}
