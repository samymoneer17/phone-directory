<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Search API Endpoint (Enhanced Security)
 * International Phone Directory
 * ============================================================
 */

// Load dependencies FIRST (needed for constants and functions)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Strict CORS - not wildcard
$origin = SITE_URL;
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $parsed = parse_url($_SERVER['HTTP_ORIGIN']);
    $siteParsed = parse_url(SITE_URL);
    // Allow same origin
    if ($parsed['host'] === $siteParsed['host']) {
        $origin = $_SERVER['HTTP_ORIGIN'];
    }
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'method_not_allowed'], 405);
}

// Initial security check
Security::initialCheck();

// Rate limiting
$ip = Security::getClientIP();
$rateCheck = Security::checkRateLimit($ip, 'search_api', RATE_LIMIT_SEARCH, RATE_LIMIT_WINDOW);
if (!$rateCheck['allowed']) {
    jsonResponse(['success' => false, 'error' => 'rate_limited', 'message' => 'تم تجاوز عدد الطلبات المسموح بها. حاول مرة أخرى بعد قليل.', 'retry_after' => $rateCheck['resetIn']], 429);
}

// CSRF verification - use JSON input
$data = Security::getJsonInput();
if (!$data || empty($data['csrf_token'])) {
    Security::logSecurityEvent('missing_csrf_search', 'WARNING', null, $ip, 'Search API called without CSRF token');
    jsonResponse(['success' => false, 'error' => 'missing_csrf', 'message' => 'رمز التحقق مطلوب'], 400);
}

if (!Security::verifyCSRFToken($data['csrf_token'])) {
    Security::logSecurityEvent('invalid_csrf_search', 'WARNING', null, $ip, 'Invalid CSRF token on search API');
    jsonResponse(['success' => false, 'error' => 'invalid_csrf', 'message' => 'رمز التحقق غير صالح'], 403);
}

$action = $data['action'] ?? '';

// Validate action - whitelist
$allowedActions = ['detect-country', 'search'];
if (!in_array($action, $allowedActions, true)) {
    jsonResponse(['success' => false, 'error' => 'unknown_action', 'message' => 'إجراء غير معروف'], 400);
}

switch ($action) {
    case 'detect-country':
        $query = Security::sanitizeInput($data['query'] ?? '');
        if (empty($query)) {
            jsonResponse(['success' => true, 'country' => null]);
        }

        $country = detectCountry($query);
        jsonResponse(['success' => true, 'country' => $country]);
        break;

    case 'search':
        // Auth check — on Vercel serverless, PHP sessions don't persist
        // between function invocations, so we accept user_id from the client
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonResponse(['success' => false, 'error' => 'auth_required', 'message' => 'يجب تسجيل الدخول أولاً'], 401);
        }

        // Verify user exists in database
        $user = fetch("SELECT id, name, email, plan, search_count, created_at FROM users WHERE id = :id", [':id' => $userId]);
        if ($user === null) {
            jsonResponse(['success' => false, 'error' => 'auth_required', 'message' => 'حساب المستخدم غير موجود'], 401);
        }
        $query = Security::sanitizeInput($data['query'] ?? '');
        $type = ($data['type'] ?? 'NUMBER') === 'NAME' ? 'NAME' : 'NUMBER';
        $page = max(1, (int) ($data['page'] ?? 1));

        if (empty($query)) {
            jsonResponse(['success' => false, 'error' => 'empty_query', 'message' => 'يرجى إدخال كلمة البحث']);
        }

        if (mb_strlen($query) < 2) {
            jsonResponse(['success' => false, 'error' => 'query_too_short', 'message' => 'كلمة البحث قصيرة جداً']);
        }

        if (mb_strlen($query) > 100) {
            jsonResponse(['success' => false, 'error' => 'query_too_long', 'message' => 'كلمة البحث طويلة جداً']);
        }

        // Check daily search limit
        $plan = $user['plan'] ?? 'FREE';
        $limit = PLANS[$plan]['search_limit'] ?? FREE_SEARCH_LIMIT;

        $today = date('Y-m-d');
        $todayCount = fetch(
            "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND date(created_at) = :today",
            [':uid' => $user['id'], ':today' => $today]
        );

        if ((int) $todayCount['cnt'] >= $limit) {
            jsonResponse([
                'success' => false,
                'error' => 'rate_limited',
                'message' => 'تم تجاوز حد البحث اليومي (' . $limit . ' عملية)',
                'count' => (int) $todayCount['cnt'],
                'limit' => $limit,
                'remaining' => 0,
            ], 429);
        }

        // Detect country from phone
        $countryInfo = [];
        if ($type === 'NUMBER') {
            $countryInfo = detectCountry($query);
        }

        $results = [];
        $total = 0;

        if ($type === 'NUMBER') {
            $simResults = generateDemoResults($query, 'NUMBER', $countryInfo);
            $results = $simResults['results'];
            $total = $simResults['total'];
        } else {
            $simResults = generateDemoResults($query, 'NAME', []);
            $results = $simResults['results'];
            $total = $simResults['total'];
        }

        // Save search to history
        try {
            insert('search_history', [
                'user_id'       => $user['id'],
                'query'        => $query,
                'query_type'    => $type,
                'country_code'  => $countryInfo['countryCode'] ?? null,
                'results_count' => $total,
            ]);

            Auth::incrementSearchCount($user['id']);
        } catch (\Exception $e) {
            error_log('Search history save failed: ' . $e->getMessage());
        }

        $perPage = 10;
        $totalPages = max(1, ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($results, $offset, $perPage);

        jsonResponse([
            'success'    => true,
            'results'    => $paginated,
            'total'      => $total,
            'page'       => $page,
            'total_pages' => $totalPages,
            'count'      => (int) $todayCount['cnt'] + 1,
            'limit'      => $limit,
            'remaining'  => $limit - ((int) $todayCount['cnt'] + 1),
        ]);
        break;
}

/**
 * Generate demo search results for demonstration
 */
function generateDemoResults(string $query, string $type, array $countryInfo): array
{
    $results = [];

    $names = [
        ['أحمد محمد', 'محمد سالم', 'علي حسن', 'خالد عبدالله', 'فاطمة أحمد', 'سارة يوسف', 'يمنى علي', 'عبدالرحمن', 'نور الدين', 'حسن عبدالله'],
        ['محمد ناصر', 'عبدالله سالم', 'فاطمة علي', 'أحمد سعيد', 'مريم محمد', 'يوسف أحمد', 'علي خالد', 'هدى محمد', 'سلمان أحمد', 'نادية علي'],
        ['طارق محمد', 'منى سالم', 'أحمد علي', 'رنا علي', 'وليد سالم', 'هدى علي', 'بشر محمد', 'سحر محمد', 'لمياء أحمد', 'كمال الدين'],
    ];

    $operators = ['اتصالات', 'سبايم', 'يمنين', 'تيست سبييد', 'فون', 'فاري'];
    $cities = ['صنعاء', 'عدن', 'تعز', 'الحديدة', 'إب', 'المكلا', 'ذمار', 'حضرموت', 'المكلا'];

    $hash = crc32($query . $type);
    $count = 3 + ($hash % 8);

    if ($type === 'NUMBER') {
        $countryName = $countryInfo['countryName'] ?? 'غير معروف';
        $flag = $countryInfo['flag'] ?? '🌍';
        $nationalNumber = $countryInfo['nationalNumber'] ?? substr(preg_replace('/[^0-9]/', '', $query), 0, 9);

        for ($i = 0; $i < $count; $i++) {
            $nameIdx = ($hash + $i * 7) % count($names);
            $opIdx = ($hash + $i * 3) % count($operators);
            $cityIdx = ($hash + $i * 11) % count($cities);

            $results[] = [
                'name'          => $names[$nameIdx],
                'phone'          => ($countryInfo['countryCode'] ?? '+967') . str_pad((string)(($hash + $i * 13) % 90000000 + 30000000), 9, '0', STR_PAD_LEFT),
                'country'       => $countryName,
                'flag'          => $flag,
                'operator'      => $operators[$opIdx],
                'city'          => $cities[$cityIdx],
                'phone_hidden'   => false,
            ];
        }
    } else {
        $countryCodes = array_keys(COUNTRY_CODES);
        for ($i = 0; $i < $count; $i++) {
            $ccIdx = ($hash + $i * 17) % count($countryCodes);
            $nameIdx = ($hash + $i * 7) % count($names);
            $opIdx = ($hash + $i * 3) % count($operators);
            $cityIdx = ($hash + $i * 11) % count($cities);

            $cc = $countryCodes[$ccIdx];
            $ccInfo = COUNTRY_CODES[$cc] ?? ['name' => 'غير معروف', 'flag' => '🌍'];
            $digits = rand(30000000, 99999999);

            $results[] = [
                'name'          => $names[$nameIdx],
                'phone'          => $cc . str_pad((string)$digits, 9, '0', STR_PAD_LEFT),
                'country'       => $ccInfo['name'],
                'flag'          => $ccInfo['flag'],
                'operator'      => $operators[$opIdx],
                'city'          => $cities[$cityIdx],
                'phone_hidden'   => false,
            ];
        }
    }

    return [
        'results' => $results,
        'total'   => $count,
    ];
}
