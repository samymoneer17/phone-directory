<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Search API Endpoint (Enhanced Security)
 * International Phone Directory
 * ============================================================
 *
 * Replaces the old generateDemoResults() with real carrier/region
 * lookups based on actual number prefix assignments, plus database
 * name searches.
 */

// Start output buffering to ensure pure JSON responses
ob_start();

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
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Auth-Token');
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
        // Auth check using token (Vercel serverless compatible)
        $user = Auth::getUserByRequestToken();
        if ($user === null) {
            jsonResponse(['success' => false, 'error' => 'auth_required', 'message' => 'يجب تسجيل الدخول أولاً'], 401);
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

        // Check daily search limit (best-effort, may fail if DB doesn't persist on Vercel)
        $plan = $user['plan'] ?? 'FREE';
        $limit = PLANS[$plan]['search_limit'] ?? FREE_SEARCH_LIMIT;

        try {
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
            $todaySearchCount = (int) $todayCount['cnt'];
        } catch (\Exception $e) {
            $todaySearchCount = 0; // Allow search if DB query fails
        }

        // Detect country from phone
        $countryInfo = [];
        if ($type === 'NUMBER') {
            $countryInfo = detectCountry($query);
        }

        $results = [];
        $total = 0;
        $externalSources = [];

        // البحث في قاعدة البيانات المحلية (النظام الأساسي)
        if ($type === 'NUMBER') {
            $lookup = performRealLookup($query, $countryInfo);
            $results = $lookup['results'];
            $total = $lookup['total'];
        } else {
            $lookup = searchByName($query);
            $results = $lookup['results'];
            $total = $lookup['total'];
        }

        // إضافة مصدر للنتائج المحلية
        foreach ($results as &$r) {
            if (!isset($r['source'])) {
                $r['source'] = 'local';
                $r['source_name'] = 'قاعدة البيانات';
                $r['source_icon'] = '🗄️';
            }
        }
        unset($r);

        // البحث الخارجي عبر الأدوات الثلاث (AkWhats + Loligram + Yemen Phone Book)
        try {
            require_once __DIR__ . '/../includes/external-search.php';
            $externalSearch = new ExternalSearch();
            $externalResults = $externalSearch->searchAll($query, $type, $countryInfo);

            if (!empty($externalResults['results'])) {
                // دمج النتائج الخارجية مع النتائج المحلية
                $seen = [];
                foreach ($results as $existing) {
                    $key = ($existing['phone'] ?? '') . '|' . ($existing['name'] ?? '');
                    $seen[$key] = true;
                }

                foreach ($externalResults['results'] as $extResult) {
                    $key = ($extResult['phone'] ?? '') . '|' . ($extResult['name'] ?? '');
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[] = $extResult;
                    }
                }

                $total = count($results);
            }

            $externalSources = $externalResults['sources'] ?? [];
        } catch (\Exception $e) {
            error_log('External search failed (non-critical): ' . $e->getMessage());
        }

        // Save search to history (best-effort, may fail if DB doesn't persist on Vercel)
        try {
            insert('search_history', [
                'user_id'       => $user['id'],
                'query'        => $query,
                'query_type'    => $type,
                'country_code'  => $countryInfo['countryCode'] ?? null,
                'results_count' => $total,
            ]);
        } catch (\Exception $e) {
            error_log('Search history save failed (non-critical): ' . $e->getMessage());
        }

        try {
            Auth::incrementSearchCount($user['id'], $user['plan'] ?? 'FREE');
        } catch (\Exception $e) {
            error_log('Search count increment failed (non-critical): ' . $e->getMessage());
        }

        $perPage = 10;
        $totalPages = max(1, ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($results, $offset, $perPage);

        $response = [
            'success'    => true,
            'results'    => $paginated,
            'total'      => $total,
            'page'       => $page,
            'total_pages' => $totalPages,
            'count'      => $todaySearchCount + 1,
            'limit'      => $limit,
            'remaining'  => $limit - ($todaySearchCount + 1),
        ];

        // Attach country_info for NUMBER searches
        if ($type === 'NUMBER' && !empty($countryInfo) && isset($countryInfo['countryCode'])) {
            $response['country_info'] = $countryInfo;
        }

        // Attach external search sources info
        if (!empty($externalSources)) {
            $response['external_sources'] = $externalSources;
        }

        jsonResponse($response);
        break;
}

// ============================================================
// CARRIER DATABASE - Real prefix-to-carrier mappings
// ============================================================

/**
 * Comprehensive carrier / operator database keyed by ISO country code.
 *
 * Each entry is an array of rules. A rule is:
 *   [
 *     'prefix'       => string  — first 1-3 digits of the national number
 *     'name'         => string  — carrier name (Arabic)
 *     'name_en'      => string  — carrier name (English)
 *     'type'         => string  — 'mobile' | 'landline' | 'voip'
 *     'region'       => string  — region / city name (Arabic), optional
 *   ]
 *
 * Rules are matched longest-prefix-first.
 */
function getCarrierDatabase(): array
{
    return [
        // ──────────────── Yemen (+967) ────────────────
        'YE' => [
            ['prefix' => '78', 'name' => 'MTN يمن', 'name_en' => 'MTN Yemen', 'type' => 'mobile', 'region' => 'صنعاء'],
            ['prefix' => '77', 'name' => 'يمن موبايل', 'name_en' => 'Yemen Mobile (Y)', 'type' => 'mobile', 'region' => 'صنعاء'],
            ['prefix' => '76', 'name' => 'يمن موبايل', 'name_en' => 'Yemen Mobile (Y)', 'type' => 'mobile', 'region' => 'عدن'],
            ['prefix' => '73', 'name' => 'سبأفون', 'name_en' => 'Sabafon', 'type' => 'mobile', 'region' => 'تعز / إب'],
            ['prefix' => '71', 'name' => 'MTN يمن', 'name_en' => 'MTN Yemen (Spacetel)', 'type' => 'mobile', 'region' => 'صنعاء'],
            ['prefix' => '70', 'name' => 'MTN يمن', 'name_en' => 'MTN Yemen', 'type' => 'mobile', 'region' => 'صنعاء'],
            ['prefix' => '75', 'name' => 'هاي', 'name_en' => 'Hi (E-Plus)', 'type' => 'mobile', 'region' => 'عدن / الحديدة'],
            ['prefix' => '74', 'name' => 'هاي', 'name_en' => 'Hi (E-Plus)', 'type' => 'mobile', 'region' => 'تعز'],
            ['prefix' => '33', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'صنعاء'],
            ['prefix' => '32', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'عدن'],
            ['prefix' => '31', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'تعز'],
            ['prefix' => '34', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'الحديدة'],
            ['prefix' => '35', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'إب'],
            ['prefix' => '36', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'حضرموت'],
            ['prefix' => '37', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'المكلا'],
            ['prefix' => '38', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'ذمار'],
            ['prefix' => '39', 'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'مأرب'],
            ['prefix' => '1',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'صنعاء'],
            ['prefix' => '2',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'عدن'],
            ['prefix' => '4',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'تعز'],
            ['prefix' => '5',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'الحديدة'],
            ['prefix' => '6',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'إب'],
            ['prefix' => '8',  'name' => 'يمن نت', 'name_en' => 'YemenNet', 'type' => 'landline', 'region' => 'حضرموت'],
        ],

        // ──────────────── Saudi Arabia (+966) ────────────────
        'SA' => [
            // STC mobile
            ['prefix' => '50', 'name' => 'STC', 'name_en' => 'STC (Saudi Telecom)', 'type' => 'mobile'],
            ['prefix' => '53', 'name' => 'STC', 'name_en' => 'STC (Saudi Telecom)', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'STC', 'name_en' => 'STC (Saudi Telecom)', 'type' => 'mobile'],
            ['prefix' => '56', 'name' => 'STC', 'name_en' => 'STC (Saudi Telecom)', 'type' => 'mobile'],
            ['prefix' => '54', 'name' => 'STC / زين', 'name_en' => 'STC / Zain KSA', 'type' => 'mobile'],
            // Mobily
            ['prefix' => '58', 'name' => 'موبايلي', 'name_en' => 'Mobily', 'type' => 'mobile'],
            ['prefix' => '59', 'name' => 'موبايلي', 'name_en' => 'Mobily', 'type' => 'mobile'],
            ['prefix' => '52', 'name' => 'موبايلي', 'name_en' => 'Mobily', 'type' => 'mobile'],
            // Zain
            ['prefix' => '49', 'name' => 'زين السعودية', 'name_en' => 'Zain KSA', 'type' => 'mobile'],
            ['prefix' => '40', 'name' => 'زين السعودية', 'name_en' => 'Zain KSA', 'type' => 'mobile'],
            // Landlines by region
            ['prefix' => '11', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'الرياض'],
            ['prefix' => '12', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'جدة / مكة المكرمة'],
            ['prefix' => '13', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'الدمام / المنطقة الشرقية'],
            ['prefix' => '14', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'المدينة المنورة'],
            ['prefix' => '16', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'القصيم / بريدة'],
            ['prefix' => '17', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'أبها / عسير'],
            ['prefix' => '19', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'تبوك'],
            ['prefix' => '38', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'نجران'],
            ['prefix' => '39', 'name' => 'STC', 'name_en' => 'STC (Fixed)', 'type' => 'landline', 'region' => 'جازان'],
        ],

        // ──────────────── UAE (+971) ────────────────
        'AE' => [
            ['prefix' => '50', 'name' => 'اتصالات', 'name_en' => 'Etisalat', 'type' => 'mobile'],
            ['prefix' => '52', 'name' => 'اتصالات', 'name_en' => 'Etisalat', 'type' => 'mobile'],
            ['prefix' => '54', 'name' => 'اتصالات', 'name_en' => 'Etisalat', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'اتصالات', 'name_en' => 'Etisalat', 'type' => 'mobile'],
            ['prefix' => '56', 'name' => 'دو', 'name_en' => 'du', 'type' => 'mobile'],
            ['prefix' => '58', 'name' => 'دو', 'name_en' => 'du', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '2', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'أبوظبي'],
            ['prefix' => '3', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'دبي'],
            ['prefix' => '4', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'دبي'],
            ['prefix' => '6', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'الشارقة / عجمان'],
            ['prefix' => '7', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'عجمان / أم القيوين'],
            ['prefix' => '9', 'name' => 'اتصالات', 'name_en' => 'Etisalat (Fixed)', 'type' => 'landline', 'region' => 'الفجيرة / رأس الخيمة'],
        ],

        // ──────────────── Egypt (+20) ────────────────
        'EG' => [
            ['prefix' => '10', 'name' => 'فودافون مصر', 'name_en' => 'Vodafone Egypt', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'اتصالات مصر', 'name_en' => 'Etisalat Egypt', 'type' => 'mobile'],
            ['prefix' => '12', 'name' => 'أورانج مصر', 'name_en' => 'Orange Egypt', 'type' => 'mobile'],
            ['prefix' => '15', 'name' => 'وي', 'name_en' => 'WE (Telecom Egypt)', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '2', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'القاهرة / الجيزة'],
            ['prefix' => '3', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الإسكندرية'],
            ['prefix' => '40', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الدقهلية / المنصورة'],
            ['prefix' => '48', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الغربية'],
            ['prefix' => '50', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الشرقية'],
            ['prefix' => '62', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'المنيا'],
            ['prefix' => '82', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'القليوبية'],
            ['prefix' => '84', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'سوهاج'],
            ['prefix' => '86', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الأقصر / أسوان'],
            ['prefix' => '88', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'بني سويف'],
            ['prefix' => '92', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الفيوم'],
            ['prefix' => '93', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'الغربية'],
            ['prefix' => '95', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'القليوبية'],
            ['prefix' => '96', 'name' => 'تيليوم مصر', 'name_en' => 'Telecom Egypt', 'type' => 'landline', 'region' => 'المنوفية'],
        ],

        // ──────────────── Iraq (+964) ────────────────
        'IQ' => [
            ['prefix' => '770', 'name' => 'زين العراق', 'name_en' => 'Zain Iraq', 'type' => 'mobile'],
            ['prefix' => '771', 'name' => 'زين العراق', 'name_en' => 'Zain Iraq', 'type' => 'mobile'],
            ['prefix' => '772', 'name' => 'زين العراق', 'name_en' => 'Zain Iraq', 'type' => 'mobile'],
            ['prefix' => '773', 'name' => 'آسيا سيل', 'name_en' => 'Asiacell', 'type' => 'mobile'],
            ['prefix' => '774', 'name' => 'آسيا سيل', 'name_en' => 'Asiacell', 'type' => 'mobile'],
            ['prefix' => '775', 'name' => 'آسيا سيل', 'name_en' => 'Asiacell', 'type' => 'mobile'],
            ['prefix' => '780', 'name' => 'كورك', 'name_en' => 'Korek Telecom', 'type' => 'mobile'],
            ['prefix' => '781', 'name' => 'كورك', 'name_en' => 'Korek Telecom', 'type' => 'mobile'],
            ['prefix' => '782', 'name' => 'كورك', 'name_en' => 'Korek Telecom', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '1', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بغداد'],
            ['prefix' => '40', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'البصرة'],
            ['prefix' => '41', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'نينوى / الموصل'],
            ['prefix' => '42', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'أربيل'],
            ['prefix' => '50', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'كركوك'],
            ['prefix' => '60', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'النجف'],
            ['prefix' => '66', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'كربلاء'],
        ],

        // ──────────────── Jordan (+962) ────────────────
        'JO' => [
            ['prefix' => '79', 'name' => 'زين الأردن', 'name_en' => 'Zain Jordan', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'أورانج الأردن', 'name_en' => 'Orange Jordan', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'أورانج الأردن', 'name_en' => 'Orange Jordan', 'type' => 'mobile'],
            ['prefix' => '75', 'name' => 'أمنية', 'name_en' => 'Umniah', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'أمنية', 'name_en' => 'Umniah', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'Xpress Telecom', 'name_en' => 'Xpress Telecom', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'عمان'],
            ['prefix' => '3', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'إربد'],
            ['prefix' => '5', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الزرقاء'],
            ['prefix' => '6', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'البلقاء / السلط'],
            ['prefix' => '7', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الكرك'],
        ],

        // ──────────────── Kuwait (+965) ────────────────
        'KW' => [
            ['prefix' => '9', 'name' => 'زين الكويت', 'name_en' => 'Zain Kuwait', 'type' => 'mobile'],
            ['prefix' => '6', 'name' => 'أوريدو', 'name_en' => 'Ooredoo Kuwait', 'type' => 'mobile'],
            ['prefix' => '5', 'name' => 'فيفا', 'name_en' => 'VIVA (STC Kuwait)', 'type' => 'mobile'],
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
            ['prefix' => '4', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
        ],

        // ──────────────── Bahrain (+973) ────────────────
        'BH' => [
            ['prefix' => '3', 'name' => 'بتلكو', 'name_en' => 'Batelco', 'type' => 'mobile'],
            ['prefix' => '66', 'name' => 'زين البحرين', 'name_en' => 'Zain Bahrain', 'type' => 'mobile'],
            ['prefix' => '38', 'name' => 'ستي كوم', 'name_en' => 'STC Bahrain', 'type' => 'mobile'],
            ['prefix' => '39', 'name' => 'فيفا البحرين', 'name_en' => 'VIVA Bahrain', 'type' => 'mobile'],
            ['prefix' => '1', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
        ],

        // ──────────────── Qatar (+974) ────────────────
        'QA' => [
            ['prefix' => '33', 'name' => 'أوريدو', 'name_en' => 'Ooredoo Qatar', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'فودافون قطر', 'name_en' => 'Vodafone Qatar', 'type' => 'mobile'],
            ['prefix' => '66', 'name' => 'أوريدو', 'name_en' => 'Ooredoo Qatar', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'فودافون قطر', 'name_en' => 'Vodafone Qatar', 'type' => 'mobile'],
            ['prefix' => '44', 'name' => 'فودافون قطر', 'name_en' => 'Vodafone Qatar', 'type' => 'mobile'],
            ['prefix' => '3', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
        ],

        // ──────────────── Oman (+968) ────────────────
        'OM' => [
            ['prefix' => '9', 'name' => 'أومنتل', 'name_en' => 'Omantel', 'type' => 'mobile'],
            ['prefix' => '7', 'name' => 'أوريدو عُمان', 'name_en' => 'Ooredoo Oman', 'type' => 'mobile'],
            ['prefix' => '2', 'name' => 'أومنتل', 'name_en' => 'Omantel (Fixed)', 'type' => 'landline'],
            ['prefix' => '25', 'name' => 'أومنتل', 'name_en' => 'Omantel (Fixed)', 'type' => 'landline', 'region' => 'مسقط'],
        ],

        // ──────────────── Lebanon (+961) ────────────────
        'LB' => [
            ['prefix' => '70', 'name' => 'ألفا', 'name_en' => 'Alfa', 'type' => 'mobile'],
            ['prefix' => '71', 'name' => 'تاتش', 'name_en' => 'Touch', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'ألفا', 'name_en' => 'Alfa', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'تاتش', 'name_en' => 'Touch', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'ألفا', 'name_en' => 'Alfa', 'type' => 'mobile'],
            ['prefix' => '81', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
            ['prefix' => '1', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بيروت'],
            ['prefix' => '4', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الجبل'],
            ['prefix' => '5', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الجنوب'],
            ['prefix' => '6', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بعلبك / الهرمل'],
            ['prefix' => '7', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الشمال'],
            ['prefix' => '8', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'البقاع'],
            ['prefix' => '9', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'عكار'],
        ],

        // ──────────────── Syria (+963) ────────────────
        'SY' => [
            ['prefix' => '9', 'name' => 'MTN سوريا', 'name_en' => 'MTN Syria', 'type' => 'mobile'],
            ['prefix' => '33', 'name' => 'سيريتل', 'name_en' => 'Syriatel', 'type' => 'mobile'],
            ['prefix' => '44', 'name' => 'سيريتل', 'name_en' => 'Syriatel', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'سيريتل', 'name_en' => 'Syriatel', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'دمشق'],
            ['prefix' => '13', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'حلب'],
            ['prefix' => '15', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'حمص'],
            ['prefix' => '16', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'اللاذقية'],
            ['prefix' => '22', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'ريف دمشق'],
        ],

        // ──────────────── Palestine (+970) ────────────────
        'PS' => [
            ['prefix' => '59', 'name' => 'جوال', 'name_en' => 'Jawwal', 'type' => 'mobile'],
            ['prefix' => '56', 'name' => 'واتنين', 'name_en' => 'Wataniya (Ooredoo)', 'type' => 'mobile'],
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'الضفة الغربية'],
            ['prefix' => '8', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'غزة'],
        ],

        // ──────────────── Morocco (+212) ────────────────
        'MA' => [
            ['prefix' => '6', 'name' => 'إنوي', 'name_en' => 'Inwi', 'type' => 'mobile'],
            ['prefix' => '67', 'name' => 'إنوي', 'name_en' => 'Inwi', 'type' => 'mobile'],
            ['prefix' => '69', 'name' => 'إنوي', 'name_en' => 'Inwi', 'type' => 'mobile'],
            ['prefix' => '66', 'name' => 'أورانج المغرب', 'name_en' => 'Orange Morocco', 'type' => 'mobile'],
            ['prefix' => '68', 'name' => 'أورانج المغرب', 'name_en' => 'Orange Morocco', 'type' => 'mobile'],
            ['prefix' => '61', 'name' => 'ماروك تيليكوم', 'name_en' => 'Maroc Telecom', 'type' => 'mobile'],
            ['prefix' => '62', 'name' => 'ماروك تيليكوم', 'name_en' => 'Maroc Telecom', 'type' => 'mobile'],
            ['prefix' => '63', 'name' => 'ماروك تيليكوم', 'name_en' => 'Maroc Telecom', 'type' => 'mobile'],
            ['prefix' => '64', 'name' => 'ماروك تيليكوم', 'name_en' => 'Maroc Telecom', 'type' => 'mobile'],
            ['prefix' => '65', 'name' => 'ماروك تيليكوم', 'name_en' => 'Maroc Telecom', 'type' => 'mobile'],
        ],

        // ──────────────── Algeria (+213) ────────────────
        'DZ' => [
            ['prefix' => '5', 'name' => 'موبيليس', 'name_en' => 'Mobilis', 'type' => 'mobile'],
            ['prefix' => '6', 'name' => 'جيزي', 'name_en' => 'Djezzy', 'type' => 'mobile'],
            ['prefix' => '7', 'name' => 'أوراسكوم', 'name_en' => 'Ooredoo Algeria', 'type' => 'mobile'],
        ],

        // ──────────────── Tunisia (+216) ────────────────
        'TN' => [
            ['prefix' => '2', 'name' => 'تونيزي تيليكوم', 'name_en' => 'Tunisie Telecom', 'type' => 'mobile'],
            ['prefix' => '5', 'name' => 'أورانج تونس', 'name_en' => 'Orange Tunisia', 'type' => 'mobile'],
            ['prefix' => '9', 'name' => 'أوريدو تونس', 'name_en' => 'Ooredoo Tunisia', 'type' => 'mobile'],
            ['prefix' => '4', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
        ],

        // ──────────────── Libya (+218) ────────────────
        'LY' => [
            ['prefix' => '9', 'name' => 'المدني', 'name_en' => 'Al-Madani', 'type' => 'mobile'],
            ['prefix' => '92', 'name' => 'المدني', 'name_en' => 'Libyana', 'type' => 'mobile'],
            ['prefix' => '94', 'name' => 'المدني', 'name_en' => 'Libyana', 'type' => 'mobile'],
            ['prefix' => '95', 'name' => 'المدني', 'name_en' => 'Al-Madani', 'type' => 'mobile'],
        ],

        // ──────────────── Sudan (+249) ────────────────
        'SD' => [
            ['prefix' => '9', 'name' => 'زين السودان', 'name_en' => 'Zain Sudan', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'زين السودان', 'name_en' => 'Zain Sudan', 'type' => 'mobile'],
            ['prefix' => '91', 'name' => 'MTN السودان', 'name_en' => 'MTN Sudan', 'type' => 'mobile'],
            ['prefix' => '92', 'name' => 'سوداتل', 'name_en' => 'Sudatel', 'type' => 'mobile'],
            ['prefix' => '93', 'name' => 'سوداتل', 'name_en' => 'Sudatel', 'type' => 'mobile'],
        ],

        // ──────────────── Turkey (+90) ────────────────
        'TR' => [
            ['prefix' => '50', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '51', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '53', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '54', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '56', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '57', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '58', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '59', 'name' => 'تورك تيليكوم', 'name_en' => 'Turk Telekom', 'type' => 'mobile'],
            ['prefix' => '52', 'name' => 'تيليا سونيرا', 'name_en' => 'TeliaSonera (Turkcell)', 'type' => 'mobile'],
            // Landline area codes
            ['prefix' => '212', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'إسطنبول (الأوروبي)'],
            ['prefix' => '216', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'إسطنبول (الآسيوي)'],
            ['prefix' => '232', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'إزمير'],
            ['prefix' => '312', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'أنقرة'],
            ['prefix' => '422', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'قونية'],
        ],

        // ──────────────── USA / Canada (+1) ────────────────
        'US' => [
            // Major area codes
            ['prefix' => '212', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'نيويورك'],
            ['prefix' => '213', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'لوس أنجلوس'],
            ['prefix' => '310', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'لوس أنجلوس'],
            ['prefix' => '415', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'سان فرانسيسكو'],
            ['prefix' => '202', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'واشنطن'],
            ['prefix' => '312', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'شيكاغو'],
            ['prefix' => '713', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'هيوستن'],
            ['prefix' => '305', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'ميامي'],
            ['prefix' => '617', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'بوسطن'],
            ['prefix' => '206', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'سياتل'],
            ['prefix' => '408', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'وادي السيليكون'],
            ['prefix' => '469', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'دالاس'],
            ['prefix' => '602', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'فينيكس'],
            ['prefix' => '303', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'دينفر'],
            ['prefix' => '404', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'أتلانتا'],
            ['prefix' => '215', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'فيلادلفيا'],
            ['prefix' => '510', 'name' => 'هاتف أمريكي', 'name_en' => 'US Phone', 'type' => 'landline', 'region' => 'أوكلاند'],
            // Canadian
            ['prefix' => '416', 'name' => 'هاتف كندي', 'name_en' => 'Canadian Phone', 'type' => 'landline', 'region' => 'تورنتو'],
            ['prefix' => '514', 'name' => 'هاتف كندي', 'name_en' => 'Canadian Phone', 'type' => 'landline', 'region' => 'مونتريال'],
            ['prefix' => '604', 'name' => 'هاتف كندي', 'name_en' => 'Canadian Phone', 'type' => 'landline', 'region' => 'فانكوفر'],
            ['prefix' => '403', 'name' => 'هاتف كندي', 'name_en' => 'Canadian Phone', 'type' => 'landline', 'region' => 'كالغاري'],
            ['prefix' => '613', 'name' => 'هاتف كندي', 'name_en' => 'Canadian Phone', 'type' => 'landline', 'region' => 'أوتاوا'],
        ],

        // ──────────────── UK (+44) ────────────────
        'GB' => [
            // Mobile prefixes
            ['prefix' => '71', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '72', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '74', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '75', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'هاتف بريطاني', 'name_en' => 'UK Mobile', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '20', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'لندن'],
            ['prefix' => '21', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'برمنغهام'],
            ['prefix' => '23', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'ساوثهامبتون'],
            ['prefix' => '28', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'بلفاست'],
            ['prefix' => '29', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'كارديف'],
            ['prefix' => '131', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'إدنبرة'],
            ['prefix' => '141', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'غلاسكو'],
            ['prefix' => '161', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'مانشستر'],
            ['prefix' => '151', 'name' => 'هاتف ثابت', 'name_en' => 'BT Fixed', 'type' => 'landline', 'region' => 'ليفربول'],
        ],

        // ──────────────── Germany (+49) ────────────────
        'DE' => [
            // Mobile prefixes
            ['prefix' => '151', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '152', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '160', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '161', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '162', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '163', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '170', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '171', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '172', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '175', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '176', 'name' => 'تي موبايل', 'name_en' => 'Telekom (T-Mobile)', 'type' => 'mobile'],
            ['prefix' => '159', 'name' => 'أورانج ألمانيا', 'name_en' => 'Orange (now O2)', 'type' => 'mobile'],
            ['prefix' => '176', 'name' => 'أورانج ألمانيا', 'name_en' => 'O2 Germany', 'type' => 'mobile'],
            ['prefix' => '155', 'name' => 'فودافون', 'name_en' => 'Vodafone Germany', 'type' => 'mobile'],
            ['prefix' => '156', 'name' => 'فودافون', 'name_en' => 'Vodafone Germany', 'type' => 'mobile'],
            ['prefix' => '157', 'name' => 'فودافون', 'name_en' => 'Vodafone Germany', 'type' => 'mobile'],
            ['prefix' => '178', 'name' => 'فودافون', 'name_en' => 'Vodafone Germany', 'type' => 'mobile'],
            ['prefix' => '179', 'name' => 'فودافون', 'name_en' => 'Vodafone Germany', 'type' => 'mobile'],
            // Landlines
            ['prefix' => '30', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'برلين'],
            ['prefix' => '89', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'ميونخ'],
            ['prefix' => '40', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'هامبورغ'],
            ['prefix' => '211', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'دوسلدورف'],
            ['prefix' => '69', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'فرانكفورت'],
            ['prefix' => '711', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'شتوتغارت'],
            ['prefix' => '621', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'مانهايم'],
            ['prefix' => '341', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'لايبزيغ'],
        ],

        // ──────────────── France (+33) ────────────────
        'FR' => [
            ['prefix' => '6', 'name' => 'هاتف فرنسي', 'name_en' => 'French Mobile', 'type' => 'mobile'],
            ['prefix' => '7', 'name' => 'هاتف فرنسي', 'name_en' => 'French Mobile', 'type' => 'mobile'],
            ['prefix' => '1', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'باريس / إيل دو فرانس'],
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'شمال غرب فرنسا'],
            ['prefix' => '3', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'شمال شرق فرنسا'],
            ['prefix' => '4', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'جنوب شرق فرنسا'],
            ['prefix' => '5', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'جنوب غرب فرنسا'],
        ],

        // ──────────────── Saudi Arabia override for +966 mobile VoIP detect ────────────────
        // Additional Middle Eastern countries
        'AF' => [
            ['prefix' => '7', 'name' => 'روشان', 'name_en' => 'Roshan', 'type' => 'mobile'],
            ['prefix' => '70', 'name' => 'أفغان ويرلس', 'name_en' => 'Afghan Wireless', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'إتحاد', 'name_en' => 'Etisalat Afghanistan', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'MTN أفغانستان', 'name_en' => 'MTN Afghanistan', 'type' => 'mobile'],
        ],

        'PK' => [
            ['prefix' => '30', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '31', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '32', 'name' => 'واريد', 'name_en' => 'Warid', 'type' => 'mobile'],
            ['prefix' => '33', 'name' => 'أوفو', 'name_en' => 'Ufone', 'type' => 'mobile'],
            ['prefix' => '34', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '35', 'name' => 'سكي تيل', 'name_en' => 'Scom', 'type' => 'mobile'],
            ['prefix' => '300', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '301', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '303', 'name' => 'أوفو', 'name_en' => 'Ufone', 'type' => 'mobile'],
            ['prefix' => '304', 'name' => 'سكي تيل', 'name_en' => 'Scom', 'type' => 'mobile'],
            ['prefix' => '306', 'name' => 'زون', 'name_en' => 'Zong', 'type' => 'mobile'],
            ['prefix' => '308', 'name' => 'واريد', 'name_en' => 'Warid', 'type' => 'mobile'],
            ['prefix' => '309', 'name' => 'واريد', 'name_en' => 'Warid', 'type' => 'mobile'],
        ],

        'IN' => [
            ['prefix' => '70', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '72', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '74', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '75', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '80', 'name' => 'BSNL', 'name_en' => 'BSNL', 'type' => 'mobile'],
            ['prefix' => '81', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '82', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '83', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '84', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '85', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '86', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '87', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '88', 'name' => 'Vodafone Idea', 'name_en' => 'Vodafone Idea (Vi)', 'type' => 'mobile'],
            ['prefix' => '89', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '91', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '92', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '93', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '94', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '95', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '96', 'name' => 'Jio', 'name_en' => 'Reliance Jio', 'type' => 'mobile'],
            ['prefix' => '97', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '98', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
            ['prefix' => '99', 'name' => 'Airtel', 'name_en' => 'Bharti Airtel', 'type' => 'mobile'],
        ],

        'NG' => [
            ['prefix' => '701', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '702', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '703', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '704', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '705', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '706', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '708', 'name' => 'Airtel نيجيريا', 'name_en' => 'Airtel Nigeria', 'type' => 'mobile'],
            ['prefix' => '802', 'name' => 'Airtel نيجيريا', 'name_en' => 'Airtel Nigeria', 'type' => 'mobile'],
            ['prefix' => '803', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '804', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '805', 'name' => 'Glo نيجيريا', 'name_en' => 'Globacom', 'type' => 'mobile'],
            ['prefix' => '806', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '807', 'name' => 'Glo نيجيريا', 'name_en' => 'Globacom', 'type' => 'mobile'],
            ['prefix' => '808', 'name' => 'Glo نيجيريا', 'name_en' => 'Globacom', 'type' => 'mobile'],
            ['prefix' => '809', 'name' => 'Glo نيجيريا', 'name_en' => 'Globacom', 'type' => 'mobile'],
            ['prefix' => '810', 'name' => 'Airtel نيجيريا', 'name_en' => 'Airtel Nigeria', 'type' => 'mobile'],
            ['prefix' => '811', 'name' => 'Airtel نيجيريا', 'name_en' => 'Airtel Nigeria', 'type' => 'mobile'],
            ['prefix' => '812', 'name' => 'Airtel نيجيريا', 'name_en' => 'Airtel Nigeria', 'type' => 'mobile'],
            ['prefix' => '813', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '814', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '815', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '816', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '817', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '818', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '819', 'name' => 'MTN نيجيريا', 'name_en' => 'MTN Nigeria', 'type' => 'mobile'],
            ['prefix' => '908', 'name' => '9mobile', 'name_en' => '9mobile', 'type' => 'mobile'],
            ['prefix' => '909', 'name' => '9mobile', 'name_en' => '9mobile', 'type' => 'mobile'],
        ],

        // ──────────────── Somalia (+252) ────────────────
        'SO' => [
            ['prefix' => '61', 'name' => 'تلكوم', 'name_en' => 'Somtel', 'type' => 'mobile'],
            ['prefix' => '62', 'name' => 'سومتيل', 'name_en' => 'Somtel', 'type' => 'mobile'],
            ['prefix' => '63', 'name' => 'ناشيونال لينك', 'name_en' => 'NationLink', 'type' => 'mobile'],
            ['prefix' => '65', 'name' => 'هورنتيليكوم', 'name_en' => 'Hormuud Telecom', 'type' => 'mobile'],
            ['prefix' => '66', 'name' => 'هورنتيليكوم', 'name_en' => 'Hormuud Telecom', 'type' => 'mobile'],
            ['prefix' => '67', 'name' => 'تلكوم', 'name_en' => 'Telesom', 'type' => 'mobile'],
            ['prefix' => '68', 'name' => 'سومالiland تيليكوم', 'name_en' => 'Somaliland Telecom', 'type' => 'mobile'],
            ['prefix' => '69', 'name' => 'جنتل تيليكوم', 'name_en' => 'Golis Telecom', 'type' => 'mobile'],
            ['prefix' => '70', 'name' => 'سوم نت', 'name_en' => 'SOMNET', 'type' => 'mobile'],
            ['prefix' => '71', 'name' => 'ناشيونال لينك', 'name_en' => 'NationLink', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'إتيسالات الصومال', 'name_en' => 'Somali Telco', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'إتيسالات الصومال', 'name_en' => 'Somali Telco', 'type' => 'mobile'],
        ],

        // ──────────────── Russia (+7) ────────────────
        'RU' => [
            ['prefix' => '9', 'name' => 'هاتف روسي', 'name_en' => 'Russian Mobile', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'بيلاين', 'name_en' => 'Beeline', 'type' => 'mobile'],
            ['prefix' => '91', 'name' => 'ميغافون', 'name_en' => 'Megafon', 'type' => 'mobile'],
            ['prefix' => '92', 'name' => 'ميغافون', 'name_en' => 'Megafon', 'type' => 'mobile'],
            ['prefix' => '93', 'name' => 'إم تي إس', 'name_en' => 'MTS', 'type' => 'mobile'],
            ['prefix' => '94', 'name' => 'تي تي كي', 'name_en' => 'Tele2 Russia', 'type' => 'mobile'],
            ['prefix' => '95', 'name' => 'تي تي كي', 'name_en' => 'Tele2 Russia', 'type' => 'mobile'],
            ['prefix' => '96', 'name' => 'بيلاين', 'name_en' => 'Beeline', 'type' => 'mobile'],
            ['prefix' => '97', 'name' => 'ميغافون', 'name_en' => 'Megafon', 'type' => 'mobile'],
            ['prefix' => '98', 'name' => 'إم تي إس', 'name_en' => 'MTS', 'type' => 'mobile'],
            ['prefix' => '99', 'name' => 'بيلاين', 'name_en' => 'Beeline', 'type' => 'mobile'],
            ['prefix' => '495', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'موسكو'],
            ['prefix' => '812', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'سان بطرسبرغ'],
        ],

        // ──────────────── Ukraine (+380) ────────────────
        'UA' => [
            ['prefix' => '50', 'name' => 'كييفستار', 'name_en' => 'Kyivstar', 'type' => 'mobile'],
            ['prefix' => '66', 'name' => 'كييفستار', 'name_en' => 'Kyivstar', 'type' => 'mobile'],
            ['prefix' => '67', 'name' => 'كييفستار', 'name_en' => 'Kyivstar', 'type' => 'mobile'],
            ['prefix' => '68', 'name' => 'كييفستار', 'name_en' => 'Kyivstar', 'type' => 'mobile'],
            ['prefix' => '39', 'name' => 'كومستار', 'name_en' => 'Comstar', 'type' => 'mobile'],
            ['prefix' => '63', 'name' => 'لايفسيل', 'name_en' => 'Lifecell', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'لايفسيل', 'name_en' => 'Lifecell', 'type' => 'mobile'],
            ['prefix' => '93', 'name' => 'لايفسيل', 'name_en' => 'Lifecell', 'type' => 'mobile'],
            ['prefix' => '95', 'name' => 'فودافون أوكرانيا', 'name_en' => 'Vodafone Ukraine', 'type' => 'mobile'],
            ['prefix' => '99', 'name' => 'فودافون أوكرانيا', 'name_en' => 'Vodafone Ukraine', 'type' => 'mobile'],
        ],

        // ──────────────── China (+86) ────────────────
        'CN' => [
            ['prefix' => '130', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '131', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '132', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '133', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '134', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '135', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '136', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '137', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '138', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '139', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '145', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '147', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '149', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '150', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '151', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '152', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '155', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '156', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '157', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '158', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '159', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '166', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '170', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '171', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '172', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '173', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '175', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '176', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '177', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '178', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '180', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '181', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '182', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '183', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '184', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '185', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '186', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '187', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '188', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '189', 'name' => 'تشاينا تيليكوم', 'name_en' => 'China Telecom', 'type' => 'mobile'],
            ['prefix' => '191', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '198', 'name' => 'تشاينا موبايل', 'name_en' => 'China Mobile', 'type' => 'mobile'],
            ['prefix' => '199', 'name' => 'تشاينا يونيكوم', 'name_en' => 'China Unicom', 'type' => 'mobile'],
            ['prefix' => '10', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بكين'],
            ['prefix' => '20', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'قوانغتشو'],
            ['prefix' => '21', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'شنغهاي'],
        ],

        // ──────────────── Japan (+81) ────────────────
        'JP' => [
            ['prefix' => '70', 'name' => 'هاتف ياباني', 'name_en' => 'Japanese Mobile', 'type' => 'mobile'],
            ['prefix' => '80', 'name' => 'هاتف ياباني', 'name_en' => 'Japanese Mobile', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'هاتف ياباني', 'name_en' => 'Japanese Mobile', 'type' => 'mobile'],
        ],

        // ──────────────── South Korea (+82) ────────────────
        'KR' => [
            ['prefix' => '10', 'name' => 'SK تيليكوم', 'name_en' => 'SK Telecom', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'SK تيليكوم', 'name_en' => 'SK Telecom', 'type' => 'mobile'],
            ['prefix' => '12', 'name' => 'KT', 'name_en' => 'KT Corporation', 'type' => 'mobile'],
            ['prefix' => '15', 'name' => 'كيو بلس', 'name_en' => 'Korea Telecom (KPU)', 'type' => 'mobile'],
            ['prefix' => '16', 'name' => 'إل جي يو بلس', 'name_en' => 'LG U+', 'type' => 'mobile'],
            ['prefix' => '17', 'name' => 'إل جي يو بلس', 'name_en' => 'LG U+', 'type' => 'mobile'],
            ['prefix' => '18', 'name' => 'إل جي يو بلس', 'name_en' => 'LG U+', 'type' => 'mobile'],
            ['prefix' => '19', 'name' => 'إل جي يو بلس', 'name_en' => 'LG U+', 'type' => 'mobile'],
        ],

        // ──────────────── Indonesia (+62) ────────────────
        'ID' => [
            ['prefix' => '811', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '812', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '813', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '821', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '822', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '823', 'name' => 'تيلكومسيل', 'name_en' => 'Telkomsel', 'type' => 'mobile'],
            ['prefix' => '852', 'name' => 'إكسل كوميندو', 'name_en' => 'XL Axiata', 'type' => 'mobile'],
            ['prefix' => '853', 'name' => 'إكسل كوميندو', 'name_en' => 'XL Axiata', 'type' => 'mobile'],
            ['prefix' => '851', 'name' => 'إندوسات', 'name_en' => 'Indosat Ooredoo', 'type' => 'mobile'],
            ['prefix' => '814', 'name' => 'إندوسات', 'name_en' => 'Indosat Ooredoo', 'type' => 'mobile'],
            ['prefix' => '815', 'name' => 'إندوسات', 'name_en' => 'Indosat Ooredoo', 'type' => 'mobile'],
            ['prefix' => '816', 'name' => 'إندوسات', 'name_en' => 'Indosat Ooredoo', 'type' => 'mobile'],
            ['prefix' => '855', 'name' => 'سمارتفن', 'name_en' => 'Smartfren', 'type' => 'mobile'],
            ['prefix' => '856', 'name' => 'سمارتفن', 'name_en' => 'Smartfren', 'type' => 'mobile'],
            ['prefix' => '857', 'name' => 'سمارتفن', 'name_en' => 'Smartfren', 'type' => 'mobile'],
            ['prefix' => '858', 'name' => 'سمارتفن', 'name_en' => 'Smartfren', 'type' => 'mobile'],
        ],

        // ──────────────── Bangladesh (+880) ────────────────
        'BD' => [
            ['prefix' => '13', 'name' => 'جي بي', 'name_en' => 'Grameenphone', 'type' => 'mobile'],
            ['prefix' => '14', 'name' => 'جي بي', 'name_en' => 'Grameenphone', 'type' => 'mobile'],
            ['prefix' => '15', 'name' => 'تي تي كي', 'name_en' => 'Teletalk', 'type' => 'mobile'],
            ['prefix' => '16', 'name' => 'إيرتيل', 'name_en' => 'Airtel Bangladesh', 'type' => 'mobile'],
            ['prefix' => '17', 'name' => 'جي بي', 'name_en' => 'Grameenphone', 'type' => 'mobile'],
            ['prefix' => '18', 'name' => 'رابي', 'name_en' => 'Robi', 'type' => 'mobile'],
            ['prefix' => '19', 'name' => 'بأنغلalink', 'name_en' => 'Banglalink', 'type' => 'mobile'],
        ],

        // ──────────────── Philippines (+63) ────────────────
        'PH' => [
            ['prefix' => '817', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '906', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '915', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '916', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '917', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '926', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '927', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '935', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '936', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '937', 'name' => 'جلوب', 'name_en' => 'Globe Telecom', 'type' => 'mobile'],
            ['prefix' => '945', 'name' => 'سمارت', 'name_en' => 'Smart Communications', 'type' => 'mobile'],
            ['prefix' => '946', 'name' => 'سمارت', 'name_en' => 'Smart Communications', 'type' => 'mobile'],
            ['prefix' => '947', 'name' => 'سمارت', 'name_en' => 'Smart Communications', 'type' => 'mobile'],
            ['prefix' => '973', 'name' => 'سمارت', 'name_en' => 'Smart Communications', 'type' => 'mobile'],
            ['prefix' => '974', 'name' => 'سمارت', 'name_en' => 'Smart Communications', 'type' => 'mobile'],
            ['prefix' => '997', 'name' => 'ديجيتل تيليكوم', 'name_en' => 'Dito Telecommunity', 'type' => 'mobile'],
        ],

        // ──────────────── Brazil (+55) ────────────────
        'BR' => [
            ['prefix' => '11', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'ساو باولو'],
            ['prefix' => '21', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'ريو دي جانيرو'],
            ['prefix' => '31', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'بيلو هوريزونتي'],
            ['prefix' => '41', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'كوريتيبا'],
            ['prefix' => '51', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'بورتو أليغري'],
            ['prefix' => '61', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'برازيليا'],
            ['prefix' => '71', 'name' => 'هاتف برازيلي', 'name_en' => 'Brazilian Phone', 'type' => 'mobile', 'region' => 'سلفادور'],
        ],

        // ──────────────── Italy (+39) ────────────────
        'IT' => [
            ['prefix' => '30', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '31', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '32', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '33', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '34', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '35', 'name' => 'تي موبايل إيطاليا', 'name_en' => 'TIM', 'type' => 'mobile'],
            ['prefix' => '36', 'name' => 'فودافون إيطاليا', 'name_en' => 'Vodafone Italy', 'type' => 'mobile'],
            ['prefix' => '37', 'name' => 'ويند', 'name_en' => 'WindTre', 'type' => 'mobile'],
            ['prefix' => '38', 'name' => 'ويند', 'name_en' => 'WindTre', 'type' => 'mobile'],
            ['prefix' => '39', 'name' => 'إيلياد', 'name_en' => 'Iliad', 'type' => 'mobile'],
            ['prefix' => '02', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'ميلانو'],
            ['prefix' => '06', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'روما'],
            ['prefix' => '011', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'تورينو'],
        ],

        // ──────────────── Spain (+34) ────────────────
        'ES' => [
            ['prefix' => '6', 'name' => 'موفيل إسبانيا', 'name_en' => 'Spanish Mobile', 'type' => 'mobile'],
            ['prefix' => '7', 'name' => 'هاتف إسباني', 'name_en' => 'Spanish Phone', 'type' => 'mobile'],
            ['prefix' => '91', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'مدريد'],
            ['prefix' => '93', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'برشلونة'],
            ['prefix' => '95', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'إشبيلية'],
            ['prefix' => '96', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'فالنسيا'],
        ],

        // ──────────────── Mexico (+52) ────────────────
        'MX' => [
            ['prefix' => '1', 'name' => 'هاتف مكسيكي', 'name_en' => 'Mexican Mobile', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'هاتف مكسيكي', 'name_en' => 'Telcel', 'type' => 'mobile', 'region' => 'مكسيكو سيتي'],
            ['prefix' => '33', 'name' => 'هاتف مكسيكي', 'name_en' => 'Telcel', 'type' => 'mobile', 'region' => 'غوادالاخارا'],
            ['prefix' => '81', 'name' => 'هاتف مكسيكي', 'name_en' => 'Telcel', 'type' => 'mobile', 'region' => 'مونتيري'],
        ],

        // ──────────────── Argentina (+54) ────────────────
        'AR' => [
            ['prefix' => '15', 'name' => 'هاتف أرجنتيني', 'name_en' => 'Argentine Mobile', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بوينس آيرس'],
            ['prefix' => '221', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'لا بلاتا'],
            ['prefix' => '341', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'روساريو'],
            ['prefix' => '351', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'كوردوبا'],
        ],

        // ──────────────── South Africa (+27) ────────────────
        'ZA' => [
            ['prefix' => '6', 'name' => 'هاتف جنوب أفريقي', 'name_en' => 'South African Mobile', 'type' => 'mobile'],
            ['prefix' => '71', 'name' => 'فوداكوم', 'name_en' => 'Vodacom', 'type' => 'mobile'],
            ['prefix' => '72', 'name' => 'فوداكوم', 'name_en' => 'Vodacom', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'MTN جنوب أفريقيا', 'name_en' => 'MTN South Africa', 'type' => 'mobile'],
            ['prefix' => '74', 'name' => 'MTN جنوب أفريقيا', 'name_en' => 'MTN South Africa', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'MTN جنوب أفريقيا', 'name_en' => 'MTN South Africa', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'سيل سي', 'name_en' => 'Cell C', 'type' => 'mobile'],
            ['prefix' => '8', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline'],
        ],

        // ──────────────── Kenya (+254) ────────────────
        'KE' => [
            ['prefix' => '10', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '70', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '71', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '72', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '73', 'name' => 'أيرتل كينيا', 'name_en' => 'Airtel Kenya', 'type' => 'mobile'],
            ['prefix' => '74', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '75', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'أيرتل كينيا', 'name_en' => 'Airtel Kenya', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'سافاري كوم', 'name_en' => 'Safaricom', 'type' => 'mobile'],
        ],

        // ──────────────── Saudi Arabia again — catch all mobile prefix ────────────────
        // These are already above, just noting no duplicate needed

        // ──────────────── Thailand (+66) ────────────────
        'TH' => [
            ['prefix' => '6', 'name' => 'هاتف تايلاندي', 'name_en' => 'Thai Mobile', 'type' => 'mobile'],
            ['prefix' => '8', 'name' => 'هاتف تايلاندي', 'name_en' => 'Thai Mobile', 'type' => 'mobile'],
            ['prefix' => '9', 'name' => 'هاتف تايلاندي', 'name_en' => 'Thai Mobile', 'type' => 'mobile'],
            ['prefix' => '81', 'name' => 'هاتف تايلاندي', 'name_en' => 'Thai Mobile', 'type' => 'mobile'],
        ],

        // ──────────────── Vietnam (+84) ────────────────
        'VN' => [
            ['prefix' => '31', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '32', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '33', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '34', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '35', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '36', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '37', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '38', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '39', 'name' => 'فييتتل', 'name_en' => 'Viettel', 'type' => 'mobile'],
            ['prefix' => '86', 'name' => 'فينافون', 'name_en' => 'Vinaphone', 'type' => 'mobile'],
            ['prefix' => '87', 'name' => 'فينافون', 'name_en' => 'Vinaphone', 'type' => 'mobile'],
            ['prefix' => '88', 'name' => 'موبيفون', 'name_en' => 'Mobifone', 'type' => 'mobile'],
            ['prefix' => '89', 'name' => 'موبيفون', 'name_en' => 'Mobifone', 'type' => 'mobile'],
            ['prefix' => '90', 'name' => 'موبيفون', 'name_en' => 'Mobifone', 'type' => 'mobile'],
            ['prefix' => '91', 'name' => 'موبيفون', 'name_en' => 'Mobifone', 'type' => 'mobile'],
            ['prefix' => '92', 'name' => 'فيتنام موبايل', 'name_en' => 'Vietnamobile', 'type' => 'mobile'],
            ['prefix' => '93', 'name' => 'بي فون', 'name_en' => 'Beeline VN', 'type' => 'mobile'],
            ['prefix' => '94', 'name' => 'فينافون', 'name_en' => 'Vinaphone', 'type' => 'mobile'],
            ['prefix' => '95', 'name' => 'سفون', 'name_en' => 'SFone', 'type' => 'mobile'],
            ['prefix' => '96', 'name' => 'فيتنام موبايل', 'name_en' => 'Vietnamobile', 'type' => 'mobile'],
            ['prefix' => '97', 'name' => 'فيتنام موبايل', 'name_en' => 'Vietnamobile', 'type' => 'mobile'],
            ['prefix' => '98', 'name' => 'فيتنام موبايل', 'name_en' => 'Vietnamobile', 'type' => 'mobile'],
            ['prefix' => '99', 'name' => 'جي موبايل', 'name_en' => 'Gmobile', 'type' => 'mobile'],
        ],

        // ──────────────── Malaysia (+60) ────────────────
        'MY' => [
            ['prefix' => '10', 'name' => 'ديجي', 'name_en' => 'DiGi', 'type' => 'mobile'],
            ['prefix' => '11', 'name' => 'تي تي كي', 'name_en' => 'Telekom Malaysia', 'type' => 'mobile'],
            ['prefix' => '12', 'name' => 'ماكسيس', 'name_en' => 'Maxis', 'type' => 'mobile'],
            ['prefix' => '13', 'name' => 'سيلكوم', 'name_en' => 'Celcom', 'type' => 'mobile'],
            ['prefix' => '14', 'name' => 'ديجي', 'name_en' => 'DiGi', 'type' => 'mobile'],
            ['prefix' => '15', 'name' => 'تي تي كي', 'name_en' => 'Telekom Malaysia', 'type' => 'mobile'],
            ['prefix' => '16', 'name' => 'ديجي', 'name_en' => 'DiGi', 'type' => 'mobile'],
            ['prefix' => '17', 'name' => 'ماكسيس', 'name_en' => 'Maxis', 'type' => 'mobile'],
            ['prefix' => '18', 'name' => 'يو موبايل', 'name_en' => 'U Mobile', 'type' => 'mobile'],
            ['prefix' => '19', 'name' => 'سيلكوم', 'name_en' => 'Celcom', 'type' => 'mobile'],
        ],

        // ──────────────── Colombia (+57) ────────────────
        'CO' => [
            ['prefix' => '30', 'name' => 'كومفيل', 'name_en' => 'Comcel (Claro)', 'type' => 'mobile'],
            ['prefix' => '31', 'name' => 'كومفيل', 'name_en' => 'Comcel (Claro)', 'type' => 'mobile'],
            ['prefix' => '32', 'name' => 'كومفيل', 'name_en' => 'Comcel (Claro)', 'type' => 'mobile'],
            ['prefix' => '33', 'name' => 'تيغو', 'name_en' => 'Tigo', 'type' => 'mobile'],
            ['prefix' => '34', 'name' => 'تيغو', 'name_en' => 'Tigo', 'type' => 'mobile'],
            ['prefix' => '35', 'name' => 'موفيستار', 'name_en' => 'Movistar', 'type' => 'mobile'],
            ['prefix' => '1', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بوغوتا'],
        ],

        // ──────────────── Australia (+61) ────────────────
        'AU' => [
            ['prefix' => '4', 'name' => 'هاتف أسترالي', 'name_en' => 'Australian Mobile', 'type' => 'mobile'],
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'سيدني / نيو ساوث ويلز'],
            ['prefix' => '3', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'ملبورن / فيكتوريا'],
            ['prefix' => '7', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بريزبن / كوينزلاند'],
            ['prefix' => '8', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'أديلايد / جنوب أستراليا'],
        ],

        // ──────────────── Israel (+972) ────────────────
        'IL' => [
            ['prefix' => '50', 'name' => 'بيلفون', 'name_en' => 'Pelephone', 'type' => 'mobile'],
            ['prefix' => '52', 'name' => 'سيلكوم', 'name_en' => 'Cellcom', 'type' => 'mobile'],
            ['prefix' => '53', 'name' => 'سيلكوم', 'name_en' => 'Cellcom', 'type' => 'mobile'],
            ['prefix' => '54', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Hot Mobile', 'type' => 'mobile'],
            ['prefix' => '55', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Hot Mobile', 'type' => 'mobile'],
            ['prefix' => '58', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Golan Telecom', 'type' => 'mobile'],
            ['prefix' => '59', 'name' => 'هاتف إسرائيلي', 'name_en' => 'HOT Mobile', 'type' => 'mobile'],
            ['prefix' => '76', 'name' => 'جوال إسرائيلي', 'name_en' => 'Partner (Orange)', 'type' => 'mobile'],
            ['prefix' => '77', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Hot Mobile', 'type' => 'mobile'],
            ['prefix' => '78', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Cellcom', 'type' => 'mobile'],
            ['prefix' => '79', 'name' => 'هاتف إسرائيلي', 'name_en' => 'Golan Telecom', 'type' => 'mobile'],
            ['prefix' => '2', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'القدس'],
            ['prefix' => '3', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'تل أبيب'],
            ['prefix' => '4', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'حيفا'],
            ['prefix' => '8', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'بير شيفا'],
            ['prefix' => '9', 'name' => 'هاتف ثابت', 'name_en' => 'Fixed Line', 'type' => 'landline', 'region' => 'المنطقة الشمالية'],
        ],
    ];
}

/**
 * Look up carrier info for a phone number using prefix matching.
 *
 * Returns:
 *   [
 *     'name'    => string — carrier name (Arabic)
 *     'name_en' => string — carrier name (English)
 *     'type'    => string — 'mobile' | 'landline' | 'voip'
 *     'region'  => string — region/city (Arabic), or '' if unknown
 *   ]
 */
function getCarrierInfo(string $phoneNumber, array $countryInfo): array
{
    $default = [
        'name'    => 'غير معروف',
        'name_en' => 'Unknown',
        'type'    => 'unknown',
        'region'  => '',
    ];

    $isoCode = $countryInfo['isoCode'] ?? '';
    $nationalNumber = $countryInfo['nationalNumber'] ?? '';

    if (empty($isoCode) || empty($nationalNumber)) {
        return $default;
    }

    $db = getCarrierDatabase();
    if (!isset($db[$isoCode])) {
        return $default;
    }

    $rules = $db[$isoCode];

    // Sort rules by prefix length DESC for longest-prefix matching
    usort($rules, fn($a, $b) => strlen($b['prefix']) - strlen($a['prefix']));

    foreach ($rules as $rule) {
        if (str_starts_with($nationalNumber, $rule['prefix'])) {
            return [
                'name'    => $rule['name'],
                'name_en' => $rule['name_en'],
                'type'    => $rule['type'],
                'region'  => $rule['region'] ?? '',
            ];
        }
    }

    return $default;
}

/**
 * Validate a phone number and return validity info.
 *
 * Expected lengths (national number only, no country code):
 *   Yemen:    9 digits (mobile) or 6-7 digits (landline)
 *   Saudi:    9 digits (mobile) or 7-8 digits (landline)
 *   UAE:      9 digits (mobile) or 7-9 digits (landline)
 *   Egypt:    10 digits (mobile) or 8 digits (landline)
 *   Iraq:     10 digits (mobile)
 *   USA/CA:   10 digits
 *   UK:       10-11 digits
 *   Germany:  10-11 digits
 *   General:  7-15 digits
 */
function validatePhoneNumber(string $phoneNumber, array $countryInfo): array
{
    $nationalNumber = $countryInfo['nationalNumber'] ?? '';
    $isoCode = $countryInfo['isoCode'] ?? '';
    $len = strlen($nationalNumber);

    if ($len < 5) {
        return ['valid' => false, 'reason' => 'الرقم قصير جداً'];
    }

    if ($len > 15) {
        return ['valid' => false, 'reason' => 'الرقم طويل جداً'];
    }

    // Check that the national number is all digits
    if (!ctype_digit($nationalNumber)) {
        return ['valid' => false, 'reason' => 'الرقم يحتوي على أحرف غير صالحة'];
    }

    // Country-specific length checks
    $lengths = [
        'YE' => [[9, 'mobile'], [6, 'landline'], [7, 'landline'], [8, 'landline']],
        'SA' => [[9, 'mobile'], [7, 'landline'], [8, 'landline']],
        'AE' => [[9, 'mobile'], [7, 'landline'], [8, 'landline'], [9, 'landline']],
        'EG' => [[10, 'mobile'], [8, 'landline']],
        'IQ' => [[10, 'mobile']],
        'JO' => [[9, 'mobile'], [7, 'landline'], [8, 'landline']],
        'KW' => [[8, 'mobile'], [7, 'landline'], [8, 'landline']],
        'US' => [[10, 'mobile'], [10, 'landline']],
        'GB' => [[10, 'mobile'], [10, 'landline'], [11, 'landline']],
        'DE' => [[10, 'mobile'], [11, 'mobile'], [6, 'landline'], [7, 'landline'], [8, 'landline'], [9, 'landline'], [10, 'landline'], [11, 'landline']],
        'TR' => [[10, 'mobile'], [7, 'landline'], [10, 'landline']],
        'FR' => [[9, 'mobile'], [9, 'landline']],
        'IT' => [[9, 'mobile'], [10, 'mobile'], [6, 'landline'], [10, 'landline']],
        'RU' => [[10, 'mobile']],
        'UA' => [[9, 'mobile']],
        'CN' => [[11, 'mobile'], [10, 'landline'], [11, 'landline']],
        'IN' => [[10, 'mobile'], [6, 'landline'], [7, 'landline'], [8, 'landline']],
        'PK' => [[10, 'mobile']],
        'BD' => [[10, 'mobile']],
        'NG' => [[10, 'mobile']],
        'JP' => [[10, 'mobile']],
        'KR' => [[9, 'mobile'], [10, 'mobile']],
        'ID' => [[10, 'mobile'], [11, 'mobile'], [7, 'landline'], [8, 'landline']],
        'PH' => [[10, 'mobile']],
        'MA' => [[9, 'mobile']],
        'DZ' => [[9, 'mobile']],
        'TN' => [[8, 'mobile']],
        'SD' => [[9, 'mobile']],
        'QA' => [[8, 'mobile']],
        'BH' => [[8, 'mobile']],
        'OM' => [[8, 'mobile'], [7, 'landline'], [8, 'landline']],
        'LB' => [[7, 'mobile'], [8, 'mobile'], [6, 'landline'], [7, 'landline'], [8, 'landline']],
        'SY' => [[9, 'mobile']],
        'SO' => [[8, 'mobile'], [9, 'mobile']],
        'BR' => [[11, 'mobile']],
        'CO' => [[10, 'mobile']],
        'AU' => [[9, 'mobile']],
        'MX' => [[10, 'mobile']],
        'AR' => [[10, 'mobile']],
        'ES' => [[9, 'mobile'], [9, 'landline']],
        'TH' => [[9, 'mobile']],
        'VN' => [[9, 'mobile'], [10, 'mobile']],
        'MY' => [[9, 'mobile'], [10, 'mobile'], [8, 'landline'], [9, 'landline']],
        'ZA' => [[9, 'mobile']],
        'KE' => [[9, 'mobile']],
        'IL' => [[9, 'mobile'], [7, 'landline'], [8, 'landline']],
        'AF' => [[9, 'mobile']],
    ];

    if (isset($lengths[$isoCode])) {
        foreach ($lengths[$isoCode] as $pair) {
            if ($len === $pair[0]) {
                return ['valid' => true, 'reason' => 'صالح'];
            }
        }
        return ['valid' => false, 'reason' => 'طول الرقم غير صحيح لـ' . ($countryInfo['countryName'] ?? 'هذا البلد')];
    }

    // Generic: 7-15 digits is probably OK
    if ($len >= 7 && $len <= 15) {
        return ['valid' => true, 'reason' => 'صالح (تقريبي)'];
    }

    return ['valid' => false, 'reason' => 'طول الرقم غير صحيح'];
}

/**
 * Format a phone number in international format with spaces.
 *
 * Examples:
 *   +967 781 428 914
 *   +1 (212) 555-1234
 *   +44 20 7946 0958
 */
function formatInternationalNumber(string $phoneNumber, array $countryInfo): string
{
    $cc = $countryInfo['countryCode'] ?? '';
    $national = $countryInfo['nationalNumber'] ?? '';
    $iso = $countryInfo['isoCode'] ?? '';

    if (empty($cc) || empty($national)) {
        // Fallback: just return the cleaned number
        return '+' . preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    switch ($iso) {
        case 'YE':
            // 9 digits: XXX XXX XXX
            if (strlen($national) === 9) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
            }
            break;
        case 'SA':
            // 9 digits: XX XXX XXXX
            if (strlen($national) === 9) {
                return $cc . ' ' . substr($national, 0, 2) . ' ' . substr($national, 2, 3) . ' ' . substr($national, 5);
            }
            // Landline: X XXX XXXX
            if (strlen($national) === 8) {
                return $cc . ' ' . substr($national, 0, 1) . ' ' . substr($national, 1, 3) . ' ' . substr($national, 4);
            }
            break;
        case 'AE':
            if (strlen($national) === 9) {
                return $cc . ' ' . substr($national, 0, 1) . ' ' . substr($national, 1, 3) . ' ' . substr($national, 4);
            }
            break;
        case 'EG':
            if (strlen($national) === 10) {
                return $cc . ' ' . substr($national, 0, 2) . ' ' . substr($national, 2, 4) . ' ' . substr($national, 6);
            }
            break;
        case 'IQ':
            if (strlen($national) === 10) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
            }
            break;
        case 'JO':
            if (strlen($national) === 9) {
                return $cc . ' ' . substr($national, 0, 1) . ' ' . substr($national, 1, 4) . ' ' . substr($national, 5);
            }
            break;
        case 'KW':
            if (strlen($national) === 8) {
                return $cc . ' ' . substr($national, 0, 4) . ' ' . substr($national, 4);
            }
            break;
        case 'US':
            if (strlen($national) === 10) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
            }
            break;
        case 'GB':
            if (strlen($national) >= 10) {
                return $cc . ' ' . substr($national, 0, 2) . ' ' . substr($national, 2, 4) . ' ' . substr($national, 6);
            }
            break;
        case 'DE':
            // Generic: format nicely
            if (strlen($national) >= 10) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
            }
            break;
        case 'TR':
            if (strlen($national) === 10) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
            }
            break;
        case 'CN':
            if (strlen($national) === 11) {
                return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 4) . ' ' . substr($national, 7);
            }
            break;
        case 'IN':
            if (strlen($national) === 10) {
                return $cc . ' ' . substr($national, 0, 4) . ' ' . substr($national, 4, 3) . ' ' . substr($national, 7);
            }
            break;
        case 'FR':
            if (strlen($national) === 9) {
                return $cc . ' ' . substr($national, 0, 1) . ' ' . substr($national, 1, 2) . ' ' . substr($national, 3, 2) . ' ' . substr($national, 5, 2) . ' ' . substr($national, 7, 2);
            }
            break;
    }

    // Generic formatting: group into 3-4 digit chunks
    $len = strlen($national);
    if ($len <= 4) {
        return $cc . ' ' . $national;
    }
    if ($len <= 6) {
        return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3);
    }
    if ($len <= 8) {
        return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
    }
    return $cc . ' ' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
}

/**
 * Perform a real phone number lookup.
 *
 * Returns carrier, region, validity, type, and formatted number
 * based on the actual number prefix database.
 */
function performRealLookup(string $query, array $countryInfo): array
{
    $countryCode  = $countryInfo['countryCode'] ?? '';
    $countryName  = $countryInfo['countryName'] ?? 'غير معروف';
    $flag         = $countryInfo['flag'] ?? '🌍';
    $nationalNum  = $countryInfo['nationalNumber'] ?? '';

    // Build the full international number
    $fullNumber = $countryCode . $nationalNum;

    // Get carrier info
    $carrier = getCarrierInfo($query, $countryInfo);

    // Validate number
    $validation = validatePhoneNumber($query, $countryInfo);

    // Format number
    $formatted = formatInternationalNumber($query, $countryInfo);

    // Determine phone type in Arabic
    $phoneTypeAr = match ($carrier['type']) {
        'mobile'   => 'هاتف محمول',
        'landline' => 'هاتف أرضي',
        'voip'     => 'VoIP',
        default    => 'غير معروف',
    };

    $result = [
        'name'             => 'رقم غير معروف',
        'phone'            => $fullNumber,
        'country'          => $countryName,
        'flag'             => $flag,
        'operator'         => $carrier['name'],
        'operator_en'      => $carrier['name_en'],
        'city'             => $carrier['region'] ?: 'غير معروف',
        'location'         => ($carrier['region'] ? $carrier['region'] . '، ' : '') . $countryName,
        'type'             => $phoneTypeAr,
        'phone_type'       => $carrier['type'],
        'phone_type_ar'    => $phoneTypeAr,
        'number_valid'     => $validation['valid'],
        'validation_reason'=> $validation['reason'],
        'formatted_number' => $formatted,
        'phone_hidden'     => false,
    ];

    // Try to find a name in the database by phone number
    try {
        $userRow = fetch(
            "SELECT name FROM users WHERE phone = :phone LIMIT 1",
            [':phone' => $fullNumber]
        );
        if ($userRow && !empty($userRow['name'])) {
            $result['name'] = $userRow['name'];
        }
    } catch (\Exception $e) {
        // Non-critical: DB lookup failed
    }

    return [
        'results' => [$result],
        'total'   => 1,
    ];
}

/**
 * Search by name in the users and search_history tables.
 *
 * Returns matching records with carrier/region info enriched
 * from the carrier database.
 */
function searchByName(string $query): array
{
    $results = [];
    $seen = []; // Deduplicate by phone number

    // Clean the query for SQL LIKE
    $likeQuery = '%' . addcslashes($query, '%_') . '%';

    try {
        // Search users table
        $userRows = fetchAll(
            "SELECT name, phone FROM users WHERE name LIKE :q ORDER BY name ASC LIMIT 50",
            [':q' => $likeQuery]
        );

        foreach ($userRows as $row) {
            $phone = $row['phone'] ?? '';
            if (empty($phone) || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;

            $countryInfo = detectCountry($phone);
            $carrier = getCarrierInfo($phone, $countryInfo);
            $formatted = formatInternationalNumber($phone, $countryInfo);
            $validation = validatePhoneNumber($phone, $countryInfo);

            $phoneTypeAr = match ($carrier['type']) {
                'mobile'   => 'هاتف محمول',
                'landline' => 'هاتف أرضي',
                'voip'     => 'VoIP',
                default    => 'غير معروف',
            };
            $cName = $countryInfo['countryName'] ?? 'غير معروف';
            $cRegion = $carrier['region'] ?: '';

            $results[] = [
                'name'             => $row['name'] ?? 'غير معروف',
                'phone'            => $phone,
                'country'          => $cName,
                'flag'             => $countryInfo['flag'] ?? '🌍',
                'operator'         => $carrier['name'],
                'operator_en'      => $carrier['name_en'],
                'city'             => $cRegion ?: 'غير معروف',
                'location'         => ($cRegion ? $cRegion . '، ' : '') . $cName,
                'type'             => $phoneTypeAr,
                'phone_type'       => $carrier['type'],
                'phone_type_ar'    => $phoneTypeAr,
                'number_valid'     => $validation['valid'],
                'formatted_number' => $formatted,
                'phone_hidden'     => false,
            ];
        }
    } catch (\Exception $e) {
        // Non-critical
    }

    try {
        // Also search search_history for unique phone numbers
        // (some users may have searched for numbers with names in the query field)
        $historyRows = fetchAll(
            "SELECT DISTINCT query AS phone FROM search_history 
             WHERE query_type = 'NUMBER' 
             AND query NOT LIKE '%[^0-9+]%'
             AND query LIKE :q2 
             ORDER BY created_at DESC 
             LIMIT 50",
            [':q2' => $likeQuery]
        );

        foreach ($historyRows as $row) {
            $phone = $row['phone'] ?? '';
            if (empty($phone) || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;

            $countryInfo = detectCountry($phone);
            $carrier = getCarrierInfo($phone, $countryInfo);
            $formatted = formatInternationalNumber($phone, $countryInfo);
            $validation = validatePhoneNumber($phone, $countryInfo);

            $phoneTypeAr2 = match ($carrier['type']) {
                'mobile'   => 'هاتف محمول',
                'landline' => 'هاتف أرضي',
                'voip'     => 'VoIP',
                default    => 'غير معروف',
            };
            $cName2 = $countryInfo['countryName'] ?? 'غير معروف';
            $cRegion2 = $carrier['region'] ?: '';

            $results[] = [
                'name'             => 'رقم غير معروف',
                'phone'            => $phone,
                'country'          => $cName2,
                'flag'             => $countryInfo['flag'] ?? '🌍',
                'operator'         => $carrier['name'],
                'operator_en'      => $carrier['name_en'],
                'city'             => $cRegion2 ?: 'غير معروف',
                'location'         => ($cRegion2 ? $cRegion2 . '، ' : '') . $cName2,
                'type'             => $phoneTypeAr2,
                'phone_type'       => $carrier['type'],
                'phone_type_ar'    => $phoneTypeAr2,
                'number_valid'     => $validation['valid'],
                'formatted_number' => $formatted,
                'phone_hidden'     => false,
            ];
        }
    } catch (\Exception $e) {
        // Non-critical
    }

    return [
        'results' => $results,
        'total'   => count($results),
    ];
}
