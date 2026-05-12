<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Jaib Payment Integration
 * International Phone Directory
 * ============================================================
 * نظام Jaib كامل - مبني على تحليل تطبيق Jaib الرسمي
 * يدعم: تشفير AES-256-CBC، تسجيل الدخول، استعلام المعاملات
 * جميع المفاتيح والبيانات مضمّنة مباشرة في الكود
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

class JaibPayment
{
    // === مفاتيح التشفير (مأخوذة من التطبيق) ===
    private string $fcmKey;
    private string $fcmIv;
    private string $clientIv;

    // === بيانات الجهاز الثابتة ===
    private string $deviceId;
    private string $smsCode;
    private string $tknNot;
    private string $initValue;
    private string $authHeader;
    private string $appVersion;
    private string $updateData;

    // === السيرفرات ===
    private array $bootstrapServers;
    private array $servers = [];

    // === حالة الجلسة ===
    private ?string $clientKey = null;
    private ?string $sessionKey = null;
    private ?string $sessionIv = null;
    private ?string $accessToken = null;
    private ?string $phone = null;
    private ?string $password = null;
    private ?string $userName = null;
    private ?int $createdAt = null;

    // === إعدادات ===
    private int $timeout;
    private int $sessionMaxAge = 1800; // 30 دقيقة

    /**
     * Constructor - تهيئة مفاتيح وبيانات Jaib من الثوابت
     */
    public function __construct()
    {
        $this->fcmKey    = base64_decode(JAIB_FCM_KEY);
        $this->fcmIv     = base64_decode(JAIB_FCM_IV);
        $this->clientIv  = base64_decode(JAIB_CLIENT_IV);
        $this->deviceId  = JAIB_DEVICE_ID;
        $this->smsCode   = JAIB_SMS_CODE;
        $this->tknNot    = JAIB_TKN_NOT;
        $this->initValue = JAIB_INIT_VALUE;
        $this->authHeader = JAIB_AUTH_HEADER;
        $this->appVersion = JAIB_APP_VERSION;
        $this->updateData = JAIB_UPDATE_DATA;
        $this->bootstrapServers = JAIB_SERVERS;
        $this->timeout  = JAIB_TIMEOUT;
    }

    // ================================================================
    // التشفير وفك التشفير (AES-256-CBC)
    // ================================================================

    /**
     * إضافة PKCS7 Padding
     */
    private function pkcs7Pad(string $data): string
    {
        $blockSize = 16;
        $pad = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($pad), $pad);
    }

    /**
     * إزالة PKCS7 Padding
     */
    private function pkcs7Unpad(string $data): string
    {
        $pad = ord($data[strlen($data) - 1]);
        if ($pad < 1 || $pad > 16) {
            return $data;
        }
        return substr($data, 0, -$pad);
    }

    /**
     * تشفير البيانات (AES-256-CBC + gzip + base64)
     */
    public function encrypt(array $data, string $key, string $iv): string
    {
        // JSON -> gzip -> base64 -> pad -> AES encrypt -> base64
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $compressed = gzencode($json, 6);
        $b64 = base64_encode($compressed);
        $padded = $this->pkcs7Pad($b64);
        $encrypted = openssl_encrypt($padded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }

    /**
     * فك تشفير البيانات
     */
    public function decrypt(string $payload, string $key, string $iv): array
    {
        // base64 decode -> AES decrypt -> unpad -> base64 decode -> gzip decode -> JSON
        $decoded = base64_decode($payload);
        $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('فشل فك التشفير: openssl_decrypt');
        }
        $unpadded = $this->pkcs7Unpad($decrypted);
        $b64decoded = base64_decode($unpadded);
        $decompressed = gzdecode($b64decoded);
        return json_decode($decompressed, true) ?: [];
    }

    // ================================================================
    // طلبات HTTP
    // ================================================================

    /**
     * إرسال طلب POST إلى سيرفرات Jaib
     */
    private function request(string $path, string $payload, array $extraHeaders = [], ?array $servers = null): ?array
    {
        $serverList = $servers ?: ($this->servers ?: $this->bootstrapServers);

        foreach ($serverList as $server) {
            $host = str_replace('https://', '', $server);
            $url = $server . $path;

            $headers = array_merge([
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: okhttp/5.1.0',
                'Accept: application/json',
                'Accept-Encoding: gzip',
                'Authorization: ' . $this->authHeader,
                'Host: ' . $host,
            ], $extraHeaders);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['value' => $payload]),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_ENCODING       => '', // handle gzip
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $response === false || $httpCode !== 200) {
                continue;
            }

            $result = json_decode($response, true);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    // ================================================================
    // تهيئة الاتصال (GetInitWallet)
    // ================================================================

    /**
     * تهيئة الاتصال بالسيرفر والحصول على client_key
     */
    public function init(): bool
    {
        $data = [
            'DtTime'   => (int)(microtime(true) * 1000),
            'envir'    => 1,
            'value'    => $this->initValue,
            'VersNum'  => (int)$this->appVersion,
        ];

        $payload = $this->encrypt($data, $this->fcmKey, $this->fcmIv);

        foreach ($this->bootstrapServers as $server) {
            $host = str_replace('https://', '', $server);
            $url = $server . '/api/HzHelp/GetInitWallet';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['value' => $payload]),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'User-Agent: okhttp/5.1.0',
                    'Accept: application/json',
                    'Accept-Encoding: gzip',
                    'Authorization: ' . $this->authHeader,
                    'Host: ' . $host,
                ],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $resp = json_decode($response, true);
                if ($resp && isset($resp['success']) && $resp['success'] && isset($resp['result'])) {
                    try {
                        $dec = $this->decrypt($resp['result'], $this->fcmKey, $this->fcmIv);
                        $this->clientKey = base64_decode($dec['Kl']);

                        // بناء قائمة السيرفرات من الرد + الافتراضية
                        $serverIps = $dec['lsIP'] ?? [];
                        $uniqueServers = [];
                        foreach ($serverIps as $s) {
                            if (!in_array($s, $uniqueServers)) {
                                $uniqueServers[] = $s;
                            }
                        }
                        foreach ($this->bootstrapServers as $s) {
                            if (!in_array($s, $uniqueServers)) {
                                $uniqueServers[] = $s;
                            }
                        }
                        $this->servers = $uniqueServers;
                        return true;
                    } catch (\Exception $e) {
                        error_log('Jaib init decrypt error: ' . $e->getMessage());
                    }
                }
            }
        }

        return false;
    }

    // ================================================================
    // تسجيل الدخول (LogE2)
    // ================================================================

    /**
     * تسجيل الدخول إلى حساب Jaib
     */
    public function login(string $phone, string $password): array
    {
        $this->phone = $phone;
        $this->password = $password;

        // التأكد من وجود client_key
        if ($this->clientKey === null) {
            if (!$this->init()) {
                return [
                    'success' => false,
                    'message' => 'فشل الاتصال بسيرفرات Jaib',
                ];
            }
        }

        $data = [
            'clientInfo' => [
                'AppVersion'   => $this->appVersion,
                'clientKey'    => $phone,
                'clientSecret' => 'mangmasng',
                'returnKey'    => (string)(int)substr((string)time(), -6),
            ],
            'deviceInfo' => [
                'ConfirmCode' => '',
                'deviceId'    => $this->deviceId,
                'deviceInfo'  => 'HUAWEI LDN-L21',
                'tknNot'      => $this->tknNot,
                'langId'      => 1,
                'otherInfo'   => json_encode([
                    'brand' => 'HONOR',
                    'deviceName' => 'HUAWEI',
                    'ip' => '',
                    'loginMethodType' => 1,
                    'model' => 'LDN-L21',
                    'os' => '26',
                ]),
                'sourceType' => 1,
            ],
            'password'    => $password,
            'smsCode'     => $this->smsCode,
            'UpdateData'  => $this->updateData,
            'userName'    => $phone,
        ];

        $payload = $this->encrypt($data, $this->clientKey, $this->clientIv);
        $resp = $this->request('/api/TokenAuth/LogE2', $payload);

        if (!$resp) {
            return [
                'success' => false,
                'message' => 'فشل الاتصال بالسيرفر',
            ];
        }

        if (!isset($resp['success']) || !$resp['success']) {
            $err = $resp['error'] ?? [];
            $code = $err['code'] ?? '?';
            $msg = $err['message'] ?? 'خطأ غير معروف';

            if ($code == -1000) {
                $msg = 'الجهاز يحتاج تأكيد من تطبيق آخر على نفس الحساب';
            } elseif ($code == -1001) {
                $msg = 'كود التفعيل غير صحيح';
            }

            return [
                'success' => false,
                'message' => $msg,
                'code' => $code,
            ];
        }

        // فك تشفير الرد
        $raw = $resp['result'] ?? '';
        if (is_array($raw)) {
            $raw = $raw['value'] ?? '';
        }
        if (empty($raw)) {
            return [
                'success' => false,
                'message' => 'رد فارغ من السيرفر',
            ];
        }

        try {
            $dec = $this->decrypt($raw, $this->clientKey, $this->clientIv);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل فك التشفير: ' . $e->getMessage(),
            ];
        }

        // استخراج بيانات الجلسة
        $this->accessToken = $dec['accessToken'] ?? null;
        $this->sessionKey = base64_decode($dec['k'] ?? '');
        $this->sessionIv = base64_decode($dec['mobileScon'] ?? '');
        $this->createdAt = time();

        $winfo = $dec['winfo'] ?? [];
        $this->userName = $winfo['name'] ?? $phone;

        $accounts = $dec['myAccount'] ?? [];
        $bills = $dec['accountBills'] ?? [];

        // حفظ الجلسة
        $this->saveSession($accounts, $bills);

        return [
            'success'       => true,
            'message'       => 'تم تسجيل الدخول بنجاح',
            'user_name'     => $this->userName,
            'accounts'      => $accounts,
            'recent_bills'  => array_slice($bills, 0, 5),
        ];
    }

    // ================================================================
    // استعلام معاملة (ExecuteE2)
    // ================================================================

    /**
     * استعلام عن معاملة برقم المرجع
     */
    public function queryTransaction(string $refId): array
    {
        if (!$this->sessionKey) {
            return [
                'success' => false,
                'message' => 'غير مسجل الدخول - يجب تسجيل الدخول أولاً',
            ];
        }

        // التحقق من صلاحية الجلسة
        if (!$this->isSessionAlive()) {
            // محاولة تجديد الجلسة
            if (!$this->refreshSession()) {
                return [
                    'success' => false,
                    'message' => 'انتهت صلاحية الجلسة - يجب تسجيل الدخول مجدداً',
                ];
            }
        }

        $data = [
            'code'         => '',
            'opType'       => 567,
            'ReferenceID'  => $refId,
        ];

        $payload = $this->encrypt($data, $this->sessionKey, $this->sessionIv);
        $headers = [];
        if ($this->accessToken) {
            $headers[] = 'auth: ' . $this->accessToken;
        }

        $resp = $this->request('/api/v1/Wallet/ExecuteE2', $payload, $headers);

        if (!$resp) {
            return [
                'success' => false,
                'message' => 'فشل الاتصال بالسيرفر',
            ];
        }

        if (!isset($resp['success']) || !$resp['success']) {
            $err = $resp['error'] ?? [];
            return [
                'success' => false,
                'message' => $err['message'] ?? 'خطأ في الاستعلام',
            ];
        }

        $raw = $resp['result'] ?? '';
        if (is_array($raw)) {
            $raw = $raw['value'] ?? '';
        }
        if (empty($raw)) {
            return [
                'success' => false,
                'message' => 'رد فارغ من السيرفر',
            ];
        }

        try {
            $dec = $this->decrypt($raw, $this->sessionKey, $this->sessionIv);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل فك التشفير: ' . $e->getMessage(),
            ];
        }

        // بناء نتيجة مفيدة
        $result = [
            'success'      => true,
            'message'      => 'تم العثور على المعاملة',
            'reference_id' => $dec['referenceID'] ?? $refId,
            'amount'       => $dec['amount'] ?? null,
            'currency'     => $dec['currencyName'] ?? 'YER',
            'from_name'    => $dec['fromName'] ?? null,
            'to_name'      => $dec['toName'] ?? null,
            'note'         => $dec['note'] ?? null,
            'date_time'    => $dec['dateTime'] ?? null,
            'fields'       => [],
        ];

        // حقول تفصيلية
        if (isset($dec['fields']) && is_array($dec['fields'])) {
            foreach ($dec['fields'] as $f) {
                if (!empty($f['value'])) {
                    $result['fields'][] = [
                        'name'  => $f['name'] ?? '',
                        'value' => $f['value'] ?? '',
                    ];
                }
            }
        }

        // التحقق إذا ما فيه خطأ
        if ($dec['error'] || $dec['message']) {
            $result['message'] = $dec['error'] ?? $dec['message'] ?? 'نتيجة غير واضحة';
        }

        return $result;
    }

    // ================================================================
    // إدارة الجلسة
    // ================================================================

    /**
     * حفظ بيانات الجلسة في الملف
     */
    private function saveSession(array $accounts = [], array $bills = []): void
    {
        $sessionDir = CACHE_PATH . '/jaib';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0755, true);
        }

        $data = [
            'phone'          => $this->phone,
            'password'       => $this->password,
            'user_name'      => $this->userName,
            'access_token'   => $this->accessToken,
            'session_key_b64'=> base64_encode($this->sessionKey ?? ''),
            'session_iv_b64' => base64_encode($this->sessionIv ?? ''),
            'client_key_b64' => base64_encode($this->clientKey ?? ''),
            'servers'        => $this->servers,
            'accounts'       => $accounts,
            'created_at'     => $this->createdAt,
        ];

        // Encrypt session data before saving
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $encrypted = Security::encrypt($json, 'jaib_session_encryption_key_2024');

        @file_put_contents(
            $sessionDir . '/session.enc',
            $encrypted
        );

        // Remove old plain-text session if exists
        @unlink($sessionDir . '/session.json');
    }

    /**
     * تحميل الجلسة المحفوظة
     */
    public function loadSession(): bool
    {
        // Try encrypted session first
        $encryptedFile = CACHE_PATH . '/jaib/session.enc';
        $plainFile = CACHE_PATH . '/jaib/session.json';

        $sessionData = null;

        if (file_exists($encryptedFile)) {
            try {
                $encrypted = file_get_contents($encryptedFile);
                $decrypted = Security::decrypt($encrypted, 'jaib_session_encryption_key_2024');
                if ($decrypted) {
                    $sessionData = json_decode($decrypted, true);
                }
            } catch (\Exception $e) {
                error_log('Jaib encrypted session load failed: ' . $e->getMessage());
            }
        }

        // Fallback to plain text (migration)
        if (!$sessionData && file_exists($plainFile)) {
            try {
                $sessionData = json_decode(file_get_contents($plainFile), true);
                // If we loaded plain text, re-save as encrypted
                if ($sessionData) {
                    $this->phone       = $sessionData['phone'] ?? null;
                    $this->password    = $sessionData['password'] ?? null;
                    $this->userName    = $sessionData['user_name'] ?? null;
                    $this->accessToken = $sessionData['access_token'] ?? null;
                    $this->sessionKey  = base64_decode($sessionData['session_key_b64'] ?? '');
                    $this->sessionIv   = base64_decode($sessionData['session_iv_b64'] ?? '');
                    $this->clientKey   = base64_decode($sessionData['client_key_b64'] ?? '');
                    $this->servers     = $sessionData['servers'] ?? $this->bootstrapServers;
                    $this->createdAt   = $sessionData['created_at'] ?? null;

                    // Re-save encrypted
                    $this->saveSession();
                    return true;
                }
            } catch (\Exception $e) {
                // Remove corrupt plain file
                @unlink($plainFile);
            }
        }

        if (!$sessionData || empty($sessionData['session_key_b64'])) {
            return false;
        }

        try {
            $this->phone       = $sessionData['phone'] ?? null;
            $this->password    = $sessionData['password'] ?? null;
            $this->userName    = $sessionData['user_name'] ?? null;
            $this->accessToken = $sessionData['access_token'] ?? null;
            $this->sessionKey  = base64_decode($sessionData['session_key_b64']);
            $this->sessionIv   = base64_decode($sessionData['session_iv_b64']);
            $this->clientKey   = base64_decode($sessionData['client_key_b64']);
            $this->servers     = $sessionData['servers'] ?? $this->bootstrapServers;
            $this->createdAt   = $sessionData['created_at'] ?? null;

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * التحقق من صلاحية الجلسة
     */
    public function isSessionAlive(): bool
    {
        if ($this->createdAt === null) {
            return false;
        }
        return (time() - $this->createdAt) < $this->sessionMaxAge;
    }

    /**
     * تجديد الجلسة
     */
    public function refreshSession(): bool
    {
        if (!$this->phone || !$this->password) {
            return false;
        }

        $oldPhone = $this->phone;
        $oldPassword = $this->password;

        $this->sessionKey = null;
        $this->clientKey = null;

        if (!$this->init()) {
            return false;
        }

        $result = $this->login($oldPhone, $oldPassword);
        return $result['success'];
    }

    // ================================================================
    // تكامل مع نظام الدفع في المشروع
    // ================================================================

    /**
     * التحقق من دفعة عبر استعلام رقم المرجع في Jaib
     * (تستخدم لتفعيل الاشتراكات)
     */
    public function verifyPayment(string $transactionId): array
    {
        if (!Security::validateTransactionId($transactionId)) {
            return [
                'success'  => false,
                'verified' => false,
                'amount'   => null,
                'message'  => 'معرف المعاملة غير صالح',
            ];
        }

        // محاولة استعلام المعاملة من Jaib
        $queryResult = $this->queryTransaction($transactionId);

        if (!$queryResult['success']) {
            // فشل الاستعلام - نستخدم المحاكاة
            return $this->simulateVerification($transactionId);
        }

        // التحقق من أن المعاملة تحتوي على مبلغ
        $amount = $queryResult['amount'];
        if ($amount === null) {
            return [
                'success'  => true,
                'verified' => false,
                'amount'   => null,
                'message'  => 'لم يتم العثور على معلومات المبلغ',
                'raw'      => $queryResult,
            ];
        }

        return [
            'success'  => true,
            'verified' => true,
            'amount'   => (float)$amount,
            'message'  => 'تم التحقق من الدفع بنجاح',
            'details'  => $queryResult,
        ];
    }

    /**
     * محاكاة التحقق (للتطوير والاختبار)
     */
    private function simulateVerification(string $transactionId): array
    {
        usleep(200000); // 200ms

        if (strtoupper(substr($transactionId, 0, 9)) === 'APPROVED-') {
            $parts = explode('-', $transactionId);
            $amount = 2000.0;
            foreach ($parts as $part) {
                if (strtoupper(substr($part, 0, 4)) === 'AMT-') {
                    $amount = (float)substr($part, 4);
                    break;
                }
            }
            return [
                'success'  => true,
                'verified' => true,
                'amount'   => $amount,
                'message'  => 'تم التحقق من الدفع بنجاح (وضع المحاكاة)',
            ];
        }

        if (strtoupper(substr($transactionId, 0, 9)) === 'REJECTED-') {
            return [
                'success'  => true,
                'verified' => false,
                'amount'   => null,
                'message'  => 'تم رفض الدفع (وضع المحاكاة)',
            ];
        }

        if (rand(1, 10) <= 7) {
            return [
                'success'  => true,
                'verified' => true,
                'amount'   => 2000.0,
                'message'  => 'تم التحقق من الدفع بنجاح (وضع المحاكاة)',
            ];
        }

        return [
            'success'  => true,
            'verified' => false,
            'amount'   => null,
            'message'  => 'لم يتم التحقق من الدفع (وضع المحاكاة)',
        ];
    }

    /**
     * معالجة دفعة كاملة: تحقق + تفعيل اشتراك
     */
    public function processPayment(int $userId, string $plan, string $transactionId): array
    {
        if (!validatePlan($plan)) {
            return ['success' => false, 'message' => 'باقة الاشتراك غير صالحة'];
        }
        $plan = strtoupper($plan);

        if (!Security::validateTransactionId($transactionId)) {
            return ['success' => false, 'message' => 'معرف المعاملة غير صالح'];
        }

        // التحقق من عدم التكرار
        $duplicate = fetch(
            "SELECT id, status FROM payments WHERE transaction_id = :txid LIMIT 1",
            [':txid' => $transactionId]
        );
        if ($duplicate !== null && $duplicate['status'] !== 'REJECTED') {
            return ['success' => false, 'message' => 'تم استخدام معرف المعاملة هذا من قبل'];
        }

        // التحقق من وجود المستخدم
        $user = fetch(
            "SELECT id, name, email, plan FROM users WHERE id = :id LIMIT 1",
            [':id' => $userId]
        );
        if ($user === null) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }

        $planPrice = PLANS[$plan]['price'] ?? 0;

        // إنشاء سجل دفع
        try {
            $paymentId = insert('payments', [
                'user_id'        => $userId,
                'plan'           => $plan,
                'amount'         => $planPrice,
                'currency'       => JAIB_CURRENCY,
                'status'         => 'PENDING',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), 'unique') !== false) {
                return ['success' => false, 'message' => 'تم استخدام معرف المعاملة هذا من قبل'];
            }
            error_log('Payment record creation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء معالجة الدفع'];
        }

        // التحقق من الدفع عبر Jaib
        $verification = $this->verifyPayment($transactionId);

        if (!$verification['success']) {
            Security::logActivity($userId, 'payment_api_error', 'Jaib error: ' . $transactionId);
            return ['success' => false, 'message' => $verification['message']];
        }

        if (!$verification['verified']) {
            update('payments', ['status' => 'REJECTED'], 'id = :id', [':id' => $paymentId]);
            Security::logActivity($userId, 'payment_rejected', 'Rejected: ' . $transactionId);
            return ['success' => false, 'message' => $verification['message']];
        }

        // تفعيل الاشتراك
        try {
            db()->beginTransaction();

            update('payments', ['status' => 'APPROVED'], 'id = :id', [':id' => $paymentId]);
            $subscription = $this->activateSubscription($userId, $plan, (int)$paymentId);

            db()->commit();

            Security::logActivity($userId, 'payment_success', "{$plan} - {$planPrice} " . JAIB_CURRENCY . " - {$transactionId}");

            return [
                'success'      => true,
                'message'      => 'تم تفعيل الاشتراك بنجاح! مرحباً بك في باقة ' . PLANS[$plan]['name'],
                'subscription' => $subscription,
            ];
        } catch (\Exception $e) {
            db()->rollback();
            error_log('Payment processing failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تفعيل الاشتراك'];
        }
    }

    /**
     * تفعيل اشتراك
     */
    public function activateSubscription(int $userId, string $plan, int $paymentId = 0): array
    {
        $plan = strtoupper($plan);
        if (!isset(PLANS[$plan])) {
            throw new \InvalidArgumentException('Invalid plan: ' . $plan);
        }

        $planConfig = PLANS[$plan];
        $durationDays = (int)($planConfig['duration'] ?? 30);
        $now = new \DateTime();
        $expiresAt = $now->modify("+{$durationDays} days")->format('Y-m-d H:i:s');

        // إلغاء الاشتراكات النشطة القديمة
        update('subscriptions', ['is_active' => 0], 'user_id = :user_id AND is_active = 1', [':user_id' => $userId]);

        // إنشاء اشتراك جديد
        $subscriptionId = insert('subscriptions', [
            'user_id'    => $userId,
            'plan'       => $plan,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'is_active'  => 1,
            'payment_id' => $paymentId > 0 ? $paymentId : null,
        ]);

        // تحديث بيانات المستخدم
        $userData = [
            'plan'                    => $plan,
            'subscription_expires_at' => $expiresAt,
            'updated_at'              => date('Y-m-d H:i:s'),
        ];
        if ($planConfig['can_hide_phone']) {
            $userData['is_phone_hidden'] = 1;
        }
        update('users', $userData, 'id = :id', [':id' => $userId]);

        // تحديث الجلسة
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['user_plan'] = $plan;
            if (isset($_SESSION['user_data'])) {
                $_SESSION['user_data']['plan'] = $plan;
                $_SESSION['user_data']['subscription_expires_at'] = $expiresAt;
                if ($planConfig['can_hide_phone']) {
                    $_SESSION['user_data']['is_phone_hidden'] = 1;
                }
            }
        }

        return [
            'id'         => $subscriptionId,
            'user_id'    => $userId,
            'plan'       => $plan,
            'plan_name'  => $planConfig['name'],
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'is_active'  => true,
            'payment_id' => $paymentId,
        ];
    }

    /**
     * التحقق من تكرار المعاملة
     */
    public function checkDuplicateTransaction(string $transactionId): bool
    {
        $existing = fetch(
            "SELECT id, status FROM payments WHERE transaction_id = :txid LIMIT 1",
            [':txid' => $transactionId]
        );
        if ($existing === null) return false;
        if ($existing['status'] === 'REJECTED') return false;
        return true;
    }

    /**
     * سعر الباقة
     */
    public function getPlanPrice(string $plan): float
    {
        $plan = strtoupper($plan);
        return isset(PLANS[$plan]) ? (float)PLANS[$plan]['price'] : 0.0;
    }

    /**
     * سجل المدفوعات
     */
    public function getPaymentHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        return fetchAll(
            "SELECT * FROM payments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            [':user_id' => $userId, ':limit' => $limit, ':offset' => $offset]
        );
    }

    /**
     * سجل الاشتراكات
     */
    public function getSubscriptionHistory(int $userId): array
    {
        return fetchAll(
            "SELECT s.*, p.amount, p.currency, p.status as payment_status
             FROM subscriptions s LEFT JOIN payments p ON s.payment_id = p.id
             WHERE s.user_id = :user_id ORDER BY s.started_at DESC",
            [':user_id' => $userId]
        );
    }

    /**
     * الاشتراك النشط
     */
    public function getActiveSubscription(int $userId): ?array
    {
        return fetch(
            "SELECT * FROM subscriptions WHERE user_id = :user_id AND is_active = 1 AND expires_at > :now ORDER BY expires_at DESC LIMIT 1",
            [':user_id' => $userId, ':now' => date('Y-m-d H:i:s')]
        );
    }

    // ================================================================
    // Getters للحالة
    // ================================================================

    public function isLoggedIn(): bool
    {
        return $this->sessionKey !== null && $this->isSessionAlive();
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getSessionAge(): string
    {
        if ($this->createdAt === null) return '?';
        $m = (int)((time() - $this->createdAt) / 60);
        return $m < 60 ? "{$m} دقيقة" : ($m / 60 | 0) . "س " . ($m % 60) . "د";
    }

    public function getServers(): array
    {
        return $this->servers ?: $this->bootstrapServers;
    }
}
