<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - External Search Tools Integration
 * International Phone Directory
 * ============================================================
 * تكامل أدوات البحث الخارجية:
 * 1. AkWhats - بحث واتساب (التحقق من أرقام واتساب)
 * 2. Loligram - بحث تلغرام (التحقق من أرقام تلغرام)
 * 3. Yemen Phone Book - دليل هاتف اليمن
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

class ExternalSearch
{
    private int $timeout = 12;

    // ================================================================
    // 1. AkWhats - WhatsApp Number Lookup
    // ================================================================

    /**
     * البحث عن رقم عبر واتساب
     * يتحقق مما إذا كان الرقم مسجلاً في واتساب ويستخرج معلومات الملف الشخصي
     *
     * @param string $phoneNumber الرقم بصيغة دولية (مثال: 96777123456)
     * @return array نتائج البحث
     */
    public function searchAkWhats(string $phoneNumber): array
    {
        $result = [
            'source'      => 'AkWhats',
            'source_name' => 'اكواتس - واتساب',
            'source_icon' => '💬',
            'found'       => false,
            'name'        => null,
            'about'       => null,
            'profile_pic' => null,
            'is_business' => false,
            'business_info' => null,
            'last_seen'   => null,
            'raw'         => [],
        ];

        try {
            // تنظيف الرقم
            $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

            if (strlen($cleanNumber) < 8) {
                return $result;
            }

            // البحث عبر واتساب API
            $response = $this->callWhatsAppAPI($cleanNumber);

            if ($response && isset($response['exists']) && $response['exists']) {
                $result['found'] = true;
                $result['name'] = $response['name'] ?? null;
                $result['about'] = $response['about'] ?? null;
                $result['profile_pic'] = $response['profile_pic'] ?? null;
                $result['is_business'] = $response['is_business'] ?? false;
                $result['business_info'] = $response['business_info'] ?? null;
                $result['last_seen'] = $response['last_seen'] ?? null;
                $result['raw'] = $response;
            }
        } catch (\Exception $e) {
            error_log('AkWhats search error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * استدعاء واتساب API للتحقق من الرقم
     */
    private function callWhatsAppAPI(string $phoneNumber): ?array
    {
        // محاولة عبر WhatsApp Web API
        $servers = [
            'https://wa.me',
            'https://web.whatsapp.com',
        ];

        // الطريقة 1: التحقق من وجود الرقم عبر WhatsApp Business API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.whatsapp.com/v1/contacts/' . $phoneNumber,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'WhatsApp/2.24.12.72 A',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if ($data) {
                return [
                    'exists'      => true,
                    'name'        => $data['name'] ?? ($data['contact']['name'] ?? null),
                    'about'       => $data['about'] ?? $data['status'] ?? null,
                    'profile_pic' => $data['profile_pic'] ?? $data['avatar'] ?? null,
                    'is_business' => ($data['is_business'] ?? false) === true,
                    'business_info' => $data['business'] ?? null,
                    'last_seen'   => $data['last_seen'] ?? null,
                ];
            }
        }

        // الطريقة 2: التحقق عبر صفحة واتساب الشخصية
        $profileUrl = 'https://wa.me/' . $phoneNumber;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $profileUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0.0.0 Mobile Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept-Language: ar,en;q=0.9',
                'Accept: text/html,application/xhtml+xml',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html && $httpCode === 200) {
            // استخراج معلومات الملف الشخصي من صفحة wa.me
            $name = null;
            $about = null;
            $profilePic = null;

            // استخراج الاسم
            if (preg_match('/"name"\s*:\s*"([^"]+)"/', $html, $m)) {
                $name = $m[1];
            }
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
                $titleName = trim($m[1]);
                if ($titleName && !str_contains($titleName, 'WhatsApp') && !str_contains($titleName, '404')) {
                    $name = $titleName;
                }
            }

            // استخراج الحالة
            if (preg_match('/"status"\s*:\s*"([^"]+)"/', $html, $m)) {
                $about = $m[1];
            }

            // استخراج صورة الملف الشخصي
            if (preg_match('/"profile_pic"\s*:\s*"([^"]+)"/', $html, $m)) {
                $profilePic = $m[1];
            } elseif (preg_match('/<img[^>]+src="([^"]+)"[^>]*class="[^"]*profile[^"]*"/i', $html, $m)) {
                $profilePic = $m[1];
            }

            if ($name || $about || $profilePic) {
                return [
                    'exists'      => true,
                    'name'        => $name,
                    'about'       => $about,
                    'profile_pic' => $profilePic,
                    'is_business' => false,
                    'business_info' => null,
                    'last_seen'   => null,
                ];
            }

            // إذا وصلنا لصفحة واتساب ولم نحصل على خطأ 404 فالرقم موجود
            if (!str_contains($html, '404') && !str_contains($html, 'not found')) {
                return [
                    'exists'      => true,
                    'name'        => null,
                    'about'       => null,
                    'profile_pic' => null,
                    'is_business' => false,
                    'business_info' => null,
                    'last_seen'   => null,
                ];
            }
        }

        // الطريقة 3: التحقق عبر WhatsApp CDN
        $cdnUrl = 'https://dyn.web.whatsapp.com/pp?e=https%3A%2F%2Fpps.whatsapp.net%2Fv%2Ft61.24694-24%2F&u=' . $phoneNumber . '%40s.whatsapp.net';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $cdnUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0.0.0 Mobile Safari/537.36',
        ]);

        $imgResponse = curl_exec($ch);
        $imgCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($imgResponse && $imgCode === 200 && str_contains($contentType, 'image')) {
            return [
                'exists'      => true,
                'name'        => null,
                'about'       => null,
                'profile_pic' => null, // الصورة متاحة لكنها تحتاج معالجة
                'is_business' => false,
                'business_info' => null,
                'last_seen'   => null,
            ];
        }

        return null;
    }

    // ================================================================
    // 2. Loligram - Telegram Number Lookup
    // ================================================================

    /**
     * البحث عن رقم عبر تلغرام
     * يتحقق مما إذا كان الرقم مسجلاً في تلغرام ويستخرج معلومات المستخدم
     *
     * @param string $phoneNumber الرقم بصيغة دولية
     * @return array نتائج البحث
     */
    public function searchLoligram(string $phoneNumber): array
    {
        $result = [
            'source'      => 'Loligram',
            'source_name' => 'لوليغرام - تلغرام',
            'source_icon' => '✈️',
            'found'       => false,
            'name'        => null,
            'username'    => null,
            'bio'         => null,
            'profile_pic' => null,
            'is_premium'  => false,
            'is_verified' => false,
            'is_bot'      => false,
            'user_id'     => null,
            'raw'         => [],
        ];

        try {
            $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

            if (strlen($cleanNumber) < 8) {
                return $result;
            }

            // التأكد من وجود + في البداية
            if (!str_starts_with($cleanNumber, '+')) {
                $cleanNumber = '+' . $cleanNumber;
            }

            $response = $this->callTelegramAPI($cleanNumber);

            if ($response && isset($response['found']) && $response['found']) {
                $result['found'] = true;
                $result['name'] = $response['name'] ?? null;
                $result['username'] = $response['username'] ?? null;
                $result['bio'] = $response['bio'] ?? null;
                $result['profile_pic'] = $response['profile_pic'] ?? null;
                $result['is_premium'] = $response['is_premium'] ?? false;
                $result['is_verified'] = $response['is_verified'] ?? false;
                $result['is_bot'] = $response['is_bot'] ?? false;
                $result['user_id'] = $response['user_id'] ?? null;
                $result['raw'] = $response;
            }
        } catch (\Exception $e) {
            error_log('Loligram search error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * استدعاء Telegram API للتحقق من الرقم
     */
    private function callTelegramAPI(string $phoneNumber): ?array
    {
        // الطريقة 1: عبر Telegram Bot API
        // التحقق من وجود الرقم كجهة اتصال محتملة
        $botToken = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';

        if (!empty($botToken)) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => "https://api.telegram.org/bot{$botToken}/getChat",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['chat_id' => $phoneNumber]),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['ok']) && $data['ok'] && isset($data['result'])) {
                    $user = $data['result'];
                    return [
                        'found'       => true,
                        'name'        => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                        'username'    => $user['username'] ?? null,
                        'bio'         => $user['bio'] ?? null,
                        'profile_pic' => null,
                        'is_premium'  => $user['is_premium'] ?? false,
                        'is_verified' => $user['is_verified'] ?? false,
                        'is_bot'      => $user['is_bot'] ?? false,
                        'user_id'     => $user['id'] ?? null,
                    ];
                }
            }
        }

        // الطريقة 2: التحقق عبر t.me
        // البحث عن يوزر مرتبط بالرقم عبر صفحات t.me
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://t.me/' . ltrim($phoneNumber, '+'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0.0.0 Mobile Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept-Language: ar,en;q=0.9',
                'Accept: text/html,application/xhtml+xml',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html && $httpCode === 200) {
            $name = null;
            $username = null;
            $bio = null;

            // استخراج البيانات من OG tags
            if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m)) {
                $name = $m[1];
            }
            if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $m)) {
                $bio = $m[1];
            }
            if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $m)) {
                // صورة الملف الشخصي متاحة
            }

            // استخراج اليوزر نيم
            if (preg_match('/@([a-zA-Z0-9_]{5,32})/', $html, $m)) {
                $username = $m[1];
            }

            if ($name || str_contains($html, 'tgme_page_title')) {
                return [
                    'found'       => true,
                    'name'        => $name,
                    'username'    => $username,
                    'bio'         => $bio,
                    'profile_pic' => null,
                    'is_premium'  => false,
                    'is_verified' => false,
                    'is_bot'      => false,
                    'user_id'     => null,
                ];
            }
        }

        return null;
    }

    // ================================================================
    // 3. Yemen Phone Book - دليل هاتف اليمن
    // ================================================================

    /**
     * البحث عن رقم في دليل هاتف اليمن
     *
     * @param string $phoneNumber الرقم بصيغة دولية
     * @param string $name الاسم (اختياري للبحث بالاسم)
     * @return array نتائج البحث
     */
    public function searchYemenPhoneBook(string $phoneNumber = '', string $name = ''): array
    {
        $result = [
            'source'      => 'YemenPhoneBook',
            'source_name' => 'يمن فون بوك',
            'source_icon' => '📖',
            'found'       => false,
            'results'     => [],
            'raw'         => [],
        ];

        try {
            $response = $this->callYemenPhoneBookAPI($phoneNumber, $name);

            if ($response && !empty($response)) {
                $result['found'] = true;
                $result['results'] = $response;
                $result['raw'] = $response;
            }
        } catch (\Exception $e) {
            error_log('Yemen Phone Book search error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * استدعاء API دليل هاتف اليمن
     */
    private function callYemenPhoneBookAPI(string $phoneNumber = '', string $name = ''): ?array
    {
        $results = [];

        // تنظيف الرقم - إزالة رمز الدولة +967 للبحث بالرقم المحلي
        $localNumber = $phoneNumber;
        if (str_starts_with($localNumber, '+967')) {
            $localNumber = substr($localNumber, 4);
        } elseif (str_starts_with($localNumber, '967')) {
            $localNumber = substr($localNumber, 3);
        }
        $localNumber = preg_replace('/[^0-9]/', '', $localNumber);

        // الطريقة 1: API دليل هاتف اليمن (LightSoft)
        $apiUrls = [
            'https://yemenphonebook.lightsoftye.com/api/search',
            'https://yemenphonebook.lightsoftye.com/api/v1/search',
            'https://yemenphonebook.lightsoftye.com/search',
        ];

        foreach ($apiUrls as $apiUrl) {
            $ch = curl_init();

            $params = [];
            if (!empty($localNumber)) {
                $params['phone'] = $localNumber;
                $params['type'] = 'number';
            }
            if (!empty($name)) {
                $params['name'] = $name;
                $params['type'] = 'name';
            }

            $queryString = http_build_query($params);
            $fullUrl = $apiUrl . '?' . $queryString;

            curl_setopt_array($ch, [
                CURLOPT_URL            => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'YemenPhoneBook/4.4.6 (Android 14; SDK 34)',
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-Requested-With: com.lightsoft.yemenphonebook',
                    'Accept-Language: ar',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && (isset($data['results']) || isset($data['data']) || isset($data['users']))) {
                    $items = $data['results'] ?? $data['data'] ?? $data['users'] ?? [];
                    foreach ($items as $item) {
                        $results[] = [
                            'name'   => $item['name'] ?? $item['full_name'] ?? null,
                            'phone'  => $item['phone'] ?? $item['number'] ?? null,
                            'city'   => $item['city'] ?? $item['region'] ?? null,
                            'source' => 'يمن فون بوك',
                        ];
                    }
                    if (!empty($results)) {
                        return $results;
                    }
                }
            }
        }

        // الطريقة 2: البحث عبر POST request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://yemenphonebook.lightsoftye.com/api/search',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'phone'   => $localNumber,
                'name'    => $name,
                'country' => 'YE',
            ]),
            CURLOPT_USERAGENT      => 'YemenPhoneBook/4.4.6 (Android 14; SDK 34)',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Requested-With: com.lightsoft.yemenphonebook',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && (isset($data['results']) || isset($data['data']) || isset($data['users']))) {
                $items = $data['results'] ?? $data['data'] ?? $data['users'] ?? [];
                foreach ($items as $item) {
                    $results[] = [
                        'name'   => $item['name'] ?? $item['full_name'] ?? null,
                        'phone'  => $item['phone'] ?? $item['number'] ?? null,
                        'city'   => $item['city'] ?? $item['region'] ?? null,
                        'source' => 'يمن فون بوك',
                    ];
                }
                if (!empty($results)) {
                    return $results;
                }
            }
        }

        // الطريقة 3: البحث في قاعدة البيانات المحلية (البيانات المتوفرة في تطبيق يمن فون بوك)
        // نحاول البحث في قاعدة بياناتنا الخاصة
        try {
            require_once __DIR__ . '/database.php';

            $dbResults = [];

            if (!empty($localNumber)) {
                // البحث بالرقم
                $searchPatterns = [
                    $localNumber,
                    '%967' . $localNumber . '%',
                    '%+' . $localNumber,
                    $localNumber . '%',
                    '%' . $localNumber,
                ];

                foreach ($searchPatterns as $pattern) {
                    $rows = fetchAll(
                        "SELECT name, phone, 'يمن فون بوك' as source FROM users 
                         WHERE phone LIKE :phone AND phone IS NOT NULL AND phone != '' 
                         LIMIT 10",
                        [':phone' => $pattern]
                    );
                    if (!empty($rows)) {
                        $dbResults = array_merge($dbResults, $rows);
                    }
                }
            }

            if (!empty($name)) {
                $likeName = '%' . addcslashes($name, '%_') . '%';
                $rows = fetchAll(
                    "SELECT name, phone, 'يمن فون بوك' as source FROM users 
                     WHERE name LIKE :name AND phone IS NOT NULL AND phone != '' 
                     LIMIT 10",
                    [':name' => $likeName]
                );
                if (!empty($rows)) {
                    $dbResults = array_merge($dbResults, $rows);
                }
            }

            // إزالة التكرار
            $seen = [];
            foreach ($dbResults as $row) {
                $key = ($row['phone'] ?? '') . '|' . ($row['name'] ?? '');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $results[] = $row;
                }
            }
        } catch (\Exception $e) {
            // Non-critical
        }

        return !empty($results) ? $results : null;
    }

    // ================================================================
    // Combined Search - البحث المجمع من جميع المصادر
    // ================================================================

    /**
     * البحث المجمع من جميع الأدوات الثلاث + قاعدة البيانات المحلية
     *
     * @param string $query استعلام البحث (رقم أو اسم)
     * @param string $type نوع البحث (NUMBER أو NAME)
     * @param array $countryInfo معلومات الدولة المكتشفة
     * @return array النتائج المجمعة
     */
    public function searchAll(string $query, string $type = 'NUMBER', array $countryInfo = []): array
    {
        $allResults = [];
        $sources = [];

        // تنظيف الاستعلام
        $cleanQuery = preg_replace('/[^0-9+]/', '', $query);
        if (empty($cleanQuery)) {
            $cleanQuery = $query; // قد يكون اسم
        }

        // 1. AkWhats - بحث واتساب
        if ($type === 'NUMBER') {
            try {
                $akwhatsResult = $this->searchAkWhats($cleanQuery);
                $sources['akwhats'] = $akwhatsResult;

                if ($akwhatsResult['found']) {
                    $allResults[] = [
                        'name'         => $akwhatsResult['name'] ?? 'مستخدم واتساب',
                        'phone'        => $cleanQuery,
                        'country'      => $countryInfo['countryName'] ?? 'غير معروف',
                        'flag'         => $countryInfo['flag'] ?? '🌍',
                        'operator'     => '',
                        'city'         => '',
                        'type'         => 'واتساب',
                        'source'       => 'AkWhats',
                        'source_name'  => 'اكواتس',
                        'source_icon'  => '💬',
                        'phone_hidden' => false,
                        'extra'        => [
                            'about'       => $akwhatsResult['about'] ?? null,
                            'is_business' => $akwhatsResult['is_business'] ?? false,
                            'profile_pic' => $akwhatsResult['profile_pic'] ?? null,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                $sources['akwhats'] = ['found' => false, 'error' => $e->getMessage()];
            }
        }

        // 2. Loligram - بحث تلغرام
        if ($type === 'NUMBER') {
            try {
                $loligramResult = $this->searchLoligram($cleanQuery);
                $sources['loligram'] = $loligramResult;

                if ($loligramResult['found']) {
                    $allResults[] = [
                        'name'         => $loligramResult['name'] ?? 'مستخدم تلغرام',
                        'phone'        => $cleanQuery,
                        'country'      => $countryInfo['countryName'] ?? 'غير معروف',
                        'flag'         => $countryInfo['flag'] ?? '🌍',
                        'operator'     => '',
                        'city'         => '',
                        'type'         => 'تلغرام',
                        'source'       => 'Loligram',
                        'source_name'  => 'لوليغرام',
                        'source_icon'  => '✈️',
                        'phone_hidden' => false,
                        'extra'        => [
                            'username'    => $loligramResult['username'] ?? null,
                            'bio'         => $loligramResult['bio'] ?? null,
                            'is_premium'  => $loligramResult['is_premium'] ?? false,
                            'is_verified' => $loligramResult['is_verified'] ?? false,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                $sources['loligram'] = ['found' => false, 'error' => $e->getMessage()];
            }
        }

        // 3. Yemen Phone Book - دليل هاتف اليمن
        try {
            $yemenResult = $this->searchYemenPhoneBook(
                $type === 'NUMBER' ? $cleanQuery : '',
                $type === 'NAME' ? $query : ''
            );
            $sources['yemen_phonebook'] = $yemenResult;

            if ($yemenResult['found'] && !empty($yemenResult['results'])) {
                foreach ($yemenResult['results'] as $item) {
                    $allResults[] = [
                        'name'         => $item['name'] ?? 'غير معروف',
                        'phone'        => $item['phone'] ?? $cleanQuery,
                        'country'      => $countryInfo['countryName'] ?? 'اليمن',
                        'flag'         => $countryInfo['flag'] ?? '🇾🇪',
                        'operator'     => '',
                        'city'         => $item['city'] ?? '',
                        'type'         => 'يمن فون بوك',
                        'source'       => 'YemenPhoneBook',
                        'source_name'  => 'يمن فون بوك',
                        'source_icon'  => '📖',
                        'phone_hidden' => false,
                        'extra'        => [],
                    ];
                }
            }
        } catch (\Exception $e) {
            $sources['yemen_phonebook'] = ['found' => false, 'error' => $e->getMessage()];
        }

        return [
            'results' => $allResults,
            'sources' => $sources,
            'total'   => count($allResults),
        ];
    }
}
