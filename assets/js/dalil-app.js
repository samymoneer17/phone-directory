/**
 * dalil-app.js - Shared JS module for phone directory
 * Handles: auth state, CSRF tokens, API calls, user menu
 * 
 * Vercel Serverless Note:
 * PHP sessions in /tmp don't persist between serverless function invocations.
 * Auth state relies primarily on localStorage (client-side) with server
 * verification as a secondary check. Only explicit logout clears the state.
 */

const DalilApp = {
    apiBase: '/api',

    // Get a fresh CSRF token from server
    async getCSRFToken() {
        try {
            const res = await fetch(this.apiBase + '/csrf.php', {
                credentials: 'same-origin',
            });
            if (!res.ok) return '';
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

    // Get cached CSRF token (no server request)
    getCSRFTokenSync() {
        return sessionStorage.getItem('csrf_token') || '';
    },

    // Check auth status (Vercel-compatible: localStorage-first approach)
    async checkAuth() {
        // Step 1: Immediately show user from localStorage (instant UI)
        var cachedUser = this.getUser();
        if (cachedUser) {
            this.updateUI(cachedUser);
        }

        // Step 2: Verify with server in background (best-effort)
        try {
            var res = await fetch(this.apiBase + '/check-auth.php', {
                credentials: 'same-origin',
            });
            var data = await res.json();

            if (data.success && data.logged_in) {
                // Server confirms login — update localStorage with fresh data
                localStorage.setItem('user', JSON.stringify(data.user));
                this.updateUI(data.user);
                return data.user;
            }

            // Server says "not logged in" — on Vercel this usually means
            // the session was lost between containers, NOT that the user
            // actually logged out. Trust localStorage unless explicitly logged out.
            if (cachedUser) {
                // Keep the cached user, but refresh from server next time
                return cachedUser;
            }

            // No cached user and server says not logged in — genuinely logged out
            this.updateUI(null);
            return null;
        } catch(e) {
            console.error('Auth check failed:', e);
            // If server is unreachable, trust localStorage
            return cachedUser;
        }
    },

    // Save user to localStorage and update UI
    setUser(user) {
        if (user) {
            localStorage.setItem('user', JSON.stringify(user));
            this.updateUI(user);
        }
    },

    // Clear user state (explicit logout)
    logout() {
        localStorage.removeItem('user');
        this.updateUI(null);
    },

    // Update UI based on auth state
    updateUI(user) {
        var authBtns = document.getElementById('authBtns');
        var userMenu = document.getElementById('userMenu');
        var mobileAuthBtns = document.getElementById('mobileAuthBtns');
        var mobileUser = document.getElementById('mobileUser');

        if (user) {
            if (authBtns) authBtns.style.display = 'none';
            if (userMenu) {
                userMenu.style.display = 'block';
                var nameEl = userMenu.querySelector('.user-name');
                var avatarEl = userMenu.querySelector('.user-avatar-text');
                var planEl = userMenu.querySelector('.plan-badge-text');
                if (nameEl) nameEl.textContent = user.name;
                if (avatarEl) avatarEl.textContent = user.name ? user.name.charAt(0) : '?';
                if (planEl) {
                    var plans = {FREE: '\u0645\u062c\u0627\u0646\u064a', PRO: '\u0627\u062d\u062a\u0631\u0627\u0641\u064a', PREMIUM: '\u0645\u0645\u064a\u0632'};
                    planEl.textContent = plans[user.plan] || '\u0645\u062c\u0627\u0646\u064a';
                }
            }
            if (mobileAuthBtns) mobileAuthBtns.style.display = 'none';
            if (mobileUser) {
                mobileUser.style.display = 'block';
                var mName = mobileUser.querySelector('.mobile-user-name');
                var mPlan = mobileUser.querySelector('.mobile-plan-badge');
                if (mName) mName.textContent = user.name;
                if (mPlan) {
                    var plans = {FREE: '\u0645\u062c\u0627\u0646\u064a', PRO: '\u0627\u062d\u062a\u0631\u0627\u0641\u064a', PREMIUM: '\u0645\u0645\u064a\u0632'};
                    mPlan.textContent = plans[user.plan] || '\u0645\u062c\u0627\u0646\u064a';
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
            var u = localStorage.getItem('user');
            return u ? JSON.parse(u) : null;
        } catch(e) {
            return null;
        }
    },

    // API POST with CSRF - auto-retries on CSRF failure
    // Automatically includes user_id from localStorage for serverless auth
    async post(endpoint, data, retries) {
        data = data || {};
        retries = retries !== undefined ? retries : 1;

        // Get fresh CSRF token before each request
        var token = await this.getCSRFToken();
        data.csrf_token = token;

        // Auto-include user_id from localStorage (Vercel serverless auth)
        var user = this.getUser();
        if (user && user.id && !data.user_id) {
            data.user_id = user.id;
        }

        try {
            var res = await fetch(this.apiBase + '/' + endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify(data),
            });
            var result = await res.json();

            // If CSRF invalid, get a new token and retry once
            if (!result.success && res.status === 403 && retries > 0) {
                console.warn('CSRF token expired, refreshing and retrying...');
                var newToken = await this.getCSRFToken();
                data.csrf_token = newToken;
                var retryRes = await fetch(this.apiBase + '/' + endpoint, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    body: JSON.stringify(data),
                });
                return retryRes.json();
            }

            return result;
        } catch(e) {
            console.error('POST request failed:', e);
            return {success: false, error: '\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645'};
        }
    },

    // Initialize — show user immediately from cache, then verify in background
    async init() {
        // Show user from localStorage immediately (no flicker)
        var cachedUser = this.getUser();
        if (cachedUser) {
            this.updateUI(cachedUser);
        }

        // Get CSRF token and verify auth in parallel (non-blocking)
        this.getCSRFToken();
        this.checkAuth();
    }
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    DalilApp.init();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
