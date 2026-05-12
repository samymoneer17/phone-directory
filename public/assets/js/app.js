/**
 * ============================================
 * دليل الهاتف الدولي - International Phone Directory
 * Core Application JavaScript - app.js
 * Version: 1.0.0
 * ============================================
 */

// ============================================
// 1. DOM READY HANDLER
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

/**
 * Main Application Class
 * Handles all core UI interactions and utilities
 */
class App {
    constructor() {
        this.mobileMenuOpen = false;
        this.activeModals = [];
        this.toastTimeout = null;
        this.toastQueue = [];
    }

    /**
     * Initialize all application components
     */
    static init() {
        const app = new App();

        // Initialize components
        app.initMobileMenu();
        app.initSmoothScroll();
        app.initScrollHeader();
        app.initScrollAnimations();
        app.initAnimatedCounters();
        app.initTooltips();
        app.initAutoResizeTextareas();

        // Make app globally available
        window.App = app;

        // Dispatch ready event
        document.dispatchEvent(new CustomEvent('app:ready'));
    }

    // ============================================
    // 2. MOBILE MENU
    // ============================================

    /**
     * Initialize mobile hamburger menu
     */
    initMobileMenu() {
        const hamburger = document.querySelector('.hamburger');
        const mobileOverlay = document.querySelector('.mobile-overlay');
        const mobileMenu = document.querySelector('.mobile-menu');
        const mobileClose = document.querySelector('.mobile-menu-close');

        if (!hamburger || !mobileMenu) return;

        // Toggle menu
        hamburger.addEventListener('click', () => this.toggleMobileMenu());
        if (mobileClose) {
            mobileClose.addEventListener('click', () => this.closeMobileMenu());
        }
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', () => this.closeMobileMenu());
        }

        // Close menu on link click
        const menuLinks = mobileMenu.querySelectorAll('.mobile-menu-link');
        menuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                this.closeMobileMenu();
            });
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.mobileMenuOpen) {
                this.closeMobileMenu();
            }
        });

        // Close on resize to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 640 && this.mobileMenuOpen) {
                this.closeMobileMenu();
            }
        });
    }

    /**
     * Toggle mobile menu open/close
     */
    toggleMobileMenu() {
        if (this.mobileMenuOpen) {
            this.closeMobileMenu();
        } else {
            this.openMobileMenu();
        }
    }

    /**
     * Open mobile menu
     */
    openMobileMenu() {
        const hamburger = document.querySelector('.hamburger');
        const mobileOverlay = document.querySelector('.mobile-overlay');
        const mobileMenu = document.querySelector('.mobile-menu');

        if (hamburger) hamburger.classList.add('active');
        if (mobileOverlay) mobileOverlay.classList.add('open');
        if (mobileMenu) mobileMenu.classList.add('open');

        document.body.style.overflow = 'hidden';
        this.mobileMenuOpen = true;
    }

    /**
     * Close mobile menu
     */
    closeMobileMenu() {
        const hamburger = document.querySelector('.hamburger');
        const mobileOverlay = document.querySelector('.mobile-overlay');
        const mobileMenu = document.querySelector('.mobile-menu');

        if (hamburger) hamburger.classList.remove('active');
        if (mobileOverlay) mobileOverlay.classList.remove('open');
        if (mobileMenu) mobileMenu.classList.remove('open');

        document.body.style.overflow = '';
        this.mobileMenuOpen = false;
    }

    // ============================================
    // 3. SMOOTH SCROLL
    // ============================================

    /**
     * Initialize smooth scroll for anchor links
     */
    initSmoothScroll() {
        const anchors = document.querySelectorAll('a[href^="#"]');
        anchors.forEach((anchor) => {
            anchor.addEventListener('click', (e) => {
                const targetId = anchor.getAttribute('href');
                if (targetId === '#' || targetId === '#!') return;

                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    const headerHeight = document.querySelector('.header')?.offsetHeight || 64;
                    const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });

                    // Update URL hash without scroll
                    history.pushState(null, '', targetId);
                }
            });
        });
    }

    // ============================================
    // 4. SCROLL HEADER EFFECT
    // ============================================

    /**
     * Add shadow to header on scroll
     */
    initScrollHeader() {
        const header = document.querySelector('.header');
        if (!header) return;

        let lastScroll = 0;

        const handleScroll = () => {
            const currentScroll = window.scrollY;

            if (currentScroll > 10) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScroll = currentScroll;
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Initial check
    }

    // ============================================
    // 5. SCROLL-BASED ANIMATIONS
    // ============================================

    /**
     * Initialize Intersection Observer for scroll animations
     */
    initScrollAnimations() {
        const animatedElements = document.querySelectorAll(
            '[data-animate], .animate-on-scroll'
        );

        if (!animatedElements.length) return;

        // Check if IntersectionObserver is supported
        if (!('IntersectionObserver' in window)) {
            // Fallback: show all elements immediately
            animatedElements.forEach((el) => {
                el.classList.add('is-visible');
            });
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const animation = element.getAttribute('data-animate') || 'animate-slideUp';
                        const delay = element.getAttribute('data-animate-delay') || 0;

                        setTimeout(() => {
                            element.classList.add(animation, 'is-visible');
                            element.style.opacity = '';
                        }, parseInt(delay, 10));

                        observer.unobserve(element);
                    }
                });
            },
            {
                root: null,
                rootMargin: '0px 0px -60px 0px',
                threshold: 0.1
            }
        );

        animatedElements.forEach((el) => {
            el.style.opacity = '0';
            observer.observe(el);
        });

        // Store observer for cleanup
        this._scrollObserver = observer;
    }

    // ============================================
    // 6. ANIMATED COUNTERS
    // ============================================

    /**
     * Initialize animated number counters
     * Use [data-counter] attribute with the target number
     */
    initAnimatedCounters() {
        const counters = document.querySelectorAll('[data-counter]');

        if (!counters.length) return;

        if (!('IntersectionObserver' in window)) {
            counters.forEach((el) => {
                el.textContent = this._formatNumber(parseInt(el.getAttribute('data-counter'), 10));
            });
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        this._animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.5 }
        );

        counters.forEach((counter) => observer.observe(counter));
    }

    /**
     * Animate a counter element from 0 to target value
     * @param {HTMLElement} element
     */
    _animateCounter(element) {
        const target = parseInt(element.getAttribute('data-counter'), 10);
        const duration = parseInt(element.getAttribute('data-counter-duration') || '2000', 10);
        const prefix = element.getAttribute('data-counter-prefix') || '';
        const suffix = element.getAttribute('data-counter-suffix') || '';
        const useSeparator = element.getAttribute('data-counter-separator') !== 'false';

        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function (easeOutExpo)
            const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const current = Math.floor(eased * target);

            const formatted = useSeparator ? this._formatNumber(current) : current.toString();
            element.textContent = `${prefix}${formatted}${suffix}`;

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                const finalFormatted = useSeparator ? this._formatNumber(target) : target.toString();
                element.textContent = `${prefix}${finalFormatted}${suffix}`;
            }
        };

        requestAnimationFrame(animate);
    }

    // ============================================
    // 7. TOAST NOTIFICATION SYSTEM
    // ============================================

    /**
     * Show a toast notification
     * @param {Object} options - Toast options
     * @param {string} options.message - Message text
     * @param {string} options.title - Optional title
     * @param {string} options.type - 'success', 'error', 'warning', 'info'
     * @param {number} options.duration - Duration in ms (default 4000)
     * @param {boolean} options.closable - Whether closeable (default true)
     */
    static toast(options = {}) {
        const {
            message = '',
            title = '',
            type = 'info',
            duration = 4000,
            closable = true
        } = options;

        // Get or create container
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('role', 'alert');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        // Toast icon SVGs
        const icons = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${this._escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${this._escapeHtml(message)}</div>
            </div>
            ${closable ? `<button class="toast-close" aria-label="إغلاق"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>` : ''}
            <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
        `;

        // Close button handler
        if (closable) {
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => this._removeToast(toast));
        }

        // Add to container
        container.appendChild(toast);

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this._removeToast(toast);
            }, duration);
        }

        return toast;
    }

    /**
     * Remove a toast with animation
     * @param {HTMLElement} toast
     */
    static _removeToast(toast) {
        if (!toast || !toast.parentNode) return;

        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    // ============================================
    // 8. MODAL SYSTEM
    // ============================================

    /**
     * Open a modal
     * @param {string} modalId - ID of the modal element
     * @param {Object} options - Modal options
     */
    static openModal(modalId, options = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.warn(`Modal with ID "${modalId}" not found.`);
            return;
        }

        const overlay = modal.closest('.modal-overlay') || modal;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        // Focus trap - focus first focusable element
        setTimeout(() => {
            const focusable = modal.querySelector(
                'input, select, textarea, button, [tabindex]:not([tabindex="-1"])'
            );
            if (focusable) focusable.focus();
        }, 100);

        // Close on escape
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                App.closeModal(modalId);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);

        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                App.closeModal(modalId);
            }
        });

        // Dispatch open event
        modal.dispatchEvent(new CustomEvent('modal:open', { detail: options }));
    }

    /**
     * Close a modal
     * @param {string} modalId - ID of the modal element
     */
    static closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const overlay = modal.closest('.modal-overlay') || modal;
        overlay.classList.remove('open');
        document.body.style.overflow = '';

        // Dispatch close event
        modal.dispatchEvent(new CustomEvent('modal:close'));
    }

    /**
     * Close all open modals
     */
    static closeAllModals() {
        document.querySelectorAll('.modal-overlay.open').forEach((overlay) => {
            overlay.classList.remove('open');
        });
        document.body.style.overflow = '';
    }

    // ============================================
    // 9. FORM VALIDATION HELPERS
    // ============================================

    /**
     * Validate an email address
     * @param {string} email
     * @returns {boolean}
     */
    static isValidEmail(email) {
        const regex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        return regex.test(email);
    }

    /**
     * Validate a phone number (international format)
     * @param {string} phone
     * @returns {boolean}
     */
    static isValidPhone(phone) {
        // Accept digits, spaces, dashes, parentheses, plus sign
        const cleaned = phone.replace(/[\s\-\(\)]/g, '');
        const regex = /^\+?[0-9]{7,15}$/;
        return regex.test(cleaned);
    }

    /**
     * Validate password strength
     * @param {string} password
     * @returns {Object} { score: 0-3, label: string }
     */
    static getPasswordStrength(password) {
        if (!password || password.length === 0) {
            return { score: 0, label: '', className: '' };
        }

        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;

        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        if (score <= 1) {
            return { score: 1, label: 'ضعيف', className: 'weak' };
        } else if (score <= 3) {
            return { score: 2, label: 'متوسط', className: 'fair' };
        } else {
            return { score: 3, label: 'قوي', className: 'strong' };
        }
    }

    /**
     * Show a field error
     * @param {HTMLElement} field - Input element
     * @param {string} message - Error message
     */
    static showFieldError(field, message) {
        if (!field) return;

        // Remove existing errors
        App.clearFieldError(field);

        field.classList.add('input-error');
        field.classList.remove('input-success');

        const errorEl = document.createElement('div');
        errorEl.className = 'form-error';
        errorEl.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${App._escapeHtml(message)}`;

        const formGroup = field.closest('.form-group') || field.parentElement;
        formGroup.appendChild(errorEl);
    }

    /**
     * Show a field success state
     * @param {HTMLElement} field
     */
    static showFieldSuccess(field) {
        if (!field) return;

        App.clearFieldError(field);
        field.classList.remove('input-error');
        field.classList.add('input-success');
    }

    /**
     * Clear field error
     * @param {HTMLElement} field
     */
    static clearFieldError(field) {
        if (!field) return;

        field.classList.remove('input-error');

        const formGroup = field.closest('.form-group') || field.parentElement;
        const existingError = formGroup.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
    }

    /**
     * Clear all form errors
     * @param {HTMLFormElement} form
     */
    static clearFormErrors(form) {
        if (!form) return;

        form.querySelectorAll('.input-error').forEach((el) => {
            el.classList.remove('input-error');
        });
        form.querySelectorAll('.form-error').forEach((el) => {
            el.remove();
        });
    }

    /**
     * Validate a form
     * @param {HTMLFormElement} form - Form element
     * @param {Object} rules - Validation rules { fieldName: { required, email, minLength, ... } }
     * @returns {boolean}
     */
    static validateForm(form, rules) {
        let isValid = true;
        App.clearFormErrors(form);

        for (const [fieldName, fieldRules] of Object.entries(rules)) {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (!field) continue;

            const value = field.value.trim();

            // Required check
            if (fieldRules.required && !value) {
                const msg = fieldRules.requiredMessage || 'هذا الحقل مطلوب';
                App.showFieldError(field, msg);
                isValid = false;
                continue;
            }

            // Skip further validation if empty and not required
            if (!value) continue;

            // Email check
            if (fieldRules.email && !App.isValidEmail(value)) {
                App.showFieldError(field, 'يرجى إدخال بريد إلكتروني صحيح');
                isValid = false;
                continue;
            }

            // Phone check
            if (fieldRules.phone && !App.isValidPhone(value)) {
                App.showFieldError(field, 'يرجى إدخال رقم هاتف صحيح');
                isValid = false;
                continue;
            }

            // Min length check
            if (fieldRules.minLength && value.length < fieldRules.minLength) {
                App.showFieldError(field, `يجب أن يكون الحد الأدنى ${fieldRules.minLength} أحرف`);
                isValid = false;
                continue;
            }

            // Max length check
            if (fieldRules.maxLength && value.length > fieldRules.maxLength) {
                App.showFieldError(field, `يجب أن يكون الحد الأقصى ${fieldRules.maxLength} حرف`);
                isValid = false;
                continue;
            }

            // Match field
            if (fieldRules.match) {
                const matchField = form.querySelector(`[name="${fieldRules.match}"]`);
                if (matchField && value !== matchField.value.trim()) {
                    App.showFieldError(field, 'الحقلان غير متطابقين');
                    isValid = false;
                    continue;
                }
            }

            // Custom validator
            if (typeof fieldRules.custom === 'function') {
                const result = fieldRules.custom(value);
                if (result !== true) {
                    App.showFieldError(field, result || 'قيمة غير صحيحة');
                    isValid = false;
                    continue;
                }
            }

            // If valid, show success
            App.showFieldSuccess(field);
        }

        return isValid;
    }

    // ============================================
    // 10. AJAX HELPER
    // ============================================

    /**
     * Make an AJAX request
     * @param {Object} options
     * @param {string} options.url - Request URL
     * @param {string} options.method - HTTP method (GET, POST, PUT, DELETE)
     * @param {Object} options.data - Request body data
     * @param {Object} options.headers - Additional headers
     * @param {string} options.contentType - Content type header
     * @param {number} options.timeout - Request timeout in ms
     * @returns {Promise<Object>}
     */
    static async ajax(options = {}) {
        const {
            url = '',
            method = 'GET',
            data = null,
            headers = {},
            contentType = 'application/json',
            timeout = 30000
        } = options;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        const config = {
            method: method.toUpperCase(),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...headers
            },
            signal: controller.signal
        };

        // Add CSRF token if available
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            config.headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
        }

        // Add body for non-GET requests
        if (data && method.toUpperCase() !== 'GET') {
            if (contentType === 'application/json') {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(data);
            } else if (contentType === 'application/x-www-form-urlencoded') {
                config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                config.body = new URLSearchParams(data).toString();
            } else if (data instanceof FormData) {
                // Let browser set Content-Type with boundary
                config.body = data;
            }
        }

        // Append query params for GET requests
        let requestUrl = url;
        if (data && method.toUpperCase() === 'GET') {
            const params = new URLSearchParams(data).toString();
            requestUrl += (requestUrl.includes('?') ? '&' : '?') + params;
        }

        try {
            const response = await fetch(requestUrl, config);
            clearTimeout(timeoutId);

            // Parse response
            let responseData;
            const responseContentType = response.headers.get('Content-Type') || '';

            if (responseContentType.includes('application/json')) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }

            if (!response.ok) {
                const error = new Error(responseData.message || `HTTP Error: ${response.status}`);
                error.status = response.status;
                error.data = responseData;
                throw error;
            }

            return {
                success: true,
                data: responseData,
                status: response.status
            };
        } catch (error) {
            clearTimeout(timeoutId);

            if (error.name === 'AbortError') {
                return {
                    success: false,
                    error: 'انتهت مهلة الطلب',
                    status: 0
                };
            }

            return {
                success: false,
                error: error.message || 'حدث خطأ أثناء الطلب',
                status: error.status || 0
            };
        }
    }

    // ============================================
    // 11. CLIPBOARD UTILITIES
    // ============================================

    /**
     * Copy text to clipboard
     * @param {string} text - Text to copy
     * @returns {Promise<boolean>}
     */
    static async copyToClipboard(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }

            // Fallback for non-HTTPS contexts
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.top = '-9999px';
            textArea.style.left = '-9999px';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                return true;
            } catch (err) {
                return false;
            } finally {
                document.body.removeChild(textArea);
            }
        } catch (error) {
            return false;
        }
    }

    /**
     * Copy phone number with formatted toast
     * @param {string} phoneNumber
     * @param {string} label - Optional label for toast message
     */
    static async copyPhone(phoneNumber, label = '') {
        const success = await App.copyToClipboard(phoneNumber);
        if (success) {
            App.toast({
                type: 'success',
                title: 'تم النسخ',
                message: label ? `تم نسخ رقم ${label}` : `تم نسخ الرقم: ${phoneNumber}`,
                duration: 2500
            });
        } else {
            App.toast({
                type: 'error',
                title: 'فشل النسخ',
                message: 'لم يتم نسخ الرقم، حاول مرة أخرى',
                duration: 3000
            });
        }
    }

    // ============================================
    // 12. NUMBER FORMATTING
    // ============================================

    /**
     * Format a number with thousand separators
     * @param {number} num
     * @returns {string}
     */
    static _formatNumber(num) {
        if (typeof num !== 'number') num = parseInt(num, 10) || 0;
        return num.toLocaleString('ar-SA');
    }

    /**
     * Format a phone number for display
     * @param {string} phone
     * @returns {string}
     */
    static formatPhone(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/[^\d+]/g, '');

        // Simple formatting based on length
        if (cleaned.startsWith('+')) {
            const number = cleaned.slice(1);
            if (number.length === 12) {
                return `+${number.slice(0, 3)} ${number.slice(3, 6)} ${number.slice(6, 9)} ${number.slice(9)}`;
            } else if (number.length === 11) {
                return `+${number.slice(0, 2)} ${number.slice(2, 5)} ${number.slice(5, 8)} ${number.slice(8)}`;
            }
        }

        if (cleaned.length === 10) {
            return `${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6, 10)}`;
        }

        return cleaned;
    }

    // ============================================
    // 13. TEXTAREA AUTO-RESIZE
    // ============================================

    /**
     * Initialize auto-resize for textareas with .input-auto class
     */
    initAutoResizeTextareas() {
        const textareas = document.querySelectorAll('textarea.input-auto');
        textareas.forEach((textarea) => {
            // Set initial height
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';

            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        });
    }

    // ============================================
    // 14. TOOLTIP INITIALIZATION
    // ============================================

    /**
     * Initialize tooltips (CSS-based, using data-tooltip attribute)
     */
    initTooltips() {
        // Tooltips are handled by CSS, but we can add ARIA attributes
        document.querySelectorAll('[data-tooltip]').forEach((el) => {
            el.setAttribute('role', 'button');
            el.setAttribute('tabindex', '0');
        });
    }

    // ============================================
    // 15. UTILITY METHODS
    // ============================================

    /**
     * Escape HTML to prevent XSS
     * @param {string} str
     * @returns {string}
     */
    static _escapeHtml(str) {
        if (typeof str !== 'string') return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Debounce function
     * @param {Function} func
     * @param {number} wait
     * @returns {Function}
     */
    static debounce(func, wait = 300) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Throttle function
     * @param {Function} func
     * @param {number} limit
     * @returns {Function}
     */
    static throttle(func, limit = 300) {
        let inThrottle;
        return function (...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => {
                    inThrottle = false;
                }, limit);
            }
        };
    }

    /**
     * Generate a unique ID
     * @returns {string}
     */
    static generateId() {
        return 'id_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Check if element is in viewport
     * @param {HTMLElement} element
     * @returns {boolean}
     */
    static isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    /**
     * Sleep utility
     * @param {number} ms
     * @returns {Promise}
     */
    static sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    /**
     * Safely parse JSON
     * @param {string} str
     * @param {*} fallback
     * @returns {*}
     */
    static parseJSON(str, fallback = null) {
        try {
            return JSON.parse(str);
        } catch (e) {
            return fallback;
        }
    }
}
