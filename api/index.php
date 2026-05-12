<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Main Router (Vercel PHP)
 * International Phone Directory
 * ============================================================
 * 
 * This file handles ALL page requests on Vercel.
 * API endpoints (api/auth.php, api/payment.php, etc.) are handled
 * by their own files via vercel-php runtime.
 * 
 * Static files (CSS, JS, images) are served from /public/.
 */

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove trailing slash
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// Remove query string from path (already done by parse_url)
// But for Vercel, sometimes the full URI includes the action path
// e.g., /login?action=submit

// Map paths to PHP page files
$routes = [
    '/'            => 'index.php',
    '/index'       => 'index.php',
    '/index.php'   => 'index.php',
    '/login'       => 'login.php',
    '/login.php'   => 'login.php',
    '/register'    => 'register.php',
    '/register.php'=> 'register.php',
    '/dashboard'   => 'dashboard.php',
    '/dashboard.php'=> 'dashboard.php',
    '/account'     => 'account.php',
    '/account.php' => 'account.php',
    '/search'      => 'search.php',
    '/search.php'  => 'search.php',
    '/plans'       => 'plans.php',
    '/plans.php'   => 'plans.php',
    '/forgot-password'    => 'forgot-password.php',
    '/forgot-password.php'=> 'forgot-password.php',
];

// Find the matching route
$pageFile = $routes[$path] ?? null;

if ($pageFile && file_exists(__DIR__ . '/../' . $pageFile)) {
    // Include the page file
    // The page file will handle its own requires (config, header, footer)
    require __DIR__ . '/../' . $pageFile;
    exit;
}

// If no route matched, serve the corresponding HTML file from public/
// This is a fallback for when pages are rendered as static HTML
$htmlFile = __DIR__ . '/../public' . $path;
if (is_file($htmlFile)) {
    $ext = pathinfo($htmlFile, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'html':
            header('Content-Type: text/html; charset=utf-8');
            readfile($htmlFile);
            exit;
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            readfile($htmlFile);
            exit;
        case 'js':
            header('Content-Type: application/javascript; charset=utf-8');
            readfile($htmlFile);
            exit;
    }
}

// Try with .html extension
if (!is_file($htmlFile) && is_file($htmlFile . '.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($htmlFile . '.html');
    exit;
}

// 404 - Not Found
http_response_code(404);
echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>404 - الصفحة غير موجودة</title>';
echo '</head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;direction:rtl;">';
echo '<div style="text-align:center;"><h1>404</h1><p>الصفحة غير موجودة</p>';
echo '<a href="/">العودة للرئيسية</a></div></body></html>';
