/**
 * ============================================
 * دليل الهاتف الدولي - International Phone Directory
 * Theme Manager - theme.js
 * Version: 1.0.0
 * ============================================
 * 
 * Manages dark/light mode switching with:
 * - localStorage persistence
 * - System preference detection
 * - Flash prevention (inline script support)
 * - Smooth theme transitions
 */

class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'phone-directory-theme';
        this.THEME_DARK = 'dark';
        this.THEME_LIGHT = 'light';
        this.SYSTEM_KEY = 'system';
        this.CURRENT_THEME = this.THEME_LIGHT;

        // Theme toggle buttons (will be auto-discovered or set manually)
        this.toggleButtons = [];

        // Callback for theme change
        this.onChangeCallback = null;

        // System media query listener
        this.mediaQuery = null;

        // Initialize
        this._init();
    }

    /**
     * Initialize theme manager
     * Applies theme immediately and sets up listeners
     */
    _init() {
        // Apply theme immediately to prevent flash
        this.applyTheme(this.getPreferredTheme(), false);

        // Set up system preference listener
        this._setupSystemPreferenceListener();

        // Auto-discover and bind toggle buttons after DOM is ready
        this._setupAutoDiscovery();
    }

    /**
     * Get the user's preferred theme
     * Priority: localStorage > system preference > light (default)
     * @returns {string} 'dark', 'light', or 'system'
     */
    getPreferredTheme() {
        const stored = this._getStoredTheme();
        if (stored) {
            return stored;
        }

        const systemPrefersDark = this._getSystemPreference();
        return systemPrefersDark ? this.THEME_DARK : this.THEME_LIGHT;
    }

    /**
     * Get the stored theme from localStorage
     * @returns {string|null}
     */
    _getStoredTheme() {
        try {
            return localStorage.getItem(this.STORAGE_KEY);
        } catch (e) {
            // localStorage not available (private browsing, etc.)
            return null;
        }
    }

    /**
     * Store theme preference in localStorage
     * @param {string} theme
     */
    _storeTheme(theme) {
        try {
            localStorage.setItem(this.STORAGE_KEY, theme);
        } catch (e) {
            // Silently fail if localStorage not available
        }
    }

    /**
     * Detect system dark mode preference
     * @returns {boolean}
     */
    _getSystemPreference() {
        if (typeof window === 'undefined') return false;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    /**
     * Set up listener for system preference changes
     */
    _setupSystemPreferenceListener() {
        if (typeof window === 'undefined') return;

        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        // Modern browsers
        if (this.mediaQuery.addEventListener) {
            this.mediaQuery.addEventListener('change', (e) => {
                const stored = this._getStoredTheme();
                // Only auto-switch if user hasn't explicitly chosen a theme
                if (!stored || stored === this.SYSTEM_KEY) {
                    this.applyTheme(e.matches ? this.THEME_DARK : this.THEME_LIGHT, true);
                }
            });
        }
        // Legacy Safari
        else if (this.mediaQuery.addListener) {
            this.mediaQuery.addListener((e) => {
                const stored = this._getStoredTheme();
                if (!stored || stored === this.SYSTEM_KEY) {
                    this.applyTheme(e.matches ? this.THEME_DARK : this.THEME_LIGHT, true);
                }
            });
        }
    }

    /**
     * Apply theme to the document
     * @param {string} theme - 'dark' or 'light'
     * @param {boolean} animate - Whether to animate the transition
     */
    applyTheme(theme, animate = true) {
        if (typeof document === 'undefined') return;

        const resolvedTheme = this._resolveTheme(theme);

        // Add transition class for smooth switching
        if (animate) {
            document.documentElement.classList.add('theme-transition');
            // Remove transition class after animation completes
            setTimeout(() => {
                document.documentElement.classList.remove('theme-transition');
            }, 350);
        }

        // Apply or remove dark class
        if (resolvedTheme === this.THEME_DARK) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Update meta theme-color
        this._updateMetaThemeColor(resolvedTheme);

        // Update current theme state
        this.CURRENT_THEME = resolvedTheme;

        // Update toggle button states
        this._updateToggleButtons(resolvedTheme);

        // Update theme toggle icons
        this._updateToggleIcons(resolvedTheme);

        // Fire change callback
        if (typeof this.onChangeCallback === 'function') {
            this.onChangeCallback(resolvedTheme);
        }

        // Dispatch custom event
        this._dispatchThemeEvent(resolvedTheme);
    }

    /**
     * Resolve theme string to actual theme value
     * @param {string} theme
     * @returns {string}
     */
    _resolveTheme(theme) {
        if (theme === this.SYSTEM_KEY) {
            return this._getSystemPreference() ? this.THEME_DARK : this.THEME_LIGHT;
        }
        return theme === this.THEME_DARK ? this.THEME_DARK : this.THEME_LIGHT;
    }

    /**
     * Toggle between dark and light mode
     */
    toggle() {
        const newTheme = this.CURRENT_THEME === this.THEME_DARK ? this.THEME_LIGHT : this.THEME_DARK;
        this.applyTheme(newTheme, true);
        this._storeTheme(newTheme);
    }

    /**
     * Set specific theme
     * @param {string} theme - 'dark', 'light', or 'system'
     */
    setTheme(theme) {
        const resolvedTheme = this._resolveTheme(theme);
        this.applyTheme(resolvedTheme, true);
        this._storeTheme(theme);
    }

    /**
     * Update the meta theme-color tag for mobile browsers
     * @param {string} theme
     */
    _updateMetaThemeColor(theme) {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }

        if (theme === this.THEME_DARK) {
            metaThemeColor.setAttribute('content', '#0B1120');
        } else {
            metaThemeColor.setAttribute('content', '#F8FAFC');
        }
    }

    /**
     * Auto-discover theme toggle buttons in the DOM
     * Binds click handlers to elements with data-theme-toggle attribute
     */
    _setupAutoDiscovery() {
        if (typeof document === 'undefined') return;

        const discoverAndBind = () => {
            const toggleElements = document.querySelectorAll('[data-theme-toggle]');
            toggleElements.forEach((el) => {
                if (!el.hasAttribute('data-theme-bound')) {
                    el.setAttribute('data-theme-bound', 'true');
                    el.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggle();
                    });
                    this.toggleButtons.push(el);
                }
            });

            // Update initial state
            this._updateToggleButtons(this.CURRENT_THEME);
            this._updateToggleIcons(this.CURRENT_THEME);
        };

        // Run immediately if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', discoverAndBind);
        } else {
            discoverAndBind();
        }

        // Also run on DOM mutations (for dynamically added buttons)
        this._observer = new MutationObserver(() => {
            discoverAndBind();
        });

        if (document.body) {
            this._observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    /**
     * Manually bind a toggle button
     * @param {HTMLElement} element
     */
    bindToggle(element) {
        if (!element) return;

        element.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });

        this.toggleButtons.push(element);
        this._updateToggleButtons(this.CURRENT_THEME);
        this._updateToggleIcons(this.CURRENT_THEME);
    }

    /**
     * Update toggle button states
     * @param {string} theme
     */
    _updateToggleButtons(theme) {
        this.toggleButtons.forEach((btn) => {
            // Update aria-pressed attribute
            btn.setAttribute('aria-pressed', theme === this.THEME_DARK ? 'true' : 'false');
            btn.setAttribute('aria-label', theme === this.THEME_DARK ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع الداكن');
        });
    }

    /**
     * Update toggle button icons
     * Shows sun icon in dark mode, moon icon in light mode
     * @param {string} theme
     */
    _updateToggleIcons(theme) {
        this.toggleButtons.forEach((btn) => {
            const sunIcon = btn.querySelector('[data-theme-icon="sun"]');
            const moonIcon = btn.querySelector('[data-theme-icon="moon"]');
            const singleIcon = btn.querySelector('.theme-icon-single');

            if (sunIcon && moonIcon) {
                if (theme === this.THEME_DARK) {
                    sunIcon.style.display = '';
                    moonIcon.style.display = 'none';
                } else {
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = '';
                }
            }

            if (singleIcon) {
                if (theme === this.THEME_DARK) {
                    singleIcon.innerHTML = this._getSunSVG();
                } else {
                    singleIcon.innerHTML = this._getMoonSVG();
                }
            }
        });
    }

    /**
     * Get SVG for sun icon (shown in dark mode)
     * @returns {string}
     */
    _getSunSVG() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    }

    /**
     * Get SVG for moon icon (shown in light mode)
     * @returns {string}
     */
    _getMoonSVG() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    }

    /**
     * Dispatch custom theme change event
     * @param {string} theme
     */
    _dispatchThemeEvent(theme) {
        if (typeof window === 'undefined') return;

        const event = new CustomEvent('themechange', {
            detail: {
                theme: theme,
                isDark: theme === this.THEME_DARK,
                isLight: theme === this.THEME_LIGHT
            },
            bubbles: true,
            cancelable: true
        });

        document.documentElement.dispatchEvent(event);
        window.dispatchEvent(event);
    }

    /**
     * Register callback for theme changes
     * @param {Function} callback - Called with theme string
     */
    onChange(callback) {
        if (typeof callback === 'function') {
            this.onChangeCallback = callback;
        }
    }

    /**
     * Check if current theme is dark
     * @returns {boolean}
     */
    isDark() {
        return this.CURRENT_THEME === this.THEME_DARK;
    }

    /**
     * Check if current theme is light
     * @returns {boolean}
     */
    isLight() {
        return this.CURRENT_THEME === this.THEME_LIGHT;
    }

    /**
     * Get the current theme
     * @returns {string}
     */
    getTheme() {
        return this.CURRENT_THEME;
    }

    /**
     * Destroy the theme manager
     * Clean up event listeners and observers
     */
    destroy() {
        if (this._observer) {
            this._observer.disconnect();
        }

        // Remove event listeners from toggle buttons
        this.toggleButtons.forEach((btn) => {
            btn.removeEventListener('click', () => {});
            btn.removeAttribute('data-theme-bound');
        });

        this.toggleButtons = [];
        this.onChangeCallback = null;
    }
}

// ============================================
// INSTANTIATION & FLASH PREVENTION
// ============================================

/**
 * Create global theme manager instance
 */
const themeManager = new ThemeManager();

/**
 * Export for module usage
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
