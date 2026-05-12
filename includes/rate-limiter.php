<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Rate Limiter Class
 * International Phone Directory
 * ============================================================
 * Advanced rate limiting using database-backed sliding window
 * with IP-based blocking and configurable thresholds.
 */

require_once __DIR__ . '/database.php';

class RateLimiter
{
    /** @var int Default window size in seconds (1 minute) */
    private const DEFAULT_WINDOW = 60;

    /** @var int Default max requests per window */
    private const DEFAULT_MAX = 60;

    /** @var int Block duration in seconds (15 minutes) */
    private const BLOCK_DURATION = 900;

    /** @var int Max violations before permanent block */
    private const MAX_VIOLATIONS = 10;

    /** @var int|null Cached remaining requests for current check */
    private ?int $lastRemaining = null;

    /**
     * Check rate limit for an IP and endpoint
     *
     * @param string      $ip           Client IP address
     * @param string      $endpoint     Endpoint/action identifier
     * @param int         $maxPerMinute Maximum requests per minute (default: 60)
     * @param int         $window       Time window in seconds (default: 60)
     * @return bool True if request is allowed, false if rate limited
     */
    public function check(string $ip, string $endpoint, int $maxPerMinute = self::DEFAULT_MAX, int $window = self::DEFAULT_WINDOW): bool
    {
        // Sanitize inputs
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $endpoint = preg_replace('/[^a-zA-Z0-9_\-:\/]/', '', $endpoint);

        // Check if IP is permanently blocked
        if ($this->isBlocked($ip)) {
            return false;
        }

        try {
            $now = time();
            $windowStart = $now - $window;

            // Clean up expired records (periodic)
            if (rand(1, 100) === 1) {
                $this->cleanupExpired($windowStart);
            }

            // Find existing record
            $record = fetch(
                "SELECT * FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint",
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            if ($record === null) {
                // First request - create new record
                insert('rate_limits', [
                    'ip_address'    => $ip,
                    'endpoint'      => $endpoint,
                    'request_count' => 1,
                    'window_start'  => date('Y-m-d H:i:s', $now),
                ]);

                $this->lastRemaining = $maxPerMinute - 1;
                return true;
            }

            $recordTime = strtotime($record['window_start']);
            $count = (int) $record['request_count'];

            // Check if the window has expired
            if ($now - $recordTime >= $window) {
                // Reset window
                update(
                    'rate_limits',
                    [
                        'request_count' => 1,
                        'window_start'  => date('Y-m-d H:i:s', $now),
                    ],
                    'ip_address = :ip AND endpoint = :endpoint',
                    [':ip' => $ip, ':endpoint' => $endpoint]
                );

                $this->lastRemaining = $maxPerMinute - 1;
                return true;
            }

            // Increment within current window
            $newCount = $count + 1;

            update(
                'rate_limits',
                ['request_count' => $newCount],
                'ip_address = :ip AND endpoint = :endpoint',
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            $remaining = max(0, $maxPerMinute - $newCount);
            $this->lastRemaining = $remaining;

            if ($newCount > $maxPerMinute) {
                // Rate limit exceeded - log violation
                $this->logViolation($ip, $endpoint);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // On database error, allow the request (fail open)
            error_log('Rate limiter error: ' . $e->getMessage());
            $this->lastRemaining = $maxPerMinute;
            return true;
        }
    }

    /**
     * Check if an IP address is blocked
     *
     * @param string $ip Client IP address
     * @return bool True if blocked
     */
    public function isBlocked(string $ip): bool
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';

        try {
            // Check violation count
            $violations = fetch(
                "SELECT COUNT(*) as total FROM activity_logs 
                 WHERE ip_address = :ip 
                 AND action = 'rate_limit_exceeded' 
                 AND created_at > :since",
                [
                    ':ip'    => $ip,
                    ':since' => date('Y-m-d H:i:s', time() - 3600), // last hour
                ]
            );

            $violationCount = (int) ($violations['total'] ?? 0);

            return $violationCount >= self::MAX_VIOLATIONS;
        } catch (\Exception $e) {
            error_log('Block check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get remaining requests for an IP and endpoint
     *
     * @param string $ip           Client IP address
     * @param string $endpoint     Endpoint/action identifier
     * @param int    $maxPerMinute Maximum requests per minute
     * @param int    $window       Time window in seconds
     * @return int Number of remaining requests (0 if rate limited)
     */
    public function getRemaining(string $ip, string $endpoint, int $maxPerMinute = self::DEFAULT_MAX, int $window = self::DEFAULT_WINDOW): int
    {
        // Return cached value if available from a recent check
        if ($this->lastRemaining !== null) {
            $remaining = $this->lastRemaining;
            $this->lastRemaining = null;
            return $remaining;
        }

        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $endpoint = preg_replace('/[^a-zA-Z0-9_\-:\/]/', '', $endpoint);

        try {
            $record = fetch(
                "SELECT * FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint",
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            if ($record === null) {
                return $maxPerMinute;
            }

            $recordTime = strtotime($record['window_start']);
            $count = (int) $record['request_count'];

            // Window expired
            if (time() - $recordTime >= $window) {
                return $maxPerMinute;
            }

            return max(0, $maxPerMinute - $count);
        } catch (\Exception $e) {
            return $maxPerMinute;
        }
    }

    /**
     * Get time until rate limit resets (in seconds)
     *
     * @param string $ip       Client IP address
     * @param string $endpoint Endpoint/action identifier
     * @param int    $window   Time window in seconds
     * @return int Seconds until reset
     */
    public function getResetTime(string $ip, string $endpoint, int $window = self::DEFAULT_WINDOW): int
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $endpoint = preg_replace('/[^a-zA-Z0-9_\-:\/]/', '', $endpoint);

        try {
            $record = fetch(
                "SELECT window_start FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint",
                [':ip' => $ip, ':endpoint' => $endpoint]
            );

            if ($record === null) {
                return 0;
            }

            $recordTime = strtotime($record['window_start']);
            $resetTime = $recordTime + $window;
            $remaining = $resetTime - time();

            return max(0, $remaining);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get rate limit headers for HTTP response
     *
     * @param string $ip           Client IP address
     * @param string $endpoint     Endpoint/action identifier
     * @param int    $maxPerMinute Maximum requests per minute
     * @param int    $window       Time window in seconds
     * @return array{X-RateLimit-Limit: int, X-RateLimit-Remaining: int, X-RateLimit-Reset: int}
     */
    public function getHeaders(string $ip, string $endpoint, int $maxPerMinute = self::DEFAULT_MAX, int $window = self::DEFAULT_WINDOW): array
    {
        $remaining = $this->getRemaining($ip, $endpoint, $maxPerMinute, $window);
        $resetTime = $this->getResetTime($ip, $endpoint, $window);

        return [
            'X-RateLimit-Limit'     => $maxPerMinute,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset'     => $resetTime,
        ];
    }

    /**
     * Send rate limit HTTP headers
     *
     * @param string $ip           Client IP address
     * @param string $endpoint     Endpoint/action identifier
     * @param int    $maxPerMinute Maximum requests per minute
     * @param int    $window       Time window in seconds
     * @return void
     */
    public function sendHeaders(string $ip, string $endpoint, int $maxPerMinute = self::DEFAULT_MAX, int $window = self::DEFAULT_WINDOW): void
    {
        $headers = $this->getHeaders($ip, $endpoint, $maxPerMinute, $window);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Send a 429 Too Many Requests response
     *
     * @param int    $retryAfter Seconds until the user can retry
     * @param string $message    Optional custom message
     * @return void
     */
    public function sendTooManyRequests(int $retryAfter = 60, string $message = ''): void
    {
        if ($message === '') {
            $message = 'تم تجاوز عدد الطلبات المسموح بها. حاول مرة أخرى بعد قليل.';
        }

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => [
                'code'    => 429,
                'message' => $message,
                'retry_after' => $retryAfter,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Log a rate limit violation
     *
     * @param string $ip       Client IP address
     * @param string $endpoint The endpoint that was violated
     * @return void
     */
    private function logViolation(string $ip, string $endpoint): void
    {
        try {
            insert('activity_logs', [
                'user_id'    => null,
                'action'     => 'rate_limit_exceeded',
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'details'    => 'Endpoint: ' . $endpoint,
            ]);
        } catch (\Exception $e) {
            error_log('Violation log failed: ' . $e->getMessage());
        }
    }

    /**
     * Clean up expired rate limit records
     *
     * @param int $windowStart Unix timestamp of window start cutoff
     * @return void
     */
    private function cleanupExpired(int $windowStart): void
    {
        try {
            $cutoff = date('Y-m-d H:i:s', $windowStart);
            query("DELETE FROM rate_limits WHERE window_start < :cutoff", [':cutoff' => $cutoff]);
        } catch (\Exception $e) {
            // Silent fail for cleanup
        }
    }

    /**
     * Reset rate limit for a specific IP and endpoint
     * (Useful for admin actions or testing)
     *
     * @param string $ip       Client IP address
     * @param string $endpoint Endpoint/action identifier
     * @return bool
     */
    public function reset(string $ip, string $endpoint): bool
    {
        try {
            $affected = delete(
                'rate_limits',
                'ip_address = :ip AND endpoint = :endpoint',
                [':ip' => $ip, ':endpoint' => $endpoint]
            );
            return $affected > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reset all rate limits for a specific IP
     *
     * @param string $ip Client IP address
     * @return int Number of records deleted
     */
    public function resetAll(string $ip): int
    {
        try {
            return delete(
                'rate_limits',
                'ip_address = :ip',
                [':ip' => $ip]
            );
        } catch (\Exception $e) {
            return 0;
        }
    }
}
