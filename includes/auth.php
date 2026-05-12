<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Authentication Class
 * International Phone Directory
 * ============================================================
 * Complete authentication system with login, register,
 * Google OAuth, password reset, and subscription management.
 */

require_once __DIR__ . '/security.php';

class Auth
{
    /**
     * Require user authentication - redirect to login if not logged in
     *
     * @return void
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
    }

    /**
     * Require admin role - redirect to home if not admin
     *
     * @return void
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();

        $user = self::getCurrentUser();
        if (!$user || $user['role'] !== 'ADMIN') {
            redirect('/');
            exit;
        }
    }

    /**
     * Check if a user is currently logged in
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get the current authenticated user's data
     *
     * @return array|null User data array or null if not logged in
     */
    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        // Check session cache first
        if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }

        // Fetch fresh from database
        $user = fetch(
            "SELECT * FROM users WHERE id = :id LIMIT 1",
            [':id' => $_SESSION['user_id']]
        );

        if ($user === null) {
            // User was deleted but session still exists
            self::logout();
            return null;
        }

        // Cache in session
        $_SESSION['user_data'] = $user;

        return $user;
    }

    /**
     * Log in a user with email and password
     *
     * @param string $email    User email
     * @param string $password Plain text password
     * @return array{success: bool, message: string, user?: array}
     */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        // Validate inputs
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان',
            ];
        }

        // Check rate limit
        $ip = Security::getClientIP();
        $rateCheck = Security::checkRateLimit($ip, 'login', RATE_LIMIT_LOGIN, RATE_LIMIT_WINDOW);
        if (!$rateCheck['allowed']) {
            Security::logActivity(null, 'login_blocked', 'Rate limited: ' . $ip);
            return [
                'success' => false,
                'message' => 'تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى بعد دقيقة.',
            ];
        }

        // Find user by email
        $user = fetch(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($user === null) {
            Security::logActivity(null, 'login_failed', 'Email not found: ' . $email);
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            ];
        }

        // Check if user has a password (Google-only accounts)
        if (empty($user['password'])) {
            return [
                'success' => false,
                'message' => 'هذا الحساب مسجل بواسطة Google. يرجى تسجيل الدخول باستخدام Google.',
            ];
        }

        // Verify password
        if (!Security::verifyPassword($password, $user['password'])) {
            Security::logActivity($user['id'], 'login_failed', 'Wrong password');
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            ];
        }

        // Check if password needs rehashing
        if (Security::needsRehash($user['password'])) {
            $newHash = Security::hashPassword($password);
            update(
                'users',
                ['password' => $newHash],
                'id = :id',
                [':id' => $user['id']]
            );
        }

        // Set session
        self::setUserSession($user);

        // Update last login timestamp
        update(
            'users',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $user['id']]
        );

        Security::logActivity($user['id'], 'login', 'User logged in successfully');

        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => $user,
        ];
    }

    /**
     * Register a new user account
     *
     * @param array $data User data: name, email, password, phone (optional)
     * @return array{success: bool, message: string, user?: array}
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

        // Validate password
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

        // Validate phone if provided
        if (!empty($phone) && !Security::validatePhone($phone)) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير صالح',
            ];
        }

        // Check if email already exists
        $existing = fetch(
            "SELECT id FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($existing !== null) {
            return [
                'success' => false,
                'message' => 'هذا البريد الإلكتروني مسجل بالفعل',
            ];
        }

        // Hash password
        $hashedPassword = Security::hashPassword($password);

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

            // Auto-login after registration
            if ($user !== null) {
                self::setUserSession($user);
            }

            Security::logActivity($userId, 'register', 'New user registered: ' . $email);

            return [
                'success' => true,
                'message' => 'تم إنشاء الحساب بنجاح',
                'user' => $user,
            ];
        } catch (\Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.',
            ];
        }
    }

    /**
     * Login or register a user via Google OAuth
     *
     * @param array $googleData Google user data: id, email, name, picture, verified_email
     * @return array{success: bool, message: string, user?: array, isNew: bool}
     */
    public static function loginWithGoogle(array $googleData): array
    {
        $googleId = $googleData['id'] ?? '';
        $email = strtolower(trim($googleData['email'] ?? ''));
        $name = Security::sanitizeInput($googleData['name'] ?? '');
        $picture = $googleData['picture'] ?? '';
        $verifiedEmail = (bool) ($googleData['verified_email'] ?? false);

        if (empty($googleId) || empty($email)) {
            return [
                'success' => false,
                'message' => 'بيانات Google غير مكتملة',
            ];
        }

        if (!$verifiedEmail) {
            return [
                'success' => false,
                'message' => 'يجب التحقق من البريد الإلكتروني في Google أولاً',
            ];
        }

        try {
            // Check for existing user with this Google ID
            $user = fetch(
                "SELECT * FROM users WHERE google_id = :google_id LIMIT 1",
                [':google_id' => $googleId]
            );

            if ($user !== null) {
                // Existing Google user - update name and avatar
                update(
                    'users',
                    [
                        'name'   => $name,
                        'avatar' => $picture ?: null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = :id',
                    [':id' => $user['id']]
                );

                // Refresh user data
                $user = fetch(
                    "SELECT * FROM users WHERE id = :id LIMIT 1",
                    [':id' => $user['id']]
                );

                self::setUserSession($user);
                Security::logActivity($user['id'], 'google_login', 'Google login');

                return [
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'user' => $user,
                    'isNew' => false,
                ];
            }

            // Check if user exists with this email (link accounts)
            $existingEmail = fetch(
                "SELECT * FROM users WHERE email = :email LIMIT 1",
                [':email' => $email]
            );

            if ($existingEmail !== null) {
                // Link Google account to existing user
                update(
                    'users',
                    [
                        'google_id'   => $googleId,
                        'avatar'      => $picture ?: $existingEmail['avatar'],
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ],
                    'id = :id',
                    [':id' => $existingEmail['id']]
                );

                $user = fetch(
                    "SELECT * FROM users WHERE id = :id LIMIT 1",
                    [':id' => $existingEmail['id']]
                );

                self::setUserSession($user);
                Security::logActivity($user['id'], 'google_linked', 'Google account linked');

                return [
                    'success' => true,
                    'message' => 'تم ربط حساب Google بنجاح',
                    'user' => $user,
                    'isNew' => false,
                ];
            }

            // Create new user
            $userId = insert('users', [
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'avatar'    => $picture ?: null,
                'plan'      => 'FREE',
                'role'      => 'USER',
            ]);

            $user = fetch(
                "SELECT * FROM users WHERE id = :id LIMIT 1",
                [':id' => $userId]
            );

            if ($user !== null) {
                self::setUserSession($user);
            }

            Security::logActivity($userId, 'google_register', 'New Google registration: ' . $email);

            return [
                'success' => true,
                'message' => 'تم إنشاء الحساب وتسجيل الدخول بنجاح',
                'user' => $user,
                'isNew' => true,
            ];
        } catch (\Exception $e) {
            error_log('Google login failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول بحساب Google',
            ];
        }
    }

    /**
     * Log out the current user
     *
     * @return void
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            Security::logActivity((int) $userId, 'logout', 'User logged out');
        }

        // Clear all session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $cookieParams = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Generate and send a password reset token
     *
     * @param string $email User's email address
     * @return array{success: bool, message: string}
     */
    public static function forgotPassword(string $email): array
    {
        $email = strtolower(trim($email));

        if (empty($email)) {
            return [
                'success' => false,
                'message' => 'البريد الإلكتروني مطلوب',
            ];
        }

        // Always return success to prevent email enumeration
        // But only actually send if user exists

        $user = fetch(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($user === null) {
            Security::logActivity(null, 'forgot_password', 'Email not found: ' . $email);
            return [
                'success' => true,
                'message' => 'إذا كان هذا البريد مسجلاً، ستصلك رسالة لإعادة تعيين كلمة المرور',
            ];
        }

        // Check rate limit for password reset
        $ip = Security::getClientIP();
        $rateCheck = Security::checkRateLimit($ip, 'reset_password', 3, 300);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'message' => 'تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى بعد 5 دقائق.',
            ];
        }

        // Generate reset token
        $token = Security::generateResetToken();
        $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

        try {
            update(
                'users',
                [
                    'reset_token' => $token,
                    'reset_token_expires_at' => $expiresAt,
                ],
                'id = :id',
                [':id' => $user['id']]
            );

            Security::logActivity($user['id'], 'reset_requested', 'Password reset token generated');

            // In production, send email with reset link
            // mail($email, 'Reset Password', SITE_URL . '/reset-password.php?token=' . $token);

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

    /**
     * Reset a user's password using a token
     *
     * @param string $token       The reset token
     * @param string $newPassword The new password
     * @return array{success: bool, message: string}
     */
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

        // Find user with valid token
        $user = fetch(
            "SELECT * FROM users WHERE reset_token = :token AND reset_token_expires_at > :now LIMIT 1",
            [
                ':token' => $token,
                ':now'   => date('Y-m-d H:i:s'),
            ]
        );

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية',
            ];
        }

        try {
            $hashedPassword = Security::hashPassword($newPassword);

            update(
                'users',
                [
                    'password'              => $hashedPassword,
                    'reset_token'           => null,
                    'reset_token_expires_at' => null,
                    'updated_at'            => date('Y-m-d H:i:s'),
                ],
                'id = :id',
                [':id' => $user['id']]
            );

            Security::logActivity($user['id'], 'password_reset', 'Password was reset successfully');

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

    /**
     * Update a user's profile information
     *
     * @param int   $userId User ID
     * @param array $data   Fields to update: name, phone, avatar, is_phone_hidden
     * @return array{success: bool, message: string}
     */
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
                    // Only paid users can hide phone
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
            update(
                'users',
                $updateData,
                'id = :id',
                [':id' => $userId]
            );

            // Update session cache
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

    /**
     * Update a user's password (requires current password)
     *
     * @param int    $userId      User ID
     * @param string $oldPassword Current password
     * @param string $newPassword New password
     * @return array{success: bool, message: string}
     */
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

        // Get current password hash
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

        // Verify old password
        if (empty($user['password'])) {
            return [
                'success' => false,
                'message' => 'لا يمكن تغيير كلمة المرور لحساب Google. يرجى تعيين كلمة مرور أولاً.',
            ];
        }

        if (!Security::verifyPassword($oldPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة',
            ];
        }

        try {
            $hashedPassword = Security::hashPassword($newPassword);

            update(
                'users',
                [
                    'password'   => $hashedPassword,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = :id',
                [':id' => $userId]
            );

            Security::logActivity($userId, 'password_changed', 'Password changed successfully');

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

    /**
     * Check if the current user's subscription is active
     *
     * @param int|null $userId Optional user ID (defaults to current user)
     * @return array{active: bool, plan: string, expiresAt: string|null}
     */
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

        // Free plan is always active
        if ($plan === 'FREE') {
            return ['active' => true, 'plan' => 'FREE', 'expiresAt' => null];
        }

        // Check if subscription has expired
        if ($expiresAt !== null && strtotime($expiresAt) < time()) {
            // Subscription expired - downgrade to free
            update(
                'users',
                [
                    'plan' => 'FREE',
                    'subscription_expires_at' => null,
                    'is_phone_hidden' => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = :id',
                [':id' => $userId]
            );

            // Deactivate subscriptions
            update(
                'subscriptions',
                ['is_active' => 0],
                'user_id = :user_id AND is_active = 1',
                [':user_id' => $userId]
            );

            return ['active' => false, 'plan' => 'FREE', 'expiresAt' => null];
        }

        return [
            'active'   => true,
            'plan'     => $plan,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Check if a user can hide their phone number
     *
     * @param int|null $userId Optional user ID (defaults to current user)
     * @return bool
     */
    public static function canHidePhone(?int $userId = null): bool
    {
        $sub = self::checkSubscription($userId);
        if (!$sub['active']) {
            return false;
        }

        $planConfig = PLANS[$sub['plan']] ?? PLANS['FREE'];
        return (bool) ($planConfig['can_hide_phone'] ?? false);
    }

    /**
     * Increment the search count for a user (for daily limits)
     *
     * @param int|null $userId Optional user ID (defaults to current user)
     * @return array{count: int, limit: int, remaining: int}
     */
    public static function incrementSearchCount(?int $userId = null): array
    {
        $user = self::getCurrentUser();
        if ($user === null) {
            return ['count' => 0, 'limit' => FREE_SEARCH_LIMIT, 'remaining' => FREE_SEARCH_LIMIT];
        }

        $id = $userId ?? $user['id'];
        $plan = $user['plan'] ?? 'FREE';
        $limit = PLANS[$plan]['search_limit'] ?? FREE_SEARCH_LIMIT;

        // Get today's search count
        $today = date('Y-m-d');
        $todayCount = fetch(
            "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND date(created_at) = :today",
            [':uid' => $id, ':today' => $today]
        );

        $currentCount = (int) ($todayCount['cnt'] ?? 0);

        // Check if limit reached
        if ($currentCount >= $limit) {
            return [
                'count'     => $currentCount,
                'limit'     => $limit,
                'remaining' => 0,
            ];
        }

        // Increment user's total search count
        update(
            'users',
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
     * Set user data in session after successful login
     *
     * @param array $user
     * @return void
     */
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
    }

    /**
     * Check if the session has expired
     *
     * @return bool
     */
    public static function isSessionExpired(): bool
    {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }

        return (time() - $_SESSION['login_time']) > SESSION_LIFETIME;
    }

    /**
     * Refresh the session (extend expiry)
     *
     * @return void
     */
    public static function refreshSession(): void
    {
        if (self::isLoggedIn()) {
            $_SESSION['login_time'] = time();
        }
    }
}
