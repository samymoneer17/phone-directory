<?php
/**
 * Auth Check API Endpoint
 * Returns current user info if logged in
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

Security::secureSession();

if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    jsonResponse([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'plan'  => $user['plan'],
            'role'  => $user['role'],
            'avatar' => $user['avatar'] ?? '',
        ],
    ]);
} else {
    jsonResponse([
        'success' => true,
        'logged_in' => false,
    ]);
}
