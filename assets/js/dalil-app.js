/**
 * dalil-app.js - Shared JS module for phone directory
 * Handles: auth state, CSRF tokens, API calls, user menu
 */

const DalilApp = {
    apiBase: '/api',

    // Get CSRF token from server
    async getCSRFToken() {
        try {
            const res = await fetch(this.apiBase + '/csrf.php');
            const data = await res.json();
            if (data.success && data.csrf_token) {
                sessionStorage.setItem('csrf_token', data.csrf_token);
                return data.csrf_token;
            }
        } catch(e) {
            console.error('CSRF fetch failed:', e);
        }
        return '';
    },

    getCSRFTokenSync() {
        return sessionStorage.getItem('csrf_token') || '';
    },

    // Check auth status
    async checkAuth() {
        try {
            const res = await fetch(this.apiBase + '/check-auth.php');
            const data = await res.json();
            if (data.success) {
                if (data.logged_in) {
                    localStorage.setItem('user', JSON.stringify(data.user));
                    this.updateUI(data.user);
                    return data.user;
                } else {
                    localStorage.removeItem('user');
                    this.updateUI(null);
                    return null;
                }
            }
        } catch(e) {
            console.error('Auth check failed:', e);
        }
        return null;
    },

    // Update UI based on auth state
    updateUI(user) {
        const authBtns = document.getElementById('authBtns');
        const userMenu = document.getElementById('userMenu');
        const mobileAuthBtns = document.getElementById('mobileAuthBtns');
        const mobileUser = document.getElementById('mobileUser');

        if (user) {
            if (authBtns) authBtns.style.display = 'none';
            if (userMenu) {
                userMenu.style.display = 'block';
                const nameEl = userMenu.querySelector('.user-name');
                const avatarEl = userMenu.querySelector('.user-avatar-text');
                const planEl = userMenu.querySelector('.plan-badge-text');
                if (nameEl) nameEl.textContent = user.name;
                if (avatarEl) avatarEl.textContent = user.name ? user.name.charAt(0) : '?';
                if (planEl) {
                    const plans = {FREE: 'مجاني', PRO: 'احترافي', PREMIUM: 'مميز'};
                    planEl.textContent = plans[user.plan] || 'مجاني';
                }
            }
            if (mobileAuthBtns) mobileAuthBtns.style.display = 'none';
            if (mobileUser) {
                mobileUser.style.display = 'block';
                const mName = mobileUser.querySelector('.mobile-user-name');
                const mPlan = mobileUser.querySelector('.mobile-plan-badge');
                if (mName) mName.textContent = user.name;
                if (mPlan) {
                    const plans = {FREE: 'مجاني', PRO: 'احترافي', PREMIUM: 'مميز'};
                    mPlan.textContent = plans[user.plan] || 'مجاني';
                }
            }
        } else {
            if (authBtns) authBtns.style.display = 'flex';
            if (userMenu) userMenu.style.display = 'none';
            if (mobileAuthBtns) mobileAuthBtns.style.display = 'flex';
            if (mobileUser) mobileUser.style.display = 'none';
        }
    },

    // Get user from localStorage
    getUser() {
        try {
            const u = localStorage.getItem('user');
            return u ? JSON.parse(u) : null;
        } catch(e) {
            return null;
        }
    },

    // API POST with CSRF
    async post(endpoint, data = {}) {
        const token = await this.getCSRFToken();
        data.csrf_token = token;
        const res = await fetch(this.apiBase + '/' + endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data),
        });
        return res.json();
    },

    // Initialize
    async init() {
        await this.getCSRFToken();
        await this.checkAuth();
    }
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    DalilApp.init();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
