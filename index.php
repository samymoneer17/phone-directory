<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Homepage
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'الرئيسية - ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero" id="hero">
    <!-- Floating Decorative Elements -->
    <div class="hero-float hero-float-1"></div>
    <div class="hero-float hero-float-2"></div>
    <div class="hero-float hero-float-3"></div>
    <div class="hero-float hero-float-4"></div>

    <div class="hero-content">
        <!-- Badge -->
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            أكثر من 50 دولة مدعومة
        </div>

        <!-- Title -->
        <h1 class="hero-title" style="background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
            دليل الهاتف الدولي
        </h1>

        <!-- Subtitle -->
        <p class="hero-subtitle">
            ابحث عن أي رقم أو اسم في ثوانٍ. اكتشف هوية المتصلين المجهولين من أكثر من 50 دولة حول العالم.
        </p>

        <!-- Search Box -->
        <div class="hero-search">
            <div class="search-box-inner" id="heroSearchBox">
                <div class="search-country-code" id="heroCountryCode" style="display:none;">
                    <span class="search-country-flag" id="heroCountryFlag">🌍</span>
                    <span class="search-country-dial" id="heroCountryDial"></span>
                </div>
                <input type="text" class="search-input" id="heroSearchInput" placeholder="أدخل الاسم أو الرقم (مثال: 966512345678)" autocomplete="off" dir="ltr" style="text-align:right;">
                <button class="search-btn" id="heroSearchBtn" onclick="window.location.href='<?php echo getPageUrl('search.php'); ?>?q=' + encodeURIComponent(document.getElementById('heroSearchInput').value)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    بحث
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stats-bar-item">
                <div class="stats-bar-number" data-target="100000">0</div>
                <div class="stats-bar-label">رقم في القاعدة</div>
            </div>
            <div class="stats-bar-divider"></div>
            <div class="stats-bar-item">
                <div class="stats-bar-number" data-target="3">0</div>
                <div class="stats-bar-label">أدوات بحث</div>
            </div>
            <div class="stats-bar-divider"></div>
            <div class="stats-bar-item">
                <div class="stats-bar-number" data-target="50">0</div>
                <div class="stats-bar-label">دولة مدعومة</div>
            </div>
            <div class="stats-bar-divider"></div>
            <div class="stats-bar-item">
                <div class="stats-bar-number" data-target="10000">0</div>
                <div class="stats-bar-label">مستخدم نشط</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section" style="padding: 5rem 0; background: var(--bg-primary);">
    <div class="container" style="max-width: 1100px;">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-primary);">لماذا دليل الهاتف الدولي؟</h2>
            <p style="font-size: 1rem; color: var(--text-secondary); max-width: 500px; margin: 0 auto;">نوفر لك أفضل تجربة بحث عن الأرقام الهاتفية بأحدث التقنيات</p>
        </div>

        <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 1.5rem;" id="featuresGrid">
            <!-- Feature 1 -->
            <div class="card card-hover" style="text-align: center; padding: 2rem 1.5rem;">
                <div style="width: 60px; height: 60px; margin: 0 auto 1.25rem; background: #DBEAFE; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#1E40AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">بحث سريع</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">احصل على نتائج فورية مع البحث الذكي الذي يتعرف على الدولة تلقائياً</p>
            </div>

            <!-- Feature 2 -->
            <div class="card card-hover" style="text-align: center; padding: 2rem 1.5rem;">
                <div style="width: 60px; height: 60px; margin: 0 auto 1.25rem; background: #D1FAE5; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">3 أدوات بحث حقيقية</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">اكواتس + لوليغرام + يمن فون بوك - نتائج حقيقية من مصادر متعددة</p>
            </div>

            <!-- Feature 3 -->
            <div class="card card-hover" style="text-align: center; padding: 2rem 1.5rem;">
                <div style="width: 60px; height: 60px; margin: 0 auto 1.25rem; background: #FEF3C7; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">دفع آمن عبر جيب</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">نظام دفع آمن وموثوق عبر محفظة جيب مع دعم الدفع الجزئي والتراكم</p>
            </div>

            <!-- Feature 4 -->
            <div class="card card-hover" style="text-align: center; padding: 2rem 1.5rem;">
                <div style="width: 60px; height: 60px; margin: 0 auto 1.25rem; background: #EDE9FE; border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">جميع الدول</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">ندعم أكثر من 50 دولة حول العالم مع التعرف التلقائي على الدولة والمشغل</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section style="padding: 5rem 0; background: var(--bg-secondary);">
    <div class="container" style="max-width: 900px;">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-primary);">كيف يعمل؟</h2>
            <p style="font-size: 1rem; color: var(--text-secondary); max-width: 450px; margin: 0 auto;">ثلاث خطوات بسيطة للحصول على النتائج</p>
        </div>

        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 2rem;">
            <!-- Step 1 -->
            <div style="text-align: center; position: relative;">
                <div style="width: 72px; height: 72px; margin: 0 auto 1.25rem; background: linear-gradient(135deg, #1E40AF, #3B82F6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.75rem; font-weight: 800; font-family: 'Inter', sans-serif; box-shadow: 0 8px 24px -4px rgba(30, 64, 175, 0.35);">
                    1
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">أدخل الرقم أو الاسم</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">اكتب رقم الهاتف أو اسم الشخص الذي تبحث عنه في خانة البحث</p>
                <!-- Arrow (hidden on mobile) -->
                <div style="display: none; position: absolute; top: 36px; left: -30px; color: #CBD5E1;" class="step-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </div>
            </div>

            <!-- Step 2 -->
            <div style="text-align: center; position: relative;">
                <div style="width: 72px; height: 72px; margin: 0 auto 1.25rem; background: linear-gradient(135deg, #059669, #10B981); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.75rem; font-weight: 800; font-family: 'Inter', sans-serif; box-shadow: 0 8px 24px -4px rgba(5, 150, 105, 0.35);">
                    2
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">كشف الدولة تلقائياً</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">يقوم النظام بتحديد الدولة والمشغل تلقائياً من الرقم المدخل</p>
            </div>

            <!-- Step 3 -->
            <div style="text-align: center;">
                <div style="width: 72px; height: 72px; margin: 0 auto 1.25rem; background: linear-gradient(135deg, #D97706, #F59E0B); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.75rem; font-weight: 800; font-family: 'Inter', sans-serif; box-shadow: 0 8px 24px -4px rgba(217, 119, 6, 0.35);">
                    3
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">احصل على النتائج</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; margin: 0;">اعرض تفاصيل النتائج الكاملة مع اسم صاحب الرقم والمشغل والدولة</p>
            </div>
        </div>
    </div>
</section>

<!-- Plans Preview Section -->
<section class="pricing-section" style="padding: 5rem 0; background: var(--bg-primary);">
    <div class="pricing-header">
        <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-primary);">اختر خطتك المناسبة</h2>
        <p style="font-size: 1rem; color: var(--text-secondary);">ابدأ مجاناً وقم بالترقية متى شئت</p>
    </div>

    <div class="pricing-grid" style="max-width: 1000px; margin: 0 auto; padding: 0 1rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
        <!-- Free Plan -->
        <div class="plan-card" style="background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 1.25rem; padding: 2rem;">
            <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                <div class="plan-name" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">مجاني</div>
                <div class="plan-description" style="font-size: 0.8rem; color: var(--text-muted);">للمستخدمين العاديين</div>
            </div>
            <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                <span class="plan-price-amount" style="font-size: 3rem; font-weight: 800; font-family: 'Inter'; color: var(--text-primary); direction: ltr;">0</span>
                <span class="plan-price-currency" style="font-size: 1.25rem; color: var(--text-secondary);">ر.ي</span>
                <div class="plan-price-period" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">مجاني للأبد</div>
            </div>
            <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    10 عمليات بحث يومياً
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    نتائج أساسية
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    البحث بالاسم والرقم
                </div>
            </div>
            <div class="plan-footer" style="margin-top: auto;">
                <a href="<?php echo getPageUrl('register.php'); ?>" class="btn btn-secondary btn-lg" style="width: 100%; justify-content: center;">ابدأ مجاناً</a>
            </div>
        </div>

        <!-- Pro Plan -->
        <div class="plan-card plan-popular" style="background: var(--bg-card); border: 2px solid var(--accent); border-radius: 1.25rem; padding: 2rem; position: relative; transform: scale(1.03); box-shadow: 0 8px 30px -6px rgba(16, 185, 129, 0.25); z-index: 1;">
            <div class="plan-badge" style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); padding: 0.3rem 1rem; background: linear-gradient(135deg, var(--accent), #06b6d4); color: white; font-size: 0.75rem; font-weight: 700; border-radius: 9999px; white-space: nowrap; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">الأكثر شعبية</div>
            <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                <div class="plan-name" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">احترافي</div>
                <div class="plan-description" style="font-size: 0.8rem; color: var(--text-muted);">للمحترفين</div>
            </div>
            <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                <span class="plan-price-amount" style="font-size: 3rem; font-weight: 800; font-family: 'Inter'; color: var(--text-primary); direction: ltr;">2,000</span>
                <span class="plan-price-currency" style="font-size: 1.25rem; color: var(--text-secondary);">ر.ي</span>
                <div class="plan-price-period" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">شهرياً</div>
            </div>
            <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    100 عملية بحث يومياً
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    نتائج تفصيلية
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    إخفاء رقم الهاتف
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    إحصائيات متقدمة
                </div>
            </div>
            <div class="plan-footer" style="margin-top: auto;">
                <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">اشترك الآن</a>
            </div>
        </div>

        <!-- Premium Plan -->
        <div class="plan-card" style="background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 1.25rem; padding: 2rem;">
            <div class="plan-header" style="text-align: center; margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                <div class="plan-name" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">مميز</div>
                <div class="plan-description" style="font-size: 0.8rem; color: var(--text-muted);">للشركات والأعمال</div>
            </div>
            <div class="plan-price" style="text-align: center; margin-bottom: 1.75rem;">
                <span class="plan-price-amount" style="font-size: 3rem; font-weight: 800; font-family: 'Inter'; color: var(--text-primary); direction: ltr;">5,000</span>
                <span class="plan-price-currency" style="font-size: 1.25rem; color: var(--text-secondary);">ر.ي</span>
                <div class="plan-price-period" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">شهرياً</div>
            </div>
            <div class="plan-features" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    بحث غير محدود
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    كل مميزات الاحترافي
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    أسبقية في البحث
                </div>
                <div class="plan-feature" style="display: flex; align-items: center; gap: 0.625rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    دعم فني VIP
                </div>
            </div>
            <div class="plan-footer" style="margin-top: auto;">
                <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-secondary btn-lg" style="width: 100%; justify-content: center;">اشترك الآن</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 5rem 0; background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 50%, #06b6d4 100%); text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: url('data:image/svg+xml,<svg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"><circle cx=\"30\" cy=\"30\" r=\"1\" fill=\"rgba(255,255,255,0.1)\"/></svg>'); opacity: 0.5;"></div>
    <div style="position: relative; z-index: 1;">
        <h2 style="font-size: 2.25rem; font-weight: 800; color: white; margin-bottom: 1rem;">جاهز للبدء؟</h2>
        <p style="font-size: 1.1rem; color: rgba(255,255,255,0.85); margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">انضم إلى آلاف المستخدمين وابدأ في البحث عن الأرقام الآن مجاناً</p>
        <a href="<?php echo getPageUrl('register.php'); ?>" class="btn" style="background: white; color: #1E40AF; font-weight: 700; font-size: 1.1rem; padding: 1rem 2.5rem; border-radius: 1rem; box-shadow: 0 8px 24px -4px rgba(0,0,0,0.2);">
            ابدأ الآن مجاناً
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
    </div>
</section>

<!-- Responsive overrides -->
<style>
    @media (max-width: 768px) {
        #featuresGrid { grid-template-columns: repeat(2, 1fr) !important; }
        .pricing-grid { grid-template-columns: 1fr !important; max-width: 400px !important; }
        .plan-popular { transform: scale(1) !important; }
        .hero-title { font-size: 2.25rem !important; }
        .hero-subtitle { font-size: 1rem !important; }
        .stats-bar { gap: 1.5rem !important; }
        .stats-bar-number { font-size: 1.5rem !important; }
        .step-arrow { display: none !important; }
    }
    @media (max-width: 480px) {
        #featuresGrid { grid-template-columns: 1fr !important; }
    }
</style>

<!-- Scripts -->
<script src="<?php echo getPageUrl('assets/js/search.js'); ?>"></script>
<script src="<?php echo getPageUrl('assets/js/app.js'); ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
