<?php
/**
 * CSRF Token API Endpoint (Stateless HMAC-based)
 * Returns a fresh CSRF token for form submissions
 * 
 * Token format: {random}.{timestamp}.{hmac}
 * No session storage needed — works on Vercel serverless
 */

ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

// Strict CORS
$origin = SITE_URL;
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $parsed = parse_url($_SERVER['HTTP_ORIGIN']);
    $siteParsed = parse_url(SITE_URL);
    if ($parsed['host'] === $siteParsed['host']) {
        $origin = $_SERVER['HTTP_ORIGIN'];
    }
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Rate limit CSRF token generation
$ip = Security::getClientIP();
$rateCheck = Security::checkRateLimit($ip, 'csrf_token', 30, 60);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Generate a stateless CSRF token (no session needed)
$token = Security::generateCSRFToken();

jsonResponse([
    'success' => true,
    'csrf_token' => $token,
]);
