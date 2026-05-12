<?php
/**
 * Auth Check API Endpoint (Enhanced Security)
 * Returns current user info if logged in
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

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
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

Security::secureSession();

// Rate limit
$ip = Security::getClientIP();
$rateCheck = Security::checkRateLimit($ip, 'check_auth', 30, 60);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    // Never expose sensitive fields
    jsonResponse([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id'    => (int) ($user['id'] ?? 0),
            'name'  => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'plan'  => $user['plan'] ?? 'FREE',
            'role'  => $user['role'] ?? 'USER',
            'avatar' => $user['avatar'] ?? '',
        ],
    ]);
} else {
    jsonResponse([
        'success' => true,
        'logged_in' => false,
    ]);
}
