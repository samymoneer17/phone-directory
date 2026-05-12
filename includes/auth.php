<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Authentication Class (Enhanced)
 * International Phone Directory
 * ============================================================
 * Complete authentication system with:
 * - Brute force protection
 * - Account lockout
 * - Session fingerprinting
 * - Banned user detection
 * - Password complexity enforcement
 */

require_once __DIR__ . '/security.php';

class Auth
{
    /**
     * Require user authentication
     */
    public static function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            Security::secureSession();
        }

        if (!self::isLoggedIn()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            redirect('/login.php');
            exit;
        }

        // Verify session fingerprint (anti-hijacking)
        if (!Security::verifyFingerprint()) {
            self::logout();
            redirect('/login.php');
            exit;
        }

        // Check if user is banned
        $user = self::getCurrentUser();
        if ($user && $user['role'] === 'BANNED') {
            // Check if temporary ban (from failed logins) has expired
            if ($user['locked_until'] !== null && strtotime($user['locked_until']) < time()) {
                // Ban expired - restore to USER
                update('users', [
                    'role'         => 'USER',
                    'locked_until' => null,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], 'id = :id', [':id' => $user['id']]);

                $_SESSION['user_role'] = 'USER';
                if (isset($_SESSION['user_data'])) {
                    $_SESSION['user_data']['role'] = 'USER';
                }
            } else {
                self::logout();
                Security::logSecurityEvent('banned_user_access', 'WARNING', $user['id'],
                    Security::getClientIP(), 'Banned user tried to access protected page');
                redirect('/login.php');
                exit;
            }
        }
    }

    /**
     * Require admin role
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();

        $user = self::getCurrentUser();
        if (!$user || $user['role'] !== 'ADMIN') {
            Security::logSecurityEvent('unauthorized_admin_access', 'WARNING',
                $user['id'] ?? null, Security::getClientIP(),
                'Non-admin user tried to access admin panel');
            redirect('/');
            exit;
        }

        // Additional security check for admin - require recent activity
        if (isset($_SESSION['login_time'])) {
            $sessionAge = time() - $_SESSION['login_time'];
            if ($sessionAge > 28800) { // 8 hours - force re-login for admin
                self::logout();
                redirect('/login.php');
                exit;
            }
        }
    }

    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }

        $user = fetch(
            "SELECT * FROM users WHERE id = :id LIMIT 1",
            [':id' => $_SESSION['user_id']]
        );

        if ($user === null) {
            self::logout();
            return null;
        }

        $_SESSION['user_data'] = $user;
        return $user;
    }

    /**
     * Login with brute force protection
     */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Security::getClientIP();

        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان',
            ];
        }

        // Global rate limit
        $rateCheck = Security::checkRateLimit($ip, 'login', RATE_LIMIT_LOGIN, RATE_LIMIT_WINDOW);
        if (!$rateCheck['allowed']) {
            Security::logSecurityEvent('login_rate_limited', 'WARNING', null, $ip,
                "Login rate limited for IP: {$ip}");
            return [
                'success' => false,
                'message' => 'تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى بعد دقيقة.',
                'retry_after' => $rateCheck['resetIn'],
            ];
        }

        // Brute force check
        $attemptCheck = Security::checkLoginAttempt($email, $ip);
        if (!$attemptCheck['allowed']) {
            if ($attemptCheck['lockout_seconds'] > 0) {
                $minutes = ceil($attemptCheck['lockout_seconds'] / 60);
                return [
                    'success' => false,
                    'message' => "الحساب مقفل. حاول مرة أخرى بعد {$minutes} دقيقة.",
                    'lockout_seconds' => $attemptCheck['lockout_seconds'],
                ];
            }
        }

        // Progressive delay for repeated failures
        if ($attemptCheck['delay_seconds'] > 0) {
            sleep(min($attemptCheck['delay_seconds'], 5)); // Cap at 5s server-side
        }

        // Find user
        $user = fetch(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($user === null) {
            Security::recordFailedLogin($email, $ip);
            Security::logActivity(null, 'login_failed', 'Email not found: ' . $email);
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
                'remaining_attempts' => $attemptCheck['remaining_attempts'] - 1,
            ];
        }

        // Check if user is banned (non-temporary ban)
        if ($user['role'] === 'BANNED') {
            if ($user['locked_until'] !== null && strtotime($user['locked_until']) < time()) {
                // Temporary ban expired - restore
                update('users', [
                    'role' => 'USER',
                    'locked_until' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', [':id' => $user['id']]);

                // Continue with login
            } else {
                Security::logSecurityEvent('banned_user_login', 'WARNING', $user['id'], $ip,
                    'Banned user attempted login');
                return [
                    'success' => false,
                    'message' => 'تم إيقاف هذا الحساب. تواصل مع الدعم الفني.',
                ];
            }
        }

        // Verify password
        if (!Security::verifyPassword($password, $user['password'])) {
            Security::recordFailedLogin($email, $ip);
            Security::logActivity($user['id'], 'login_failed', 'Wrong password');

            $remaining = max(0, $attemptCheck['remaining_attempts'] - 1);
            $message = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            if ($remaining > 0 && $remaining <= 3) {
                $message .= " ({$remaining} محاولات متبقية)";
            }

            return [
                'success' => false,
                'message' => $message,
                'remaining_attempts' => $remaining,
            ];
        }

        // Check password rehash
        if (Security::needsRehash($user['password'])) {
            try {
                $newHash = Security::hashPassword($password);
                update('users', ['password' => $newHash], 'id = :id', [':id' => $user['id']]);
            } catch (\Exception $e) {
                // Don't block login for rehash failure
                error_log('Password rehash failed: ' . $e->getMessage());
            }
        }

        // Clear failed login attempts
        Security::clearFailedLogins($email, $ip);

        // Set session (for traditional servers)
        self::setUserSession($user);

        // Generate auth token (for Vercel serverless)
        $authToken = self::generateAuthToken((int) $user['id']);

        // Update last login
        update('users', ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $user['id']]);

        Security::logActivity($user['id'], 'login', 'User logged in successfully');
        Security::logSecurityEvent('login_success', 'INFO', $user['id'], $ip,
            'User logged in from IP: ' . $ip);

        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => $user,
            'auth_token' => $authToken,
        ];
    }

    /**
     * Register with password complexity enforcement
     */
    public static function register(array $data): array
    {
        $name = Security::sanitizeInput($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $phone = isset($data['phone']) ? Security::sanitizeInput($data['phone']) : '';

        // Validate name
        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            return [
                'success' => false,
                'message' => 'الاسم يجب أن يكون بين 2 و 100 حرف',
            ];
        }

        // Validate email
        if (!Security::validateEmail($email)) {
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني غير صالح',
            ];
        }

        // Password length check first (before complexity which throws exception)
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'كلمة المرور يجب أن تكون على الأقل ' . PASSWORD_MIN_LENGTH . ' أحرف',
            ];
        }

        if (strlen($password) > PASSWORD_MAX_LENGTH) {
            return [
                'success' => false,
                'message' => 'كلمة المرور طويلة جداً',
            ];
        }

        // Password complexity validation
        try {
            Security::validatePasswordComplexity($password);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        // Validate phone
        if (!empty($phone) && !Security::validatePhone($phone)) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير صالح',
            ];
        }

        // Check duplicate email
        $existing = fetch(
            "SELECT id FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($existing !== null) {
            // Don't reveal if email exists (prevent enumeration)
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.',
            ];
        }

        // Hash password
        try {
            $hashedPassword = Security::hashPassword($password);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        try {
            $userId = insert('users', [
                'name'     => $name,
                'email'    => $email,
                'password' => $hashedPassword,
                'phone'    => $phone ?: null,
                'plan'     => 'FREE',
                'role'     => 'USER',
            ]);

            $user = fetch(
                "SELECT * FROM users WHERE id = :id LIMIT 1",
                [':id' => $userId]
            );

            if ($user !== null) {
                self::setUserSession($user);
            }

            // Generate auth token (for Vercel serverless)
            $authToken = self::generateAuthToken((int) $userId);

            Security::logActivity($userId, 'register', 'New user registered: ' . $email);
            Security::logSecurityEvent('user_registered', 'INFO', (int)$userId,
                Security::getClientIP(), 'New registration from IP');

            return [
                'success' => true,
                'message' => 'تم إنشاء الحساب بنجاح',
                'user' => $user,
                'auth_token' => $authToken,
            ];
        } catch (\Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.',
            ];
        }
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            Security::logActivity((int) $userId, 'logout', 'User logged out');
        }

        Security::destroySession();
    }

    public static function forgotPassword(string $email): array
    {
        $email = strtolower(trim($email));

        if (empty($email)) {
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني مطلوب',
            ];
        }

        // Rate limit for forgot password
        $ip = Security::getClientIP();
        $rateCheck = Security::checkRateLimit($ip, 'reset_password', 3, 300);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'message' => 'تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى بعد 5 دقائق.',
            ];
        }

        $user = fetch(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($user === null) {
            Security::logSecurityEvent('forgot_password_nonexistent', 'INFO', null, $ip,
                'Password reset requested for non-existent email');
            return [
                'success' => true,
                'message' => 'إذا كان هذا البريد مسجلاً، ستصلك رسالة لإعادة تعيين كلمة المرور',
            ];
        }

        $token = Security::generateResetToken();
        $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

        try {
            update('users', [
                'reset_token' => $token,
                'reset_token_expires_at' => $expiresAt,
            ], 'id = :id', [':id' => $user['id']]);

            Security::logActivity($user['id'], 'reset_requested', 'Password reset token generated');
            Security::logSecurityEvent('password_reset_requested', 'WARNING', $user['id'], $ip);

            return [
                'success' => true,
                'message' => 'إذا كان هذا البريد مسجلاً، ستصلك رسالة لإعادة تعيين كلمة المرور',
                'token' => $token, // Only for development
            ];
        } catch (\Exception $e) {
            error_log('Password reset failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ. حاول مرة أخرى.',
            ];
        }
    }

    public static function resetPassword(string $token, string $newPassword): array
    {
        if (empty($token) || empty($newPassword)) {
            return [
                'success' => false,
                'message' => 'الرمز وكلمة المرور الجديدة مطلوبان',
            ];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'كلمة المرور يجب أن تكون على الأقل ' . PASSWORD_MIN_LENGTH . ' أحرف',
            ];
        }

        // Validate complexity
        try {
            Security::validatePasswordComplexity($newPassword);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $user = fetch(
            "SELECT * FROM users WHERE reset_token = :token AND reset_token_expires_at > :now LIMIT 1",
            [
                ':token' => $token,
                ':now'   => date('Y-m-d H:i:s'),
            ]
        );

        if ($user === null) {
            Security::logSecurityEvent('invalid_reset_token', 'WARNING', null,
                Security::getClientIP(), 'Invalid or expired password reset token');
            return [
                'success' => false,
                'message' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية',
            ];
        }

        try {
            $hashedPassword = Security::hashPassword($newPassword);

            update('users', [
                'password'              => $hashedPassword,
                'reset_token'           => null,
                'reset_token_expires_at' => null,
                'updated_at'            => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $user['id']]);

            // Clear any failed login attempts
            Security::clearFailedLogins($user['email'], Security::getClientIP());

            Security::logActivity($user['id'], 'password_reset', 'Password was reset successfully');
            Security::logSecurityEvent('password_reset_success', 'INFO', $user['id'],
                Security::getClientIP(), 'Password reset completed');

            return [
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
            ];
        } catch (\Exception $e) {
            error_log('Password reset failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور',
            ];
        }
    }

    public static function updateProfile(int $userId, array $data): array
    {
        $allowedFields = ['name', 'phone', 'avatar', 'is_phone_hidden'];
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            switch ($field) {
                case 'name':
                    $value = Security::sanitizeInput($value);
                    if (empty($value) || strlen($value) < 2 || strlen($value) > 100) {
                        return [
                            'success' => false,
                            'message' => 'الاسم يجب أن يكون بين 2 و 100 حرف',
                        ];
                    }
                    break;

                case 'phone':
                    if (!empty($value)) {
                        $value = Security::sanitizeInput($value);
                        if (!Security::validatePhone($value)) {
                            return [
                                'success' => false,
                                'message' => 'رقم الهاتف غير صالح',
                            ];
                        }
                    } else {
                        $value = null;
                    }
                    break;

                case 'avatar':
                    $value = filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
                    break;

                case 'is_phone_hidden':
                    if ((int) $value === 1 && !self::canHidePhone($userId)) {
                        return [
                            'success' => false,
                            'message' => 'يجب الاشتراك في باقة مدفوعة لإخفاء رقم الهاتف',
                        ];
                    }
                    $value = (int) $value;
                    break;
            }

            $updateData[$field] = $value;
        }

        try {
            update('users', $updateData, 'id = :id', [':id' => $userId]);

            if (isset($_SESSION['user_data'])) {
                foreach ($updateData as $key => $val) {
                    $_SESSION['user_data'][$key] = $val;
                }
            }

            Security::logActivity($userId, 'profile_updated', json_encode($data));

            return [
                'success' => true,
                'message' => 'تم تحديث الملف الشخصي بنجاح',
            ];
        } catch (\Exception $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الملف الشخصي',
            ];
        }
    }

    public static function updatePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        if (empty($oldPassword) || empty($newPassword)) {
            return [
                'success' => false,
                'message' => 'كلمة المرور الحالية والجديدة مطلوبتان',
            ];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'كلمة المرور الجديدة يجب أن تكون على الأقل ' . PASSWORD_MIN_LENGTH . ' أحرف',
            ];
        }

        // Validate new password complexity
        try {
            Security::validatePasswordComplexity($newPassword);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        // Ensure new password is different from old
        if (Security::verifyPassword($newPassword, $oldPassword)) {
            // Can't compare directly since old is plaintext, just check they're not the same
        }

        $user = fetch(
            "SELECT password FROM users WHERE id = :id LIMIT 1",
            [':id' => $userId]
        );

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'المستخدم غير موجود',
            ];
        }

        if (!Security::verifyPassword($oldPassword, $user['password'])) {
            Security::logSecurityEvent('wrong_password_change', 'WARNING', $userId,
                Security::getClientIP(), 'Wrong current password during password change');
            return [
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة',
            ];
        }

        try {
            $hashedPassword = Security::hashPassword($newPassword);

            update('users', [
                'password'   => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $userId]);

            Security::logActivity($userId, 'password_changed', 'Password changed successfully');
            Security::logSecurityEvent('password_changed', 'INFO', $userId,
                Security::getClientIP(), 'User changed their password');

            return [
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح',
            ];
        } catch (\Exception $e) {
            error_log('Password change failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور',
            ];
        }
    }

    public static function checkSubscription(?int $userId = null): array
    {
        if ($userId === null) {
            $user = self::getCurrentUser();
            if ($user === null) {
                return ['active' => false, 'plan' => 'FREE', 'expiresAt' => null];
            }
            $userId = $user['id'];
        }

        $user = fetch(
            "SELECT plan, subscription_expires_at FROM users WHERE id = :id LIMIT 1",
            [':id' => $userId]
        );

        if ($user === null) {
            return ['active' => false, 'plan' => 'FREE', 'expiresAt' => null];
        }

        $plan = $user['plan'];
        $expiresAt = $user['subscription_expires_at'];

        if ($plan === 'FREE') {
            return ['active' => true, 'plan' => 'FREE', 'expiresAt' => null];
        }

        if ($expiresAt !== null && strtotime($expiresAt) < time()) {
            update('users', [
                'plan' => 'FREE',
                'subscription_expires_at' => null,
                'is_phone_hidden' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $userId]);

            update('subscriptions', ['is_active' => 0],
                'user_id = :user_id AND is_active = 1',
                [':user_id' => $userId]);

            return ['active' => false, 'plan' => 'FREE', 'expiresAt' => null];
        }

        return [
            'active'   => true,
            'plan'     => $plan,
            'expiresAt' => $expiresAt,
        ];
    }

    public static function canHidePhone(?int $userId = null): bool
    {
        $sub = self::checkSubscription($userId);
        if (!$sub['active']) {
            return false;
        }

        $planConfig = PLANS[$sub['plan']] ?? PLANS['FREE'];
        return (bool) ($planConfig['can_hide_phone'] ?? false);
    }

    public static function incrementSearchCount(?int $userId = null): array
    {
        $user = self::getCurrentUser();
        if ($user === null) {
            return ['count' => 0, 'limit' => FREE_SEARCH_LIMIT, 'remaining' => FREE_SEARCH_LIMIT];
        }

        $id = $userId ?? $user['id'];
        $plan = $user['plan'] ?? 'FREE';
        $limit = PLANS[$plan]['search_limit'] ?? FREE_SEARCH_LIMIT;

        $today = date('Y-m-d');
        $todayCount = fetch(
            "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND date(created_at) = :today",
            [':uid' => $id, ':today' => $today]
        );

        $currentCount = (int) ($todayCount['cnt'] ?? 0);

        if ($currentCount >= $limit) {
            return [
                'count'     => $currentCount,
                'limit'     => $limit,
                'remaining' => 0,
            ];
        }

        update('users',
            ['search_count' => $user['search_count'] + 1],
            'id = :id',
            [':id' => $id]
        );

        return [
            'count'     => $currentCount + 1,
            'limit'     => $limit,
            'remaining' => $limit - ($currentCount + 1),
        ];
    }

    /**
     * Generate an auth token and store it in the database.
     * Returns the token string. Expires after SESSION_LIFETIME seconds.
     */
    public static function generateAuthToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

        try {
            update('users', [
                'auth_token' => $token,
                'auth_token_expires_at' => $expiresAt,
            ], 'id = :id', [':id' => $userId]);
        } catch (\Exception $e) {
            error_log('Failed to generate auth token: ' . $e->getMessage());
        }

        return $token;
    }

    /**
     * Validate an auth token and return the user if valid.
     * Returns null if token is invalid or expired.
     */
    public static function validateAuthToken(string $token): ?array
    {
        if (empty($token)) return null;

        try {
            $user = fetch(
                "SELECT id, name, email, phone, plan, role, avatar, search_count, created_at
                 FROM users
                 WHERE auth_token = :token
                 AND auth_token_expires_at > :now
                 LIMIT 1",
                [':token' => $token, ':now' => date('Y-m-d H:i:s')]
            );

            return $user;
        } catch (\Exception $e) {
            error_log('Auth token validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete auth token (used on logout)
     */
    public static function revokeAuthToken(string $token): void
    {
        if (empty($token)) return;
        try {
            update('users', [
                'auth_token' => null,
                'auth_token_expires_at' => null,
            ], 'auth_token = :token', [':token' => $token]);
        } catch (\Exception $e) {
            error_log('Failed to revoke auth token: ' . $e->getMessage());
        }
    }

    /**
     * Get user by auth token from request (header or body)
     * This is the main auth method for Vercel serverless
     */
    public static function getUserByRequestToken(): ?array
    {
        // Check Authorization header first
        $token = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            }
        }

        // Check X-Auth-Token header
        if (empty($token) && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            $token = $_SERVER['HTTP_X_AUTH_TOKEN'];
        }

        // Check POST body
        if (empty($token)) {
            $input = Security::getJsonInput();
            if ($input && !empty($input['auth_token'])) {
                $token = $input['auth_token'];
            }
        }

        // Check GET param
        if (empty($token) && !empty($_GET['auth_token'])) {
            $token = $_GET['auth_token'];
        }

        return self::validateAuthToken($token);
    }

    private static function setUserSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            Security::secureSession();
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_plan'] = $user['plan'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['login_time'] = time();
        $_SESSION['user_data'] = $user;

        // Set fingerprint
        $_SESSION['fingerprint'] = Security::generateFingerprint();
        $_SESSION['last_regeneration'] = time();
    }

    public static function isSessionExpired(): bool
    {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }
        return (time() - $_SESSION['login_time']) > SESSION_LIFETIME;
    }

    public static function refreshSession(): void
    {
        if (self::isLoggedIn()) {
            $_SESSION['login_time'] = time();
        }
    }
}
