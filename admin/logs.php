<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin Activity Logs
 * International Phone Directory
 * ============================================================
 */

$pageTitle = 'سجل الأنشطة';
$adminPage = 'logs';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireAdmin();

$currentUser = Auth::getCurrentUser();
$csrfToken = Security::getCSRFToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--a-bg:#f8fafc;--a-sidebar:#0f172a;--a-sidebar-w:260px;--a-card:#fff;--a-text:#0f172a;--a-text2:#475569;--a-muted:#94a3b8;--a-border:#e2e8f0;--a-accent:#10b981;--a-danger:#ef4444;--a-warning:#f59e0b;--a-info:#3b82f6;--a-shadow:0 4px 6px rgba(0,0,0,.07);--a-shadow-lg:0 10px 15px rgba(0,0,0,.08);--a-radius:12px;--a-purple:#a855f7;--a-cyan:#06b6d4}
        [data-theme="dark"]{--a-bg:#0b1120;--a-sidebar:#060d1a;--a-card:#1e293b;--a-text:#f1f5f9;--a-text2:#94a3b8;--a-muted:#64748b;--a-border:#1e293b;--a-shadow:0 4px 6px rgba(0,0,0,.3);--a-shadow-lg:0 10px 15px rgba(0,0,0,.4)}
        body{font-family:'Cairo',sans-serif;background:var(--a-bg);color:var(--a-text);min-height:100vh}
        .admin-sidebar{position:fixed;top:0;right:0;bottom:0;width:var(--a-sidebar-w);background:var(--a-sidebar);z-index:100;display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.05);transition:transform .3s}
        .admin-sidebar-logo{padding:1.5rem;display:flex;align-items:center;gap:.75rem;border-bottom:1px solid rgba(255,255,255,.08)}
        .admin-sidebar-logo-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--a-accent),#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff}
        .admin-sidebar-logo h2{color:#fff;font-size:1.1rem;font-weight:700}
        .admin-sidebar-nav{flex:1;padding:1rem .75rem;overflow-y:auto}
        .admin-sidebar-link{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:.9rem;font-weight:500;transition:all .2s;margin-bottom:.25rem}
        .admin-sidebar-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.9)}
        .admin-sidebar-link.active{background:linear-gradient(135deg,var(--a-accent),#06b6d4);color:#fff;font-weight:600;box-shadow:0 4px 12px rgba(16,185,129,.3)}
        .admin-sidebar-link svg{width:20px;height:20px;flex-shrink:0}
        .admin-sidebar-divider{height:1px;background:rgba(255,255,255,.08);margin:.75rem 1rem}
        .admin-sidebar-footer{padding:1rem .75rem;border-top:1px solid rgba(255,255,255,.08)}
        .admin-sidebar-footer .admin-sidebar-link{color:rgba(239,68,68,.7)}
        .admin-sidebar-footer .admin-sidebar-link:hover{color:#ef4444;background:rgba(239,68,68,.1)}
        .admin-mobile-toggle{display:none;position:fixed;top:1rem;right:1rem;z-index:200;width:44px;height:44px;border-radius:10px;background:var(--a-sidebar);color:#fff;border:none;cursor:pointer;align-items:center;justify-content:center;box-shadow:var(--a-shadow-lg)}
        .admin-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:90}
        .admin-main{margin-right:var(--a-sidebar-w);padding:2rem;min-height:100vh;transition:margin-right .3s}

        .admin-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
        .admin-header h1{font-size:1.75rem;font-weight:800}
        .admin-header-actions{display:flex;gap:.75rem;align-items:center}
        .admin-toolbar{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem}
        .admin-search{position:relative;flex:1;min-width:200px;max-width:320px}
        .admin-search input{width:100%;padding:.625rem 1rem .625rem 2.5rem;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.875rem;font-family:'Cairo',sans-serif;outline:none;transition:border-color .2s}
        .admin-search input:focus{border-color:var(--a-accent)}
        .admin-search svg{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--a-muted);width:18px;height:18px}
        .admin-filter{padding:.625rem 1rem;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.85rem;font-family:'Cairo',sans-serif;outline:none;cursor:pointer;min-width:140px;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left .75rem center;padding-left:2rem}
        .admin-filter:focus{border-color:var(--a-accent)}
        .admin-filter[type="date"]{padding-left:1rem;background-image:none;min-width:150px}
        .admin-card{background:var(--a-card);border:1px solid var(--a-border);border-radius:var(--a-radius);box-shadow:var(--a-shadow);overflow:hidden}
        .admin-card-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid var(--a-border);flex-wrap:wrap;gap:.5rem}
        .admin-card-title{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
        .admin-count{background:var(--a-accent);color:#fff;padding:.15rem .5rem;border-radius:9999px;font-size:.75rem;font-weight:700}
        .admin-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .admin-table th{padding:.75rem 1rem;text-align:right;font-weight:600;color:var(--a-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.03em;background:var(--a-bg);border-bottom:1px solid var(--a-border);white-space:nowrap}
        .admin-table td{padding:.625rem .875rem;border-bottom:1px solid var(--a-border);color:var(--a-text2);vertical-align:middle}
        .admin-table tbody tr:hover{background:rgba(16,185,129,.03)}
        .admin-table tr:last-child td{border-bottom:none}

        .action-badge{display:inline-flex;padding:.15rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:600;white-space:nowrap}
        .action-badge.login{background:rgba(59,130,246,.1);color:var(--a-info)}
        .action-badge.register{background:rgba(16,185,129,.1);color:var(--a-accent)}
        .action-badge.payment{background:rgba(245,158,11,.1);color:var(--a-warning)}
        .action-badge.search{background:rgba(6,182,212,.1);color:var(--a-cyan)}
        .action-badge.error{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .action-badge.admin{background:rgba(168,85,247,.1);color:var(--a-purple)}
        .action-badge.default{background:rgba(148,163,184,.1);color:#94a3b8}

        .pagination{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem;flex-wrap:wrap}
        .page-btn{padding:.375rem .75rem;border-radius:8px;background:var(--a-card);border:1px solid var(--a-border);color:var(--a-text);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Cairo',sans-serif}
        .page-btn:hover,.page-btn.active{background:var(--a-accent);color:#fff;border-color:var(--a-accent)}
        .page-btn:disabled{opacity:.4;cursor:not-allowed}
        .admin-spinner{display:flex;justify-content:center;padding:3rem}
        .admin-spinner::after{content:'';width:32px;height:32px;border:3px solid var(--a-border);border-top-color:var(--a-accent);border-radius:50%;animation:aspin .7s linear infinite}
        @keyframes aspin{to{transform:rotate(360deg)}}

        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.625rem 1.25rem;font-family:'Cairo',sans-serif;font-size:.875rem;font-weight:600;border-radius:10px;border:2px solid transparent;cursor:pointer;transition:all .2s;white-space:nowrap}
        .btn-sm{padding:.375rem .875rem;font-size:.8rem;border-radius:8px}
        .btn-danger{background:var(--a-danger);color:#fff}.btn-danger:hover{opacity:.9}
        .btn-ghost{background:transparent;color:var(--a-text2);border-color:var(--a-border)}.btn-ghost:hover{background:var(--a-bg)}
        .btn-info{background:var(--a-info);color:#fff}.btn-info:hover{opacity:.9}

        /* Auto-refresh indicator */
        .auto-refresh{display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--a-muted)}
        .auto-refresh-dot{width:8px;height:8px;border-radius:50%;background:var(--a-accent);animation:pulse 2s infinite}
        .auto-refresh.off .auto-refresh-dot{background:var(--a-muted);animation:none}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

        /* Confirm clear modal */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s;padding:1rem}
        .modal-overlay.show{opacity:1;visibility:visible}
        .modal{background:var(--a-card);border:1px solid var(--a-border);border-radius:16px;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s}
        .modal-overlay.show .modal{transform:scale(1)}
        .modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--a-border)}
        .modal-header h3{font-size:1.1rem;font-weight:700}
        .modal-close{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;color:var(--a-muted);cursor:pointer;transition:all .2s}
        .modal-close:hover{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .modal-body{padding:1.5rem}
        .modal-footer{display:flex;gap:.75rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--a-border)}
        .fg{margin-bottom:1rem}
        .fg label{display:block;font-size:.85rem;font-weight:600;color:var(--a-text2);margin-bottom:.375rem}
        .fg input{width:100%;padding:.625rem 1rem;background:var(--a-bg);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.875rem;font-family:'Cairo',sans-serif;outline:none;transition:border-color .2s;direction:ltr;text-align:left}
        .fg input:focus{border-color:var(--a-accent)}
        .fg .hint{font-size:.75rem;color:var(--a-muted);margin-top:.375rem}

        .admin-toast-container{position:fixed;top:1rem;left:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem}
        .admin-toast{padding:.875rem 1.25rem;border-radius:10px;font-size:.875rem;font-weight:500;box-shadow:var(--a-shadow-lg);animation:tin .3s;display:flex;align-items:center;gap:.5rem;max-width:380px}
        .admin-toast.success{background:var(--a-accent);color:#fff}
        .admin-toast.error{background:var(--a-danger);color:#fff}
        .admin-toast.info{background:var(--a-info);color:#fff}
        @keyframes tin{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}

        .log-detail-tooltip{position:fixed;z-index:600;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;padding:.75rem 1rem;font-size:.8rem;color:var(--a-text2);box-shadow:var(--a-shadow-lg);max-width:350px;word-break:break-word;display:none;pointer-events:none}
        .log-detail-tooltip.show{display:block}

        @media(max-width:768px){.admin-sidebar{transform:translateX(100%)}.admin-sidebar.open{transform:translateX(0)}.admin-overlay.show{display:block}.admin-mobile-toggle{display:flex}.admin-main{margin-right:0;padding:1rem;padding-top:4rem}.admin-toolbar{flex-direction:column;align-items:stretch}.admin-search{max-width:100%}}
        ::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--a-muted);border-radius:3px}
    </style>
    <script>(function(){var t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>
    <button class="admin-mobile-toggle" id="sidebarToggle" aria-label="فتح القائمة"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg></button>
    <div class="admin-overlay" id="adminOverlay"></div>

    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-logo">
            <div class="admin-sidebar-logo-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
            <h2>لوحة التحكم</h2>
        </div>
        <nav class="admin-sidebar-nav">
            <a href="index.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>الرئيسية</a>
            <a href="users.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>المستخدمين</a>
            <a href="payments.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>المدفوعات</a>
            <a href="logs.php" class="admin-sidebar-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>السجلات</a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-divider"></div>
            <a href="../index.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>العودة للموقع</a>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-header">
            <div><h1>سجل الأنشطة</h1></div>
            <div class="admin-header-actions">
                <div class="auto-refresh" id="autoRefreshIndicator">
                    <div class="auto-refresh-dot"></div>
                    <span id="autoRefreshLabel">تحديث تلقائي: 30 ثانية</span>
                </div>
                <button class="btn btn-sm btn-info" id="toggleAutoRefresh" onclick="toggleAutoRefresh()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                    <span id="refreshBtnLabel">إيقاف</span>
                </button>
                <button class="btn btn-sm btn-danger" onclick="openClearModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    حذف الكل
                </button>
            </div>
        </div>

        <div class="admin-toolbar">
            <div class="admin-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="البحث في السجلات..." autocomplete="off">
            </div>
            <select class="admin-filter" id="filterAction">
                <option value="">كل الإجراءات</option>
                <option value="login">تسجيل الدخول</option>
                <option value="register">تسجيل جديد</option>
                <option value="payment">المدفوعات</option>
                <option value="search">البحث</option>
                <option value="admin">إدارة</option>
                <option value="error">الأخطاء</option>
            </select>
            <input type="date" class="admin-filter" id="filterFrom" title="من تاريخ">
            <input type="date" class="admin-filter" id="filterTo" title="إلى تاريخ">
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    سجل النشاط <span class="admin-count" id="totalLogsCount">-</span>
                </div>
            </div>
            <div id="logsTableContainer"><div class="admin-spinner"></div></div>
            <div class="pagination" id="paginationContainer"></div>
        </div>
    </main>

    <!-- Clear Confirm Modal -->
    <div class="modal-overlay" id="clearModal">
        <div class="modal">
            <div class="modal-header">
                <h3>تأكيد حذف جميع السجلات</h3>
                <button class="modal-close" onclick="closeClearModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="modal-body">
                <p style="text-align:center;margin-bottom:1rem;font-size:.95rem;">سيتم حذف جميع سجلات النشاط نهائياً.<br><small style="color:var(--a-danger);">لا يمكن التراجع عن هذا الإجراء!</small></p>
                <div class="fg">
                    <label>اكتب <strong>DELETE_ALL_LOGS</strong> للتأكيد:</label>
                    <input type="text" id="clearConfirmInput" placeholder="DELETE_ALL_LOGS">
                    <div class="hint">يجب كتابة النص بالضبط كما هو موضح للتأكيد</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeClearModal()">إلغاء</button>
                <button class="btn btn-danger" id="clearConfirmBtn" onclick="confirmClearLogs()" disabled>حذف جميع السجلات</button>
            </div>
        </div>
    </div>

    <!-- Detail tooltip -->
    <div class="log-detail-tooltip" id="detailTooltip"></div>

    <div class="admin-toast-container" id="toastContainer"></div>

    <script>
        const CSRF = '<?php echo $csrfToken; ?>';
        const API = '../api/admin.php';
        let currentPage = 1;
        let autoRefreshInterval = null;
        let autoRefreshEnabled = true;

        // Sidebar
        const sidebar=document.getElementById('adminSidebar'),overlay=document.getElementById('adminOverlay'),toggleBtn=document.getElementById('sidebarToggle');
        toggleBtn.addEventListener('click',()=>{sidebar.classList.add('open');overlay.classList.add('show');});
        overlay.addEventListener('click',()=>{sidebar.classList.remove('open');overlay.classList.remove('show');});

        function showToast(msg,type='success'){const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='admin-toast '+type;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),4000);}
        function fmtNum(n){return Number(n).toLocaleString('ar-EG');}

        function timeAgo(d){if(!d)return'-';const s=Math.floor((Date.now()-new Date(d))/1000);if(s<60)return'الآن';if(s<3600)return'منذ '+Math.floor(s/60)+' دقيقة';if(s<86400)return'منذ '+Math.floor(s/3600)+' ساعة';if(s<2592000)return'منذ '+Math.floor(s/86400)+' يوم';if(s<31536000)return'منذ '+Math.floor(s/2592000)+' شهر';return'منذ '+Math.floor(s/31536000)+' سنة';}

        function actionClass(action){
            if(!action)return'default';
            if(action.includes('login')||action.includes('logout'))return'login';
            if(action.includes('register'))return'register';
            if(action.includes('payment'))return'payment';
            if(action.includes('search'))return'search';
            if(action.includes('error')||action.includes('fail')||action.includes('blocked'))return'error';
            if(action.includes('admin'))return'admin';
            return'default';
        }

        function actionLabel(action){
            const labels={
                'login':'تسجيل دخول','logout':'تسجيل خروج','register':'تسجيل جديد',
                'login_failed':'فشل الدخول','login_blocked':'دخول محظور',
                'profile_updated':'تحديث الملف','password_changed':'تغيير كلمة المرور',
                'password_reset':'إعادة تعيين كلمة المرور','reset_requested':'طلب إعادة تعيين',
                'search':'بحث','payment':'دفعة','error':'خطأ',
            };
            for(const [key,label] of Object.entries(labels)){
                if(action.includes(key))return label;
            }
            return action;
        }

        async function api(action,data={}){data.action=action;data.csrf_token=CSRF;try{const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});return await r.json();}catch(e){return{success:false,message:'خطأ في الاتصال'};}}

        async function loadLogs(){
            const search=document.getElementById('searchInput').value;
            const actionType=document.getElementById('filterAction').value;
            const from=document.getElementById('filterFrom').value;
            const to=document.getElementById('filterTo').value;

            const res=await api('logs/list',{page:currentPage,action_type:actionType,date_from:from,date_to:to});
            if(!res.success){showToast(res.message,'error');return;}

            const {data,pagination}=res;
            document.getElementById('totalLogsCount').textContent=fmtNum(pagination.total);

            if(data.length===0){
                document.getElementById('logsTableContainer').innerHTML='<div style="padding:3rem;text-align:center;color:var(--a-muted);">لا يوجد سجلات مطابقة</div>';
                document.getElementById('paginationContainer').innerHTML='';
                return;
            }

            let html=`<div style="overflow-x:auto"><table class="admin-table"><thead><tr>
                <th>#</th><th>المستخدم</th><th>الإجراء</th><th>عنوان IP</th><th>التاريخ</th><th>التفاصيل</th>
            </tr></thead><tbody>`;

            data.forEach((l,i)=>{
                const num=(currentPage-1)*pagination.per_page+i+1;
                const cls=actionClass(l.action);
                const lbl=actionLabel(l.action);
                const detail=l.details||'-';
                const truncated=detail.length>60?detail.substring(0,60)+'...':detail;
                const userName=l.user_name||'زائر';

                html+=`<tr>
                    <td>${num}</td>
                    <td style="font-weight:600;color:var(--a-text);">${userName}</td>
                    <td><span class="action-badge ${cls}">${lbl}</span></td>
                    <td style="font-size:.78rem;direction:ltr;text-align:right;font-family:monospace;">${l.ip_address||'-'}</td>
                    <td style="white-space:nowrap;font-size:.78rem;">${timeAgo(l.created_at)}</td>
                    <td style="font-size:.8rem;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:help;" title="${detail.replace(/"/g,'&quot;')}">${truncated}</td>
                </tr>`;
            });

            html+='</tbody></table></div>';
            document.getElementById('logsTableContainer').innerHTML=html;

            let pagHtml='';
            if(pagination.total_pages>1){
                pagHtml+=`<button class="page-btn" ${currentPage<=1?'disabled':''} onclick="goPage(${currentPage-1})">السابق</button>`;
                const start=Math.max(1,currentPage-2),end=Math.min(pagination.total_pages,currentPage+2);
                for(let p=start;p<=end;p++) pagHtml+=`<button class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</button>`;
                pagHtml+=`<button class="page-btn" ${currentPage>=pagination.total_pages?'disabled':''} onclick="goPage(${currentPage+1})">التالي</button>`;
            }
            document.getElementById('paginationContainer').innerHTML=pagHtml;
        }

        function goPage(p){currentPage=p;loadLogs();}

        // Filters
        document.getElementById('searchInput').addEventListener('input',()=>{currentPage=1;loadLogs();});
        document.getElementById('filterAction').addEventListener('change',()=>{currentPage=1;loadLogs();});
        ['filterFrom','filterTo'].forEach(id=>{
            document.getElementById(id).addEventListener('change',()=>{currentPage=1;loadLogs();});
        });

        // Auto-refresh
        function startAutoRefresh(){
            if(autoRefreshInterval)clearInterval(autoRefreshInterval);
            autoRefreshInterval=setInterval(()=>{loadLogs();},30000);
            document.getElementById('autoRefreshIndicator').classList.remove('off');
            document.getElementById('refreshBtnLabel').textContent='إيقاف';
        }

        function stopAutoRefresh(){
            if(autoRefreshInterval)clearInterval(autoRefreshInterval);
            autoRefreshInterval=null;
            document.getElementById('autoRefreshIndicator').classList.add('off');
            document.getElementById('refreshBtnLabel').textContent='تشغيل';
        }

        function toggleAutoRefresh(){
            if(autoRefreshEnabled){stopAutoRefresh();showToast('تم إيقاف التحديث التلقائي','info');}
            else{startAutoRefresh();showToast('تم تشغيل التحديث التلقائي','success');}
            autoRefreshEnabled=!autoRefreshEnabled;
        }

        // Clear logs modal
        function openClearModal(){
            document.getElementById('clearConfirmInput').value='';
            document.getElementById('clearConfirmBtn').disabled=true;
            document.getElementById('clearModal').classList.add('show');
        }
        function closeClearModal(){document.getElementById('clearModal').classList.remove('show');}

        document.getElementById('clearConfirmInput').addEventListener('input',function(){
            document.getElementById('clearConfirmBtn').disabled=this.value!=='DELETE_ALL_LOGS';
        });

        async function confirmClearLogs(){
            const res=await api('logs/clear',{confirm:'DELETE_ALL_LOGS'});
            if(res.success){
                showToast('تم حذف جميع السجلات بنجاح ('+res.deleted_count+' سجل)');
                closeClearModal();loadLogs();
            }else{
                showToast(res.message,'error');
            }
        }

        // Close modal on overlay click
        document.getElementById('clearModal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');});

        // Init
        document.addEventListener('DOMContentLoaded',()=>{
            loadLogs();
            startAutoRefresh();
        });
    </script>
</body>
</html>
