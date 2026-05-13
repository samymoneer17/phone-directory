<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Plans & Pricing Page
 * International Phone Directory
 * ============================================================
 */

$pageTitle = 'الباقات والأسعار - ' . SITE_NAME;

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';

$selectedPlan = $_GET['plan'] ?? '';
$paymentSuccess = getFlash('success');
$paymentError = getFlash('error');
$paymentSubmitted = false;
$paymentVerified = false;
$paymentMessage = '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        $paymentError = 'طلب غير صالح';
    } else {
        require_once __DIR__ . '/includes/jaib-payment.php';
        $plan = strtoupper($_POST['plan'] ?? '');
        $transactionId = trim($_POST['transaction_id'] ?? '');

        if (empty($plan) || !validatePlan($plan)) {
            $paymentError = 'يرجى اختيار باقة صالحة';
        } elseif (empty($transactionId)) {
            $paymentError = 'يرجى إدخال رقم العملية';
        } else {
            $jaib = new JaibPayment();
            $result = $jaib->processPayment($currentUser['id'], $plan, $transactionId);

            if ($result['success']) {
                $paymentSubmitted = true;
                $paymentVerified = true;
                $paymentMessage = $result['message'];
                flash('success', $paymentMessage);
                redirect(getPageUrl('plans.php') . '?plan=' . $plan . '&status=success');
                exit;
            } else {
                $paymentError = $result['message'];
            }
        }
    }
}

// Check status query param
$status = $_GET['status'] ?? '';
if ($status === 'success') {
    $paymentSubmitted = true;
    $paymentVerified = true;
}

$csrfToken = Security::getCSRFToken();
?>

<!-- Plans Page -->
<section style="padding: 3rem 0 4rem; background: var(--bg-primary); min-height: 60vh;">
    <div class="container" style="max-width: 1100px; padding: 0 1rem;">

        <!-- Header -->
        <div class="pricing-header" style="text-align: center; margin-bottom: 3rem;">
            <h2 style="font-size: 2.25rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-primary);">اختر خطتك المناسبة</h2>
            <p style="font-size: 1.05rem; color: var(--text-secondary); max-width: 550px; margin: 0 auto; line-height: 1.7;">
                قارن بين الباقات واختر الأنسب لاحتياجاتك. يمكنك الترقية في أي وقت.
            </p>
        </div>

        <!-- Payment Success Message -->
        <?php if ($paymentVerified && !empty($paymentSuccess)): ?>
        <div class="alert" style="background: #D1FAE5; border: 1px solid #A7F3D0; color: #065F46; max-width: 600px; margin: 0 auto 2rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($paymentSuccess); ?></div></div>
        </div>
        <?php endif; ?>

        <!-- Payment Error -->
        <?php if (!empty($paymentError)): ?>
        <div class="alert" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; max-width: 600px; margin: 0 auto 2rem;">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="alert-content"><div><?php echo sanitizeOutput($paymentError); ?></div></div>
        </div>
        <?php endif; ?>

        <!-- Pricing Grid -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; max-width: 1000px; margin: 0 auto;">

            <!-- FREE Plan -->
            <div class="plan-card" style="background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 1.25rem; padding: 2rem; display: flex; flex-direction: column; transition: all 0.3s;">
                <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                    <div class="plan-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">مجاني</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">للمستخدمين العاديين</div>
                </div>
                <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                    <span style="font-size: 3.25rem; font-weight: 800; color: var(--text-primary); font-family: 'Inter'; direction: ltr; display: inline-block;">0</span>
                    <span style="font-size: 1.25rem; font-weight: 600; color: var(--text-secondary);"> ر.ي</span>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">مجاني للأبد</div>
                </div>
                <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                    <?php foreach (PLANS['FREE']['features'] as $feature): ?>
                    <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo sanitizeOutput($feature); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: auto;">
                    <?php if ($isLoggedIn && $currentUser['plan'] === 'FREE'): ?>
                    <div class="btn btn-ghost" style="width: 100%; justify-content: center; cursor: default; opacity: 0.5;">الباقة الحالية</div>
                    <?php else: ?>
                    <a href="<?php echo getPageUrl('register.php'); ?>" class="btn btn-secondary btn-lg" style="width: 100%; justify-content: center; text-decoration: none;">ابدأ مجاناً</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRO Plan -->
            <div class="plan-card" style="background: var(--bg-card); border: 2px solid var(--accent); border-radius: 1.25rem; padding: 2rem; display: flex; flex-direction: column; position: relative; transform: scale(1.04); box-shadow: 0 8px 30px -6px rgba(16, 185, 129, 0.25); z-index: 1;">
                <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); padding: 0.3rem 1rem; background: linear-gradient(135deg, var(--accent), #06b6d4); color: white; font-size: 0.75rem; font-weight: 700; border-radius: 9999px; white-space: nowrap; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">⭐ الأكثر شعبية</div>
                <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                    <div class="plan-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">احترافي</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">للمحترفين</div>
                </div>
                <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                    <span style="font-size: 3.25rem; font-weight: 800; color: var(--text-primary); font-family: 'Inter'; direction: ltr; display: inline-block;">2,000</span>
                    <span style="font-size: 1.25rem; font-weight: 600; color: var(--text-secondary);"> ر.ي</span>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">شهرياً</div>
                </div>
                <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                    <?php foreach (PLANS['PRO']['features'] as $feature): ?>
                    <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo sanitizeOutput($feature); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: auto;">
                    <?php if ($isLoggedIn && $currentUser['plan'] === 'PRO' && $subscription['active']): ?>
                    <div class="btn btn-primary" style="width: 100%; justify-content: center; cursor: default; opacity: 0.7;">الباقة الحالية</div>
                    <?php else: ?>
                    <button type="button" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;" onclick="window.location.href='<?php echo getPageUrl('payment.php'); ?>?plan=PRO'">
                        اشترك الآن
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PREMIUM Plan -->
            <div class="plan-card" style="background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 1.25rem; padding: 2rem; display: flex; flex-direction: column; transition: all 0.3s;">
                <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                    <div class="plan-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">مميز</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">للشركات والأعمال</div>
                </div>
                <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                    <span style="font-size: 3.25rem; font-weight: 800; color: var(--text-primary); font-family: 'Inter'; direction: ltr; display: inline-block;">5,000</span>
                    <span style="font-size: 1.25rem; font-weight: 600; color: var(--text-secondary);"> ر.ي</span>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">شهرياً</div>
                </div>
                <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                    <?php foreach (PLANS['PREMIUM']['features'] as $feature): ?>
                    <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo sanitizeOutput($feature); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: auto;">
                    <?php if ($isLoggedIn && $currentUser['plan'] === 'PREMIUM' && $subscription['active']): ?>
                    <div class="btn btn-primary" style="width: 100%; justify-content: center; cursor: default; opacity: 0.7;">الباقة الحالية</div>
                    <?php else: ?>
                    <button type="button" class="btn btn-secondary btn-lg" style="width: 100%; justify-content: center;" onclick="window.location.href='<?php echo getPageUrl('payment.php'); ?>?plan=PREMIUM'">
                        اشترك الآن
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Section (hidden by default) -->
        <div id="paymentSection" style="display: none; max-width: 550px; margin: 3rem auto 0;">
            <div class="card" style="padding: 2rem; border: 2px solid var(--accent); border-radius: 1.25rem; box-shadow: 0 8px 30px -6px rgba(16, 185, 129, 0.2);">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; vertical-align: middle; margin-left: 0.25rem;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12h6"/></svg>
                        إتمام الدفع
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        الباقة المختارة: <strong id="selectedPlanName" style="color: var(--accent);"></strong>
                        - <strong id="selectedPlanPrice" style="color: var(--text-primary);"></strong> ر.ي/شهر
                    </p>
                </div>

                <form method="POST" action="" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="plan" id="paymentPlanInput">

                    <div style="background: var(--bg-secondary); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">الدفع عبر محفظة جيب</span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.8;">
                            <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.75rem; text-align: center;">
                                <span style="color: var(--text-secondary); font-size: 0.75rem;">حوّل المبلغ إلى هذا الرقم:</span><br>
                                <strong style="font-size: 1.25rem; color: var(--accent); letter-spacing: 1px; direction: ltr; display: inline-block;"><?php echo JAIB_RECEIVER_ACCOUNT; ?></strong>
                            </div>
                            1. افتح تطبيق محفظة جيب<br>
                            2. اذهب لقسم التحويل<br>
                            3. أدخل رقم المستقبل: <strong style="color: var(--text-primary); direction: ltr; display: inline-block;"><?php echo JAIB_RECEIVER_ACCOUNT; ?></strong><br>
                            4. أدخل المبلغ <strong id="paymentAmount" style="color: var(--text-primary);"></strong> ر.ي<br>
                            5. أكمل عملية التحويل<br>
                            6. أدخل رقم العملية في الحقل أدناه
                        </div>
                        <div style="background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 0.5rem; padding: 0.6rem; margin-top: 0.5rem; font-size: 0.75rem; color: #92400E;">
                            💡 يمكنك الدفع على دفعات! إذا لم يكن لديك المبلغ الكامل، ادفع ما تستطيع وسيتجمع الرصيد تلقائياً.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="label label-required">رقم العملية من محفظة جيب</label>
                        <input type="text" name="transaction_id" class="input" placeholder="أدخل رقم العملية هنا" required style="direction: ltr; text-align: left;">
                        <div class="form-hint">أدخل رقم العملية الذي تحصل عليه بعد إتمام الدفع</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;" id="paymentSubmitBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تحقق من الدفع
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="javascript:void(0)" onclick="hidePaymentForm()" style="font-size: 0.85rem; color: var(--text-secondary); text-decoration: underline;">إلغاء</a>
                </div>
            </div>
        </div>

        <!-- FAQ -->
        <div style="max-width: 700px; margin: 3rem auto 0; text-align: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">أسئلة شائعة</h3>
            <div style="text-align: right;">
                <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-light);">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">هل يمكنني استرداد أموالي؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">نعم، يمكنك طلب استرداد الأموال خلال 24 ساعة من تاريخ الشراء.</p>
                </div>
                <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-light);">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">كيف أقوم بالدفع؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">نقبل الدفع عبر محفظة جيب. أدخل رقم العملية بعد إتمام الدفع وسيتم التحقق تلقائياً.</p>
                </div>
                <div style="padding: 1rem 0;">
                    <h4 style="font-size: 0.925rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">هل الاشتراك يتجدد تلقائياً؟</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">لا، يجب تجديد الاشتراك يدوياً عند انتهاء المدة. سنرسل لك إشعاراً قبل انتهاء الاشتراك.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    @media (max-width: 768px) {
        .plan-card { transform: scale(1) !important; }
        div[style*="grid-template-columns: repeat(3"] { grid-template-columns: 1fr !important; }
    }
</style>

<script>
function showPaymentForm(plan) {
    var plans = {
        'PRO': { name: 'احترافي', price: '2,000' },
        'PREMIUM': { name: 'مميز', price: '5,000' }
    };
    document.getElementById('selectedPlanName').textContent = plans[plan].name;
    document.getElementById('selectedPlanPrice').textContent = plans[plan].price;
    document.getElementById('paymentAmount').textContent = plans[plan].price;
    document.getElementById('paymentPlanInput').value = plan;
    document.getElementById('paymentSection').style.display = 'block';
    document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth' });
}

function hidePaymentForm() {
    document.getElementById('paymentSection').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
