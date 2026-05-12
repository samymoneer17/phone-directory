<?php
/**
 * Auth Check API Endpoint (Vercel Serverless Compatible)
 * 
 * Accepts auth_token from:
 * - Authorization: Bearer <token> header
 * - X-Auth-Token header
 * - GET ?auth_token=<token>
 * - POST body {auth_token: "..."}
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Auth-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Rate limit
$ip = Security::getClientIP();
$rateCheck = Security::checkRateLimit($ip, 'check_auth', 30, 60);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check auth using token from request
$user = Auth::getUserByRequestToken();

if ($user !== null) {
    jsonResponse([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id'           => (int) $user['id'],
            'name'         => $user['name'] ?? '',
            'email'        => $user['email'] ?? '',
            'phone'        => $user['phone'] ?? '',
            'plan'         => $user['plan'] ?? 'FREE',
            'role'         => $user['role'] ?? 'USER',
            'avatar'       => $user['avatar'] ?? '',
            'search_count' => (int) ($user['search_count'] ?? 0),
            'created_at'   => $user['created_at'] ?? '',
        ],
    ]);
} else {
    jsonResponse([
        'success' => true,
        'logged_in' => false,
    ]);
}
