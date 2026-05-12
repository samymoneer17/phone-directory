<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Jaib Payment Integration
 * International Phone Directory
 * ============================================================
 * Payment processing class for Jaib wallet integration.
 * Supports payment verification, subscription activation,
 * and transaction management.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

class JaibPayment
{
    /** @var string Jaib API base URL */
    private string $apiUrl;

    /** @var string Jaib API key */
    private string $apiKey;

    /** @var string Merchant ID */
    private string $merchantId;

    /** @var string Merchant secret for signature */
    private string $merchantSecret;

    /** @var int Request timeout in seconds */
    private int $timeout;

    /**
     * Constructor - initialize Jaib payment configuration
     */
    public function __construct()
    {
        $this->apiUrl         = JAIB_API_URL;
        $this->apiKey         = JAIB_API_KEY;
        $this->merchantId     = JAIB_MERCHANT_ID;
        $this->merchantSecret = JAIB_MERCHANT_SECRET;
        $this->timeout        = JAIB_TIMEOUT;
    }

    /**
     * Verify a payment transaction with Jaib API
     *
     * @param string $transactionId The unique transaction ID
     * @return array{success: bool, verified: bool, amount: float|null, message: string}
     */
    public function verifyPayment(string $transactionId): array
    {
        // Validate transaction ID
        if (!Security::validateTransactionId($transactionId)) {
            return [
                'success'  => false,
                'verified' => false,
                'amount'   => null,
                'message'  => 'معرف المعاملة غير صالح',
            ];
        }

        // If Jaib API is not configured, use simulation mode
        if (empty($this->apiKey) || empty($this->merchantId)) {
            return $this->simulateVerification($transactionId);
        }

        // Build verification request
        $endpoint = $this->apiUrl . '/payments/verify';

        $payload = [
            'merchant_id'   => $this->merchantId,
            'transaction_id' => $transactionId,
            'timestamp'     => time(),
        ];

        // Generate signature
        $signature = $this->generateSignature($payload);
        $payload['signature'] = $signature;

        try {
            $response = $this->makeApiRequest('POST', $endpoint, $payload);

            if ($response === null) {
                return [
                    'success'  => false,
                    'verified' => false,
                    'amount'   => null,
                    'message'  => 'فشل الاتصال بخدمة الدفع',
                ];
            }

            if (isset($response['status']) && $response['status'] === 'approved') {
                return [
                    'success'  => true,
                    'verified' => true,
                    'amount'   => (float) ($response['amount'] ?? 0),
                    'message'  => 'تم التحقق من الدفع بنجاح',
                ];
            }

            return [
                'success'  => true,
                'verified' => false,
                'amount'   => null,
                'message'  => $response['message'] ?? 'لم يتم التحقق من الدفع',
            ];
        } catch (\Exception $e) {
            error_log('Jaib verification error: ' . $e->getMessage());
            return [
                'success'  => false,
                'verified' => false,
                'amount'   => null,
                'message'  => 'حدث خطأ أثناء التحقق من الدفع',
            ];
        }
    }

    /**
     * Simulate payment verification (for development/testing)
     * Transaction IDs starting with "APPROVED-" simulate successful payments
     *
     * @param string $transactionId
     * @return array{success: bool, verified: bool, amount: float|null, message: string}
     */
    private function simulateVerification(string $transactionId): array
    {
        // Simulate a slight delay like a real API call
        usleep(200000); // 200ms

        // Check for simulation prefix
        if (strtoupper(substr($transactionId, 0, 9)) === 'APPROVED-') {
            // Extract amount from transaction ID if encoded
            $parts = explode('-', $transactionId);
            $amount = null;
            foreach ($parts as $part) {
                if (strtoupper(substr($part, 0, 4)) === 'AMT-') {
                    $amount = (float) substr($part, 4);
                    break;
                }
            }

            if ($amount === null) {
                $amount = 2000.0; // Default PRO plan price
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

        // Random simulation (70% chance of success)
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
     * Process a payment: verify transaction and activate subscription
     *
     * @param int    $userId        User ID
     * @param string $plan          Plan name (PRO, PREMIUM)
     * @param string $transactionId Transaction ID from Jaib
     * @return array{success: bool, message: string, subscription?: array}
     */
    public function processPayment(int $userId, string $plan, string $transactionId): array
    {
        // Validate plan
        if (!validatePlan($plan)) {
            return [
                'success' => false,
                'message' => 'باقة الاشتراك غير صالحة',
            ];
        }

        $plan = strtoupper($plan);

        // Validate transaction ID
        if (!Security::validateTransactionId($transactionId)) {
            return [
                'success' => false,
                'message' => 'معرف المعاملة غير صالح',
            ];
        }

        // Check for duplicate transaction
        $duplicate = $this->checkDuplicateTransaction($transactionId);
        if ($duplicate) {
            return [
                'success' => false,
                'message' => 'تم استخدام معرف المعاملة هذا من قبل',
            ];
        }

        // Verify user exists
        $user = fetch(
            "SELECT id, name, email, plan FROM users WHERE id = :id LIMIT 1",
            [':id' => $userId]
        );

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'المستخدم غير موجود',
            ];
        }

        // Get plan price
        $planPrice = $this->getPlanPrice($plan);

        // Create pending payment record
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
            // Duplicate transaction_id constraint violation
            if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), 'unique') !== false) {
                return [
                    'success' => false,
                    'message' => 'تم استخدام معرف المعاملة هذا من قبل',
                ];
            }
            error_log('Payment record creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الدفع',
            ];
        }

        // Verify payment with Jaib
        $verification = $this->verifyPayment($transactionId);

        if (!$verification['success']) {
            // API call failed - keep payment as PENDING
            Security::logActivity($userId, 'payment_api_error', 'Jaib API error for transaction: ' . $transactionId);
            return [
                'success' => false,
                'message' => $verification['message'],
            ];
        }

        if (!$verification['verified']) {
            // Payment not verified
            update(
                'payments',
                ['status' => 'REJECTED'],
                'id = :id',
                [':id' => $paymentId]
            );

            Security::logActivity($userId, 'payment_rejected', 'Payment rejected: ' . $transactionId);
            return [
                'success' => false,
                'message' => $verification['message'],
            ];
        }

        // Payment verified - update status and activate subscription
        try {
            db()->beginTransaction();

            update(
                'payments',
                ['status' => 'APPROVED'],
                'id = :id',
                [':id' => $paymentId]
            );

            // Activate subscription
            $subscription = $this->activateSubscription($userId, $plan, (int) $paymentId);

            db()->commit();

            Security::logActivity(
                $userId,
                'payment_success',
                "Payment approved: {$plan} plan, amount: {$planPrice} " . JAIB_CURRENCY . ", transaction: {$transactionId}"
            );

            return [
                'success'      => true,
                'message'      => 'تم تفعيل الاشتراك بنجاح! مرحباً بك في باقة ' . PLANS[$plan]['name'],
                'subscription' => $subscription,
            ];
        } catch (\Exception $e) {
            db()->rollback();
            error_log('Payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تفعيل الاشتراك',
            ];
        }
    }

    /**
     * Check if a transaction ID has already been used
     *
     * @param string $transactionId
     * @return bool True if duplicate
     */
    public function checkDuplicateTransaction(string $transactionId): bool
    {
        $existing = fetch(
            "SELECT id, status FROM payments WHERE transaction_id = :txid LIMIT 1",
            [':txid' => $transactionId]
        );

        if ($existing === null) {
            return false;
        }

        // If the existing payment was rejected, allow retry
        if ($existing['status'] === 'REJECTED') {
            return false;
        }

        return true;
    }

    /**
     * Get the price for a subscription plan
     *
     * @param string $plan Plan name
     * @return float Price amount
     */
    public function getPlanPrice(string $plan): float
    {
        $plan = strtoupper($plan);

        if (!isset(PLANS[$plan])) {
            return 0.0;
        }

        return (float) PLANS[$plan]['price'];
    }

    /**
     * Activate a subscription for a user
     *
     * @param int    $userId    User ID
     * @param string $plan      Plan name
     * @param int    $paymentId Associated payment ID
     * @return array Subscription details
     */
    public function activateSubscription(int $userId, string $plan, int $paymentId = 0): array
    {
        $plan = strtoupper($plan);

        if (!isset(PLANS[$plan])) {
            throw new \InvalidArgumentException('Invalid plan: ' . $plan);
        }

        $planConfig = PLANS[$plan];
        $durationDays = (int) ($planConfig['duration'] ?? 30);
        $now = new \DateTime();
        $expiresAt = $now->modify("+{$durationDays} days")->format('Y-m-d H:i:s');

        // Deactivate existing active subscriptions
        update(
            'subscriptions',
            ['is_active' => 0],
            'user_id = :user_id AND is_active = 1',
            [':user_id' => $userId]
        );

        // Create new subscription
        $subscriptionId = insert('subscriptions', [
            'user_id'   => $userId,
            'plan'      => $plan,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'is_active' => 1,
            'payment_id' => $paymentId > 0 ? $paymentId : null,
        ]);

        // Update user's plan and subscription expiry
        $userData = [
            'plan'                   => $plan,
            'subscription_expires_at' => $expiresAt,
            'updated_at'             => date('Y-m-d H:i:s'),
        ];

        // Enable phone hiding for paid plans
        if ($planConfig['can_hide_phone']) {
            $userData['is_phone_hidden'] = 1;
        }

        update(
            'users',
            $userData,
            'id = :id',
            [':id' => $userId]
        );

        // Update session if user is logged in
        if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {
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
     * Handle payment webhook callback from Jaib
     *
     * @param array $payload Webhook payload data
     * @return array{success: bool, message: string}
     */
    public function handleWebhook(array $payload): array
    {
        // Verify webhook secret
        if (empty(JAIB_WEBHOOK_SECRET)) {
            return [
                'success' => false,
                'message' => 'Webhook secret not configured',
            ];
        }

        // Verify signature
        $receivedSignature = $payload['signature'] ?? '';
        $expectedSignature = $this->generateSignature($payload);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            Security::logActivity(null, 'webhook_invalid', 'Invalid webhook signature');
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }

        $transactionId = $payload['transaction_id'] ?? '';
        $status = $payload['status'] ?? '';

        if (empty($transactionId) || empty($status)) {
            return [
                'success' => false,
                'message' => 'Missing transaction data',
            ];
        }

        // Find payment record
        $payment = fetch(
            "SELECT * FROM payments WHERE transaction_id = :txid LIMIT 1",
            [':txid' => $transactionId]
        );

        if ($payment === null) {
            return [
                'success' => false,
                'message' => 'Payment not found',
            ];
        }

        // Update payment status based on webhook
        if ($status === 'approved' && $payment['status'] === 'PENDING') {
            update(
                'payments',
                ['status' => 'APPROVED'],
                'id = :id',
                [':id' => $payment['id']]
            );

            // Activate subscription
            $this->activateSubscription(
                (int) $payment['user_id'],
                $payment['plan'],
                (int) $payment['id']
            );

            Security::logActivity(
                (int) $payment['user_id'],
                'webhook_approved',
                'Webhook: Payment approved via callback'
            );

            return [
                'success' => true,
                'message' => 'Payment approved via webhook',
            ];
        }

        if ($status === 'rejected') {
            update(
                'payments',
                ['status' => 'REJECTED'],
                'id = :id',
                [':id' => $payment['id']]
            );

            Security::logActivity(
                (int) $payment['user_id'],
                'webhook_rejected',
                'Webhook: Payment rejected via callback'
            );

            return [
                'success' => true,
                'message' => 'Payment rejected via webhook',
            ];
        }

        return [
            'success' => true,
            'message' => 'Webhook processed',
        ];
    }

    /**
     * Get user's payment history
     *
     * @param int   $userId User ID
     * @param int   $limit  Max records (default: 20)
     * @param int   $offset Offset for pagination
     * @return array Array of payment records
     */
    public function getPaymentHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        return fetchAll(
            "SELECT * FROM payments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            [
                ':user_id' => $userId,
                ':limit'   => $limit,
                ':offset'  => $offset,
            ]
        );
    }

    /**
     * Get user's subscription history
     *
     * @param int $userId User ID
     * @return array Array of subscription records
     */
    public function getSubscriptionHistory(int $userId): array
    {
        return fetchAll(
            "SELECT s.*, p.amount, p.currency, p.status as payment_status
             FROM subscriptions s
             LEFT JOIN payments p ON s.payment_id = p.id
             WHERE s.user_id = :user_id
             ORDER BY s.started_at DESC",
            [':user_id' => $userId]
        );
    }

    /**
     * Get active subscription for a user
     *
     * @param int $userId User ID
     * @return array|null Active subscription or null
     */
    public function getActiveSubscription(int $userId): ?array
    {
        return fetch(
            "SELECT * FROM subscriptions WHERE user_id = :user_id AND is_active = 1 AND expires_at > :now ORDER BY expires_at DESC LIMIT 1",
            [
                ':user_id' => $userId,
                ':now'     => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Make an HTTP request to the Jaib API
     *
     * @param string $method HTTP method
     * @param string $url    API endpoint URL
     * @param array  $data   Request data
     * @return array|null Decoded response or null on failure
     */
    private function makeApiRequest(string $method, string $url, array $data = []): ?array
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'X-Merchant-ID: ' . $this->merchantId,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            error_log('cURL error: ' . $error);
            return null;
        }

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return null;
        }

        // Check for API-level errors
        if (isset($decoded['error'])) {
            error_log('Jaib API error: ' . ($decoded['error']['message'] ?? json_encode($decoded['error'])));
            return null;
        }

        return $decoded;
    }

    /**
     * Generate a signature for API requests
     *
     * @param array $payload Request payload
     * @return string HMAC-SHA256 signature
     */
    private function generateSignature(array $payload): string
    {
        // Sort payload keys alphabetically
        ksort($payload);

        // Build signature string
        $signatureParts = [];
        foreach ($payload as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            $signatureParts[] = $key . '=' . $value;
        }

        $signatureString = implode('&', $signatureParts) . $this->merchantSecret;

        return hash_hmac('sha256', $signatureString, $this->merchantSecret);
    }
}
