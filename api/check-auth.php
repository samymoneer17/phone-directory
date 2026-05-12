<?php
/**
 * Auth Check API Endpoint (Vercel Serverless Compatible)
 * Returns current user info if logged in
 * 
 * On Vercel, PHP sessions don't persist between function invocations.
 * This endpoint accepts user_id from the client and verifies it against
 * the database, so it works reliably on serverless infrastructure.
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
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
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

// ============================================================
// Check auth: Try session first, then accept user_id from client
// ============================================================

$userId = null;

// 1. Try PHP session (works on traditional servers)
Security::secureSession();
if (Auth::isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
}

// 2. If no session, accept user_id from client (Vercel serverless)
if (!$userId) {
    // From POST body
    $input = Security::getJsonInput();
    if ($input && !empty($input['user_id'])) {
        $userId = (int) $input['user_id'];
    }
    // From GET query param
    if (!$userId && isset($_GET['user_id'])) {
        $userId = (int) $_GET['user_id'];
    }
}

if ($userId > 0) {
    // Verify user exists in database
    $user = fetch(
        "SELECT id, name, email, phone, plan, role, avatar, search_count, created_at 
         FROM users WHERE id = :id LIMIT 1",
        [':id' => $userId]
    );

    if ($user !== null) {
        // Never expose sensitive fields
        jsonResponse([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id'         => (int) $user['id'],
                'name'       => $user['name'] ?? '',
                'email'      => $user['email'] ?? '',
                'phone'      => $user['phone'] ?? '',
                'plan'       => $user['plan'] ?? 'FREE',
                'role'       => $user['role'] ?? 'USER',
                'avatar'     => $user['avatar'] ?? '',
                'search_count' => (int) ($user['search_count'] ?? 0),
                'created_at' => $user['created_at'] ?? '',
            ],
        ]);
    }
}

// Not logged in
jsonResponse([
    'success' => true,
    'logged_in' => false,
]);
