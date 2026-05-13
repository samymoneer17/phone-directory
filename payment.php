<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Payment Page
 * International Phone Directory - صفحة الدفع
 * ============================================================
 * صفحة دفع مستقلة بنظام جيب مع رقم المعاملة
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';

$pageTitle = 'الدفع - ' . SITE_NAME;

// التحقق من تسجيل الدخول
if (!$isLoggedIn) {
    flash('warning', 'يجب تسجيل الدخول أولاً للوصول لصفحة الدفع');
    redirect(getPageUrl('login.php'));
    exit;
}

$selectedPlan = strtoupper($_GET['plan'] ?? 'PRO');
if (!validatePlan($selectedPlan) || $selectedPlan === 'FREE') {
    $selectedPlan = 'PRO';
}

$paymentSuccess = getFlash('success');
$paymentError = getFlash('error');
$paymentSubmitted = false;
$paymentVerified = false;
$paymentMessage = '';
$verificationResult = null;

require_once __DIR__ . '/includes/jaib-payment.php';

// معالجة الدفع
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $paymentError = 'طلب غير صالح';
    } else {
        $plan = strtoupper($_POST['plan'] ?? '');
        $transactionId = trim($_POST['transaction_id'] ?? '');

        if (empty($plan) || !validatePlan($plan)) {
            $paymentError = 'يرجى اختيار باقة صالحة';
        } elseif (empty($transactionId)) {
            $paymentError = 'يرجى إدخال رقم العملية';
        } elseif (strlen($transactionId) < 8) {
            $paymentError = 'رقم العملية قصير جداً (8 أحرف على الأقل)';
        } else {
            $jaib = new JaibPayment();
            $result = $jaib->processPayment($currentUser['id'], $plan, $transactionId);

            if ($result['success']) {
                $paymentSubmitted = true;
                $paymentVerified = true;
                $paymentMessage = $result['message'];
                $verificationResult = $result;
                flash('success', $paymentMessage);
            } else {
                $paymentError = $result['message'];
                // حفظ رقم المعاملة لإعادة عرضه
                $lastTransactionId = $transactionId;
            }
        }
    }
}

// التحقق من حالة الاشتراك الحالي
$jaib = new JaibPayment();
$activeSubscription = $jaib->getActiveSubscription($currentUser['id']);
$userBalance = $jaib->getUserBalance($currentUser['id'], $selectedPlan);

$planConfig = PLANS[$selectedPlan];
$csrfToken = Security::getCSRFToken();
$lastTransactionId = $lastTransactionId ?? '';
?>

<!-- Payment Page -->
<section style="padding: 3rem 0 4rem; background: var(--bg-primary); min-height: 70vh;">
    <div class="container" style="max-width: 700px; padding: 0 1rem;">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 64px; height: 64px; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--accent), #06b6d4); border-radius: 1rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.3);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-primary);">صفحة الدفع</h1>
            <p style="font-size: 0.95rem; color: var(--text-secondary);">أكمل الدفع عبر محفظة جيب لتفعيل اشتراكك</p>
        </div>

        <!-- Payment Success Message -->
        <?php if ($paymentVerified && !empty($paymentSuccess)): ?>
        <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; max-width: 600px; margin: 0 auto 2rem; border-radius: 0.75rem; padding: 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <svg style="flex-shrink:0; margin-top: 2px;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <div style="font-weight: 700; margin-bottom: 0.25rem;">تم الدفع بنجاح!</div>
                <div><?php echo sanitizeOutput($paymentSuccess); ?></div>
                <?php if ($verificationResult && isset($verificationResult['subscription'])): ?>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #047857;">
                    تاريخ الانتهاء: <strong><?php echo sanitizeOutput($verificationResult['subscription']['expires_at'] ?? ''); ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Error -->
        <?php if (!empty($paymentError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; max-width: 600px; margin: 0 auto 2rem; border-radius: 0.75rem; padding: 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <svg style="flex-shrink:0; margin-top: 2px;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div>
                <div style="font-weight: 700; margin-bottom: 0.25rem;">فشل التحقق من الدفع</div>
                <div><?php echo sanitizeOutput($paymentError); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Subscription Notice -->
        <?php if ($activeSubscription): ?>
        <div style="background: #DBEAFE; border: 1px solid #BFDBFE; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 2rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <svg style="flex-shrink:0; margin-top: 2px;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1E40AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div style="color: #1E40AF;">
                <div style="font-weight: 700; margin-bottom: 0.25rem;">لديك اشتراك نشط</div>
                <div>الباقة: <strong><?php echo sanitizeOutput(PLANS[$activeSubscription['plan']]['name'] ?? $activeSubscription['plan']); ?></strong> - تنتهي في: <strong><?php echo sanitizeOutput($activeSubscription['expires_at']); ?></strong></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Card -->
        <div class="card" style="border: 2px solid var(--accent); border-radius: 1.25rem; box-shadow: 0 8px 30px -6px rgba(16, 185, 129, 0.2); overflow: hidden;">

            <!-- Plan Selection Header -->
            <div style="background: linear-gradient(135deg, var(--accent), #06b6d4); padding: 1.5rem; color: white; text-align: center;">
                <div style="font-size: 0.85rem; opacity: 0.9; margin-bottom: 0.5rem;">اختر الباقة</div>
                <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                    <?php foreach (['PRO', 'PREMIUM'] as $planKey): ?>
                    <button type="button" class="plan-select-btn" data-plan="<?php echo $planKey; ?>" onclick="selectPlan('<?php echo $planKey; ?>')" style="padding: 0.75rem 1.5rem; border-radius: 0.75rem; border: 2px solid <?php echo $selectedPlan === $planKey ? 'white' : 'rgba(255,255,255,0.3)'; ?>; background: <?php echo $selectedPlan === $planKey ? 'rgba(255,255,255,0.2)' : 'transparent'; ?>; color: white; font-weight: 700; font-family: inherit; cursor: pointer; transition: all 0.2s; font-size: 0.95rem;">
                        <?php echo sanitizeOutput(PLANS[$planKey]['name']); ?>
                        <div style="font-size: 1.25rem; margin-top: 0.25rem;"><?php echo number_format(PLANS[$planKey]['price']); ?> ر.ي</div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="padding: 2rem;">

                <!-- Payment Instructions -->
                <div style="background: var(--bg-secondary); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #06b6d4); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </div>
                        <div>
                            <div style="font-size: 1rem; font-weight: 700; color: var(--text-primary);">الدفع عبر محفظة جيب</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">حوّل المبلغ ثم أدخل رقم العملية</div>
                        </div>
                    </div>

                    <!-- Account Number -->
                    <div style="background: var(--bg-card); border: 2px dashed var(--accent); border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem; text-align: center;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">حوّل المبلغ إلى هذا الرقم:</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--accent); letter-spacing: 2px; direction: ltr; display: inline-block; font-family: 'Inter', monospace;" id="receiverAccount"><?php echo JAIB_RECEIVER_ACCOUNT; ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo sanitizeOutput(JAIB_RECEIVER_NAME); ?></div>
                        <button type="button" onclick="copyAccount()" style="margin-top: 0.5rem; padding: 0.35rem 0.75rem; border-radius: 0.375rem; border: 1px solid var(--accent); background: var(--accent-light); color: var(--accent); font-size: 0.75rem; font-weight: 600; cursor: pointer; font-family: inherit;">
                            نسخ الرقم
                        </button>
                    </div>

                    <!-- Steps -->
                    <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 2;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">1</span>
                            افتح تطبيق محفظة جيب
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">2</span>
                            اذهب لقسم التحويل
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">3</span>
                            أدخل رقم المستقبل: <strong style="color: var(--text-primary); direction: ltr; display: inline-block;"><?php echo JAIB_RECEIVER_ACCOUNT; ?></strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">4</span>
                            أدخل المبلغ <strong id="paymentAmountDisplay" style="color: var(--text-primary);"><?php echo number_format(PLANS[$selectedPlan]['price']); ?></strong> ر.ي
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">5</span>
                            أكمل عملية التحويل
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 22px; height: 22px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">6</span>
                            انسخ رقم العملية وأدخله أدناه
                        </div>
                    </div>

                    <!-- Partial Payment Note -->
                    <div style="background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 0.5rem; padding: 0.75rem; margin-top: 1rem; font-size: 0.8rem; color: #92400E; display: flex; align-items: flex-start; gap: 0.5rem;">
                        <svg style="flex-shrink:0; margin-top: 2px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div>يمكنك الدفع على دفعات! إذا لم يكن لديك المبلغ الكامل، ادفع ما تستطيع وسيتجمع الرصيد تلقائياً.
                        <?php if ($userBalance['balance'] > 0): ?>
                            <br>رصيدك الحالي: <strong><?php echo number_format($userBalance['balance']); ?> ر.ي</strong> - المتبقي: <strong><?php echo number_format($userBalance['remaining']); ?> ر.ي</strong>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <form method="POST" action="" id="paymentForm" onsubmit="return validatePaymentForm()">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="plan" id="paymentPlanInput" value="<?php echo sanitizeOutput($selectedPlan); ?>">

                    <!-- Transaction ID Field -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                            رقم العملية من محفظة جيب
                            <span style="color: var(--danger);">*</span>
                        </label>
                        <div style="position: relative;">
                            <input type="text" name="transaction_id" id="transactionIdInput" class="input" placeholder="أدخل رقم العملية هنا (مثال: 17786750694952)" value="<?php echo sanitizeOutput($lastTransactionId); ?>" required style="direction: ltr; text-align: left; font-family: 'Inter', monospace; font-size: 1.1rem; letter-spacing: 1px; padding: 1rem; border: 2px solid var(--border-color); border-radius: 0.75rem; width: 100%; background: var(--bg-card); color: var(--text-primary); transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            <div style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </div>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                            أدخل رقم العملية الذي تحصل عليه بعد إتمام الدفع من تطبيق جيب
                        </div>
                        <div id="transactionIdError" style="display: none; font-size: 0.8rem; color: var(--danger); margin-top: 0.25rem;"></div>
                    </div>

                    <!-- Plan Summary -->
                    <div style="background: var(--bg-secondary); border-radius: 0.75rem; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">الباقة المختارة:</span>
                            <span style="font-weight: 700; color: var(--accent);" id="selectedPlanDisplay"><?php echo sanitizeOutput($planConfig['name']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">المدة:</span>
                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo sanitizeOutput($planConfig['duration_text']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">المبلغ:</span>
                            <span style="font-weight: 800; font-size: 1.15rem; color: var(--text-primary);" id="selectedPlanPrice"><?php echo number_format($planConfig['price']); ?> ر.ي</span>
                        </div>
                        <?php if ($userBalance['balance'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.5rem;">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">رصيدك المتراكم:</span>
                            <span style="font-weight: 700; color: #059669;"><?php echo number_format($userBalance['balance']); ?> ر.ي</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.05rem; border-radius: 0.75rem;" id="paymentSubmitBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تحقق من الدفع
                    </button>
                </form>

                <!-- Quick Actions -->
                <div style="display: flex; justify-content: center; gap: 1.5rem; margin-top: 1.5rem;">
                    <a href="<?php echo getPageUrl('plans.php'); ?>" style="font-size: 0.85rem; color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        العودة للباقات
                    </a>
                    <a href="<?php echo getPageUrl('index.php'); ?>" style="font-size: 0.85rem; color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        الرئيسية
                    </a>
                </div>
            </div>
        </div>

        <!-- Features Included -->
        <div style="margin-top: 2rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; text-align: center;">ما ستحصل عليه</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;" id="planFeaturesGrid">
                <?php foreach ($planConfig['features'] as $feature): ?>
                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php echo sanitizeOutput($feature); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FAQ -->
        <div style="margin-top: 2.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; text-align: center;">أسئلة شائعة عن الدفع</h3>
            <div style="text-align: right;">
                <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-light);">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">من أين أحصل على رقم العملية؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.8;">بعد إتمام التحويل في تطبيق جيب، ستظهر لك شاشة تأكيد تحتوي على رقم العملية (Transaction ID). انسخ هذا الرقم وأدخله في الحقل أعلاه.</p>
                </div>
                <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-light);">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">هل يمكنني الدفع على دفعات؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.8;">نعم! يمكنك تحويل أي مبلغ وسيتجمع رصيدك تلقائياً. عندما يصل الرصيد إلى سعر الباقة، يتم تفعيل الاشتراك فوراً.</p>
                </div>
                <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-light);">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">كم يستغرق التحقق من الدفع؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.8;">يتم التحقق فوراً عبر الاتصال المباشر بسيرفرات جيب. بمجرد إدخال رقم العملية الصحيح، يتم تفعيل الاشتراك خلال ثوانٍ.</p>
                </div>
                <div style="padding: 1rem 0;">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">هل يمكنني استرداد أموالي؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.8;">نعم، يمكنك طلب استرداد الأموال خلال 24 ساعة من تاريخ الشراء عبر التواصل مع فريق الدعم.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    @media (max-width: 480px) {
        .plan-select-btn { padding: 0.5rem 1rem !important; font-size: 0.85rem !important; }
        #planFeaturesGrid { grid-template-columns: 1fr !important; }
    }
</style>

<script>
var currentPlan = '<?php echo sanitizeOutput($selectedPlan); ?>';
var plans = {
    'PRO': { name: 'احترافي', price: '<?php echo number_format(PLANS['PRO']['price']); ?>', priceNum: <?php echo PLANS['PRO']['price']; ?>, duration: '<?php echo sanitizeOutput(PLANS['PRO']['duration_text']); ?>' },
    'PREMIUM': { name: 'مميز', price: '<?php echo number_format(PLANS['PREMIUM']['price']); ?>', priceNum: <?php echo PLANS['PREMIUM']['price']; ?>, duration: '<?php echo sanitizeOutput(PLANS['PREMIUM']['duration_text']); ?>' }
};

function selectPlan(plan) {
    currentPlan = plan;
    document.getElementById('paymentPlanInput').value = plan;
    document.getElementById('selectedPlanDisplay').textContent = plans[plan].name;
    document.getElementById('selectedPlanPrice').textContent = plans[plan].price + ' ر.ي';
    document.getElementById('paymentAmountDisplay').textContent = plans[plan].price;

    // Update button styles
    document.querySelectorAll('.plan-select-btn').forEach(function(btn) {
        var btnPlan = btn.getAttribute('data-plan');
        if (btnPlan === plan) {
            btn.style.borderColor = 'white';
            btn.style.background = 'rgba(255,255,255,0.2)';
        } else {
            btn.style.borderColor = 'rgba(255,255,255,0.3)';
            btn.style.background = 'transparent';
        }
    });
}

function copyAccount() {
    var account = document.getElementById('receiverAccount').textContent.trim();
    navigator.clipboard.writeText(account).then(function() {
        var btn = event.target;
        var original = btn.textContent;
        btn.textContent = 'تم النسخ!';
        btn.style.background = '#D1FAE5';
        btn.style.color = '#065F46';
        btn.style.borderColor = '#6EE7B7';
        setTimeout(function() {
            btn.textContent = original;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    }).catch(function() {
        // Fallback for older browsers
        var input = document.createElement('input');
        input.value = account;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    });
}

function validatePaymentForm() {
    var transactionId = document.getElementById('transactionIdInput').value.trim();
    var errorEl = document.getElementById('transactionIdError');

    if (!transactionId) {
        errorEl.textContent = 'يرجى إدخال رقم العملية';
        errorEl.style.display = 'block';
        return false;
    }

    if (transactionId.length < 8) {
        errorEl.textContent = 'رقم العملية قصير جداً (8 أحرف على الأقل)';
        errorEl.style.display = 'block';
        return false;
    }

    errorEl.style.display = 'none';

    // Show loading state
    var btn = document.getElementById('paymentSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> جاري التحقق...';

    return true;
}

// Add spin animation
var style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
