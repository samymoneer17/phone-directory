<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin Users Management
 * International Phone Directory
 * ============================================================
 */

$pageTitle = 'إدارة المستخدمين';
$adminPage = 'users';
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
        .admin-toolbar{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem}
        .admin-search{position:relative;flex:1;min-width:200px;max-width:360px}
        .admin-search input{width:100%;padding:.625rem 1rem .625rem 2.5rem;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.875rem;font-family:'Cairo',sans-serif;outline:none;transition:border-color .2s}
        .admin-search input:focus{border-color:var(--a-accent)}
        .admin-search svg{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--a-muted);width:18px;height:18px}
        .admin-filter{padding:.625rem 1rem;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.85rem;font-family:'Cairo',sans-serif;outline:none;cursor:pointer;min-width:120px;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left .75rem center;padding-left:2rem}
        .admin-filter:focus{border-color:var(--a-accent)}
        .admin-card{background:var(--a-card);border:1px solid var(--a-border);border-radius:var(--a-radius);box-shadow:var(--a-shadow);overflow:hidden}
        .admin-card-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid var(--a-border);flex-wrap:wrap;gap:.5rem}
        .admin-card-title{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
        .admin-count{background:var(--a-accent);color:#fff;padding:.15rem .5rem;border-radius:9999px;font-size:.75rem;font-weight:700}
        .admin-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .admin-table th{padding:.75rem 1rem;text-align:right;font-weight:600;color:var(--a-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.03em;background:var(--a-bg);border-bottom:1px solid var(--a-border);white-space:nowrap}
        .admin-table td{padding:.625rem .875rem;border-bottom:1px solid var(--a-border);color:var(--a-text2);vertical-align:middle}
        .admin-table tbody tr:hover{background:rgba(16,185,129,.03)}
        .admin-table tr:last-child td{border-bottom:none}
        .user-cell{display:flex;align-items:center;gap:.75rem}
        .user-cell-name{font-weight:600;color:var(--a-text);font-size:.85rem}
        .user-cell-email{font-size:.75rem;color:var(--a-muted)}
        .plan-badge-admin{display:inline-flex;padding:.15rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:700}
        .plan-badge-admin.free{background:rgba(16,185,129,.1);color:var(--a-accent)}
        .plan-badge-admin.pro{background:rgba(59,130,246,.1);color:var(--a-info)}
        .plan-badge-admin.premium{background:rgba(168,85,247,.1);color:var(--a-purple)}
        .role-badge{display:inline-flex;padding:.15rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:700}
        .role-badge.user{background:rgba(148,163,184,.1);color:#94a3b8}
        .role-badge.admin{background:rgba(245,158,11,.1);color:var(--a-warning)}
        .role-badge.banned{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .actions-cell{display:flex;gap:.375rem}
        .act-btn{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;border:none;background:transparent}
        .act-btn svg{width:16px;height:16px}
        .act-btn.view{color:var(--a-info)}.act-btn.view:hover{background:rgba(59,130,246,.1)}
        .act-btn.edit{color:var(--a-accent)}.act-btn.edit:hover{background:rgba(16,185,129,.1)}
        .act-btn.delete{color:var(--a-danger)}.act-btn.delete:hover{background:rgba(239,68,68,.1)}
        .act-btn.ban{color:var(--a-warning)}.act-btn.ban:hover{background:rgba(245,158,11,.1)}
        .pagination{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem;flex-wrap:wrap}
        .page-btn{padding:.375rem .75rem;border-radius:8px;background:var(--a-card);border:1px solid var(--a-border);color:var(--a-text);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Cairo',sans-serif}
        .page-btn:hover,.page-btn.active{background:var(--a-accent);color:#fff;border-color:var(--a-accent)}
        .page-btn:disabled{opacity:.4;cursor:not-allowed}
        .admin-spinner{display:flex;justify-content:center;padding:3rem}
        .admin-spinner::after{content:'';width:32px;height:32px;border:3px solid var(--a-border);border-top-color:var(--a-accent);border-radius:50%;animation:aspin .7s linear infinite}
        @keyframes aspin{to{transform:rotate(360deg)}}

        /* Modal */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s;padding:1rem}
        .modal-overlay.show{opacity:1;visibility:visible}
        .modal{background:var(--a-card);border:1px solid var(--a-border);border-radius:16px;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 25px 50px rgba(0,0,0,.3);transform:scale(.95);transition:transform .2s}
        .modal-overlay.show .modal{transform:scale(1)}
        .modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--a-border)}
        .modal-header h3{font-size:1.1rem;font-weight:700}
        .modal-close{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;color:var(--a-muted);cursor:pointer;transition:all .2s}
        .modal-close:hover{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .modal-body{padding:1.5rem}
        .modal-footer{display:flex;gap:.75rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--a-border);flex-wrap:wrap}
        .fg{margin-bottom:1rem}
        .fg label{display:block;font-size:.85rem;font-weight:600;color:var(--a-text2);margin-bottom:.375rem}
        .fg input,.fg select{width:100%;padding:.625rem 1rem;background:var(--a-bg);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.875rem;font-family:'Cairo',sans-serif;outline:none;transition:border-color .2s}
        .fg input:focus,.fg select:focus{border-color:var(--a-accent)}
        .fg-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        .fg-check{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem;color:var(--a-text2)}
        .fg-check input[type="checkbox"]{width:18px;height:18px;accent-color:var(--a-accent)}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.625rem 1.25rem;font-family:'Cairo',sans-serif;font-size:.875rem;font-weight:600;border-radius:10px;border:2px solid transparent;cursor:pointer;transition:all .2s;white-space:nowrap}
        .btn-sm{padding:.375rem .875rem;font-size:.8rem;border-radius:8px}
        .btn-primary{background:var(--a-accent);color:#fff}.btn-primary:hover{opacity:.9}
        .btn-danger{background:var(--a-danger);color:#fff}.btn-danger:hover{opacity:.9}
        .btn-ghost{background:transparent;color:var(--a-text2);border-color:var(--a-border)}.btn-ghost:hover{background:var(--a-bg)}

        /* Detail modal */
        .detail-section{margin-bottom:1.5rem}
        .detail-section h4{font-size:.95rem;font-weight:700;color:var(--a-accent);margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid var(--a-border);display:flex;align-items:center;gap:.5rem}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        .detail-item{display:flex;flex-direction:column;gap:.15rem}
        .detail-label{font-size:.75rem;color:var(--a-muted);font-weight:600}
        .detail-value{font-size:.875rem;color:var(--a-text);font-weight:500}
        .detail-list{list-style:none;max-height:200px;overflow-y:auto}
        .detail-list li{padding:.5rem 0;border-bottom:1px solid var(--a-border);font-size:.83rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem}
        .detail-list li:last-child{border-bottom:none}
        .admin-toast-container{position:fixed;top:1rem;left:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem}
        .admin-toast{padding:.875rem 1.25rem;border-radius:10px;font-size:.875rem;font-weight:500;box-shadow:var(--a-shadow-lg);animation:tin .3s;display:flex;align-items:center;gap:.5rem;max-width:380px}
        .admin-toast.success{background:var(--a-accent);color:#fff}
        .admin-toast.error{background:var(--a-danger);color:#fff}
        @keyframes tin{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}

        @media(max-width:1024px){.admin-table{font-size:.8rem}.admin-table th,.admin-table td{padding:.5rem .625rem}}
        @media(max-width:768px){.admin-sidebar{transform:translateX(100%)}.admin-sidebar.open{transform:translateX(0)}.admin-overlay.show{display:block}.admin-mobile-toggle{display:flex}.admin-main{margin-right:0;padding:1rem;padding-top:4rem}.admin-toolbar{flex-direction:column;align-items:stretch}.admin-search{max-width:100%}.fg-row{grid-template-columns:1fr}.detail-grid{grid-template-columns:1fr}}
        @media(max-width:480px){.actions-cell{flex-direction:column;gap:.25rem}.act-btn{width:28px;height:28px}}
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
            <a href="users.php" class="admin-sidebar-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>المستخدمين</a>
            <a href="payments.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>المدفوعات</a>
            <a href="logs.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>السجلات</a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-divider"></div>
            <a href="../index.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>العودة للموقع</a>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-header">
            <div><h1>إدارة المستخدمين</h1></div>
        </div>

        <div class="admin-toolbar">
            <div class="admin-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="البحث بالاسم أو البريد أو الهاتف..." autocomplete="off">
            </div>
            <select class="admin-filter" id="filterPlan">
                <option value="">كل الخطط</option>
                <option value="FREE">مجاني</option>
                <option value="PRO">احترافي</option>
                <option value="PREMIUM">مميز</option>
            </select>
            <select class="admin-filter" id="filterRole">
                <option value="">كل الأدوار</option>
                <option value="USER">مستخدم</option>
                <option value="ADMIN">مدير</option>
            </select>
            <select class="admin-filter" id="filterStatus">
                <option value="">كل الحالات</option>
                <option value="active">نشط</option>
                <option value="expired">منتهي الصلاحية</option>
                <option value="banned">محظور</option>
            </select>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    قائمة المستخدمين <span class="admin-count" id="totalUsersCount">-</span>
                </div>
            </div>
            <div id="usersTableContainer"><div class="admin-spinner"></div></div>
            <div class="pagination" id="paginationContainer"></div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3>تعديل المستخدم</h3>
                <button class="modal-close" onclick="closeEditModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editUserId">
                <div class="fg"><label>الاسم</label><input type="text" id="editName"></div>
                <div class="fg"><label>البريد الإلكتروني</label><input type="email" id="editEmail"></div>
                <div class="fg-row">
                    <div class="fg"><label>الخطة</label><select id="editPlan"><option value="FREE">مجاني</option><option value="PRO">احترافي</option><option value="PREMIUM">مميز</option></select></div>
                    <div class="fg"><label>الدور</label><select id="editRole"><option value="USER">مستخدم</option><option value="ADMIN">مدير</option></select></div>
                </div>
                <div class="fg"><label class="fg-check"><input type="checkbox" id="editPhoneHidden"> إخفاء رقم الهاتف</label></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeEditModal()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveUser()">حفظ التعديلات</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width:400px;">
            <div class="modal-header">
                <h3>تأكيد الحذف</h3>
                <button class="modal-close" onclick="closeDeleteModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="modal-body">
                <p style="text-align:center;font-size:.95rem;">هل أنت متأكد من حذف المستخدم <strong id="deleteUserName"></strong>؟<br><small style="color:var(--a-danger);">لا يمكن التراجع عن هذا الإجراء.</small></p>
                <input type="hidden" id="deleteUserId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeDeleteModal()">إلغاء</button>
                <button class="btn btn-danger" onclick="confirmDeleteUser()">حذف</button>
            </div>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal" style="max-width:600px;">
            <div class="modal-header">
                <h3>تفاصيل المستخدم</h3>
                <button class="modal-close" onclick="closeDetailModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="modal-body" id="detailContent"><div class="admin-spinner"></div></div>
        </div>
    </div>

    <div class="admin-toast-container" id="toastContainer"></div>

    <script>
        const CSRF = '<?php echo $csrfToken; ?>';
        const API = '../api/admin.php';
        let currentPage = 1;

        // Sidebar
        const sidebar=document.getElementById('adminSidebar'),overlay=document.getElementById('adminOverlay'),toggle=document.getElementById('sidebarToggle');
        toggle.addEventListener('click',()=>{sidebar.classList.add('open');overlay.classList.add('show');});
        overlay.addEventListener('click',()=>{sidebar.classList.remove('open');overlay.classList.remove('show');});

        function showToast(msg,type='success'){const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='admin-toast '+type;t.textContent=msg;c.appendChild(t);setTimeout(()=>{t.remove();},4000);}
        function fmtNum(n){return Number(n).toLocaleString('ar-EG');}

        function avatarHtml(name,size){
            size=size||32;const initial=(name||'?').charAt(0).toUpperCase();
            const colors=['#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63','#00bcd4','#ff5722'];
            let hash=0;for(let i=0;i<(name||'').length;i++)hash=(name||'').charCodeAt(i)+((hash<<5)-hash);
            return `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${colors[Math.abs(hash)%colors.length]};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:${size*.38}px;flex-shrink:0;">${initial}</div>`;
        }

        function timeAgo(d){if(!d)return'-';const s=Math.floor((Date.now()-new Date(d))/1000);if(s<60)return'الآن';if(s<3600)return'منذ '+Math.floor(s/60)+' دقيقة';if(s<86400)return'منذ '+Math.floor(s/3600)+' ساعة';if(s<2592000)return'منذ '+Math.floor(s/86400)+' يوم';return'منذ '+Math.floor(s/2592000)+' شهر';}

        async function api(action,data={}){data.action=action;data.csrf_token=CSRF;try{const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});return await r.json();}catch(e){return{success:false,message:'خطأ في الاتصال'};}}

        async function loadUsers(){
            const search=document.getElementById('searchInput').value;
            const plan=document.getElementById('filterPlan').value;
            const role=document.getElementById('filterRole').value;
            const status=document.getElementById('filterStatus').value;

            const res=await api('users/list',{page:currentPage,search,plan,role,status});
            if(!res.success){showToast(res.message,'error');return;}

            const {data,pagination}=res;
            document.getElementById('totalUsersCount').textContent=fmtNum(pagination.total);

            if(data.length===0){
                document.getElementById('usersTableContainer').innerHTML='<div style="padding:3rem;text-align:center;color:var(--a-muted);">لا يوجد مستخدمين مطابقين للبحث</div>';
                document.getElementById('paginationContainer').innerHTML='';
                return;
            }

            const planLabels={FREE:'مجاني',PRO:'احترافي',PREMIUM:'مميز'};
            const roleLabels={USER:'مستخدم',ADMIN:'مدير',BANNED:'محظور'};

            let html=`<div style="overflow-x:auto"><table class="admin-table"><thead><tr>
                <th>#</th><th>المستخدم</th><th>الهاتف</th><th>الخطة</th><th>الدور</th><th>البحث</th><th>التسجيل</th><th>إجراءات</th>
            </tr></thead><tbody>`;

            data.forEach((u,i)=>{
                const num=(currentPage-1)*pagination.per_page+i+1;
                const planCls=(u.plan||'free').toLowerCase();
                const roleCls=(u.role||'user').toLowerCase();
                const isBanned=u.role==='BANNED';
                html+=`<tr>
                    <td>${num}</td>
                    <td><div class="user-cell">${avatarHtml(u.name,32)}<div><div class="user-cell-name">${u.name}</div><div class="user-cell-email">${u.email}</div></div></div></td>
                    <td style="font-size:.8rem;direction:ltr;text-align:right;">${u.phone||'-'}</td>
                    <td><span class="plan-badge-admin ${planCls}">${planLabels[u.plan]||u.plan}</span></td>
                    <td><span class="role-badge ${roleCls}">${roleLabels[u.role]||u.role}</span></td>
                    <td style="font-weight:600;">${fmtNum(u.search_count)}</td>
                    <td style="white-space:nowrap;font-size:.78rem;">${timeAgo(u.created_at)}</td>
                    <td><div class="actions-cell">
                        <button class="act-btn view" title="عرض التفاصيل" onclick="viewUser(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        <button class="act-btn edit" title="تعديل" onclick="openEditModal(${u.id},'${u.name.replace(/'/g,"\\'")}','${u.email}',\`${u.plan}\`,\`${u.role}\`,${u.is_phone_hidden})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        <button class="act-btn ${isBanned?'edit':'ban'}" title="${isBanned?'إلغاء الحظر':'حظر'}" onclick="toggleBan(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><${isBanned?'path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M13.8 12H3"':'circle cx="12" cy="12" r="10"'}></svg></button>
                        <button class="act-btn delete" title="حذف" onclick="openDeleteModal(${u.id},'${u.name.replace(/'/g,"\\'")}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                    </div></td>
                </tr>`;
            });

            html+='</tbody></table></div>';
            document.getElementById('usersTableContainer').innerHTML=html;

            // Pagination
            let pagHtml='';
            if(pagination.total_pages>1){
                pagHtml+=`<button class="page-btn" ${currentPage<=1?'disabled':''} onclick="goPage(${currentPage-1})">السابق</button>`;
                const start=Math.max(1,currentPage-2),end=Math.min(pagination.total_pages,currentPage+2);
                for(let p=start;p<=end;p++){
                    pagHtml+=`<button class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</button>`;
                }
                pagHtml+=`<button class="page-btn" ${currentPage>=pagination.total_pages?'disabled':''} onclick="goPage(${currentPage+1})">التالي</button>`;
            }
            document.getElementById('paginationContainer').innerHTML=pagHtml;
        }

        function goPage(p){currentPage=p;loadUsers();}

        // Filters
        document.getElementById('searchInput').addEventListener('input',()=>{currentPage=1;loadUsers();});
        ['filterPlan','filterRole','filterStatus'].forEach(id=>{
            document.getElementById(id).addEventListener('change',()=>{currentPage=1;loadUsers();});
        });

        // Edit modal
        function openEditModal(id,name,email,plan,role,phoneHidden){
            document.getElementById('editUserId').value=id;
            document.getElementById('editName').value=name;
            document.getElementById('editEmail').value=email;
            document.getElementById('editPlan').value=plan;
            document.getElementById('editRole').value=role;
            document.getElementById('editPhoneHidden').checked=!!phoneHidden;
            document.getElementById('editModal').classList.add('show');
        }
        function closeEditModal(){document.getElementById('editModal').classList.remove('show');}

        async function saveUser(){
            const id=document.getElementById('editUserId').value;
            const res=await api('users/update',{
                user_id:id,
                name:document.getElementById('editName').value,
                email:document.getElementById('editEmail').value,
                plan:document.getElementById('editPlan').value,
                role:document.getElementById('editRole').value,
                is_phone_hidden:document.getElementById('editPhoneHidden').checked?1:0
            });
            if(res.success){showToast('تم تحديث المستخدم بنجاح');closeEditModal();loadUsers();}
            else{showToast(res.message,'error');}
        }

        // Delete modal
        function openDeleteModal(id,name){
            document.getElementById('deleteUserId').value=id;
            document.getElementById('deleteUserName').textContent=name;
            document.getElementById('deleteModal').classList.add('show');
        }
        function closeDeleteModal(){document.getElementById('deleteModal').classList.remove('show');}

        async function confirmDeleteUser(){
            const id=document.getElementById('deleteUserId').value;
            const res=await api('users/delete',{user_id:id});
            if(res.success){showToast('تم حذف المستخدم بنجاح');closeDeleteModal();loadUsers();}
            else{showToast(res.message,'error');}
        }

        // Ban toggle
        async function toggleBan(id){
            const res=await api('users/ban',{user_id:id});
            if(res.success){showToast(res.message);loadUsers();}
            else{showToast(res.message,'error');}
        }

        // View user detail
        async function viewUser(id){
            document.getElementById('detailModal').classList.add('show');
            document.getElementById('detailContent').innerHTML='<div class="admin-spinner"></div>';

            const res=await api('users/detail',{user_id:id});
            if(!res.success){document.getElementById('detailContent').innerHTML='<p style="color:var(--a-danger);text-align:center;">'+res.message+'</p>';return;}

            const {user,search_history,payments,activity_logs,subscription}=res.data;
            const planLabels={FREE:'مجاني',PRO:'احترافي',PREMIUM:'مميز'};

            let html=`<div class="detail-section">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                    ${avatarHtml(user.name,56)}
                    <div><div style="font-size:1.1rem;font-weight:700;">${user.name}</div><div style="font-size:.85rem;color:var(--a-muted);">${user.email}</div></div>
                </div>
                <div class="detail-grid">
                    <div class="detail-item"><span class="detail-label">المعرف</span><span class="detail-value">#${user.id}</span></div>
                    <div class="detail-item"><span class="detail-label">الهاتف</span><span class="detail-value">${user.phone||'-'}</span></div>
                    <div class="detail-item"><span class="detail-label">الخطة</span><span class="detail-value">${planLabels[user.plan]||user.plan}</span></div>
                    <div class="detail-item"><span class="detail-label">الدور</span><span class="detail-value">${user.role}</span></div>
                    <div class="detail-item"><span class="detail-label">عمليات البحث</span><span class="detail-value">${fmtNum(user.search_count)}</span></div>
                    <div class="detail-item"><span class="detail-label">تاريخ التسجيل</span><span class="detail-value">${user.created_at||'-'}</span></div>
                    <div class="detail-item"><span class="detail-label">انتهاء الاشتراك</span><span class="detail-value">${user.subscription_expires_at||'غير مشترك'}</span></div>
                    <div class="detail-item"><span class="detail-label">إخفاء الهاتف</span><span class="detail-value">${user.is_phone_hidden?'نعم':'لا'}</span></div>
                </div>
            </div>`;

            if(search_history.length>0){
                html+=`<div class="detail-section"><h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> آخر عمليات البحث (${search_history.length})</h4>
                <ul class="detail-list">`;
                search_history.forEach(s=>{
                    html+=`<li><span>${s.query} <small style="color:var(--a-muted);">(${s.results_count} نتيجة)</small></span><span style="font-size:.75rem;color:var(--a-muted);white-space:nowrap;">${timeAgo(s.created_at)}</span></li>`;
                });
                html+=`</ul></div>`;
            }

            if(payments.length>0){
                html+=`<div class="detail-section"><h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> المدفوعات (${payments.length})</h4>
                <ul class="detail-list">`;
                payments.forEach(p=>{
                    html+=`<li><span>${planLabels[p.plan]||p.plan} - ${fmtNum(p.amount)} ر.ي</span><span style="font-size:.75rem;color:var(--a-muted);">${timeAgo(p.created_at)}</span></li>`;
                });
                html+=`</ul></div>`;
            }

            document.getElementById('detailContent').innerHTML=html;
        }
        function closeDetailModal(){document.getElementById('detailModal').classList.remove('show');}

        // Close modals on overlay click
        ['editModal','deleteModal','detailModal'].forEach(id=>{
            document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');});
        });

        // Init
        document.addEventListener('DOMContentLoaded', loadUsers);
    </script>
</body>
</html>
