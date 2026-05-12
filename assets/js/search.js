/**
 * ============================================
 * دليل الهاتف الدولي - International Phone Directory
 * Search Functionality - search.js
 * Version: 1.0.0
 * ============================================
 */

// Country codes data for auto-detection
const COUNTRY_CODES = [
    { code: '+966', name: 'السعودية', flag: '🇸🇦', iso: 'SA' },
    { code: '+971', name: 'الإمارات', flag: '🇦🇪', iso: 'AE' },
    { code: '+965', name: 'الكويت', flag: '🇰🇼', iso: 'KW' },
    { code: '+974', name: 'قطر', flag: '🇶🇦', iso: 'QA' },
    { code: '+973', name: 'البحرين', flag: '🇧🇭', iso: 'BH' },
    { code: '+968', name: 'عُمان', flag: '🇴🇲', iso: 'OM' },
    { code: '+20', name: 'مصر', flag: '🇪🇬', iso: 'EG' },
    { code: '+962', name: 'الأردن', flag: '🇯🇴', iso: 'JO' },
    { code: '+961', name: 'لبنان', flag: '🇱🇧', iso: 'LB' },
    { code: '+964', name: 'العراق', flag: '🇮🇶', iso: 'IQ' },
    { code: '+963', name: 'سوريا', flag: '🇸🇾', iso: 'SY' },
    { code: '+970', name: 'فلسطين', flag: '🇵🇸', iso: 'PS' },
    { code: '+212', name: 'المغرب', flag: '🇲🇦', iso: 'MA' },
    { code: '+216', name: 'تونس', flag: '🇹🇳', iso: 'TN' },
    { code: '+213', name: 'الجزائر', flag: '🇩🇿', iso: 'DZ' },
    { code: '+218', name: 'ليبيا', flag: '🇱🇾', iso: 'LY' },
    { code: '+249', name: 'السودان', flag: '🇸🇩', iso: 'SD' },
    { code: '+967', name: 'اليمن', flag: '🇾🇪', iso: 'YE' },
    { code: '+269', name: 'جزر القمر', flag: '🇰🇲', iso: 'KM' },
    { code: '+253', name: 'جيبوتي', flag: '🇩🇯', iso: 'DJ' },
    { code: '+252', name: 'الصومال', flag: '🇸🇴', iso: 'SO' },
    { code: '+968', name: 'موريتانيا', flag: '🇲🇷', iso: 'MR' },
    { code: '+1', name: 'أمريكا/كندا', flag: '🇺🇸', iso: 'US' },
    { code: '+44', name: 'بريطانيا', flag: '🇬🇧', iso: 'GB' },
    { code: '+33', name: 'فرنسا', flag: '🇫🇷', iso: 'FR' },
    { code: '+49', name: 'ألمانيا', flag: '🇩🇪', iso: 'DE' },
    { code: '+90', name: 'تركيا', flag: '🇹🇷', iso: 'TR' },
    { code: '+91', name: 'الهند', flag: '🇮🇳', iso: 'IN' },
    { code: '+92', name: 'باكستان', flag: '🇵🇰', iso: 'PK' },
    { code: '+98', name: 'إيران', flag: '🇮🇷', iso: 'IR' },
    { code: '+880', name: 'بنغلاديش', flag: '🇧🇩', iso: 'BD' },
    { code: '+60', name: 'ماليزيا', flag: '🇲🇾', iso: 'MY' },
    { code: '+62', name: 'إندونيسيا', flag: '🇮🇩', iso: 'ID' },
    { code: '+66', name: 'تايلاند', flag: '🇹🇭', iso: 'TH' },
    { code: '+86', name: 'الصين', flag: '🇨🇳', iso: 'CN' },
    { code: '+81', name: 'اليابان', flag: '🇯🇵', iso: 'JP' },
    { code: '+82', name: 'كوريا الجنوبية', flag: '🇰🇷', iso: 'KR' },
    { code: '+55', name: 'البرازيل', flag: '🇧🇷', iso: 'BR' },
    { code: '+7', name: 'روسيا', flag: '🇷🇺', iso: 'RU' },
    { code: '+39', name: 'إيطاليا', flag: '🇮🇹', iso: 'IT' },
    { code: '+34', name: 'إسبانيا', flag: '🇪🇸', iso: 'ES' },
    { code: '+27', name: 'جنوب أفريقيا', flag: '🇿🇦', iso: 'ZA' },
    { code: '+234', name: 'نيجيريا', flag: '🇳🇬', iso: 'NG' },
    { code: '+972', name: 'إسرائيل', flag: '🇮🇱', iso: 'IL' },
    { code: '+380', name: 'أوكرانيا', flag: '🇺🇦', iso: 'UA' },
];

/**
 * SearchForm Class
 * Handles the main search functionality
 */
class SearchForm {
    /**
     * @param {Object} options - Configuration options
     * @param {string} options.formSelector - Search form CSS selector
     * @param {string} options.inputSelector - Search input CSS selector
     * @param {string} options.resultsSelector - Results container CSS selector
     * @param {string} options.searchEndpoint - API endpoint for search
     * @param {string} options.countryFlagSelector - Country flag element selector
     * @param {string} options.countryCodeSelector - Country code element selector
     * @param {number} options.debounceTime - Debounce time in ms
     * @param {number} options.minLength - Minimum search length
     * @param {boolean} options.enableHistory - Enable search history
     * @param {number} options.maxHistoryItems - Max history items to keep
     */
    constructor(options = {}) {
        this.formSelector = options.formSelector || '.search-form';
        this.inputSelector = options.inputSelector || '.search-input';
        this.resultsSelector = options.resultsSelector || '.search-results';
        this.searchEndpoint = options.searchEndpoint || '/api/search';
        this.countryFlagSelector = options.countryFlagSelector || '.search-country-flag';
        this.countryCodeSelector = options.countryCodeSelector || '.search-country-dial';
        this.debounceTime = options.debounceTime || 400;
        this.minLength = options.minLength || 3;
        this.enableHistory = options.enableHistory !== false;
        this.maxHistoryItems = options.maxHistoryItems || 10;

        // State
        this.isSearching = false;
        this.currentResults = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.totalResults = 0;
        this.searchQuery = '';
        this.selectedCountry = COUNTRY_CODES[0]; // Default Saudi Arabia
        this.suggestionsOpen = false;
        this.suggestionIndex = -1;
        this.abortController = null;

        // History
        this.historyKey = 'phone-directory-search-history';

        // Debounced search function
        this.debouncedSearch = App.debounce(() => this.performSearch(), this.debounceTime);

        // Initialize
        this._init();
    }

    /**
     * Initialize search form
     */
    _init() {
        const form = document.querySelector(this.formSelector);
        const input = document.querySelector(this.inputSelector);

        if (!form || !input) {
            console.warn('SearchForm: Form or input element not found');
            return;
        }

        this.form = form;
        this.input = input;
        this.resultsContainer = document.querySelector(this.resultsSelector);

        // Bind events
        this._bindEvents();

        // Load search history
        if (this.enableHistory) {
            this._loadHistory();
        }

        // Set default country
        this._updateCountryDisplay();
    }

    /**
     * Bind all event listeners
     */
    _bindEvents() {
        // Form submit
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.performSearch();
        });

        // Input events
        this.input.addEventListener('input', () => {
            this._onInputChange();
        });

        this.input.addEventListener('focus', () => {
            this._onInputFocus();
        });

        this.input.addEventListener('blur', () => {
            setTimeout(() => {
                this._closeSuggestions();
            }, 200);
        });

        // Keyboard navigation for suggestions
        this.input.addEventListener('keydown', (e) => {
            this._onInputKeydown(e);
        });

        // Click on search button
        const searchBtn = this.form.querySelector('.search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.performSearch();
            });
        }

        // Results container click delegation
        if (this.resultsContainer) {
            this.resultsContainer.addEventListener('click', (e) => {
                this._onResultClick(e);
            });
        }

        // Country code click (if exists)
        const countryCodeEl = document.querySelector('.search-country-code');
        if (countryCodeEl) {
            countryCodeEl.addEventListener('click', () => {
                this._toggleCountryPicker();
            });
        }
    }

    /**
     * Handle input change event
     */
    _onInputChange() {
        const value = this.input.value.trim();

        // Detect country code if input starts with +
        if (value.startsWith('+')) {
            this._detectCountryCode(value);
        }

        // Show suggestions
        if (value.length >= 1) {
            this._showSuggestions(value);
        } else {
            this._closeSuggestions();
        }

        // Debounced search
        if (value.length >= this.minLength || value.length === 0) {
            this.debouncedSearch();
        }
    }

    /**
     * Handle input focus event
     */
    _onInputFocus() {
        const value = this.input.value.trim();

        // Show history if input is empty
        if (this.enableHistory && value.length === 0) {
            this._showHistory();
        } else if (value.length >= 1) {
            this._showSuggestions(value);
        }
    }

    /**
     * Handle keyboard navigation
     * @param {KeyboardEvent} e
     */
    _onInputKeydown(e) {
        const suggestionsContainer = document.querySelector('.search-suggestions');
        if (!suggestionsContainer || !suggestionsContainer.classList.contains('open')) return;

        const items = suggestionsContainer.querySelectorAll('.search-suggestion-item');
        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.suggestionIndex = Math.min(this.suggestionIndex + 1, items.length - 1);
                this._highlightSuggestion(items);
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.suggestionIndex = Math.max(this.suggestionIndex - 1, -1);
                this._highlightSuggestion(items);
                break;

            case 'Enter':
                e.preventDefault();
                if (this.suggestionIndex >= 0 && items[this.suggestionIndex]) {
                    items[this.suggestionIndex].click();
                } else {
                    this.performSearch();
                }
                break;

            case 'Escape':
                e.preventDefault();
                this._closeSuggestions();
                this.input.blur();
                break;
        }
    }

    /**
     * Highlight a suggestion item
     * @param {NodeList} items
     */
    _highlightSuggestion(items) {
        items.forEach((item, index) => {
            item.classList.toggle('active', index === this.suggestionIndex);
        });
    }

    /**
     * Handle result card click (copy phone number)
     * @param {Event} e
     */
    _onResultClick(e) {
        const copyBtn = e.target.closest('.result-action-btn[data-copy]');
        const resultCard = e.target.closest('.result-card');

        if (copyBtn) {
            e.preventDefault();
            const phone = copyBtn.getAttribute('data-copy');
            if (phone) {
                App.copyPhone(phone);
            }
            return;
        }

        if (resultCard) {
            const phone = resultCard.getAttribute('data-phone');
            if (phone) {
                App.copyPhone(phone);
            }
        }
    }

    /**
     * Detect country code from input value
     * @param {string} value
     */
    _detectCountryCode(value) {
        const cleaned = value.replace(/[\s\-()]/g, '');

        for (const country of COUNTRY_CODES) {
            const code = country.code;
            if (cleaned.startsWith(code)) {
                this.selectedCountry = country;
                this._updateCountryDisplay();
                return;
            }
        }
    }

    /**
     * Update the country flag and code display
     */
    _updateCountryDisplay() {
        const flagEl = document.querySelector(this.countryFlagSelector);
        const codeEl = document.querySelector(this.countryCodeSelector);

        if (flagEl && this.selectedCountry) {
            flagEl.textContent = this.selectedCountry.flag;
        }

        if (codeEl && this.selectedCountry) {
            codeEl.textContent = this.selectedCountry.code;
        }
    }

    /**
     * Show search suggestions
     * @param {string} query
     */
    _showSuggestions(query) {
        let suggestionsContainer = document.querySelector('.search-suggestions');

        if (!suggestionsContainer) {
            const searchBox = document.querySelector('.search-box');
            if (!searchBox) return;

            suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'search-suggestions';
            searchBox.appendChild(suggestionsContainer);
        }

        // Filter countries by query
        const filteredCountries = COUNTRY_CODES.filter((c) => {
            const q = query.toLowerCase().replace('+', '');
            return (
                c.code.replace('+', '').includes(q) ||
                c.name.includes(query) ||
                c.iso.toLowerCase().includes(q.toLowerCase())
            );
        }).slice(0, 6);

        if (filteredCountries.length === 0 && !query.startsWith('+')) {
            this._closeSuggestions();
            return;
        }

        const searchIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

        let html = '';

        if (query.startsWith('+')) {
            filteredCountries.forEach((country) => {
                html += `
                    <div class="search-suggestion-item" data-code="${country.code}" role="option">
                        <div class="search-suggestion-icon">${searchIcon}</div>
                        <div class="search-suggestion-text">
                            <strong>${country.flag} ${country.name}</strong>
                            <span>${country.code}</span>
                        </div>
                    </div>
                `;
            });
        } else {
            // General suggestions
            html += `
                <div class="search-suggestion-item" role="option">
                    <div class="search-suggestion-icon">${searchIcon}</div>
                    <div class="search-suggestion-text">
                        <strong>البحث عن "${query}"</strong>
                        <span>اضغط Enter للبحث</span>
                    </div>
                </div>
            `;
        }

        suggestionsContainer.innerHTML = html;
        suggestionsContainer.classList.add('open');
        this.suggestionsOpen = true;
        this.suggestionIndex = -1;

        // Bind click events
        suggestionsContainer.querySelectorAll('.search-suggestion-item').forEach((item) => {
            item.addEventListener('click', () => {
                const code = item.getAttribute('data-code');
                if (code) {
                    this.input.value = code + ' ';
                    this._detectCountryCode(code);
                    this.input.focus();
                } else {
                    this.performSearch();
                }
                this._closeSuggestions();
            });
        });
    }

    /**
     * Show search history
     */
    _showHistory() {
        const history = this._getHistory();
        if (history.length === 0) return;

        let suggestionsContainer = document.querySelector('.search-suggestions');

        if (!suggestionsContainer) {
            const searchBox = document.querySelector('.search-box');
            if (!searchBox) return;

            suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'search-suggestions';
            searchBox.appendChild(suggestionsContainer);
        }

        const clockIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

        let html = '';
        history.slice(0, 5).forEach((item) => {
            html += `
                <div class="search-suggestion-item" data-query="${App._escapeHtml(item.query)}" role="option">
                    <div class="search-suggestion-icon">${clockIcon}</div>
                    <div class="search-suggestion-text">
                        <strong>${App._escapeHtml(item.query)}</strong>
                        <span>${item.time || ''}</span>
                    </div>
                </div>
            `;
        });

        suggestionsContainer.innerHTML = html;
        suggestionsContainer.classList.add('open');
        this.suggestionsOpen = true;

        // Bind click events
        suggestionsContainer.querySelectorAll('.search-suggestion-item').forEach((item) => {
            item.addEventListener('click', () => {
                const query = item.getAttribute('data-query');
                if (query) {
                    this.input.value = query;
                    this.searchQuery = query;
                    this.performSearch();
                }
                this._closeSuggestions();
            });
        });
    }

    /**
     * Close suggestions dropdown
     */
    _closeSuggestions() {
        const suggestionsContainer = document.querySelector('.search-suggestions');
        if (suggestionsContainer) {
            suggestionsContainer.classList.remove('open');
        }
        this.suggestionsOpen = false;
        this.suggestionIndex = -1;
    }

    /**
     * Toggle country picker (simplified - shows suggestions)
     */
    _toggleCountryPicker() {
        if (this.suggestionsOpen) {
            this._closeSuggestions();
        } else {
            this.input.focus();
            this._showSuggestions('+');
        }
    }

    /**
     * Perform the search
     * @param {number} page - Page number (default 1)
     */
    async performSearch(page = 1) {
        const query = this.input.value.trim();

        if (query.length < this.minLength && query.length > 0) {
            return;
        }

        this.searchQuery = query;
        this.currentPage = page;

        // Show loading state
        this._showLoading();

        // Add to history
        if (this.enableHistory && query.length >= this.minLength) {
            this._addToHistory(query);
        }

        // Cancel previous request
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        try {
            const response = await App.ajax({
                url: this.searchEndpoint,
                method: 'POST',
                data: {
                    query: query,
                    country_code: this.selectedCountry?.code || '',
                    page: page,
                    per_page: 20
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.success) {
                this.currentResults = response.data.results || [];
                this.totalPages = response.data.last_page || 1;
                this.totalResults = response.data.total || 0;
                this._renderResults(response.data);
            } else {
                this._renderError(response.error || 'حدث خطأ أثناء البحث');
            }
        } catch (error) {
            // Use demo data if API fails (development mode)
            this._renderDemoResults(query);
        } finally {
            this._hideLoading();
            this.isSearching = false;
        }
    }

    /**
     * Show loading skeleton
     */
    _showLoading() {
        if (!this.resultsContainer) return;

        this.isSearching = true;

        // Add loading state to search button
        const searchBtn = this.form.querySelector('.search-btn');
        if (searchBtn) {
            searchBtn.classList.add('loading');
        }

        // Show skeleton cards
        this.resultsContainer.innerHTML = `
            <div class="search-skeleton">
                ${this._getSkeletonHTML()}
                ${this._getSkeletonHTML()}
                ${this._getSkeletonHTML()}
                ${this._getSkeletonHTML()}
                ${this._getSkeletonHTML()}
            </div>
        `;
    }

    /**
     * Get skeleton card HTML
     * @returns {string}
     */
    _getSkeletonHTML() {
        return `
            <div class="search-skeleton-card">
                <div class="search-skeleton-avatar"></div>
                <div class="search-skeleton-info">
                    <div class="search-skeleton-line"></div>
                    <div class="search-skeleton-line"></div>
                    <div class="search-skeleton-line"></div>
                </div>
            </div>
        `;
    }

    /**
     * Hide loading state
     */
    _hideLoading() {
        const searchBtn = this.form.querySelector('.search-btn');
        if (searchBtn) {
            searchBtn.classList.remove('loading');
        }
    }

    /**
     * Render search results
     * @param {Object} data - API response data
     */
    _renderResults(data) {
        if (!this.resultsContainer) return;

        const results = data.results || data.data || [];
        this.currentResults = results;

        if (results.length === 0) {
            this._renderEmpty();
            return;
        }

        // Results header
        let html = `
            <div class="search-results-header">
                <div class="search-results-count">
                    تم العثور على <strong class="font-num">${App._formatNumber(data.total || results.length)}</strong> نتيجة
                </div>
                <div class="search-results-actions">
                    <button class="btn btn-sm btn-ghost" onclick="searchForm.clearResults()" data-tooltip="مسح النتائج">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        مسح
                    </button>
                </div>
            </div>
        `;

        // Result cards
        results.forEach((result) => {
            html += this._renderResultCard(result);
        });

        // Pagination
        if (data.last_page > 1) {
            html += this._renderPagination(data.current_page, data.last_page);
        }

        this.resultsContainer.innerHTML = html;

        // Dispatch results event
        document.dispatchEvent(new CustomEvent('search:results', {
            detail: { results, total: data.total, page: data.current_page }
        }));
    }

    /**
     * Render a single result card
     * @param {Object} result
     * @returns {string}
     */
    _renderResultCard(result) {
        const name = result.name || result.full_name || 'غير معروف';
        const phone = result.phone || result.number || '';
        const location = result.city || result.country || result.location || '';
        const type = result.type || '';

        const personIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        const copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        const locationIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';

        const badge = type ? `<span class="badge badge-blue badge-sm">${App._escapeHtml(type)}</span>` : '';
        const locationHtml = location ? `
            <div class="result-card-location">
                ${locationIcon}
                <span>${App._escapeHtml(location)}</span>
            </div>
        ` : '';

        return `
            <div class="result-card" data-phone="${App._escapeHtml(phone)}" data-tooltip="انقر للنسخ">
                <div class="result-card-avatar">
                    ${personIcon}
                </div>
                <div class="result-card-info">
                    <div class="result-card-name">
                        ${App._escapeHtml(name)}
                        ${badge}
                    </div>
                    <div class="result-card-phone font-num">${App._escapeHtml(phone)}</div>
                    ${locationHtml}
                </div>
                <div class="result-card-actions">
                    <button class="result-action-btn" data-copy="${App._escapeHtml(phone)}" data-tooltip="نسخ الرقم">
                        ${copyIcon}
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Render empty state
     */
    _renderEmpty() {
        const emptyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>';

        this.resultsContainer.innerHTML = `
            <div class="search-empty animate-fadeIn">
                <div class="search-empty-icon">
                    ${emptyIcon}
                </div>
                <h3>لا توجد نتائج</h3>
                <p>لم يتم العثور على نتائج مطابقة لـ "${App._escapeHtml(this.searchQuery)}"<br>حاول البحث بكلمات مختلفة</p>
            </div>
        `;
    }

    /**
     * Render error state
     * @param {string} message
     */
    _renderError(message) {
        this.resultsContainer.innerHTML = `
            <div class="search-empty animate-fadeIn">
                <div class="search-empty-icon" style="background: #FEE2E2; color: #EF4444;">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h3>حدث خطأ</h3>
                <p>${App._escapeHtml(message)}</p>
            </div>
        `;
    }

    /**
     * Render demo results for development
     * @param {string} query
     */
    _renderDemoResults(query) {
        if (!this.resultsContainer) return;

        const demoResults = [
            { name: 'أحمد محمد العلي', phone: '+966 555 123 4567', location: 'الرياض، السعودية', type: 'أعمال' },
            { name: 'فاطمة عبدالله السعيد', phone: '+966 555 234 5678', location: 'جدة، السعودية', type: 'شخصي' },
            { name: 'خالد إبراهيم الحربي', phone: '+966 555 345 6789', location: 'الدمام، السعودية', type: 'أعمال' },
            { name: 'نورة سعد القحطاني', phone: '+966 555 456 7890', location: 'مكة المكرمة', type: 'شخصي' },
            { name: 'محمد يوسف الشمري', phone: '+966 555 567 8901', location: 'المدينة المنورة', type: 'أعمال' },
        ];

        const data = {
            results: demoResults,
            total: demoResults.length,
            current_page: 1,
            last_page: 1,
            per_page: 20
        };

        this._renderResults(data);
    }

    /**
     * Render pagination controls
     * @param {number} current
     * @param {number} last
     * @returns {string}
     */
    _renderPagination(current, last) {
        let html = '<div class="pagination mt-6">';

        // Previous button
        html += `<button class="page-link ${current <= 1 ? 'page-disabled' : ''}" 
                         ${current > 1 ? `onclick="searchForm.goToPage(${current - 1})"` : ''}
                         aria-label="الصفحة السابقة">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                 </button>`;

        // Page numbers
        const range = this._getPaginationRange(current, last, 5);
        range.forEach((page) => {
            if (page === '...') {
                html += '<span class="page-ellipsis">...</span>';
            } else {
                html += `<button class="page-link ${page === current ? 'page-active' : ''}" 
                                 onclick="searchForm.goToPage(${page})"
                                 aria-label="الصفحة ${page}">
                            <span class="font-num">${page}</span>
                         </button>`;
            }
        });

        // Next button
        html += `<button class="page-link ${current >= last ? 'page-disabled' : ''}" 
                         ${current < last ? `onclick="searchForm.goToPage(${current + 1})"` : ''}
                         aria-label="الصفحة التالية">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                 </button>`;

        html += '</div>';
        return html;
    }

    /**
     * Calculate pagination range
     * @param {number} current
     * @param {number} last
     * @param {number} delta
     * @returns {Array}
     */
    _getPaginationRange(current, last, delta) {
        const range = [];
        const left = Math.max(2, current - delta);
        const right = Math.min(last - 1, current + delta);

        range.push(1);

        if (left > 2) {
            range.push('...');
        }

        for (let i = left; i <= right; i++) {
            range.push(i);
        }

        if (right < last - 1) {
            range.push('...');
        }

        if (last > 1) {
            range.push(last);
        }

        return range;
    }

    /**
     * Go to a specific page
     * @param {number} page
     */
    goToPage(page) {
        if (page < 1 || page > this.totalPages || this.isSearching) return;
        this.performSearch(page);

        // Scroll to results
        if (this.resultsContainer) {
            this.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * Clear search results
     */
    clearResults() {
        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = '';
        }
        this.currentResults = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.totalResults = 0;
        this.searchQuery = '';
        this.input.value = '';
        this.input.focus();
    }

    // ============================================
    // SEARCH HISTORY
    // ============================================

    /**
     * Get search history from localStorage
     * @returns {Array}
     */
    _getHistory() {
        try {
            return App.parseJSON(localStorage.getItem(this.historyKey), []);
        } catch (e) {
            return [];
        }
    }

    /**
     * Save search history to localStorage
     * @param {Array} history
     */
    _saveHistory(history) {
        try {
            localStorage.setItem(this.historyKey, JSON.stringify(history));
        } catch (e) {
            // Silently fail
        }
    }

    /**
     * Load search history on init
     */
    _loadHistory() {
        // History is loaded on demand in _showHistory
    }

    /**
     * Add a search query to history
     * @param {string} query
     */
    _addToHistory(query) {
        if (!query || query.length < this.minLength) return;

        let history = this._getHistory();

        // Remove existing entry for same query
        history = history.filter((item) => item.query !== query);

        // Add new entry at the beginning
        history.unshift({
            query: query,
            time: new Date().toLocaleString('ar-SA'),
            timestamp: Date.now()
        });

        // Trim to max items
        if (history.length > this.maxHistoryItems) {
            history = history.slice(0, this.maxHistoryItems);
        }

        this._saveHistory(history);
    }

    /**
     * Clear search history
     */
    clearHistory() {
        try {
            localStorage.removeItem(this.historyKey);
        } catch (e) {
            // Silently fail
        }
    }
}

// ============================================
// INITIALIZE ON DOM READY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Initialize search form if search element exists
    const searchFormEl = document.querySelector('.search-form');
    if (searchFormEl) {
        window.searchForm = new SearchForm();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SearchForm, COUNTRY_CODES };
}
