<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Admin Payments Management
 * International Phone Directory
 * ============================================================
 */

$pageTitle = 'إدارة المدفوعات';
$adminPage = 'payments';
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
        :root{--a-bg:#f8fafc;--a-sidebar:#0f172a;--a-sidebar-w:260px;--a-card:#fff;--a-text:#0f172a;--a-text2:#475569;--a-muted:#94a3b8;--a-border:#e2e8f0;--a-accent:#10b981;--a-danger:#ef4444;--a-warning:#f59e0b;--a-info:#3b82f6;--a-shadow:0 4px 6px rgba(0,0,0,.07);--a-shadow-lg:0 10px 15px rgba(0,0,0,.08);--a-radius:12px;--a-purple:#a855f7}
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

        /* Stats row */
        .pay-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
        .pay-stat{background:var(--a-card);border:1px solid var(--a-border);border-radius:var(--a-radius);padding:1.25rem;box-shadow:var(--a-shadow);display:flex;flex-direction:column;gap:.25rem}
        .pay-stat-label{font-size:.78rem;color:var(--a-muted);font-weight:600}
        .pay-stat-value{font-size:1.5rem;font-weight:800}
        .pay-stat-icon{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;margin-bottom:.5rem}
        .pay-stat-icon svg{width:18px;height:18px}
        .pay-stat-icon.green{background:rgba(16,185,129,.1);color:var(--a-accent)}
        .pay-stat-icon.blue{background:rgba(59,130,246,.1);color:var(--a-info)}
        .pay-stat-icon.red{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .pay-stat-icon.gold{background:rgba(245,158,11,.1);color:var(--a-warning)}

        .admin-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
        .admin-header h1{font-size:1.75rem;font-weight:800}
        .admin-toolbar{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem}
        .admin-filter{padding:.625rem 1rem;background:var(--a-card);border:1px solid var(--a-border);border-radius:10px;color:var(--a-text);font-size:.85rem;font-family:'Cairo',sans-serif;outline:none;cursor:pointer;min-width:130px;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left .75rem center;padding-left:2rem}
        .admin-filter:focus{border-color:var(--a-accent)}
        .admin-filter[type="date"]{padding-left:1rem;background-image:none}
        .admin-card{background:var(--a-card);border:1px solid var(--a-border);border-radius:var(--a-radius);box-shadow:var(--a-shadow);overflow:hidden}
        .admin-card-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid var(--a-border);flex-wrap:wrap;gap:.5rem}
        .admin-card-title{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
        .admin-count{background:var(--a-accent);color:#fff;padding:.15rem .5rem;border-radius:9999px;font-size:.75rem;font-weight:700}
        .admin-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .admin-table th{padding:.75rem 1rem;text-align:right;font-weight:600;color:var(--a-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.03em;background:var(--a-bg);border-bottom:1px solid var(--a-border);white-space:nowrap}
        .admin-table td{padding:.625rem .875rem;border-bottom:1px solid var(--a-border);color:var(--a-text2);vertical-align:middle}
        .admin-table tbody tr:hover{background:rgba(16,185,129,.03)}
        .admin-table tr:last-child td{border-bottom:none}
        .status-badge{display:inline-flex;padding:.15rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:700}
        .status-badge.pending{background:rgba(245,158,11,.1);color:var(--a-warning)}
        .status-badge.approved{background:rgba(16,185,129,.1);color:var(--a-accent)}
        .status-badge.rejected{background:rgba(239,68,68,.1);color:var(--a-danger)}
        .plan-badge{display:inline-flex;padding:.15rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:700}
        .plan-badge.pro{background:rgba(59,130,246,.1);color:var(--a-info)}
        .plan-badge.premium{background:rgba(168,85,247,.1);color:var(--a-purple)}
        .plan-badge.free{background:rgba(148,163,184,.1);color:#94a3b8}
        .actions-cell{display:flex;gap:.375rem}
        .act-btn{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;border:none;background:transparent}
        .act-btn svg{width:16px;height:16px}
        .act-btn.approve{color:var(--a-accent)}.act-btn.approve:hover{background:rgba(16,185,129,.1)}
        .act-btn.reject{color:var(--a-danger)}.act-btn.reject:hover{background:rgba(239,68,68,.1)}
        .act-btn.delete{color:var(--a-muted)}.act-btn.delete:hover{background:rgba(239,68,68,.1)}
        .pagination{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem;flex-wrap:wrap}
        .page-btn{padding:.375rem .75rem;border-radius:8px;background:var(--a-card);border:1px solid var(--a-border);color:var(--a-text);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Cairo',sans-serif}
        .page-btn:hover,.page-btn.active{background:var(--a-accent);color:#fff;border-color:var(--a-accent)}
        .page-btn:disabled{opacity:.4;cursor:not-allowed}
        .admin-spinner{display:flex;justify-content:center;padding:3rem}
        .admin-spinner::after{content:'';width:32px;height:32px;border:3px solid var(--a-border);border-top-color:var(--a-accent);border-radius:50%;animation:aspin .7s linear infinite}
        @keyframes aspin{to{transform:rotate(360deg)}}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.625rem 1.25rem;font-family:'Cairo',sans-serif;font-size:.875rem;font-weight:600;border-radius:10px;border:2px solid transparent;cursor:pointer;transition:all .2s;white-space:nowrap}
        .btn-sm{padding:.375rem .875rem;font-size:.8rem;border-radius:8px}
        .btn-ghost{background:transparent;color:var(--a-text2);border-color:var(--a-border)}.btn-ghost:hover{background:var(--a-bg)}
        .btn-export{background:var(--a-info);color:#fff}.btn-export:hover{opacity:.9}
        .admin-toast-container{position:fixed;top:1rem;left:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem}
        .admin-toast{padding:.875rem 1.25rem;border-radius:10px;font-size:.875rem;font-weight:500;box-shadow:var(--a-shadow-lg);animation:tin .3s;display:flex;align-items:center;gap:.5rem;max-width:380px}
        .admin-toast.success{background:var(--a-accent);color:#fff}
        .admin-toast.error{background:var(--a-danger);color:#fff}
        @keyframes tin{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
        @media(max-width:1024px){.pay-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:768px){.admin-sidebar{transform:translateX(100%)}.admin-sidebar.open{transform:translateX(0)}.admin-overlay.show{display:block}.admin-mobile-toggle{display:flex}.admin-main{margin-right:0;padding:1rem;padding-top:4rem}.admin-toolbar{flex-direction:column;align-items:stretch}.pay-stats{grid-template-columns:1fr 1fr}}
        @media(max-width:480px){.pay-stats{grid-template-columns:1fr}.actions-cell{flex-direction:column;gap:.25rem}}
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
            <a href="payments.php" class="admin-sidebar-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>المدفوعات</a>
            <a href="logs.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>السجلات</a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-divider"></div>
            <a href="../index.php" class="admin-sidebar-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>العودة للموقع</a>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-header">
            <div><h1>إدارة المدفوعات</h1></div>
            <button class="btn btn-export btn-sm" onclick="exportPayments()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                تصدير
            </button>
        </div>

        <!-- Payment Stats -->
        <div class="pay-stats" id="payStats">
            <div class="pay-stat">
                <div class="pay-stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
                <span class="pay-stat-label">إجمالي المدفوعات</span>
                <span class="pay-stat-value" id="statTotal">-</span>
            </div>
            <div class="pay-stat">
                <div class="pay-stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
                <span class="pay-stat-label">المدفوعات المقبولة</span>
                <span class="pay-stat-value" id="statApproved">-</span>
            </div>
            <div class="pay-stat">
                <div class="pay-stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                <span class="pay-stat-label">المدفوعات المرفوضة</span>
                <span class="pay-stat-value" id="statRejected">-</span>
            </div>
            <div class="pay-stat">
                <div class="pay-stat-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <span class="pay-stat-label">المعلقة</span>
                <span class="pay-stat-value" id="statPending">-</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="admin-toolbar">
            <select class="admin-filter" id="filterStatus">
                <option value="">كل الحالات</option>
                <option value="PENDING">معلق</option>
                <option value="APPROVED">مقبول</option>
                <option value="REJECTED">مرفوض</option>
            </select>
            <select class="admin-filter" id="filterPlan">
                <option value="">كل الخطط</option>
                <option value="PRO">احترافي</option>
                <option value="PREMIUM">مميز</option>
            </select>
            <input type="date" class="admin-filter" id="filterFrom" title="من تاريخ">
            <input type="date" class="admin-filter" id="filterTo" title="إلى تاريخ">
        </div>

        <!-- Payments Table -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    قائمة المدفوعات <span class="admin-count" id="totalPaymentsCount">-</span>
                </div>
            </div>
            <div id="paymentsTableContainer"><div class="admin-spinner"></div></div>
            <div class="pagination" id="paginationContainer"></div>
        </div>
    </main>

    <div class="admin-toast-container" id="toastContainer"></div>

    <script>
        const CSRF = '<?php echo $csrfToken; ?>';
        const API = '../api/admin.php';
        let currentPage = 1;

        const sidebar=document.getElementById('adminSidebar'),overlay=document.getElementById('adminOverlay'),toggle=document.getElementById('sidebarToggle');
        toggle.addEventListener('click',()=>{sidebar.classList.add('open');overlay.classList.add('show');});
        overlay.addEventListener('click',()=>{sidebar.classList.remove('open');overlay.classList.remove('show');});

        function showToast(msg,type='success'){const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='admin-toast '+type;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),4000);}
        function fmtNum(n){return Number(n).toLocaleString('ar-EG');}
        function timeAgo(d){if(!d)return'-';const s=Math.floor((Date.now()-new Date(d))/1000);if(s<60)return'الآن';if(s<3600)return'منذ '+Math.floor(s/60)+' دقيقة';if(s<86400)return'منذ '+Math.floor(s/3600)+' ساعة';if(s<2592000)return'منذ '+Math.floor(s/86400)+' يوم';return'منذ '+Math.floor(s/2592000)+' شهر';}

        async function api(action,data={}){data.action=action;data.csrf_token=CSRF;try{const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});return await r.json();}catch(e){return{success:false,message:'خطأ في الاتصال'};}}

        async function loadStats(){
            const res=await api('stats/dashboard');
            if(!res.success)return;
            const ps=res.data.payment_stats;
            document.getElementById('statTotal').textContent=fmtNum(ps.total_amount)+' ر.ي';
            document.getElementById('statApproved').textContent=fmtNum(ps.approved);
            document.getElementById('statRejected').textContent=fmtNum(ps.rejected);
            document.getElementById('statPending').textContent=fmtNum(ps.pending);
        }

        async function loadPayments(){
            const status=document.getElementById('filterStatus').value;
            const plan=document.getElementById('filterPlan').value;
            const from=document.getElementById('filterFrom').value;
            const to=document.getElementById('filterTo').value;

            const res=await api('payments/list',{page:currentPage,status,plan,date_from:from,date_to:to});
            if(!res.success){showToast(res.message,'error');return;}

            const {data,pagination}=res;
            document.getElementById('totalPaymentsCount').textContent=fmtNum(pagination.total);

            if(data.length===0){
                document.getElementById('paymentsTableContainer').innerHTML='<div style="padding:3rem;text-align:center;color:var(--a-muted);">لا يوجد مدفوعات</div>';
                document.getElementById('paginationContainer').innerHTML='';
                return;
            }

            const statusLabels={PENDING:'معلق',APPROVED:'مقبول',REJECTED:'مرفوض'};
            const planLabels={PRO:'احترافي',PREMIUM:'مميز',FREE:'مجاني'};

            let html=`<div style="overflow-x:auto"><table class="admin-table"><thead><tr>
                <th>#</th><th>المستخدم</th><th>الخطة</th><th>المبلغ</th><th>الحالة</th><th>رقم العملية</th><th>تاريخ الطلب</th><th>إجراءات</th>
            </tr></thead><tbody>`;

            data.forEach((p,i)=>{
                const num=(currentPage-1)*pagination.per_page+i+1;
                const stCls=(p.status||'').toLowerCase();
                const plCls=(p.plan||'').toLowerCase();
                let actions='';
                if(p.status==='PENDING'){
                    actions+=`<button class="act-btn approve" title="قبول" onclick="approvePayment(${p.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></button>`;
                    actions+=`<button class="act-btn reject" title="رفض" onclick="rejectPayment(${p.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></button>`;
                }
                actions+=`<button class="act-btn delete" title="حذف" onclick="deletePayment(${p.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>`;

                html+=`<tr>
                    <td>${num}</td>
                    <td style="font-weight:600;color:var(--a-text);">${p.user_name||'-'}</td>
                    <td><span class="plan-badge ${plCls}">${planLabels[p.plan]||p.plan}</span></td>
                    <td style="font-weight:700;">${fmtNum(p.amount)} ر.ي</td>
                    <td><span class="status-badge ${stCls}">${statusLabels[p.status]||p.status}</span></td>
                    <td style="font-size:.78rem;direction:ltr;text-align:right;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${p.transaction_id||'-'}</td>
                    <td style="white-space:nowrap;font-size:.78rem;">${timeAgo(p.created_at)}</td>
                    <td><div class="actions-cell">${actions}</div></td>
                </tr>`;
            });

            html+='</tbody></table></div>';
            document.getElementById('paymentsTableContainer').innerHTML=html;

            let pagHtml='';
            if(pagination.total_pages>1){
                pagHtml+=`<button class="page-btn" ${currentPage<=1?'disabled':''} onclick="goPage(${currentPage-1})">السابق</button>`;
                const start=Math.max(1,currentPage-2),end=Math.min(pagination.total_pages,currentPage+2);
                for(let p=start;p<=end;p++) pagHtml+=`<button class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</button>`;
                pagHtml+=`<button class="page-btn" ${currentPage>=pagination.total_pages?'disabled':''} onclick="goPage(${currentPage+1})">التالي</button>`;
            }
            document.getElementById('paginationContainer').innerHTML=pagHtml;
        }

        function goPage(p){currentPage=p;loadPayments();}

        async function approvePayment(id){
            const res=await api('payments/approve',{payment_id:id});
            if(res.success){showToast('تم قبول الدفعة وتفعيل الاشتراك');loadPayments();loadStats();}
            else showToast(res.message,'error');
        }

        async function rejectPayment(id){
            const res=await api('payments/reject',{payment_id:id});
            if(res.success){showToast('تم رفض الدفعة');loadPayments();loadStats();}
            else showToast(res.message,'error');
        }

        async function deletePayment(id){
            const res=await api('payments/delete',{payment_id:id});
            if(res.success){showToast('تم حذف الدفعة');loadPayments();loadStats();}
            else showToast(res.message,'error');
        }

        function exportPayments(){
            showToast('جاري تحضير التصدير...','info');
            // Build CSV from current table
            const table=document.querySelector('.admin-table');
            if(!table){showToast('لا يوجد بيانات للتصدير','error');return;}
            let csv='\uFEFF'; // BOM for Arabic
            table.querySelectorAll('tr').forEach(row=>{
                const cols=[];
                row.querySelectorAll('th,td').forEach((cell,i)=>{
                    if(i<7) cols.push('"'+cell.textContent.trim().replace(/"/g,'""')+'"');
                });
                csv+=cols.join(',')+'\n';
            });
            const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
            const url=URL.createObjectURL(blob);
            const a=document.createElement('a');
            a.href=url;a.download='payments_'+new Date().toISOString().slice(0,10)+'.csv';
            a.click();URL.revokeObjectURL(url);
        }

        ['filterStatus','filterPlan'].forEach(id=>{
            document.getElementById(id).addEventListener('change',()=>{currentPage=1;loadPayments();});
        });
        ['filterFrom','filterTo'].forEach(id=>{
            document.getElementById(id).addEventListener('change',()=>{currentPage=1;loadPayments();});
        });

        document.addEventListener('DOMContentLoaded',()=>{loadStats();loadPayments();});
    </script>
</body>
</html>
