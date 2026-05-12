<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin API Endpoint (Enhanced Security)
 * International Phone Directory
 * ============================================================
 * POST-only API for admin panel CRUD operations.
 * Requires: admin authentication + CSRF verification + IP check
 */

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير صالحة'], 405);
}

// Load dependencies
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Initial security check (IP blocked, payload size)
Security::initialCheck();

// Initialize session and security
Security::secureSession();

// Require admin authentication
Auth::requireAdmin();

// Get current admin user
$adminUser = Auth::getCurrentUser();
if (!$adminUser || $adminUser['role'] !== 'ADMIN') {
    Security::logSecurityEvent('unauthorized_admin_api', 'CRITICAL',
        $adminUser['id'] ?? null, Security::getClientIP(), 'Non-admin tried to access admin API');
    jsonResponse(['success' => false, 'message' => 'غير مصرح بالوصول'], 403);
}

// Read JSON input with size limit
$input = Security::getJsonInput();
if (!$input || !is_array($input)) {
    $input = $_POST;
}

// Verify CSRF token
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    Security::logSecurityEvent('admin_csrf_fail', 'CRITICAL', $adminUser['id'],
        Security::getClientIP(), 'Invalid CSRF token on admin API');
    jsonResponse(['success' => false, 'message' => 'رمز CSRF غير صالح'], 403);
}

// Get requested action - whitelist validation
$action = $input['action'] ?? '';
$action = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $action);

$allowedActions = [
    'users/list', 'users/update', 'users/delete', 'users/ban', 'users/detail',
    'payments/list', 'payments/approve', 'payments/reject', 'payments/delete',
    'logs/list', 'logs/clear',
    'stats/dashboard',
    'security/blocked-ips', 'security/block-ip', 'security/unblock-ip',
    'security/events',
];

if (empty($action) || !in_array($action, $allowedActions, true)) {
    jsonResponse(['success' => false, 'message' => 'الإجراء غير موجود'], 400);
}

// Rate limiting for admin API
$ip = Security::getClientIP();
$rateCheck = Security::checkRateLimit($ip, 'admin_api', 120, 60);
if (!$rateCheck['allowed']) {
    jsonResponse(['success' => false, 'message' => 'تم تجاوز عدد الطلبات المسموح بها'], 429);
}

// ============================================================
// Route the action
// ============================================================
try {
    switch ($action) {
        // USERS
        case 'users/list':
            handleUsersList($input);
            break;
        case 'users/update':
            handleUsersUpdate($input);
            break;
        case 'users/delete':
            handleUsersDelete($input);
            break;
        case 'users/ban':
            handleUsersBan($input);
            break;
        case 'users/detail':
            handleUsersDetail($input);
            break;

        // PAYMENTS
        case 'payments/list':
            handlePaymentsList($input);
            break;
        case 'payments/approve':
            handlePaymentsApprove($input);
            break;
        case 'payments/reject':
            handlePaymentsReject($input);
            break;
        case 'payments/delete':
            handlePaymentsDelete($input);
            break;

        // LOGS
        case 'logs/list':
            handleLogsList($input);
            break;
        case 'logs/clear':
            handleLogsClear($input);
            break;

        // STATS
        case 'stats/dashboard':
            handleDashboardStats();
            break;

        // SECURITY
        case 'security/blocked-ips':
            handleBlockedIPs();
            break;
        case 'security/block-ip':
            handleBlockIP($input);
            break;
        case 'security/unblock-ip':
            handleUnblockIP($input);
            break;
        case 'security/events':
            handleSecurityEvents($input);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'الإجراء غير موجود'], 400);
    }
} catch (Exception $e) {
    error_log('Admin API Error [' . $action . ']: ' . $e->getMessage());
    Security::logSecurityEvent('admin_api_error', 'WARNING', $adminUser['id'] ?? null,
        Security::getClientIP(), 'Admin API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}

// ============================================================
// USERS HANDLERS
// ============================================================

function handleUsersList(array $input): void
{
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($input['per_page'] ?? 20)));
    $search = Security::sanitizeInput($input['search'] ?? '');
    $plan = Security::sanitizeInput($input['plan'] ?? '');
    $role = Security::sanitizeInput($input['role'] ?? '');
    $status = Security::sanitizeInput($input['status'] ?? '');

    $where = ['1=1'];
    $params = [];

    if (!empty($search)) {
        $where[] = "(name LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone)";
        $params[':search_name'] = '%' . $search . '%';
        $params[':search_email'] = '%' . $search . '%';
        $params[':search_phone'] = '%' . $search . '%';
    }

    if (!empty($plan) && in_array($plan, ['FREE', 'PRO', 'PREMIUM'])) {
        $where[] = "plan = :plan";
        $params[':plan'] = $plan;
    }

    if (!empty($role) && in_array($role, ['USER', 'ADMIN', 'BANNED'])) {
        $where[] = "role = :role";
        $params[':role'] = $role;
    }

    if ($status === 'active') {
        $where[] = "subscription_expires_at IS NOT NULL AND subscription_expires_at > :now";
        $params[':now'] = date('Y-m-d H:i:s');
    } elseif ($status === 'banned') {
        $where[] = "role = 'BANNED'";
    } elseif ($status === 'expired') {
        $where[] = "plan != 'FREE' AND (subscription_expires_at IS NULL OR subscription_expires_at <= :now2)";
        $params[':now2'] = date('Y-m-d H:i:s');
    }

    $whereClause = implode(' AND ', $where);

    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM users WHERE {$whereClause}",
        $params
    )['cnt'];

    $offset = ($page - 1) * $perPage;
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    $users = db()->fetchAll(
        "SELECT id, name, email, phone, avatar, role, plan, subscription_expires_at, 
                is_phone_hidden, search_count, login_attempts, locked_until, created_at, updated_at
         FROM users WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'] ?: generateAvatar($user['name'], 36),
            'role' => $user['role'],
            'plan' => $user['plan'],
            'plan_name' => PLANS[$user['plan']]['name'] ?? 'مجاني',
            'subscription_expires_at' => $user['subscription_expires_at'],
            'is_phone_hidden' => (int) $user['is_phone_hidden'],
            'search_count' => (int) $user['search_count'],
            'login_attempts' => (int) ($user['login_attempts'] ?? 0),
            'locked_until' => $user['locked_until'] ?? null,
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
        ];
    }

    jsonResponse([
        'success' => true,
        'data' => $formattedUsers,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
}

function handleUsersUpdate(array $input): void
{
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف المستخدم غير صالح'], 400);
    }

    $adminUser = Auth::getCurrentUser();
    if ($userId === (int) $adminUser['id']) {
        $role = $input['role'] ?? '';
        if ($role !== 'ADMIN') {
            jsonResponse(['success' => false, 'message' => 'لا يمكنك تغيير دورك الخاص'], 400);
        }
    }

    $user = fetch("SELECT id, role FROM users WHERE id = :id", [':id' => $userId]);
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
    }

    $updateData = ['updated_at' => date('Y-m-d H:i:s')];
    $whereParams = [':id' => $userId];

    if (isset($input['name'])) {
        $name = Security::sanitizeInput($input['name']);
        if (empty($name) || mb_strlen($name) < 2) {
            jsonResponse(['success' => false, 'message' => 'الاسم يجب أن يكون حرفين على الأقل'], 400);
        }
        $updateData['name'] = $name;
    }

    if (isset($input['email'])) {
        $email = strtolower(trim($input['email']));
        if (!Security::validateEmail($email)) {
            jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صالح'], 400);
        }
        $existing = fetch("SELECT id FROM users WHERE email = :email AND id != :id", [':email' => $email, ':id' => $userId]);
        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل'], 400);
        }
        $updateData['email'] = $email;
    }

    if (isset($input['plan'])) {
        $plan = strtoupper(Security::sanitizeInput($input['plan']));
        if (!isset(PLANS[$plan])) {
            jsonResponse(['success' => false, 'message' => 'الخطة غير صالحة'], 400);
        }
        $updateData['plan'] = $plan;

        if ($plan === 'FREE') {
            $updateData['subscription_expires_at'] = null;
            $updateData['is_phone_hidden'] = 0;
        }
    }

    if (isset($input['role'])) {
        $role = strtoupper(Security::sanitizeInput($input['role']));
        if (!in_array($role, ['USER', 'ADMIN', 'BANNED'])) {
            jsonResponse(['success' => false, 'message' => 'الدور غير صالح'], 400);
        }
        $updateData['role'] = $role;
    }

    if (isset($input['is_phone_hidden'])) {
        $updateData['is_phone_hidden'] = (int) $input['is_phone_hidden'];
    }

    if (count($updateData) <= 1) {
        jsonResponse(['success' => false, 'message' => 'لا توجد بيانات للتحديث'], 400);
    }

    $rows = update('users', $updateData, 'id = :id', $whereParams);

    Security::logActivity(
        (int) $adminUser['id'],
        'admin_user_update',
        'Updated user ID ' . $userId . ': ' . json_encode($updateData)
    );
    Security::logSecurityEvent('admin_user_updated', 'INFO', (int) $adminUser['id'],
        Security::getClientIP(), 'Admin updated user ID ' . $userId);

    jsonResponse([
        'success' => true,
        'message' => 'تم تحديث المستخدم بنجاح',
        'affected_rows' => $rows,
    ]);
}

function handleUsersDelete(array $input): void
{
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف المستخدم غير صالح'], 400);
    }

    $adminUser = Auth::getCurrentUser();
    if ($userId === (int) $adminUser['id']) {
        jsonResponse(['success' => false, 'message' => 'لا يمكنك حذف حسابك الخاص'], 400);
    }

    $user = fetch("SELECT id, name, email, role FROM users WHERE id = :id", [':id' => $userId]);
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
    }

    if ($user['role'] === 'ADMIN') {
        jsonResponse(['success' => false, 'message' => 'لا يمكنك حذف حساب مدير'], 400);
    }

    db()->query("DELETE FROM search_history WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM payments WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM subscriptions WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM activity_logs WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM login_attempts WHERE email = :email", [':email' => $user['email']]);

    $rows = db()->query("DELETE FROM users WHERE id = :id", [':id' => $userId])->rowCount();

    Security::logActivity((int) $adminUser['id'], 'admin_user_delete',
        'Deleted user: ' . $user['name'] . ' (' . $user['email'] . ')');
    Security::logSecurityEvent('admin_user_deleted', 'WARNING', (int) $adminUser['id'],
        Security::getClientIP(), 'Admin deleted user: ' . $user['email']);

    jsonResponse([
        'success' => true,
        'message' => 'تم حذف المستخدم بنجاح',
        'affected_rows' => $rows,
    ]);
}

function handleUsersBan(array $input): void
{
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف المستخدم غير صالح'], 400);
    }

    $adminUser = Auth::getCurrentUser();
    if ($userId === (int) $adminUser['id']) {
        jsonResponse(['success' => false, 'message' => 'لا يمكنك حظر حسابك الخاص'], 400);
    }

    $user = fetch("SELECT id, name, role FROM users WHERE id = :id", [':id' => $userId]);
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
    }

    $isBanned = ($user['role'] === 'BANNED');
    $newRole = $isBanned ? 'USER' : 'BANNED';

    $updateData = [
        'role' => $newRole,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if (!$isBanned) {
        // Optionally set lock duration for ban
        $banDuration = (int) ($input['duration'] ?? 0);
        if ($banDuration > 0) {
            $updateData['locked_until'] = date('Y-m-d H:i:s', time() + $banDuration);
        }
        // Reset login attempts
        $updateData['login_attempts'] = 0;
    } else {
        $updateData['locked_until'] = null;
    }

    update('users', $updateData, 'id = :id', [':id' => $userId]);

    $action = $isBanned ? 'unbanned' : 'banned';
    $message = $isBanned ? 'تم إلغاء حظر المستخدم بنجاح' : 'تم حظر المستخدم بنجاح';

    Security::logActivity((int) $adminUser['id'], 'admin_user_' . $action,
        "{$action} user: {$user['name']} (ID: {$userId})");
    Security::logSecurityEvent('admin_user_' . $action, $isBanned ? 'INFO' : 'WARNING',
        (int) $adminUser['id'], Security::getClientIP(),
        "{$action} user: {$user['name']} (ID: {$userId})");

    jsonResponse([
        'success' => true,
        'message' => $message,
        'is_banned' => !$isBanned,
    ]);
}

function handleUsersDetail(array $input): void
{
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف المستخدم غير صالح'], 400);
    }

    $user = fetch("SELECT * FROM users WHERE id = :id", [':id' => $userId]);

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
    }

    $searchHistory = fetchAll(
        "SELECT id, query, query_type, country_code, results_count, created_at
         FROM search_history WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20",
        [':uid' => $userId]
    );

    $payments = fetchAll(
        "SELECT * FROM payments WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20",
        [':uid' => $userId]
    );

    $activityLogs = fetchAll(
        "SELECT * FROM activity_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20",
        [':uid' => $userId]
    );

    $subscription = fetch(
        "SELECT * FROM subscriptions WHERE user_id = :uid AND is_active = 1 ORDER BY started_at DESC LIMIT 1",
        [':uid' => $userId]
    );

    // Login attempts
    $loginAttempts = fetchAll(
        "SELECT * FROM login_attempts WHERE email = :email ORDER BY last_attempt_at DESC LIMIT 10",
        [':email' => $user['email']]
    );

    jsonResponse([
        'success' => true,
        'data' => [
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'avatar' => $user['avatar'] ?: generateAvatar($user['name'], 64),
                'role' => $user['role'],
                'plan' => $user['plan'],
                'plan_name' => PLANS[$user['plan']]['name'] ?? 'مجاني',
                'subscription_expires_at' => $user['subscription_expires_at'],
                'is_phone_hidden' => (int) $user['is_phone_hidden'],
                'search_count' => (int) $user['search_count'],
                'login_attempts' => (int) ($user['login_attempts'] ?? 0),
                'locked_until' => $user['locked_until'] ?? null,
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ],
            'search_history' => $searchHistory,
            'payments' => $payments,
            'activity_logs' => $activityLogs,
            'subscription' => $subscription,
            'login_attempts' => $loginAttempts,
        ],
    ]);
}

// ============================================================
// PAYMENTS HANDLERS
// ============================================================

function handlePaymentsList(array $input): void
{
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($input['per_page'] ?? 20)));
    $status = Security::sanitizeInput($input['status'] ?? '');
    $plan = Security::sanitizeInput($input['plan'] ?? '');
    $dateFrom = Security::sanitizeInput($input['date_from'] ?? '');
    $dateTo = Security::sanitizeInput($input['date_to'] ?? '');

    $where = ['1=1'];
    $params = [];

    if (!empty($status) && in_array($status, ['PENDING', 'APPROVED', 'REJECTED'])) {
        $where[] = "p.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($plan) && in_array($plan, ['FREE', 'PRO', 'PREMIUM'])) {
        $where[] = "p.plan = :plan";
        $params[':plan'] = $plan;
    }

    if (!empty($dateFrom)) {
        $where[] = "p.created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $where[] = "p.created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = implode(' AND ', $where);

    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM payments p WHERE {$whereClause}",
        $params
    )['cnt'];

    $offset = ($page - 1) * $perPage;
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    $payments = db()->fetchAll(
        "SELECT p.*, u.name as user_name, u.email as user_email
         FROM payments p
         LEFT JOIN users u ON p.user_id = u.id
         WHERE {$whereClause}
         ORDER BY p.created_at DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    jsonResponse([
        'success' => true,
        'data' => $payments,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
}

function handlePaymentsApprove(array $input): void
{
    $paymentId = (int) ($input['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الدفعة غير صالح'], 400);
    }

    $payment = fetch(
        "SELECT p.*, u.name as user_name, u.email as user_email 
         FROM payments p LEFT JOIN users u ON p.user_id = u.id
         WHERE p.id = :id",
        [':id' => $paymentId]
    );

    if (!$payment) {
        jsonResponse(['success' => false, 'message' => 'الدفعة غير موجودة'], 404);
    }

    if ($payment['status'] !== 'PENDING') {
        jsonResponse(['success' => false, 'message' => 'هذه الدفعة ليست معلقة'], 400);
    }

    $adminUser = Auth::getCurrentUser();
    $plan = $payment['plan'];
    $planConfig = PLANS[$plan] ?? null;

    if (!$planConfig) {
        jsonResponse(['success' => false, 'message' => 'خطة غير صالحة'], 400);
    }

    db()->beginTransaction();

    try {
        update('payments', ['status' => 'APPROVED'], 'id = :id', [':id' => $paymentId]);

        $updateData = [
            'plan' => $plan,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($planConfig['duration'] > 0) {
            $expiryDate = date('Y-m-d H:i:s', strtotime('+' . $planConfig['duration'] . ' days'));
            $updateData['subscription_expires_at'] = $expiryDate;
        }

        update('users', $updateData, 'id = :id', [':id' => $payment['user_id']]);

        $startDate = date('Y-m-d H:i:s');
        $expiryDate = $planConfig['duration'] > 0
            ? date('Y-m-d H:i:s', strtotime('+' . $planConfig['duration'] . ' days'))
            : null;

        insert('subscriptions', [
            'user_id' => $payment['user_id'],
            'plan' => $plan,
            'started_at' => $startDate,
            'expires_at' => $expiryDate,
            'is_active' => 1,
            'payment_id' => $paymentId,
        ]);

        db()->commit();

        Security::logActivity((int) $adminUser['id'], 'admin_payment_approve',
            "Approved payment ID {$paymentId} for user {$payment['user_name']} ({$payment['plan']})");

        jsonResponse([
            'success' => true,
            'message' => 'تم قبول الدفعة وتفعيل الاشتراك بنجاح',
        ]);
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
}

function handlePaymentsReject(array $input): void
{
    $paymentId = (int) ($input['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الدفعة غير صالح'], 400);
    }

    $payment = fetch(
        "SELECT p.*, u.name as user_name 
         FROM payments p LEFT JOIN users u ON p.user_id = u.id
         WHERE p.id = :id",
        [':id' => $paymentId]
    );

    if (!$payment) {
        jsonResponse(['success' => false, 'message' => 'الدفعة غير موجودة'], 404);
    }

    if ($payment['status'] !== 'PENDING') {
        jsonResponse(['success' => false, 'message' => 'هذه الدفعة ليست معلقة'], 400);
    }

    update('payments', ['status' => 'REJECTED'], 'id = :id', [':id' => $paymentId]);

    $adminUser = Auth::getCurrentUser();
    Security::logActivity((int) $adminUser['id'], 'admin_payment_reject',
        "Rejected payment ID {$paymentId} for user {$payment['user_name']}");

    jsonResponse([
        'success' => true,
        'message' => 'تم رفض الدفعة بنجاح',
    ]);
}

function handlePaymentsDelete(array $input): void
{
    $paymentId = (int) ($input['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الدفعة غير صالح'], 400);
    }

    $payment = fetch("SELECT id FROM payments WHERE id = :id", [':id' => $paymentId]);
    if (!$payment) {
        jsonResponse(['success' => false, 'message' => 'الدفعة غير موجودة'], 404);
    }

    db()->query("DELETE FROM subscriptions WHERE payment_id = :pid", [':pid' => $paymentId]);
    $rows = db()->query("DELETE FROM payments WHERE id = :id", [':id' => $paymentId])->rowCount();

    $adminUser = Auth::getCurrentUser();
    Security::logActivity((int) $adminUser['id'], 'admin_payment_delete',
        "Deleted payment ID {$paymentId}");

    jsonResponse([
        'success' => true,
        'message' => 'تم حذف الدفعة بنجاح',
        'affected_rows' => $rows,
    ]);
}

// ============================================================
// LOGS HANDLERS
// ============================================================

function handleLogsList(array $input): void
{
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($input['per_page'] ?? 20)));
    $userId = (int) ($input['user_id'] ?? 0);
    $actionType = Security::sanitizeInput($input['action_type'] ?? '');
    $dateFrom = Security::sanitizeInput($input['date_from'] ?? '');
    $dateTo = Security::sanitizeInput($input['date_to'] ?? '');

    $where = ['1=1'];
    $params = [];

    if ($userId > 0) {
        $where[] = "l.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if (!empty($actionType)) {
        $where[] = "l.action LIKE :action_type";
        $params[':action_type'] = '%' . $actionType . '%';
    }

    if (!empty($dateFrom)) {
        $where[] = "l.created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $where[] = "l.created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = implode(' AND ', $where);

    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM activity_logs l WHERE {$whereClause}",
        $params
    )['cnt'];

    $offset = ($page - 1) * $perPage;
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    $logs = db()->fetchAll(
        "SELECT l.*, u.name as user_name, u.email as user_email
         FROM activity_logs l
         LEFT JOIN users u ON l.user_id = u.id
         WHERE {$whereClause}
         ORDER BY l.created_at DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    jsonResponse([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
}

function handleLogsClear(array $input): void
{
    $adminUser = Auth::getCurrentUser();

    $confirm = $input['confirm'] ?? '';
    if ($confirm !== 'DELETE_ALL_LOGS') {
        jsonResponse(['success' => false, 'message' => 'يجب تأكيد الحذف'], 400);
    }

    $rows = db()->query("DELETE FROM activity_logs")->rowCount();

    Security::logActivity((int) $adminUser['id'], 'admin_logs_clear',
        "Cleared all activity logs ({$rows} rows deleted)");

    jsonResponse([
        'success' => true,
        'message' => 'تم حذف جميع السجلات بنجاح',
        'deleted_count' => $rows,
    ]);
}

// ============================================================
// DASHBOARD STATS
// ============================================================

function handleDashboardStats(): void
{
    $totalUsers = (int) db()->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'];

    $activeUsers = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM users WHERE plan != 'FREE' AND (subscription_expires_at IS NULL OR subscription_expires_at > :now)",
        [':now' => date('Y-m-d H:i:s')]
    )['cnt'];

    $bannedUsers = (int) db()->fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'BANNED'")['cnt'];

    $monthStart = date('Y-m-01 00:00:00');
    $monthlyPayments = (float) db()->fetch(
        "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'APPROVED' AND created_at >= :month_start",
        [':month_start' => $monthStart]
    )['total'];

    $todayStart = date('Y-m-d 00:00:00');
    $todaySearches = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM search_history WHERE created_at >= :today",
        [':today' => $todayStart]
    )['cnt'];

    $recentUsers = db()->fetchAll(
        "SELECT id, name, email, plan, role, created_at FROM users ORDER BY created_at DESC LIMIT 5"
    );

    $recentPayments = db()->fetchAll(
        "SELECT p.*, u.name as user_name FROM payments p 
         LEFT JOIN users u ON p.user_id = u.id 
         ORDER BY p.created_at DESC LIMIT 5"
    );

    $recentLogs = db()->fetchAll(
        "SELECT l.*, u.name as user_name 
         FROM activity_logs l 
         LEFT JOIN users u ON l.user_id = u.id 
         ORDER BY l.created_at DESC LIMIT 10"
    );

    $paymentStats = db()->fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
            COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN amount ELSE 0 END), 0) as total_amount
         FROM payments"
    );

    $planDistribution = db()->fetchAll(
        "SELECT plan, COUNT(*) as count FROM users GROUP BY plan"
    );

    // Security stats
    $blockedIPs = (int) db()->fetch("SELECT COUNT(*) as cnt FROM blocked_ips WHERE (is_permanent = 1 OR expires_at > :now)", [':now' => date('Y-m-d H:i:s')])['cnt'];
    $securityEvents = (int) db()->fetch("SELECT COUNT(*) as cnt FROM security_events WHERE created_at > :since", [':since' => date('Y-m-d H:i:s', time() - 86400)])['cnt'];
    $criticalEvents = (int) db()->fetch("SELECT COUNT(*) as cnt FROM security_events WHERE severity = 'CRITICAL' AND created_at > :since", [':since' => date('Y-m-d H:i:s', time() - 86400)])['cnt'];

    $dbSize = 0;
    if (file_exists(DB_FILE)) {
        $dbSize = filesize(DB_FILE);
    }

    $totalSearches = (int) db()->fetch("SELECT COUNT(*) as cnt FROM search_history")['cnt'];
    $totalLogs = (int) db()->fetch("SELECT COUNT(*) as cnt FROM activity_logs")['cnt'];

    jsonResponse([
        'success' => true,
        'data' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'banned_users' => $bannedUsers,
            'monthly_payments' => $monthlyPayments,
            'today_searches' => $todaySearches,
            'total_searches' => $totalSearches,
            'total_logs' => $totalLogs,
            'recent_users' => $recentUsers,
            'recent_payments' => $recentPayments,
            'recent_logs' => $recentLogs,
            'payment_stats' => $paymentStats,
            'plan_distribution' => $planDistribution,
            'security' => [
                'blocked_ips' => $blockedIPs,
                'security_events_24h' => $securityEvents,
                'critical_events_24h' => $criticalEvents,
            ],
            'system_info' => [
                'php_version' => PHP_VERSION,
                'db_size' => $dbSize,
                'db_size_formatted' => formatFileSize($dbSize),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'timezone' => date_default_timezone_get(),
                'memory_usage' => formatFileSize(memory_get_usage(true)),
                'memory_peak' => formatFileSize(memory_get_peak_usage(true)),
            ],
        ],
    ]);
}

// ============================================================
// SECURITY HANDLERS
// ============================================================

function handleBlockedIPs(): void
{
    $ips = fetchAll(
        "SELECT b.*, u.name as admin_name 
         FROM blocked_ips b 
         LEFT JOIN users u ON b.blocked_by = u.id 
         WHERE b.is_permanent = 1 OR b.expires_at > :now 
         ORDER BY b.blocked_at DESC",
        [':now' => date('Y-m-d H:i:s')]
    );

    jsonResponse([
        'success' => true,
        'data' => $ips,
    ]);
}

function handleBlockIP(array $input): void
{
    $ip = Security::sanitizeInput($input['ip'] ?? '');
    $reason = Security::sanitizeInput($input['reason'] ?? '');
    $duration = (int) ($input['duration'] ?? 0); // 0 = permanent

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonResponse(['success' => false, 'message' => 'عنوان IP غير صالح'], 400);
    }

    $adminUser = Auth::getCurrentUser();
    $result = Security::blockIP($ip, $reason, $duration, (int) $adminUser['id']);

    jsonResponse([
        'success' => $result,
        'message' => $result ? 'تم حظر العنوان بنجاح' : 'فشل حظر العنوان',
    ]);
}

function handleUnblockIP(array $input): void
{
    $ip = Security::sanitizeInput($input['ip'] ?? '');

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonResponse(['success' => false, 'message' => 'عنوان IP غير صالح'], 400);
    }

    $result = Security::unblockIP($ip);

    jsonResponse([
        'success' => $result,
        'message' => $result ? 'تم إلغاء حظر العنوان بنجاح' : 'فشل إلغاء الحظر',
    ]);
}

function handleSecurityEvents(array $input): void
{
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($input['per_page'] ?? 50)));
    $severity = Security::sanitizeInput($input['severity'] ?? '');

    $where = ['1=1'];
    $params = [];

    if (!empty($severity) && in_array($severity, ['INFO', 'WARNING', 'CRITICAL'])) {
        $where[] = "severity = :severity";
        $params[':severity'] = $severity;
    }

    $whereClause = implode(' AND ', $where);

    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM security_events WHERE {$whereClause}",
        $params
    )['cnt'];

    $offset = ($page - 1) * $perPage;
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    $events = db()->fetchAll(
        "SELECT * FROM security_events WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    jsonResponse([
        'success' => true,
        'data' => $events,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
}

// ============================================================
// HELPER: Format file size
// ============================================================
function formatFileSize(int $bytes): string
{
    if ($bytes === 0) return '0 بايت';
    $units = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
