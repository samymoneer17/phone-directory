/**
 * dalil-app.js - Shared JS module for phone directory
 * Handles: auth state, CSRF tokens, API calls, user menu
 * 
 * Vercel Serverless Auth:
 * Uses JWT auth tokens instead of PHP sessions.
 * Token is stored in localStorage and sent with every API request.
 * Token expires after 2 hours — user must login again.
 */

var DalilApp = {
    apiBase: '/api',

    // Get a fresh CSRF token from server
    getCSRFToken: function() {
        var self = this;
        try {
            var res = await fetch(self.apiBase + '/csrf.php', {
                credentials: 'same-origin',
            });
            if (!res.ok) return '';
            var data = await res.json();
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
    getCSRFTokenSync: function() {
        return sessionStorage.getItem('csrf_token') || '';
    },

    // Check auth status using auth_token
    checkAuth: async function() {
        var self = this;
        var cachedUser = this.getUser();
        var authToken = localStorage.getItem('auth_token');

        // Step 1: Show user from cache immediately (instant UI)
        if (cachedUser && authToken) {
            this.updateUI(cachedUser);
        }

        // Step 2: Verify token with server
        if (authToken) {
            try {
                var res = await fetch(this.apiBase + '/check-auth.php', {
                    method: 'GET',
                    headers: {
                        'X-Auth-Token': authToken,
                    },
                    credentials: 'same-origin',
                });
                var data = await res.json();
                console.log('[DalilApp] checkAuth response:', data);

                if (data.success && data.logged_in && data.user) {
                    localStorage.setItem('user', JSON.stringify(data.user));
                    this.updateUI(data.user);
                    return data.user;
                } else {
                    // Token expired or invalid — clear everything
                    console.warn('[DalilApp] Auth token expired or invalid');
                    this.logout();
                    return null;
                }
            } catch(e) {
                console.error('[DalilApp] Auth check failed:', e);
                // If server unreachable, trust cache
                return cachedUser;
            }
        }

        // No token at all
        if (!cachedUser) {
            this.updateUI(null);
        }
        return cachedUser;
    },

    // Save user and auth token (safe — won't throw)
    setUser: function(user, authToken) {
        try {
            if (user) {
                localStorage.setItem('user', JSON.stringify(user));
                if (authToken) {
                    localStorage.setItem('auth_token', authToken);
                }
                this.updateUI(user);
            }
        } catch(e) {
            console.error('[DalilApp] setUser failed (storage issue):', e);
            // Storage might be full or blocked — try clearing old data
            try {
                localStorage.removeItem('searches_today');
                if (user) {
                    localStorage.setItem('user', JSON.stringify(user));
                    if (authToken) {
                        localStorage.setItem('auth_token', authToken);
                    }
                }
            } catch(e2) {
                console.error('[DalilApp] setUser retry failed:', e2);
            }
        }
    },

    // Clear user state (explicit logout)
    logout: function() {
        var token = localStorage.getItem('auth_token');
        try {
            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
            localStorage.removeItem('searches_today');
        } catch(e) {
            console.error('[DalilApp] logout clear failed:', e);
        }
        this.updateUI(null);

        // Revoke token on server (best-effort, don't wait)
        if (token) {
            this.getCSRFToken().then(function(csrf) {
                fetch(DalilApp.apiBase + '/auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'logout',
                        auth_token: token,
                        csrf_token: csrf,
                    }),
                }).catch(function() {});
            });
        }
    },

    // Update UI based on auth state
    updateUI: function(user) {
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
    getUser: function() {
        try {
            var u = localStorage.getItem('user');
            return u ? JSON.parse(u) : null;
        } catch(e) {
            return null;
        }
    },

    // Get auth token
    getAuthToken: function() {
        try {
            return localStorage.getItem('auth_token') || '';
        } catch(e) {
            return '';
        }
    },

    // API POST with CSRF + auth token
    post: async function(endpoint, data, retries) {
        data = data || {};
        retries = retries !== undefined ? retries : 1;

        // Get fresh CSRF token
        var token = await this.getCSRFToken();
        data.csrf_token = token;

        // Add auth token
        var authToken = this.getAuthToken();
        if (authToken && !data.auth_token) {
            data.auth_token = authToken;
        }

        try {
            var headers = {'Content-Type': 'application/json'};
            if (authToken) {
                headers['X-Auth-Token'] = authToken;
            }

            var res = await fetch(this.apiBase + '/' + endpoint, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(data),
            });

            // Read response text first, then parse (safer than res.json())
            var responseText = await res.text();
            console.log('[DalilApp] POST ' + endpoint + ' status:' + res.status + ' body:' + responseText.substring(0, 200));

            var result;
            try {
                result = JSON.parse(responseText);
            } catch(parseErr) {
                console.error('[DalilApp] JSON parse failed for ' + endpoint + ':', parseErr, 'Raw:', responseText.substring(0, 500));
                return {success: false, error: '\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645'};
            }

            // If CSRF invalid, retry once with fresh token
            if (!result.success && res.status === 403 && retries > 0) {
                console.warn('[DalilApp] CSRF token expired, refreshing and retrying...');
                var newToken = await this.getCSRFToken();
                data.csrf_token = newToken;
                var retryRes = await fetch(this.apiBase + '/' + endpoint, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: JSON.stringify(data),
                });
                var retryText = await retryRes.text();
                try {
                    return JSON.parse(retryText);
                } catch(e) {
                    return {success: false, error: '\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645'};
                }
            }

            // If auth_token expired, logout
            if (!result.success && res.status === 401 && result.error === 'auth_required') {
                console.warn('[DalilApp] Auth token expired, logging out');
                this.logout();
                return result;
            }

            return result;
        } catch(e) {
            console.error('[DalilApp] POST request failed:', e);
            return {success: false, error: '\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645'};
        }
    },

    // Initialize — show cached user, then verify token with server
    init: function() {
        var cachedUser = this.getUser();
        var authToken = null;
        try { authToken = localStorage.getItem('auth_token'); } catch(e) {}

        // Show user immediately from cache (no flicker)
        if (cachedUser && authToken) {
            this.updateUI(cachedUser);
        }

        // Verify with server and get CSRF in parallel
        this.getCSRFToken();
        this.checkAuth();
    }
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    DalilApp.init();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
