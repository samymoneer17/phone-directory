<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin API Endpoint
 * International Phone Directory
 * ============================================================
 * POST-only API for admin panel CRUD operations.
 * Requires admin authentication + CSRF verification.
 */

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير صالحة'], 405);
}

// Load dependencies
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize session and security
Security::secureSession();

// Require admin authentication
Auth::requireAdmin();

// Get current admin user
$adminUser = Auth::getCurrentUser();
if (!$adminUser || $adminUser['role'] !== 'ADMIN') {
    jsonResponse(['success' => false, 'message' => 'غير مصرح بالوصول'], 403);
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    $input = $_POST;
}

// Verify CSRF token
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'رمز CSRF غير صالح'], 403);
}

// Get requested action
$action = $input['action'] ?? '';
$action = Security::sanitizeInput($action);

if (empty($action)) {
    jsonResponse(['success' => false, 'message' => 'الإجراء غير محدد'], 400);
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
        // --------------------------------------------------
        // USERS ACTIONS
        // --------------------------------------------------
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

        // --------------------------------------------------
        // PAYMENTS ACTIONS
        // --------------------------------------------------
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

        // --------------------------------------------------
        // LOGS ACTIONS
        // --------------------------------------------------
        case 'logs/list':
            handleLogsList($input);
            break;

        case 'logs/clear':
            handleLogsClear($input);
            break;

        // --------------------------------------------------
        // STATS ACTIONS
        // --------------------------------------------------
        case 'stats/dashboard':
            handleDashboardStats();
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'الإجراء غير موجود: ' . sanitizeOutput($action)], 400);
    }
} catch (Exception $e) {
    error_log('Admin API Error [' . $action . ']: ' . $e->getMessage());
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

    if (!empty($role) && in_array($role, ['USER', 'ADMIN'])) {
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

    // Total count
    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM users WHERE {$whereClause}",
        $params
    )['cnt'];

    // Get users
    $offset = ($page - 1) * $perPage;
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    $users = db()->fetchAll(
        "SELECT id, name, email, phone, avatar, role, plan, subscription_expires_at, 
                is_phone_hidden, search_count, created_at, updated_at
         FROM users WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    // Format users
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

    // Don't allow admin to modify their own role
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

    // Name
    if (isset($input['name'])) {
        $name = Security::sanitizeInput($input['name']);
        if (empty($name) || mb_strlen($name) < 2) {
            jsonResponse(['success' => false, 'message' => 'الاسم يجب أن يكون حرفين على الأقل'], 400);
        }
        $updateData['name'] = $name;
    }

    // Email
    if (isset($input['email'])) {
        $email = strtolower(trim($input['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صالح'], 400);
        }
        $existing = fetch("SELECT id FROM users WHERE email = :email AND id != :id", [':email' => $email, ':id' => $userId]);
        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل'], 400);
        }
        $updateData['email'] = $email;
    }

    // Plan
    if (isset($input['plan'])) {
        $plan = strtoupper(Security::sanitizeInput($input['plan']));
        if (!isset(PLANS[$plan])) {
            jsonResponse(['success' => false, 'message' => 'الخطة غير صالحة'], 400);
        }
        $updateData['plan'] = $plan;

        // If downgrading to FREE, clear subscription expiry
        if ($plan === 'FREE') {
            $updateData['subscription_expires_at'] = null;
            $updateData['is_phone_hidden'] = 0;
        }
    }

    // Role
    if (isset($input['role'])) {
        $role = strtoupper(Security::sanitizeInput($input['role']));
        if (!in_array($role, ['USER', 'ADMIN'])) {
            jsonResponse(['success' => false, 'message' => 'الدور غير صالح'], 400);
        }
        $updateData['role'] = $role;
    }

    // Phone hidden
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

    // Delete related records first (SQLite cascades should handle this)
    db()->query("DELETE FROM search_history WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM payments WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM subscriptions WHERE user_id = :uid", [':uid' => $userId]);
    db()->query("DELETE FROM activity_logs WHERE user_id = :uid", [':uid' => $userId]);

    // Delete user
    $rows = db()->query("DELETE FROM users WHERE id = :id", [':id' => $userId])->rowCount();

    Security::logActivity(
        (int) $adminUser['id'],
        'admin_user_delete',
        'Deleted user: ' . $user['name'] . ' (' . $user['email'] . ')'
    );

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

    // Toggle ban: if already banned -> unban, otherwise -> ban
    $isBanned = ($user['role'] === 'BANNED');
    $newRole = $isBanned ? 'USER' : 'BANNED';

    update('users', [
        'role' => $newRole,
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $userId]);

    $action = $isBanned ? 'unbanned' : 'banned';
    $message = $isBanned ? 'تم إلغاء حظر المستخدم بنجاح' : 'تم حظر المستخدم بنجاح';

    Security::logActivity(
        (int) $adminUser['id'],
        'admin_user_' . $action,
        "{$action} user: {$user['name']} (ID: {$userId})"
    );

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

    $user = fetch(
        "SELECT * FROM users WHERE id = :id",
        [':id' => $userId]
    );

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
    }

    // Get search history (last 20)
    $searchHistory = fetchAll(
        "SELECT id, query, query_type, country_code, results_count, created_at
         FROM search_history 
         WHERE user_id = :uid 
         ORDER BY created_at DESC 
         LIMIT 20",
        [':uid' => $userId]
    );

    // Get payments
    $payments = fetchAll(
        "SELECT * FROM payments WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20",
        [':uid' => $userId]
    );

    // Get activity logs (last 20)
    $activityLogs = fetchAll(
        "SELECT * FROM activity_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20",
        [':uid' => $userId]
    );

    // Get active subscription
    $subscription = fetch(
        "SELECT * FROM subscriptions WHERE user_id = :uid AND is_active = 1 ORDER BY started_at DESC LIMIT 1",
        [':uid' => $userId]
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
                'google_id' => $user['google_id'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ],
            'search_history' => $searchHistory,
            'payments' => $payments,
            'activity_logs' => $activityLogs,
            'subscription' => $subscription,
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

    // Total count
    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM payments p WHERE {$whereClause}",
        $params
    )['cnt'];

    // Get payments
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
         FROM payments p 
         LEFT JOIN users u ON p.user_id = u.id
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
        // Update payment status
        update('payments', ['status' => 'APPROVED'], 'id = :id', [':id' => $paymentId]);

        // Update user plan
        $updateData = [
            'plan' => $plan,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($planConfig['duration'] > 0) {
            $expiryDate = date('Y-m-d H:i:s', strtotime('+' . $planConfig['duration'] . ' days'));
            $updateData['subscription_expires_at'] = $expiryDate;
        }

        update('users', $updateData, 'id = :id', [':id' => $payment['user_id']]);

        // Create subscription record
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

        Security::logActivity(
            (int) $adminUser['id'],
            'admin_payment_approve',
            "Approved payment ID {$paymentId} for user {$payment['user_name']} ({$payment['plan']})"
        );

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
         FROM payments p 
         LEFT JOIN users u ON p.user_id = u.id
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
    Security::logActivity(
        (int) $adminUser['id'],
        'admin_payment_reject',
        "Rejected payment ID {$paymentId} for user {$payment['user_name']}"
    );

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
    Security::logActivity(
        (int) $adminUser['id'],
        'admin_payment_delete',
        "Deleted payment ID {$paymentId}"
    );

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

    // Total count
    $total = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM activity_logs l WHERE {$whereClause}",
        $params
    )['cnt'];

    // Get logs
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

    Security::logActivity(
        (int) $adminUser['id'],
        'admin_logs_clear',
        "Cleared all activity logs ({$rows} rows deleted)"
    );

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
    // Total users
    $totalUsers = (int) db()->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'];

    // Active users (subscription not expired)
    $activeUsers = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM users WHERE plan != 'FREE' AND (subscription_expires_at IS NULL OR subscription_expires_at > :now)",
        [':now' => date('Y-m-d H:i:s')]
    )['cnt'];

    // Monthly payments (this month, approved)
    $monthStart = date('Y-m-01 00:00:00');
    $monthlyPayments = (float) db()->fetch(
        "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'APPROVED' AND created_at >= :month_start",
        [':month_start' => $monthStart]
    )['total'];

    // Today's searches
    $todayStart = date('Y-m-d 00:00:00');
    $todaySearches = (int) db()->fetch(
        "SELECT COUNT(*) as cnt FROM search_history WHERE created_at >= :today",
        [':today' => $todayStart]
    )['cnt'];

    // Recent users (last 5)
    $recentUsers = db()->fetchAll(
        "SELECT id, name, email, plan, role, created_at FROM users ORDER BY created_at DESC LIMIT 5"
    );

    // Recent payments (last 5)
    $recentPayments = db()->fetchAll(
        "SELECT p.*, u.name as user_name FROM payments p 
         LEFT JOIN users u ON p.user_id = u.id 
         ORDER BY p.created_at DESC LIMIT 5"
    );

    // Recent activity (last 10)
    $recentLogs = db()->fetchAll(
        "SELECT l.*, u.name as user_name 
         FROM activity_logs l 
         LEFT JOIN users u ON l.user_id = u.id 
         ORDER BY l.created_at DESC LIMIT 10"
    );

    // Payment stats
    $paymentStats = db()->fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
            COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN amount ELSE 0 END), 0) as total_amount
         FROM payments"
    );

    // Plan distribution
    $planDistribution = db()->fetchAll(
        "SELECT plan, COUNT(*) as count FROM users GROUP BY plan"
    );

    // Database size
    $dbSize = 0;
    if (file_exists(DB_FILE)) {
        $dbSize = filesize(DB_FILE);
    }

    // Total searches
    $totalSearches = (int) db()->fetch("SELECT COUNT(*) as cnt FROM search_history")['cnt'];

    // Total logs
    $totalLogs = (int) db()->fetch("SELECT COUNT(*) as cnt FROM activity_logs")['cnt'];

    jsonResponse([
        'success' => true,
        'data' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'monthly_payments' => $monthlyPayments,
            'today_searches' => $todaySearches,
            'total_searches' => $totalSearches,
            'total_logs' => $totalLogs,
            'recent_users' => $recentUsers,
            'recent_payments' => $recentPayments,
            'recent_logs' => $recentLogs,
            'payment_stats' => $paymentStats,
            'plan_distribution' => $planDistribution,
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
// HELPER: Format file size
// ============================================================
function formatFileSize(int $bytes): string
{
    if ($bytes === 0) return '0 بايت';
    $units = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
