<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Security Class (Enhanced)
 * International Phone Directory
 * ============================================================
 * Professional security utilities with:
 * - Session fingerprinting (IP + User-Agent binding)
 * - Password complexity validation
 * - Brute force protection with progressive delays
 * - IP blocking system
 * - Comprehensive CSP headers
 * - Security event audit logging
 * - CORS hardening
 * - Request size limits
 * - Anti-automation measures
 */

require_once __DIR__ . '/database.php';

class Security
{
    // ================================================================
    // THROTTLE CONFIG
    // ================================================================
    private const MAX_LOGIN_ATTEMPTS      = 5;
    private const LOCKOUT_DURATION        = 900;     // 15 minutes
    private const PROGRESSIVE_DELAY_BASE  = 1;       // seconds
    private const PROGRESSIVE_DELAY_MAX   = 30;      // seconds
    private const MAX_REQUEST_BODY_SIZE   = 1048576; // 1MB

    // ================================================================
    // PASSWORD COMPLEXITY
    // ================================================================
    private const PASSWORD_PATTERNS = [
        'uppercase'   => '/[A-Z]/',
        'lowercase'   => '/[a-z]/',
        'number'      => '/[0-9]/',
        'special'     => '/[!@#$%^&*()_+\-=\[\]{}|;:\'",.<>?\/\\\\`~]/',
    ];

    /**
     * Hash a password using bcrypt
     */
    public static function hashPassword(string $password): string
    {
        self::validatePasswordComplexity($password);

        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST,
        ]);
    }

    /**
     * Verify a password against its hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        // Prevent timing attacks on empty passwords
        if (empty($password) || empty($hash)) {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Check if a password needs rehashing
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST,
        ]);
    }

    // ================================================================
    // PASSWORD COMPLEXITY VALIDATION
    // ================================================================

    /**
     * Validate password complexity requirements
     * Requires: uppercase, lowercase, number, special char
     *
     * @param string $password
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function validatePasswordComplexity(string $password): bool
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException(
                'كلمة المرور يجب أن تكون على الأقل ' . PASSWORD_MIN_LENGTH . ' أحرف'
            );
        }

        if (strlen($password) > PASSWORD_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                'كلمة المرور يجب ألا تتجاوز ' . PASSWORD_MAX_LENGTH . ' حرف'
            );
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', '12345678', '123456789', 'qwerty', 'abc123',
            'password1', 'iloveyou', 'admin123', 'welcome1', '00000000',
            '11111111', '1234567890', '123123123', 'aaaaaaaa', 'qwertyui',
        ];

        if (in_array(strtolower($password), $weakPasswords)) {
            throw new \InvalidArgumentException('كلمة المرور ضعيفة جداً. اختر كلمة مرور أقوى');
        }

        // Check for sequences
        if (self::hasSequentialChars($password, 4)) {
            throw new \InvalidArgumentException('كلمة المرور تحتوي على تسلسل أرقام أو حروف متتالية');
        }

        // Check for repeated chars
        if (self::hasRepeatedChars($password, 3)) {
            throw new \InvalidArgumentException('كلمة المرور تحتوي على أحرف مكررة كثيراً');
        }

        // Complexity requirements
        $missing = [];
        foreach (self::PASSWORD_PATTERNS as $type => $pattern) {
            if (!preg_match($pattern, $password)) {
                $missing[] = $type;
            }
        }

        if (!empty($missing)) {
            $requirements = 'يجب أن تحتوي كلمة المرور على: حرف كبير، حرف صغير، رقم، وحرف خاص';
            throw new \InvalidArgumentException($requirements);
        }

        // Check username similarity (if user exists in context)
        if (isset($_SESSION['register_name'])) {
            $name = strtolower(trim($_SESSION['register_name']));
            if (!empty($name) && strpos(strtolower($password), $name) !== false) {
                throw new \InvalidArgumentException('كلمة المرور يجب ألا تحتوي على اسم المستخدم');
            }
        }

        return true;
    }

    /**
     * Get password strength score (0-100)
     */
    public static function getPasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        // Length score
        $len = strlen($password);
        if ($len >= 8)  $score += 15;
        if ($len >= 12) $score += 10;
        if ($len >= 16) $score += 10;

        // Complexity score
        foreach (self::PASSWORD_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $password)) {
                $score += 15;
            } else {
                $labels = [
                    'uppercase' => 'حرف كبير',
                    'lowercase' => 'حرف صغير',
                    'number'    => 'رقم',
                    'special'   => 'حرف خاص',
                ];
                $feedback[] = 'أضف ' . ($labels[$type] ?? $type);
            }
        }

        // Entropy bonus
        $uniqueChars = count(array_unique(str_split($password)));
        $entropyRatio = $uniqueChars / max(1, $len);
        $score += (int) ($entropyRatio * 15);

        $score = min(100, $score);

        // Weak password check
        $weakPasswords = ['password', '12345678', '123456789', 'qwerty', 'abc123', 'admin123'];
        if (in_array(strtolower($password), $weakPasswords)) {
            $score = 5;
            $feedback[] = 'كلمة المرور شائعة جداً';
        }

        $level = 'ضعيفة';
        if ($score >= 80) $level = 'قوية جداً';
        elseif ($score >= 60) $level = 'قوية';
        elseif ($score >= 40) $level = 'متوسطة';

        return [
            'score'    => $score,
            'level'    => $level,
            'feedback' => $feedback,
        ];
    }

    /**
     * Check if password has sequential characters
     */
    private static function hasSequentialChars(string $password, int $length): bool
    {
        $str = strtolower($password);
        $sequences = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $seqLen = strlen($sequences) - $length + 1;

        for ($i = 0; $i < $seqLen; $i++) {
            $seq = substr($sequences, $i, $length);
            $rev = strrev($seq);
            if (strpos($str, $seq) !== false || strpos($str, $rev) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if password has repeated characters
     */
    private static function hasRepeatedChars(string $password, int $count): bool
    {
        return preg_match('/(.)\1{' . ($count - 1) . '}/', $password) === 1;
    }

    // ================================================================
    // BRUTE FORCE PROTECTION
    // ================================================================

    /**
     * Check and record a login attempt
     * Returns: [allowed, remaining_attempts, lockout_seconds, delay_seconds]
     */
    public static function checkLoginAttempt(string $email, string $ip): array
    {
        try {
            $record = fetch(
                "SELECT * FROM login_attempts WHERE email = :email AND ip_address = :ip",
                [':email' => strtolower(trim($email)), ':ip' => $ip]
            );

            if ($record === null) {
                return [
                    'allowed'            => true,
                    'remaining_attempts' => self::MAX_LOGIN_ATTEMPTS,
                    'lockout_seconds'    => 0,
                    'delay_seconds'      => 0,
                ];
            }

            // Check if currently locked
            if ((int) $record['is_locked'] === 1 && $record['locked_until'] !== null) {
                $lockUntil = strtotime($record['locked_until']);
                if (time() < $lockUntil) {
                    $remaining = $lockUntil - time();
                    self::logSecurityEvent('login_blocked', 'CRITICAL', null, $ip, "Locked out: {$email}, {$remaining}s remaining");
                    return [
                        'allowed'            => false,
                        'remaining_attempts' => 0,
                        'lockout_seconds'    => $remaining,
                        'delay_seconds'      => 0,
                    ];
                } else {
                    // Lockout expired - reset
                    update('login_attempts',
                        ['attempts_count' => 0, 'is_locked' => 0, 'locked_until' => null],
                        'email = :email AND ip_address = :ip',
                        [':email' => strtolower(trim($email)), ':ip' => $ip]
                    );
                    return [
                        'allowed'            => true,
                        'remaining_attempts' => self::MAX_LOGIN_ATTEMPTS,
                        'lockout_seconds'    => 0,
                        'delay_seconds'      => 0,
                    ];
                }
            }

            $attempts = (int) $record['attempts_count'];
            $remaining = max(0, self::MAX_LOGIN_ATTEMPTS - $attempts);

            // Progressive delay: increases with each failed attempt
            $delay = 0;
            if ($attempts >= 3) {
                $delay = min(
                    self::PROGRESSIVE_DELAY_MAX,
                    self::PROGRESSIVE_DELAY_BASE * pow(2, $attempts - 3)
                );
            }

            return [
                'allowed'            => $attempts < self::MAX_LOGIN_ATTEMPTS,
                'remaining_attempts' => $remaining,
                'lockout_seconds'    => 0,
                'delay_seconds'      => $delay,
            ];
        } catch (\Exception $e) {
            error_log('Login attempt check error: ' . $e->getMessage());
            return [
                'allowed'            => true,
                'remaining_attempts' => self::MAX_LOGIN_ATTEMPTS,
                'lockout_seconds'    => 0,
                'delay_seconds'      => 0,
            ];
        }
    }

    /**
     * Record a failed login attempt
     */
    public static function recordFailedLogin(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';

        try {
            $record = fetch(
                "SELECT * FROM login_attempts WHERE email = :email AND ip_address = :ip",
                [':email' => $email, ':ip' => $ip]
            );

            if ($record === null) {
                insert('login_attempts', [
                    'email'          => $email,
                    'ip_address'     => $ip,
                    'attempts_count' => 1,
                    'last_attempt_at' => date('Y-m-d H:i:s'),
                    'is_locked'      => 0,
                ]);
            } else {
                $newCount = (int) $record['attempts_count'] + 1;
                $updateData = [
                    'attempts_count'  => $newCount,
                    'last_attempt_at'  => date('Y-m-d H:i:s'),
                ];

                // Lock account if max attempts reached
                if ($newCount >= self::MAX_LOGIN_ATTEMPTS) {
                    $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION);
                    $updateData['is_locked'] = 1;
                    $updateData['locked_until'] = $lockUntil;

                    // Also lock the user account
                    $user = fetch("SELECT id FROM users WHERE email = :email", [':email' => $email]);
                    if ($user !== null) {
                        update('users', [
                            'role'         => 'BANNED',
                            'locked_until' => $lockUntil,
                            'updated_at'   => date('Y-m-d H:i:s'),
                        ], 'id = :id', [':id' => $user['id']]);
                    }

                    self::logSecurityEvent('account_locked', 'CRITICAL', null, $ip,
                        "Account locked after {$newCount} failed attempts: {$email}");
                }

                update('login_attempts', $updateData,
                    'email = :email AND ip_address = :ip',
                    [':email' => $email, ':ip' => $ip]
                );
            }
        } catch (\Exception $e) {
            error_log('Record failed login error: ' . $e->getMessage());
        }
    }

    /**
     * Clear failed login attempts after successful login
     */
    public static function clearFailedLogins(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';

        try {
            // Clear login_attempts record
            delete('login_attempts', 'email = :email AND ip_address = :ip',
                [':email' => $email, ':ip' => $ip]);

            // Unlock user account if it was locked due to failed attempts
            $user = fetch("SELECT id, role, locked_until FROM users WHERE email = :email", [':email' => $email]);
            if ($user !== null && $user['role'] === 'BANNED' && $user['locked_until'] !== null) {
                update('users', [
                    'role'         => 'USER',
                    'locked_until' => null,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], 'id = :id', [':id' => $user['id']]);
            }
        } catch (\Exception $e) {
            error_log('Clear failed logins error: ' . $e->getMessage());
        }
    }

    // ================================================================
    // IP BLOCKING SYSTEM
    // ================================================================

    /**
     * Check if an IP is blocked
     */
    public static function isIPBlocked(string $ip): bool
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';

        try {
            $blocked = fetch(
                "SELECT * FROM blocked_ips WHERE ip_address = :ip AND (is_permanent = 1 OR expires_at > :now)",
                [':ip' => $ip, ':now' => date('Y-m-d H:i:s')]
            );
            return $blocked !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Block an IP address
     */
    public static function blockIP(string $ip, string $reason = '', int $durationSeconds = 0, int $blockedBy = null): bool
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        if (!$ip) return false;

        try {
            $expiresAt = $durationSeconds > 0
                ? date('Y-m-d H:i:s', time() + $durationSeconds)
                : null;
            $isPermanent = $durationSeconds === 0 ? 1 : 0;

            // Upsert
            $existing = fetch("SELECT id FROM blocked_ips WHERE ip_address = :ip", [':ip' => $ip]);
            if ($existing !== null) {
                update('blocked_ips', [
                    'reason'     => $reason,
                    'expires_at' => $expiresAt,
                    'is_permanent' => $isPermanent,
                    'blocked_by' => $blockedBy,
                ], 'ip_address = :ip', [':ip' => $ip]);
            } else {
                insert('blocked_ips', [
                    'ip_address'  => $ip,
                    'reason'      => $reason,
                    'blocked_by'  => $blockedBy,
                    'expires_at'  => $expiresAt,
                    'is_permanent' => $isPermanent,
                ]);
            }

            self::logSecurityEvent('ip_blocked', 'CRITICAL', $blockedBy, $ip, $reason);
            return true;
        } catch (\Exception $e) {
            error_log('Block IP error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unblock an IP address
     */
    public static function unblockIP(string $ip): bool
    {
        try {
            delete('blocked_ips', 'ip_address = :ip', [':ip' => $ip]);
            self::logSecurityEvent('ip_unblocked', 'INFO', null, $ip, 'IP unblocked');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ================================================================
    // SESSION FINGERPRINTING (Anti-Hijacking)
    // ================================================================

    /**
     * Generate a session fingerprint from IP and User-Agent
     */
    public static function generateFingerprint(): string
    {
        $ip = self::getClientIP();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Add a secret salt to prevent fingerprint forgery
        $salt = 'phone_dir_fp_salt_2024';
        return hash('sha256', $ip . '|' . $ua . '|' . $salt);
    }

    /**
     * Verify session fingerprint
     * Returns true if fingerprint matches or doesn't exist yet
     */
    public static function verifyFingerprint(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        if (!isset($_SESSION['fingerprint'])) {
            return true; // First time - no fingerprint to verify
        }

        $current = self::generateFingerprint();
        return hash_equals($_SESSION['fingerprint'], $current);
    }

    /**
     * Configure secure session with fingerprinting
     */
    public static function secureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_only_cookies', '1');

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime'  => SESSION_LIFETIME,
            'path'      => $cookieParams['path'] ?? '/',
            'domain'    => $cookieParams['domain'] ?? '',
            'secure'    => SESSION_COOKIE_SECURE,
            'httponly'   => SESSION_COOKIE_HTTPONLY,
            'samesite'  => SESSION_COOKIE_SAMESITE,
        ]);

        session_name(SESSION_NAME);
        session_start();

        // Set fingerprint on new sessions
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = self::generateFingerprint();
        }

        // Verify fingerprint - if mismatch, destroy session (possible hijacking)
        if (!self::verifyFingerprint()) {
            self::logSecurityEvent('session_hijack_attempt', 'CRITICAL',
                $_SESSION['user_id'] ?? null, self::getClientIP(),
                'Session fingerprint mismatch - possible hijacking attempt');
            self::destroySession();
            return;
        }

        // Check if session has expired
        if (isset($_SESSION['login_time']) && self::isSessionExpired()) {
            self::destroySession();
            return;
        }

        // Check if user is banned
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
            $user = fetch("SELECT role FROM users WHERE id = :id", [':id' => $_SESSION['user_id']]);
            if ($user !== null && $user['role'] === 'BANNED') {
                self::destroySession();
                return;
            }
        }

        // Regenerate session ID periodically (every 30 minutes)
        if (isset($_SESSION['last_regeneration'])) {
            if (time() - $_SESSION['last_regeneration'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
                $_SESSION['fingerprint'] = self::generateFingerprint();
            }
        } else {
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Destroy session completely
     */
    public static function destroySession(): void
    {
        $_SESSION = [];

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

        session_destroy();
    }

    // ================================================================
    // CSRF PROTECTION
    // ================================================================

    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }

    public static function verifyCSRFToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        $result = hash_equals($_SESSION['csrf_token'], $token);

        // One-time use
        if ($result) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }

        return $result;
    }

    public static function getCSRFToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            return self::generateCSRFToken();
        }

        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $token = self::getCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function csrfMetaTag(): string
    {
        $token = self::getCSRFToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ================================================================
    // INPUT SANITIZATION & VALIDATION
    // ================================================================

    public static function sanitizeInput(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $input = str_replace(chr(0), '', $input);
        $input = strip_tags($input);

        $javascriptPatterns = [
            '/javascript\s*:/i',
            '/\bon\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<object\b[^>]*>.*?<\/object>/is',
            '/<embed\b[^>]*>/i',
            '/<form\b[^>]*>.*?<\/form>/is',
            '/\bdocument\./i',
            '/\bwindow\./i',
            '/\beval\s*\(/i',
            '/\balert\s*\(/i',
            '/\bexpression\s*\(/i',
            '/\burl\s*\(/i',
            '/\bimport\s*\(/i',
            '/\brequire\s*\(/i',
            '/data\s*:\s*text\/html/i',
        ];

        foreach ($javascriptPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        $input = trim($input);
        $input = preg_replace('/\s+/', ' ', $input);

        return $input;
    }

    public static function sanitizeArray(array $inputs): array
    {
        $clean = [];
        foreach ($inputs as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$key] = self::sanitizeInput($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    /**
     * Validate and sanitize JSON input from request body
     */
    public static function getJsonInput(int $maxSize = null): ?array
    {
        $maxSize = $maxSize ?? self::MAX_REQUEST_BODY_SIZE;

        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ((int) $contentLength > $maxSize) {
            self::logSecurityEvent('payload_too_large', 'WARNING', null,
                self::getClientIP(), "Content-Length: {$contentLength}");
            return null;
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    public static function validateEmail(string $email): bool
    {
        if (empty($email) || strlen($email) > 254) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Prevent domain with consecutive dots
        if (preg_match('/\.\./', $email)) {
            return false;
        }

        $domain = substr(strrchr($email, '@'), 1);
        // Don't require MX check on Vercel as DNS might not resolve
        if (!IS_VERCEL) {
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                return false;
            }
        }

        return true;
    }

    public static function validatePhone(string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $clean = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        if (!preg_match('/^(\+|00)?[1-9]\d{6,14}$/', $clean)) {
            return false;
        }

        $digits = preg_replace('/[^0-9]/', '', $clean);
        $length = strlen($digits);

        return $length >= 7 && $length <= 15;
    }

    public static function validateTransactionId(string $id): bool
    {
        if (empty($id)) {
            return false;
        }

        $length = strlen($id);

        return $length >= 8
            && $length <= 128
            && preg_match('/^[a-zA-Z0-9\-_]+$/', $id);
    }

    public static function generateResetToken(): string
    {
        return bin2hex(random_bytes(RESET_TOKEN_LENGTH));
    }

    // ================================================================
    // SECURITY HEADERS (Enhanced)
    // ================================================================

    /**
     * Apply comprehensive security headers
     */
    public static function applySecurityHeaders(): void
    {
        // Anti-clickjacking
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: frame-ancestors \'none\'');

        // Anti-MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy browsers)
        header("X-XSS-Protection: 1; mode=block");

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=()");

        // HSTS
        if (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy
        self::setContentSecurityPolicy();
    }

    /**
     * Set Content Security Policy
     */
    public static function setContentSecurityPolicy(): void
    {
        $siteUrl = SITE_URL;

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
        ];

        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('Content-Security-Policy-Report-Only: ' . implode('; ', $csp));
    }

    public static function preventClickjacking(): void
    {
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: frame-ancestors \'none\'');
    }

    public static function preventMIME(): void
    {
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Set strict CORS headers (not wildcard)
     */
    public static function setCORS(
        string $allowOrigin = '',
        array $allowMethods = ['GET', 'POST'],
        array $allowHeaders = ['Content-Type', 'X-CSRF-Token']
    ): void {
        if ($allowOrigin === '') {
            $allowOrigin = SITE_URL;
        }

        // Sanitize origin - prevent regex injection
        $allowOrigin = filter_var($allowOrigin, FILTER_SANITIZE_URL);

        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowHeaders));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public static function enforceHTTPS(): void
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') return;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return;
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) return;
        if (defined('APP_ENV') && APP_ENV === 'development') return;

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: https://' . $host . $uri);
        exit;
    }

    // ================================================================
    // RATE LIMITING (Enhanced)
    // ================================================================

    public static function checkRateLimit(
        string $ip,
        string $endpoint,
        int $maxRequests,
        int $windowSeconds
    ): array {
        try {
            // Check if IP is blocked first
            if (self::isIPBlocked($ip)) {
                return [
                    'allowed'   => false,
                    'remaining' => 0,
                    'resetIn'   => 0,
                ];
            }

            $now = date('Y-m-d H:i:s');
            $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

            // Periodic cleanup
            if (rand(1, 100) === 1) {
                query("DELETE FROM rate_limits WHERE window_start < :window_start",
                    [':window_start' => $windowStart]);
            }

            $record = fetch(
                "SELECT * FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint",
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            if ($record === null) {
                insert('rate_limits', [
                    'ip_address'    => $ip,
                    'endpoint'      => $endpoint,
                    'request_count' => 1,
                    'window_start'  => $now,
                ]);

                return [
                    'allowed'   => true,
                    'remaining' => $maxRequests - 1,
                    'resetIn'   => $windowSeconds,
                ];
            }

            $recordTime = strtotime($record['window_start']);
            if (time() - $recordTime >= $windowSeconds) {
                update('rate_limits',
                    ['request_count' => 1, 'window_start' => $now],
                    'ip_address = :ip AND endpoint = :endpoint',
                    [':ip' => $ip, ':endpoint' => $endpoint]
                );

                return [
                    'allowed'   => true,
                    'remaining' => $maxRequests - 1,
                    'resetIn'   => $windowSeconds,
                ];
            }

            $newCount = $record['request_count'] + 1;

            update('rate_limits',
                ['request_count' => $newCount],
                'ip_address = :ip AND endpoint = :endpoint',
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            $remaining = max(0, $maxRequests - $newCount);
            $resetIn = $windowSeconds - (time() - $recordTime);

            if ($newCount > $maxRequests) {
                self::logSecurityEvent('rate_limit_exceeded', 'WARNING', null, $ip,
                    "Rate limited on {$endpoint}: {$newCount}/{$maxRequests}");

                // Auto-block IP after repeated violations
                $violationCount = (int) fetch(
                    "SELECT COUNT(*) as total FROM security_events 
                     WHERE ip_address = :ip AND event_type = 'rate_limit_exceeded' 
                     AND created_at > :since",
                    [':ip' => $ip, ':since' => date('Y-m-d H:i:s', time() - 3600)]
                )['total'];

                if ($violationCount >= 20) {
                    self::blockIP($ip, 'Automatic block: excessive rate limit violations', 3600);
                }

                return [
                    'allowed'   => false,
                    'remaining' => 0,
                    'resetIn'   => max(0, $resetIn),
                ];
            }

            return [
                'allowed'   => true,
                'remaining' => $remaining,
                'resetIn'   => max(0, $resetIn),
            ];
        } catch (\Exception $e) {
            error_log('Rate limit check failed: ' . $e->getMessage());
            return [
                'allowed'   => true,
                'remaining' => $maxRequests,
                'resetIn'   => 0,
            ];
        }
    }

    // ================================================================
    // ACTIVITY & SECURITY LOGGING
    // ================================================================

    public static function logActivity(?int $userId, string $action, string $details = ''): void
    {
        try {
            insert('activity_logs', [
                'user_id'    => $userId,
                'action'     => $action,
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'details'    => $details,
            ]);
        } catch (\Exception $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }

    /**
     * Log a security event to the dedicated security_events table
     */
    public static function logSecurityEvent(
        string $eventType,
        string $severity = 'INFO',
        ?int $userId = null,
        ?string $ip = null,
        string $description = '',
        ?array $metadata = null
    ): void {
        try {
            insert('security_events', [
                'event_type' => $eventType,
                'severity'   => $severity,
                'user_id'    => $userId,
                'ip_address' => $ip ?? self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'description' => $description,
                'metadata'   => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Exception $e) {
            error_log('Security event log failed: ' . $e->getMessage());
        }
    }

    public static function isBot(?string $userAgent = null): bool
    {
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        $botPatterns = [
            'Googlebot', 'bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider',
            'YandexBot', 'facebot', 'ia_archiver', 'crawler', 'spider',
            'bot/', 'curl/', 'wget', 'python-requests', 'java/', 'httpclient',
            'Go-http-client', 'scrapy', 'php/', 'libwww', 'MJ12bot',
            'AhrefsBot', 'SemrushBot', 'DotBot', 'SEOkicks', 'Mail.RU_Bot',
            'megaindex', 'linkdex', 'archive.org_bot', 'GPTBot', 'CCBot',
            'Bytespider', 'CensysInspect', 'Nmap', 'Nikto', 'sqlmap',
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    // ================================================================
    // UTILITY METHODS
    // ================================================================

    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function randomString(int $length = 32): string
    {
        return bin2hex(random_bytes(max(1, (int) ceil($length / 2))));
    }

    public static function randomNumericCode(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    public static function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data, string $key): ?string
    {
        $data = base64_decode($data);
        if ($data === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($data) < $ivLength) {
            return null;
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Check if session has expired
     */
    public static function isSessionExpired(): bool
    {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }
        return (time() - $_SESSION['login_time']) > SESSION_LIFETIME;
    }

    /**
     * Initial security check - call this at the very beginning of every request
     * Checks: IP blocked, bot detection, request size
     */
    public static function initialCheck(): void
    {
        $ip = self::getClientIP();

        // Check if IP is blocked
        if (self::isIPBlocked($ip)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'code' => 403,
                    'message' => 'تم حظر الوصول من هذا العنوان. تواصل مع الدعم إذا كان هذا خطأ.',
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Check request body size
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ((int) $contentLength > self::MAX_REQUEST_BODY_SIZE) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'code' => 413,
                    'message' => 'حجم الطلب كبير جداً',
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
