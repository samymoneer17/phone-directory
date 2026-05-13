<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - HTML Footer
 * International Phone Directory
 * ============================================================
 * Professional footer with glassmorphism, 3-column layout,
 * social links, and copyright notice.
 */

// Access current user if available
$currentUser = Auth::getCurrentUser();
$currentYear = date('Y');
?>
    </main><!-- /.main-content -->

    <!-- ============================================================
         Footer
         ============================================================ -->
    <footer class="site-footer" role="contentinfo">
        <div class="footer-wave">
            <svg viewBox="0 0 1440 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0,40 C360,100 1080,0 1440,60 L1440,100 L0,100 Z" fill="var(--bg-glass)"/>
            </svg>
        </div>

        <div class="footer-glass">
            <div class="footer-container">
                <!-- Footer Top: 3 Columns -->
                <div class="footer-grid">
                    <!-- Column 1: About -->
                    <div class="footer-col">
                        <div class="footer-brand">
                            <a href="<?php echo getPageUrl('index.php'); ?>" class="footer-logo">
                                <span class="footer-logo-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </span>
                                <span class="footer-logo-text">دليل الهاتف الدولي</span>
                            </a>
                            <p class="footer-description">
                                دليل هاتف دولي شامل يساعدك على البحث عن الأرقام والتعرف على هواتف مجهولة من جميع دول العالم. نقدم لك خدمة بحث سريعة ودقيقة.
                            </p>
                        </div>

                        <!-- Social Links -->
                        <div class="footer-social">
                            <a href="#" class="footer-social-link" aria-label="فيسبوك" title="فيسبوك">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                            </a>
                            <a href="#" class="footer-social-link" aria-label="تويتر" title="تويتر">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                            </a>
                            <a href="#" class="footer-social-link" aria-label="إنستغرام" title="إنستغرام">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                            </a>
                            <a href="#" class="footer-social-link" aria-label="يوتيوب" title="يوتيوب">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19.1c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.43z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="var(--bg-primary)"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Column 2: Quick Links -->
                    <div class="footer-col">
                        <h3 class="footer-heading">روابط سريعة</h3>
                        <ul class="footer-links">
                            <li>
                                <a href="<?php echo getPageUrl('index.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    الرئيسية
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('search.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    البحث المتقدم
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('plans.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    الباقات والأسعار
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('payment.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    صفحة الدفع
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('about.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    من نحن
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('contact.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    اتصل بنا
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('faq.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    الأسئلة الشائعة
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Column 3: Contact & Legal -->
                    <div class="footer-col">
                        <h3 class="footer-heading">تواصل معنا</h3>
                        <ul class="footer-contact">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <a href="mailto:info@example.com">info@example.com</a>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <span dir="ltr">+967 XXX XXX XXX</span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span>صنع في اليمن 🇾🇪</span>
                            </li>
                        </ul>

                        <h3 class="footer-heading" style="margin-top:1.5rem;">قانوني</h3>
                        <ul class="footer-links">
                            <li>
                                <a href="<?php echo getPageUrl('privacy.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    سياسة الخصوصية
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('terms.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    شروط الاستخدام
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getPageUrl('refund-policy.php'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    سياسة الاسترجاع
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Footer Bottom -->
                <div class="footer-bottom">
                    <div class="footer-bottom-content">
                        <p class="footer-copyright">
                            &copy; <?php echo $currentYear; ?>
                            <strong><?php echo sanitizeOutput(SITE_NAME); ?></strong>.
                            جميع الحقوق محفوظة.
                        </p>
                        <p class="footer-built-with">
                            صنع بـ <span style="color:var(--danger);">&hearts;</span> في اليمن
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <button class="back-to-top" id="backToTop" aria-label="العودة للأعلى" title="العودة للأعلى">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
        </button>
    </footer>

    <!-- ============================================================
         Footer Styles
         ============================================================ -->
    <style>
        /* Footer Wave */
        .footer-wave {
            margin-bottom: -2px;
            line-height: 0;
        }

        .footer-wave svg {
            width: 100%;
            height: 60px;
            display: block;
        }

        /* Footer Glassmorphism */
        .site-footer {
            margin-top: auto;
        }

        .footer-glass {
            background: var(--bg-glass);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-top: 1px solid var(--bg-glass-border);
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 1.5rem 1.5rem;
        }

        /* Footer Grid */
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2.5rem;
        }

        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* Footer Brand */
        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .footer-logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            border-radius: 8px;
            color: white;
        }

        .footer-logo-text {
            font-size: 1.15rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-description {
            color: var(--text-secondary);
            font-size: 0.925rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            max-width: 360px;
        }

        /* Social Links */
        .footer-social {
            display: flex;
            gap: 0.5rem;
        }

        .footer-social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            background: var(--accent-light);
            color: var(--accent);
            transition: var(--transition);
        }

        .footer-social-link:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        /* Footer Headings */
        .footer-heading {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 30px;
            height: 3px;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            border-radius: 2px;
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--accent);
            transform: translateX(-4px);
        }

        .footer-links a svg {
            flex-shrink: 0;
            opacity: 0.5;
            transition: var(--transition);
        }

        .footer-links a:hover svg {
            opacity: 1;
        }

        /* Footer Contact */
        .footer-contact {
            list-style: none;
        }

        .footer-contact li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .footer-contact svg {
            flex-shrink: 0;
            color: var(--accent);
        }

        .footer-contact a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-contact a:hover {
            color: var(--accent);
        }

        /* Footer Bottom */
        .footer-bottom {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }

        .footer-bottom-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        @media (max-width: 480px) {
            .footer-bottom-content {
                flex-direction: column;
                text-align: center;
            }
        }

        .footer-copyright {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .footer-built-with {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #06b6d4);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px var(--accent-glow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .back-to-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px var(--accent-glow);
        }
    </style>

    <!-- ============================================================
         Footer JavaScript
         ============================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Re-initialize Lucide icons for footer
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Back to Top Button
            var backToTopBtn = document.getElementById('backToTop');

            if (backToTopBtn) {
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 400) {
                        backToTopBtn.classList.add('show');
                    } else {
                        backToTopBtn.classList.remove('show');
                    }
                });

                backToTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>

    <!-- Close body and html -->
</body>
</html>
