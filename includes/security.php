<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Security Class
 * International Phone Directory
 * ============================================================
 * Professional security utilities for authentication,
 * input sanitization, CSRF protection, and more.
 */

require_once __DIR__ . '/database.php';

class Security
{
    /**
     * Hash a password using bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
            );
        }

        if (strlen($password) > PASSWORD_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must not exceed ' . PASSWORD_MAX_LENGTH . ' characters'
            );
        }

        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST,
        ]);
    }

    /**
     * Verify a password against its hash
     *
     * @param string $password Plain text password
     * @param string $hash     The password hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a password needs rehashing (e.g., bcrypt cost changed)
     *
     * @param string $hash
     * @return bool
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST,
        ]);
    }

    /**
     * Generate a CSRF token and store it in the session
     *
     * @return string The generated token
     */
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

    /**
     * Verify a CSRF token against the session
     *
     * @param string $token The token to verify
     * @return bool
     */
    public static function verifyCSRFToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Token comparison must be timing-safe
        $result = hash_equals($_SESSION['csrf_token'], $token);

        // Regenerate token after verification (one-time use)
        if ($result) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }

        return $result;
    }

    /**
     * Get the current CSRF token from session (for form embedding)
     *
     * @return string|null
     */
    public static function getCSRFToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate a new one if none exists
        if (empty($_SESSION['csrf_token'])) {
            return self::generateCSRFToken();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Output a hidden CSRF input field for HTML forms
     *
     * @return string HTML input element
     */
    public static function csrfField(): string
    {
        $token = self::getCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Sanitize user input: strip tags, encode entities, remove JS patterns
     *
     * @param string $input
     * @return string
     */
    public static function sanitizeInput(string $input): string
    {
        if ($input === '') {
            return '';
        }

        // Decode any existing HTML entities first
        $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove null bytes
        $input = str_replace(chr(0), '', $input);

        // Strip HTML and PHP tags
        $input = strip_tags($input);

        // Remove JavaScript patterns
        $javascriptPatterns = [
            '/javascript\s*:/i',
            '/\bon\w+\s*=\s*["\']?[^"\']*["\']?/i',   // onclick=, onload=, etc.
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

        // Trim whitespace
        $input = trim($input);

        // Normalize whitespace
        $input = preg_replace('/\s+/', ' ', $input);

        return $input;
    }

    /**
     * Deep sanitize an array of inputs
     *
     * @param array $inputs
     * @return array
     */
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
     * Validate an email address
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        if (strlen($email) > 254) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional domain check
        $domain = substr(strrchr($email, '@'), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }

        return true;
    }

    /**
     * Validate a phone number (international format)
     * Accepts formats: +967123456789, 00967123456789, 967123456789
     *
     * @param string $phone
     * @return bool
     */
    public static function validatePhone(string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        // Remove spaces, dashes, parentheses
        $clean = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        // Must start with +, 00, or digit
        if (!preg_match('/^(\+|00)?[1-9]\d{6,14}$/', $clean)) {
            return false;
        }

        // Minimum 7 digits, maximum 15 digits (ITU-T E.164)
        $digits = preg_replace('/[^0-9]/', '', $clean);
        $length = strlen($digits);

        return $length >= 7 && $length <= 15;
    }

    /**
     * Generate a password reset token (32-byte hex string)
     *
     * @return string
     */
    public static function generateResetToken(): string
    {
        return bin2hex(random_bytes(RESET_TOKEN_LENGTH));
    }

    /**
     * Validate a transaction ID (alphanumeric, 8-128 characters)
     *
     * @param string $id
     * @return bool
     */
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

    /**
     * Check rate limiting for an IP address on an endpoint
     *
     * @param string   $ip             Client IP address
     * @param string   $endpoint       API endpoint or action name
     * @param int      $maxRequests    Maximum requests allowed
     * @param int      $windowSeconds  Time window in seconds
     * @return array{allowed: bool, remaining: int, resetIn: int}
     */
    public static function checkRateLimit(
        string $ip,
        string $endpoint,
        int $maxRequests,
        int $windowSeconds
    ): array {
        try {
            $now = date('Y-m-d H:i:s');
            $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

            // Clean up old rate limit records
            query(
                "DELETE FROM rate_limits WHERE window_start < :window_start",
                [':window_start' => $windowStart]
            );

            // Get or create the rate limit record
            $record = fetch(
                "SELECT * FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint",
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            if ($record === null) {
                // First request - create record
                insert('rate_limits', [
                    'ip_address'   => $ip,
                    'endpoint'     => $endpoint,
                    'request_count' => 1,
                    'window_start' => $now,
                ]);

                return [
                    'allowed'   => true,
                    'remaining' => $maxRequests - 1,
                    'resetIn'   => $windowSeconds,
                ];
            }

            // Check if the window has expired
            $recordTime = strtotime($record['window_start']);
            if (time() - $recordTime >= $windowSeconds) {
                // Reset window
                update(
                    'rate_limits',
                    [
                        'request_count' => 1,
                        'window_start' => $now,
                    ],
                    'ip_address = :ip AND endpoint = :endpoint',
                    [':ip' => $ip, ':endpoint' => $endpoint]
                );

                return [
                    'allowed'   => true,
                    'remaining' => $maxRequests - 1,
                    'resetIn'   => $windowSeconds,
                ];
            }

            // Increment request count
            $newCount = $record['request_count'] + 1;

            update(
                'rate_limits',
                ['request_count' => $newCount],
                'ip_address = :ip AND endpoint = :endpoint',
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            $remaining = max(0, $maxRequests - $newCount);
            $resetIn = $windowSeconds - (time() - $recordTime);

            return [
                'allowed'   => $newCount <= $maxRequests,
                'remaining' => $remaining,
                'resetIn'   => max(0, $resetIn),
            ];
        } catch (\Exception $e) {
            error_log('Rate limit check failed: ' . $e->getMessage());
            // On error, allow the request (fail open)
            return [
                'allowed'   => true,
                'remaining' => $maxRequests,
                'resetIn'   => 0,
            ];
        }
    }

    /**
     * Log user activity
     *
     * @param int|null $userId   User ID (null for guests)
     * @param string   $action   Action name (e.g., 'login', 'search', 'register')
     * @param string   $details  Additional details
     * @return void
     */
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
     * Check if the current user agent is a bot/crawler
     *
     * @param string|null $userAgent
     * @return bool
     */
    public static function isBot(?string $userAgent = null): bool
    {
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        $botPatterns = [
            'Googlebot',
            'bingbot',
            'Slurp',
            'DuckDuckBot',
            'Baiduspider',
            'YandexBot',
            'facebot',
            'ia_archiver',
            ' crawler',
            'spider',
            'bot/',
            'curl/',
            'wget',
            'python-requests',
            'java/',
            'httpclient',
            'Go-http-client',
            'scrapy',
            'php/',
            'libwww',
            'MJ12bot',
            'AhrefsBot',
            'SemrushBot',
            'DotBot',
            'SEOkicks',
            'Mail.RU_Bot',
            'megaindex',
            'linkdex',
            'archive.org_bot',
            'GPTBot',
            'CCBot',
            'Bytespider',
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enforce HTTPS by redirecting HTTP requests
     *
     * @return void
     */
    public static function enforceHTTPS(): void
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return;
        }

        // Only enforce in production
        if (defined('APP_ENV') && APP_ENV === 'development') {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $redirectUrl = 'https://' . $host . $uri;
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Configure secure session parameters
     *
     * @return void
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

        // Regenerate session ID periodically (every 30 minutes)
        if (isset($_SESSION['last_regeneration'])) {
            if (time() - $_SESSION['last_regeneration'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        } else {
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Set X-Frame-Options header to prevent clickjacking
     *
     * @return void
     */
    public static function preventClickjacking(): void
    {
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: frame-ancestors \'none\'');
    }

    /**
     * Set X-Content-Type-Options header to prevent MIME sniffing
     *
     * @return void
     */
    public static function preventMIME(): void
    {
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Set restrictive CORS headers
     *
     * @param string $allowOrigin  Allowed origin(s)
     * @param array  $allowMethods Allowed HTTP methods
     * @param array  $allowHeaders Allowed request headers
     * @return void
     */
    public static function setCORS(
        string $allowOrigin = '',
        array $allowMethods = ['GET', 'POST'],
        array $allowHeaders = ['Content-Type', 'X-CSRF-Token']
    ): void {
        if ($allowOrigin === '') {
            $allowOrigin = SITE_URL;
        }

        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowHeaders));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Apply all standard security headers
     *
     * @return void
     */
    public static function applySecurityHeaders(): void
    {
        self::preventClickjacking();
        self::preventMIME();

        // Prevent XSS
        header("X-XSS-Protection: 1; mode=block");

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");

        // HSTS (only if using HTTPS)
        if (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Get the client's real IP address
     * Accounts for proxies and load balancers
     *
     * @return string
     */
    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
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

    /**
     * Generate a secure random string
     *
     * @param int $length
     * @return string
     */
    public static function randomString(int $length = 32): string
    {
        return bin2hex(random_bytes(max(1, (int) ceil($length / 2))));
    }

    /**
     * Generate a random numeric code (for OTP, etc.)
     *
     * @param int $length
     * @return string
     */
    public static function randomNumericCode(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    /**
     * Encrypt sensitive data using AES-256-CBC
     *
     * @param string $data   Data to encrypt
     * @param string $key    Encryption key
     * @return string Base64-encoded encrypted data with IV prepended
     */
    public static function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data encrypted with encrypt()
     *
     * @param string $data  Base64-encoded encrypted data
     * @param string $key   Encryption key
     * @return string|null Decrypted data or null on failure
     */
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
}
