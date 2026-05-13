<?php
/**
 * ============================================================
 * JWT (JSON Web Token) Implementation
 * Lightweight stateless auth tokens for Vercel serverless
 * ============================================================
 * Uses HMAC-SHA256 for signing. No database dependency.
 * Token contains user data and expires after TTL seconds.
 */

class JWT
{
    /**
     * Generate a JWT token
     *
     * @param array  $payload Custom claims (user data)
     * @param string $secret  HMAC secret key
     * @param int    $ttl     Time-to-live in seconds (default: SESSION_LIFETIME)
     * @return string JWT token (header.payload.signature)
     */
    public static function encode(array $payload, string $secret, int $ttl = 86400): string
    {
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;

        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signatureInput = $header . '.' . $payloadEncoded;
        $signature = self::base64UrlEncode(hash_hmac('sha256', $signatureInput, $secret, true));

        return $signatureInput . '.' . $signature;
    }

    /**
     * Validate and decode a JWT token
     *
     * @param string $token  JWT token string
     * @param string $secret HMAC secret key
     * @return array|null Payload data or null if invalid/expired
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, $secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($data)) {
            return null;
        }

        // Check expiration
        if (!isset($data['exp']) || $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * Base64Url encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url decode
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
