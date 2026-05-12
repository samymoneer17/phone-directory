/**
 * ============================================
 * دليل الهاتف الدولي - International Phone Directory
 * Authentication System - auth.js
 * Version: 1.0.0
 * ============================================
 */

// ============================================
// LOGIN FORM
// ============================================

class LoginForm {
    constructor(options = {}) {
        this.formSelector = options.formSelector || '#login-form';
        this.endpoint = options.endpoint || '/api/auth/login';
        this.redirectUrl = options.redirectUrl || '/dashboard';
        this.form = null;
        this.isLoading = false;

        this._init();
    }

    /**
     * Initialize login form
     */
    _init() {
        this.form = document.querySelector(this.formSelector);
        if (!this.form) return;

        this._bindEvents();
        this._initPasswordToggle();
    }

    /**
     * Bind form events
     */
    _bindEvents() {
        // Form submission
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submit();
        });

        // Real-time validation on blur
        const emailField = this.form.querySelector('[name="email"]');
        const passwordField = this.form.querySelector('[name="password"]');

        if (emailField) {
            emailField.addEventListener('blur', () => {
                const value = emailField.value.trim();
                if (value && !App.isValidEmail(value)) {
                    App.showFieldError(emailField, 'يرجى إدخال بريد إلكتروني صحيح');
                } else if (value) {
                    App.showFieldSuccess(emailField);
                }
            });
        }

        if (passwordField) {
            passwordField.addEventListener('blur', () => {
                const value = passwordField.value;
                if (value && value.length < 8) {
                    App.showFieldError(passwordField, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل');
                } else if (value) {
                    App.showFieldSuccess(passwordField);
                }
            });
        }
    }

    /**
     * Initialize password visibility toggle
     */
    _initPasswordToggle() {
        const passwordField = this.form.querySelector('[name="password"]');
        const toggleBtn = this.form.querySelector('.password-toggle-btn');

        if (!passwordField || !toggleBtn) return;

        const eyeOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

        toggleBtn.innerHTML = eyeClosed;
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordField.type === 'password';
            passwordField.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? eyeOpen : eyeClosed;
            toggleBtn.setAttribute('aria-label', isPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
        });
    }

    /**
     * Validate login form
     * @returns {boolean}
     */
    validate() {
        return App.validateForm(this.form, {
            email: {
                required: true,
                requiredMessage: 'يرجى إدخال البريد الإلكتروني',
                email: true
            },
            password: {
                required: true,
                requiredMessage: 'يرجى إدخال كلمة المرور',
                minLength: 8,
                minLengthMessage: 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'
            }
        });
    }

    /**
     * Submit login form
     */
    async submit() {
        if (this.isLoading) return;
        if (!this.validate()) return;

        this.isLoading = true;
        const submitBtn = this.form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML || '';

        // Show loading state
        if (submitBtn) {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text')?.textContent || (submitBtn.innerHTML = '<span class="btn-text">جاري تسجيل الدخول...</span>');
        }

        App.clearFormErrors(this.form);

        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());

            const response = await App.ajax({
                url: this.endpoint,
                method: 'POST',
                data: data,
                contentType: 'application/x-www-form-urlencoded'
            });

            if (response.success) {
                App.toast({
                    type: 'success',
                    title: 'مرحباً بك',
                    message: 'تم تسجيل الدخول بنجاح',
                    duration: 3000
                });

                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = response.data?.redirect || this.redirectUrl;
                }, 1000);
            } else {
                const errors = response.data?.errors || {};

                // Show server-side validation errors
                for (const [field, messages] of Object.entries(errors)) {
                    const fieldEl = this.form.querySelector(`[name="${field}"]`);
                    if (fieldEl && messages.length > 0) {
                        App.showFieldError(fieldEl, messages[0]);
                    }
                }

                // Show general error
                if (!Object.keys(errors).length) {
                    App.toast({
                        type: 'error',
                        title: 'فشل تسجيل الدخول',
                        message: response.data?.message || response.error || 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
                        duration: 5000
                    });
                }
            }
        } catch (error) {
            App.toast({
                type: 'error',
                title: 'خطأ في الاتصال',
                message: 'تعذر الاتصال بالخادم، حاول مرة أخرى',
                duration: 5000
            });
        } finally {
            this.isLoading = false;

            // Restore button state
            if (submitBtn) {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    }
}

// ============================================
// REGISTER FORM
// ============================================

class RegisterForm {
    constructor(options = {}) {
        this.formSelector = options.formSelector || '#register-form';
        this.endpoint = options.endpoint || '/api/auth/register';
        this.redirectUrl = options.redirectUrl || '/dashboard';
        this.form = null;
        this.isLoading = false;
        this.passwordStrengthInterval = null;

        this._init();
    }

    /**
     * Initialize register form
     */
    _init() {
        this.form = document.querySelector(this.formSelector);
        if (!this.form) return;

        this._bindEvents();
        this._initPasswordToggle();
        this._initPasswordStrength();
    }

    /**
     * Bind form events
     */
    _bindEvents() {
        // Form submission
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submit();
        });

        // Real-time validation
        const fields = {
            name: (v) => v.length < 3 ? 'الاسم يجب أن يكون 3 أحرف على الأقل' : true,
            email: (v) => !App.isValidEmail(v) ? 'يرجى إدخال بريد إلكتروني صحيح' : true,
            phone: (v) => !App.isValidPhone(v) ? 'يرجى إدخال رقم هاتف صحيح' : true,
            password: (v) => v.length < 8 ? 'كلمة المرور يجب أن تكون 8 أحرف على الأقل' : true,
            password_confirmation: (v) => {
                const password = this.form.querySelector('[name="password"]')?.value || '';
                return v !== password ? 'كلمة المرور غير متطابقة' : true;
            }
        };

        Object.entries(fields).forEach(([name, validator]) => {
            const field = this.form.querySelector(`[name="${name}"]`);
            if (field) {
                field.addEventListener('blur', () => {
                    const value = field.value.trim();
                    if (value) {
                        const result = validator(value);
                        if (result !== true) {
                            App.showFieldError(field, result);
                        } else {
                            App.showFieldSuccess(field);
                        }
                    }
                });
            }
        });

        // Terms checkbox
        const termsCheckbox = this.form.querySelector('[name="terms"]');
        if (termsCheckbox) {
            termsCheckbox.addEventListener('change', () => {
                const error = this.form.querySelector('.form-error.terms-error');
                if (error) error.remove();
            });
        }
    }

    /**
     * Initialize password visibility toggle for both password fields
     */
    _initPasswordToggle() {
        const toggleButtons = this.form.querySelectorAll('.password-toggle-btn');

        const eyeOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

        toggleButtons.forEach((btn) => {
            const passwordField = btn.closest('.password-toggle')?.querySelector('input');
            if (!passwordField) return;

            btn.innerHTML = eyeClosed;
            btn.addEventListener('click', () => {
                const isPassword = passwordField.type === 'password';
                passwordField.type = isPassword ? 'text' : 'password';
                btn.innerHTML = isPassword ? eyeOpen : eyeClosed;
            });
        });
    }

    /**
     * Initialize password strength indicator
     */
    _initPasswordStrength() {
        const passwordField = this.form.querySelector('[name="password"]');
        const strengthBar = this.form.querySelector('.password-strength-fill');
        const strengthText = this.form.querySelector('.password-strength-text');
        const strengthContainer = this.form.querySelector('.password-strength');

        if (!passwordField || !strengthContainer) return;

        passwordField.addEventListener('input', () => {
            const value = passwordField.value;
            const strength = App.getPasswordStrength(value);

            if (strengthBar) {
                strengthBar.className = 'password-strength-fill';
                if (value.length > 0) {
                    strengthBar.classList.add(strength.className);
                }
            }

            if (strengthText) {
                const labels = {
                    weak: 'ضعيفة',
                    fair: 'متوسطة',
                    strong: 'قوية'
                };
                strengthText.textContent = value.length > 0 ? `قوة كلمة المرور: ${labels[strength.className] || ''}` : '';
            }
        });
    }

    /**
     * Validate registration form
     * @returns {boolean}
     */
    validate() {
        const isValid = App.validateForm(this.form, {
            name: {
                required: true,
                requiredMessage: 'يرجى إدخال الاسم الكامل',
                minLength: 3
            },
            email: {
                required: true,
                requiredMessage: 'يرجى إدخال البريد الإلكتروني',
                email: true
            },
            phone: {
                required: true,
                requiredMessage: 'يرجى إدخال رقم الهاتف',
                phone: true
            },
            password: {
                required: true,
                requiredMessage: 'يرجى إدخال كلمة المرور',
                minLength: 8
            },
            password_confirmation: {
                required: true,
                requiredMessage: 'يرجى تأكيد كلمة المرور',
                match: 'password'
            }
        });

        if (!isValid) return false;

        // Check terms checkbox
        const termsCheckbox = this.form.querySelector('[name="terms"]');
        if (termsCheckbox && !termsCheckbox.checked) {
            const error = document.createElement('div');
            error.className = 'form-error terms-error';
            error.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>يجب الموافقة على الشروط والأحكام';
            termsCheckbox.closest('.checkbox')?.after(error);

            return false;
        }

        return true;
    }

    /**
     * Submit registration form
     */
    async submit() {
        if (this.isLoading) return;
        if (!this.validate()) return;

        this.isLoading = true;
        const submitBtn = this.form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML || '';

        if (submitBtn) {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-text">جاري إنشاء الحساب...</span>';
        }

        App.clearFormErrors(this.form);

        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());

            const response = await App.ajax({
                url: this.endpoint,
                method: 'POST',
                data: data,
                contentType: 'application/x-www-form-urlencoded'
            });

            if (response.success) {
                App.toast({
                    type: 'success',
                    title: 'تم إنشاء الحساب',
                    message: response.data?.message || 'تم إنشاء حسابك بنجاح! جاري التحويل...',
                    duration: 3000
                });

                setTimeout(() => {
                    window.location.href = response.data?.redirect || this.redirectUrl;
                }, 1500);
            } else {
                const errors = response.data?.errors || {};

                for (const [field, messages] of Object.entries(errors)) {
                    const fieldEl = this.form.querySelector(`[name="${field}"]`);
                    if (fieldEl && messages.length > 0) {
                        App.showFieldError(fieldEl, messages[0]);
                    }
                }

                if (!Object.keys(errors).length) {
                    App.toast({
                        type: 'error',
                        title: 'فشل التسجيل',
                        message: response.data?.message || response.error || 'حدث خطأ أثناء التسجيل',
                        duration: 5000
                    });
                }
            }
        } catch (error) {
            App.toast({
                type: 'error',
                title: 'خطأ في الاتصال',
                message: 'تعذر الاتصال بالخادم، حاول مرة أخرى',
                duration: 5000
            });
        } finally {
            this.isLoading = false;

            if (submitBtn) {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    }
}

// ============================================
// FORGOT PASSWORD FORM
// ============================================

class ForgotPasswordForm {
    constructor(options = {}) {
        this.formSelector = options.formSelector || '#forgot-password-form';
        this.endpoint = options.endpoint || '/api/auth/forgot-password';
        this.redirectUrl = options.redirectUrl || '/login';
        this.form = null;
        this.isLoading = false;
        this.isSubmitted = false;

        this._init();
    }

    /**
     * Initialize forgot password form
     */
    _init() {
        this.form = document.querySelector(this.formSelector);
        if (!this.form) return;

        this._bindEvents();
    }

    /**
     * Bind form events
     */
    _bindEvents() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submit();
        });

        // Real-time validation
        const emailField = this.form.querySelector('[name="email"]');
        if (emailField) {
            emailField.addEventListener('blur', () => {
                const value = emailField.value.trim();
                if (value && !App.isValidEmail(value)) {
                    App.showFieldError(emailField, 'يرجى إدخال بريد إلكتروني صحيح');
                } else if (value) {
                    App.showFieldSuccess(emailField);
                }
            });
        }
    }

    /**
     * Validate forgot password form
     * @returns {boolean}
     */
    validate() {
        return App.validateForm(this.form, {
            email: {
                required: true,
                requiredMessage: 'يرجى إدخال البريد الإلكتروني',
                email: true
            }
        });
    }

    /**
     * Submit forgot password form
     */
    async submit() {
        if (this.isLoading || this.isSubmitted) return;
        if (!this.validate()) return;

        this.isLoading = true;
        const submitBtn = this.form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML || '';

        if (submitBtn) {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-text">جاري الإرسال...</span>';
        }

        App.clearFormErrors(this.form);

        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());

            const response = await App.ajax({
                url: this.endpoint,
                method: 'POST',
                data: data,
                contentType: 'application/x-www-form-urlencoded'
            });

            if (response.success) {
                this.isSubmitted = true;

                // Replace form with success message
                const formContent = this.form.querySelector('.auth-form') || this.form;
                const successIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;color:#10B981;margin-bottom:1rem"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';

                formContent.innerHTML = `
                    <div style="text-align:center; padding:2rem 0; animation: fadeIn 0.5s ease;">
                        ${successIcon}
                        <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:0.75rem; color:#0F172A;">
                            تم إرسال الرابط بنجاح
                        </h3>
                        <p style="font-size:0.9rem; color:#64748B; line-height:1.7; margin-bottom:1.5rem;">
                            إذا كان البريد الإلكتروني مسجلاً لدينا، ستتلقى رسالة تحتوي على رابط لإعادة تعيين كلمة المرور.
                        </p>
                        <a href="${this.redirectUrl}" class="btn btn-primary">
                            العودة لتسجيل الدخول
                        </a>
                    </div>
                `;

                App.toast({
                    type: 'success',
                    title: 'تم الإرسال',
                    message: 'تحقق من بريدك الإلكتروني',
                    duration: 4000
                });
            } else {
                const errors = response.data?.errors || {};

                for (const [field, messages] of Object.entries(errors)) {
                    const fieldEl = this.form.querySelector(`[name="${field}"]`);
                    if (fieldEl && messages.length > 0) {
                        App.showFieldError(fieldEl, messages[0]);
                    }
                }

                if (!Object.keys(errors).length) {
                    App.toast({
                        type: 'error',
                        title: 'فشل الإرسال',
                        message: response.data?.message || response.error || 'حدث خطأ، حاول مرة أخرى',
                        duration: 5000
                    });
                }
            }
        } catch (error) {
            App.toast({
                type: 'error',
                title: 'خطأ في الاتصال',
                message: 'تعذر الاتصال بالخادم، حاول مرة أخرى',
                duration: 5000
            });
        } finally {
            this.isLoading = false;

            if (submitBtn && !this.isSubmitted) {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    }
}

// ============================================
// GOOGLE OAUTH HELPER
// ============================================

class GoogleAuth {
    /**
     * Redirect to Google OAuth
     * @param {string} endpoint - OAuth endpoint URL
     */
    static login(endpoint = '/auth/google') {
        window.location.href = endpoint;
    }

    /**
     * Handle OAuth callback
     * @param {string} token - Auth token from callback
     * @param {string} redirectUrl - URL to redirect after login
     */
    static handleCallback(token, redirectUrl = '/dashboard') {
        if (!token) {
            App.toast({
                type: 'error',
                title: 'فشل تسجيل الدخول',
                message: 'لم يتم استلام بيانات المصادقة',
                duration: 5000
            });
            return;
        }

        // Show loading
        App.toast({
            type: 'info',
            title: 'جاري تسجيل الدخول',
            message: 'يرجى الانتظار...',
            duration: 3000
        });

        // Store token and redirect
        try {
            localStorage.setItem('auth_token', token);
        } catch (e) {
            // Silently fail
        }

        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 500);
    }
}

// ============================================
// LOGOUT HELPER
// ============================================

class LogoutHandler {
    /**
     * Logout user
     * @param {string} endpoint - Logout API endpoint
     * @param {string} redirectUrl - URL to redirect after logout
     */
    static async logout(endpoint = '/api/auth/logout', redirectUrl = '/login') {
        try {
            await App.ajax({
                url: endpoint,
                method: 'POST',
                data: {}
            });
        } catch (e) {
            // Continue even if request fails
        }

        // Clear local auth data
        try {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_data');
        } catch (e) {
            // Silently fail
        }

        App.toast({
            type: 'success',
            title: 'تم تسجيل الخروج',
            message: 'تم تسجيل خروجك بنجاح',
            duration: 2000
        });

        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 500);
    }
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialize forms based on existence in DOM
    const loginFormEl = document.querySelector('#login-form');
    if (loginFormEl) {
        window.loginForm = new LoginForm();
    }

    const registerFormEl = document.querySelector('#register-form');
    if (registerFormEl) {
        window.registerForm = new RegisterForm();
    }

    const forgotPasswordEl = document.querySelector('#forgot-password-form');
    if (forgotPasswordEl) {
        window.forgotPasswordForm = new ForgotPasswordForm();
    }

    // Initialize Google auth buttons
    document.querySelectorAll('[data-google-auth]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const endpoint = btn.getAttribute('data-google-auth') || '/auth/google';
            GoogleAuth.login(endpoint);
        });
    });

    // Initialize logout buttons
    document.querySelectorAll('[data-logout]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const endpoint = btn.getAttribute('data-logout-endpoint') || '/api/auth/logout';
            const redirectUrl = btn.getAttribute('data-logout-redirect') || '/login';
            LogoutHandler.logout(endpoint, redirectUrl);
        });
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        LoginForm,
        RegisterForm,
        ForgotPasswordForm,
        GoogleAuth,
        LogoutHandler
    };
}
