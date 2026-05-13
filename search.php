<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Search Page
 * International Phone Directory
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'البحث - ' . SITE_NAME;
$searchQuery = $_GET['q'] ?? '';
$searchType = $_GET['type'] ?? 'NUMBER';
$page = (int) ($_GET['page'] ?? 1);
require_once __DIR__ . '/includes/header.php';

$todaySearchCount = 0;
$searchLimit = FREE_SEARCH_LIMIT;
$canSearch = true;
$searchHistory = [];

if ($isLoggedIn && $currentUser) {
    $plan = $currentUser['plan'] ?? 'FREE';
    $searchLimit = PLANS[$plan]['search_limit'] ?? FREE_SEARCH_LIMIT;

    $today = date('Y-m-d');
    $countResult = fetch(
        "SELECT COUNT(*) as cnt FROM search_history WHERE user_id = :uid AND date(created_at) = :today",
        [':uid' => $currentUser['id'], ':today' => $today]
    );
    $todaySearchCount = (int) ($countResult['cnt'] ?? 0);

    if ($todaySearchCount >= $searchLimit) {
        $canSearch = false;
    }

    // Get recent search history
    $searchHistory = fetchAll(
        "SELECT query, query_type, created_at FROM search_history WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10",
        [':uid' => $currentUser['id']]
    );
}

$countryInfo = [];
if (!empty($searchQuery)) {
    $countryInfo = detectCountry($searchQuery);
}
?>

<!-- Search Section -->
<section class="search-container" style="min-height: auto; padding: 3rem 1rem 2rem;">
    <div class="search-header" style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">البحث المتقدم</h1>
        <p>ابحث عن أي رقم هاتف أو اسم في قاعدة بياناتنا الشاملة</p>
    </div>

    <!-- Search Box -->
    <div class="search-box" style="max-width: 700px; margin: 0 auto 1.5rem;">
        <div class="search-box-inner" id="searchBoxInner">
            <?php if (!empty($countryInfo['countryCode'])): ?>
            <div class="search-country-code" id="searchCountryCode">
                <span class="search-country-flag" id="searchCountryFlag"><?php echo sanitizeOutput($countryInfo['flag']); ?></span>
                <span class="search-country-dial" id="searchCountryDial"><?php echo sanitizeOutput($countryInfo['countryCode']); ?></span>
            </div>
            <?php else: ?>
            <div class="search-country-code" id="searchCountryCode" style="display:none;">
                <span class="search-country-flag" id="searchCountryFlag">🌍</span>
                <span class="search-country-dial" id="searchCountryDial"></span>
            </div>
            <?php endif; ?>
            <input type="text" class="search-input" id="searchInput" placeholder="أدخل الاسم أو الرقم (مثال: 966512345678)" value="<?php echo sanitizeOutput($searchQuery); ?>" autocomplete="off" dir="ltr" style="text-align:right;">
            <button class="search-btn" id="searchBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                بحث
            </button>
        </div>
    </div>

    <!-- Search Type Toggle -->
    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem;">
        <button class="btn <?php echo $searchType === 'NUMBER' ? 'btn-primary' : 'btn-ghost'; ?>" id="toggleNumber" onclick="setSearchType('NUMBER')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            بحث بالرقم
        </button>
        <button class="btn <?php echo $searchType === 'NAME' ? 'btn-primary' : 'btn-ghost'; ?>" id="toggleName" onclick="setSearchType('NAME')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            بحث بالاسم
        </button>
    </div>

    <!-- Limit Warning -->
    <?php if (!$canSearch && $isLoggedIn): ?>
    <div class="alert" style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; max-width: 700px; margin: 0 auto 1.5rem;">
        <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div class="alert-content">
            <div class="alert-title">تم تجاوز حد البحث اليومي</div>
            <div>لقد استخدمت جميع عمليات البحث المتاحة اليوم (<strong><?php echo $todaySearchCount; ?> / <?php echo $searchLimit; ?></strong>). قم بترقية خطتك للحصول على المزيد.</div>
        </div>
        <a href="<?php echo getPageUrl('plans.php'); ?>" class="btn btn-warning btn-sm" style="flex-shrink:0;">ترقية الباقة</a>
    </div>
    <?php endif; ?>

    <!-- Auth Warning -->
    <?php if (!$isLoggedIn): ?>
    <div class="alert" style="background: #DBEAFE; border: 1px solid #BFDBFE; color: #1E40AF; max-width: 700px; margin: 0 auto 1.5rem;">
        <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <div class="alert-content">
            <div>سجل الدخول للاستفادة من جميع مميزات البحث والبحث عن الأرقام.</div>
        </div>
        <a href="<?php echo getPageUrl('login.php'); ?>" class="btn btn-primary btn-sm" style="flex-shrink:0;">تسجيل الدخول</a>
    </div>
    <?php endif; ?>

    <!-- Search Counter -->
    <?php if ($isLoggedIn): ?>
    <div style="text-align: center; margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted);">
        عمليات البحث اليوم: <strong style="color: var(--text-primary); direction: ltr; display: inline-block;"><?php echo $todaySearchCount; ?></strong> / <strong style="color: var(--text-primary); direction: ltr; display: inline-block;"><?php echo $searchLimit; ?></strong>
    </div>
    <?php endif; ?>
</section>

<!-- Results Area -->
<div class="container" style="max-width: 1100px; padding-bottom: 4rem;">
    <div style="display: flex; gap: 2rem;">
        <!-- Main Results -->
        <div style="flex: 1; min-width: 0;">
            <!-- Results Container -->
            <div id="searchResults">
                <?php if (!empty($searchQuery)): ?>
                <!-- Skeleton Loading -->
                <div id="skeletonLoader" class="search-skeleton">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="search-skeleton-card">
                        <div class="search-skeleton-avatar"></div>
                        <div class="search-skeleton-info">
                            <div class="search-skeleton-line"></div>
                            <div class="search-skeleton-line"></div>
                            <div class="search-skeleton-line"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Actual Results (hidden initially, shown by JS) -->
                <div id="actualResults" style="display: none;">
                    <div class="search-results-header" id="resultsHeader">
                        <div class="search-results-count" id="resultsCount"></div>
                    </div>
                    <div id="resultsList"></div>
                    <div id="pagination" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;"></div>
                </div>

                <!-- Empty State (hidden initially) -->
                <div id="emptyState" style="display: none;" class="search-empty">
                    <div class="search-empty-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/></svg>
                    </div>
                    <h3>لا توجد نتائج</h3>
                    <p>لم يتم العثور على نتائج مطابقة. حاول استخدام رقم أو اسم مختلف.</p>
                </div>
                <?php else: ?>
                <!-- Initial State -->
                <div class="search-empty">
                    <div class="search-empty-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                    <h3 style="font-size: 1.15rem; color: var(--text-secondary); margin-bottom: 0.5rem;">ابدأ بالبحث</h3>
                    <p>أدخل رقم هاتف أو اسم للبحث في قاعدة بياناتنا الشاملة</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search History Sidebar -->
        <?php if ($isLoggedIn && !empty($searchHistory)): ?>
        <div style="width: 280px; flex-shrink: 0; display: none;" class="search-sidebar" id="searchSidebar">
            <div class="card" style="padding: 1.25rem;">
                <div class="card-header" style="margin-bottom: 0.75rem; padding-bottom: 0.75rem;">
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        عمليات البحث الأخيرة
                    </h3>
                </div>
                <div style="max-height: 400px; overflow-y: auto;" class="custom-scroll">
                    <?php foreach ($searchHistory as $item): ?>
                    <a href="<?php echo getPageUrl('search.php'); ?>?q=<?php echo urlencode($item['query']); ?>&type=<?php echo sanitizeOutput($item['query_type']); ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.5rem; border-radius: 0.5rem; transition: background 0.15s; text-decoration: none; color: var(--text-secondary);" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; opacity:0.5;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:0.85rem; font-weight:600; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" dir="ltr"><?php echo sanitizeOutput($item['query']); ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted);"><?php echo timeAgo($item['created_at']); ?></div>
                        </div>
                        <span class="badge badge-gray" style="font-size:0.65rem;"><?php echo $item['query_type'] === 'NUMBER' ? 'رقم' : 'اسم'; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .search-sidebar { display: none; }
    @media (min-width: 1024px) {
        .search-sidebar { display: block !important; }
    }
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
</style>

<script>
var currentSearchType = '<?php echo sanitizeOutput($searchType); ?>';
var initialQuery = '<?php echo sanitizeOutput($searchQuery); ?>';

function setSearchType(type) {
    currentSearchType = type;
    document.getElementById('toggleNumber').className = 'btn ' + (type === 'NUMBER' ? 'btn-primary' : 'btn-ghost');
    document.getElementById('toggleName').className = 'btn ' + (type === 'NAME' ? 'btn-primary' : 'btn-ghost');
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    // Auto-search if query exists
    if (initialQuery) {
        setTimeout(function() { performSearch(initialQuery, 1); }, 500);
    }

    // Search button click
    document.getElementById('searchBtn').addEventListener('click', function() {
        var q = document.getElementById('searchInput').value.trim();
        if (q) performSearch(q, 1);
    });

    // Enter key search
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var q = this.value.trim();
            if (q) performSearch(q, 1);
        }
    });

    // Country detection on input
    document.getElementById('searchInput').addEventListener('input', function() {
        var val = this.value.trim();
        var codeEl = document.getElementById('searchCountryCode');
        if (val.startsWith('+') && val.length >= 3) {
            // Detect country from the input
            fetch('/api/search.php?XTransformPort=3000', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'detect-country', query: val, csrf_token: '<?php echo Security::getCSRFToken(); ?>' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.country) {
                    codeEl.style.display = 'flex';
                    document.getElementById('searchCountryFlag').textContent = data.country.flag || '🌍';
                    document.getElementById('searchCountryDial').textContent = data.country.code || '';
                } else {
                    codeEl.style.display = 'none';
                }
            }).catch(function() {});
        } else {
            codeEl.style.display = 'none';
        }
    });
});

function performSearch(query, page) {
    var skeleton = document.getElementById('skeletonLoader');
    var actual = document.getElementById('actualResults');
    var empty = document.getElementById('emptyState');
    var resultsList = document.getElementById('resultsList');
    var resultsCount = document.getElementById('resultsCount');
    var pagination = document.getElementById('pagination');

    if (!query) return;

    // Show skeleton
    skeleton.style.display = 'flex';
    actual.style.display = 'none';
    empty.style.display = 'none';

    fetch('/api/search.php?XTransformPort=3000', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'search',
            query: query,
            type: currentSearchType,
            page: page || 1,
            csrf_token: '<?php echo Security::getCSRFToken(); ?>'
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        skeleton.style.display = 'none';

        if (!data.success) {
            if (data.error === 'auth_required') {
                window.location.href = '<?php echo getPageUrl("login.php"); ?>';
                return;
            }
            if (data.error === 'rate_limited') {
                actual.style.display = 'none';
                empty.style.display = 'block';
                empty.querySelector('h3').textContent = 'تم تجاوز حد البحث';
                empty.querySelector('p').textContent = data.message || 'حاول مرة أخرى لاحقاً';
                return;
            }
            empty.style.display = 'block';
            return;
        }

        if (data.results && data.results.length > 0) {
            actual.style.display = 'block';
            resultsCount.innerHTML = 'تم العثور على <strong>' + data.total + '</strong> نتيجة';

            // Show external sources status
            if (data.external_sources) {
                var sourcesHtml = '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">';
                var sourceNames = {
                    'akwhats': { name: 'اكواتس', icon: '💬' },
                    'loligram': { name: 'لوليغرام', icon: '✈️' },
                    'yemen_phonebook': { name: 'يمن فون بوك', icon: '📖' }
                };
                for (var srcKey in data.external_sources) {
                    var srcData = data.external_sources[srcKey];
                    var srcInfo = sourceNames[srcKey] || { name: srcKey, icon: '🔍' };
                    var srcColor = srcData.found ? '#10B981' : '#94A3B8';
                    var srcBg = srcData.found ? 'rgba(16,185,129,0.1)' : 'rgba(148,163,184,0.1)';
                    sourcesHtml += '<span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:600;color:' + srcColor + ';background:' + srcBg + ';">' + srcInfo.icon + ' ' + srcInfo.name + (srcData.found ? ' ✓' : '') + '</span>';
                }
                sourcesHtml += '</div>';
                resultsCount.innerHTML += sourcesHtml;
            }

            resultsList.innerHTML = '';
            pagination.innerHTML = '';

            data.results.forEach(function(r) {
                var card = document.createElement('div');
                card.className = 'result-card';

                // Source badge
                var sourceBadge = '';
                if (r.source_name) {
                    var srcIcon = r.source_icon || '🔍';
                    var srcBadgeColors = {
                        'AkWhats': { bg: 'rgba(37,211,102,0.1)', color: '#25D366' },
                        'Loligram': { bg: 'rgba(36,161,222,0.1)', color: '#24A1DE' },
                        'YemenPhoneBook': { bg: 'rgba(239,68,68,0.1)', color: '#EF4444' },
                        'local': { bg: 'var(--accent-light)', color: 'var(--accent)' },
                    };
                    var srcColor2 = (srcBadgeColors[r.source] || { bg: 'var(--accent-light)', color: 'var(--accent)' });
                    sourceBadge = '<span style="display:inline-flex;align-items:center;gap:0.2rem;padding:0.1rem 0.4rem;border-radius:9999px;font-size:0.65rem;font-weight:700;color:' + srcColor2.color + ';background:' + srcColor2.bg + ';margin-right:0.5rem;">' + srcIcon + ' ' + r.source_name + '</span>';
                }

                // Extra info for external sources
                var extraHtml = '';
                if (r.extra) {
                    if (r.extra.username) extraHtml += '<div style="font-size:0.75rem;color:var(--text-muted);">@' + r.extra.username + '</div>';
                    if (r.extra.about) extraHtml += '<div style="font-size:0.75rem;color:var(--text-muted);">' + r.extra.about + '</div>';
                    if (r.extra.is_business) extraHtml += '<span style="font-size:0.65rem;color:#F59E0B;background:rgba(245,158,11,0.1);padding:0.1rem 0.3rem;border-radius:4px;">حساب تجاري</span>';
                    if (r.extra.is_premium) extraHtml += '<span style="font-size:0.65rem;color:#8B5CF6;background:rgba(139,92,246,0.1);padding:0.1rem 0.3rem;border-radius:4px;">Premium</span>';
                }

                card.innerHTML = '<div class="result-card-avatar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>' +
                    '<div class="result-card-info">' +
                        '<div class="result-card-name">' + (r.name || 'غير معروف') + sourceBadge + '</div>' +
                        '<div class="result-card-phone">' + (r.phone_hidden ? '••••••••••' : (r.phone || '')) + '</div>' +
                        '<div class="result-card-location"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' + (r.country || '') + (r.operator ? ' - ' + r.operator : '') + (r.city && r.city !== 'غير معروف' ? ' - ' + r.city : '') + '</div>' +
                        extraHtml +
                    '</div>';
                resultsList.appendChild(card);
            });

            // Pagination
            if (data.total_pages > 1) {
                for (var p = 1; p <= data.total_pages; p++) {
                    var btn = document.createElement('button');
                    btn.className = 'btn ' + (p === data.page ? 'btn-primary' : 'btn-ghost') + ' btn-sm';
                    btn.textContent = p;
                    btn.onclick = (function(pg) { return function() { performSearch(query, pg); }; })(p);
                    pagination.appendChild(btn);
                }
            }

            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            empty.style.display = 'block';
            empty.querySelector('h3').textContent = 'لا توجد نتائج';
            empty.querySelector('p').textContent = 'لم يتم العثور على نتائج مطابقة لـ "' + query + '"';
        }
    })
    .catch(function() {
        skeleton.style.display = 'none';
        empty.style.display = 'block';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
