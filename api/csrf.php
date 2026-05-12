<?php
/**
 * CSRF Token API Endpoint
 * Returns a fresh CSRF token for form submissions
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/security.php';

// Initialize session for CSRF
Security::secureSession();

$token = Security::getCSRFToken();

jsonResponse([
    'success' => true,
    'csrf_token' => $token,
]);
