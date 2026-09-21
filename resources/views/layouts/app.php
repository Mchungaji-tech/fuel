<?php
    $currentUser = $_SESSION['user'] ?? ['name' => 'Admin User', 'email' => 'admin@sarurafuel.co.ke', 'role' => 'admin'];
    $initials = strtoupper(substr($currentUser['name'] ?? 'AD', 0, 2));
    $activeCurrency = current_currency();
    $activeUri = current_uri();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= htmlspecialchars($title ?? 'Sarura Fuel — Operations Cloud') ?></title>
<link rel="manifest" href="<?= url('manifest.json') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<style>
:root, [data-theme="light"]{
  --bg:#F6F7FB; --card:#FFFFFF; --card-2:#F9FAFC;
  --text:#0F172A; --text-2:#475569; --text-3:#94A3B8;
  --border:#EEF1F6; --border-2:#E2E8F0;
  --brand:#4F46E5; --brand-soft:#EEF2FF;
  --accent:#F97316; --accent-soft:#FFF3E8;
  --green:#059669; --green-soft:#E7F8F1;
  --red:#DC2626;   --red-soft:#FDECEC;
  --amber:#D97706; --amber-soft:#FEF4E5;
  --blue:#2563EB;  --blue-soft:#E9F0FE;
  --shadow:0 1px 3px rgba(16,24,40,.06),0 1px 2px rgba(16,24,40,.04);
  --shadow-lg:0 14px 34px rgba(16,24,40,.10);
  --sidebar:#FFFFFF;
}
[data-theme="dark"]{
  --bg:#0B0F1A; --card:#121826; --card-2:#0F1522;
  --text:#F8FAFC; --text-2:#94A3B8; --text-3:#64748B;
  --border:#1E2739; --border-2:#263148;
  --brand:#818CF8; --brand-soft:#1B2140;
  --accent:#FB923C; --accent-soft:#2A1D12;
  --green:#34D399; --green-soft:#0E2A22;
  --red:#F87171;   --red-soft:#2A1416;
  --amber:#FBBF24; --amber-soft:#2A2210;
  --blue:#60A5FA;  --blue-soft:#13233E;
  --shadow:0 1px 3px rgba(0,0,0,.3);
  --shadow-lg:0 14px 34px rgba(0,0,0,.55);
  --sidebar:#0E1420;
}
*{box-sizing:border-box}
html, body{
  max-width:100%;
  overflow-x:clip;
}
html{scroll-behavior:smooth}
body{
  margin:0;
  font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  background:var(--bg);
  color:var(--text);
  font-size:15.5px;
  line-height:1.5;
  transition:background .3s,color .3s;
}
button,input,select,textarea{font-family:inherit;font-size:14.5px;}
button{cursor:pointer}
::-webkit-scrollbar{width:8px;height:8px}
::-webkit-scrollbar-thumb{background:var(--border-2);border-radius:8px}

.app{
  display:grid;
  grid-template-columns:270px minmax(0, 1fr);
  min-height:100vh;
  max-width:100vw;
  align-items:start;
  transition:grid-template-columns .22s ease;
}
.app.sidebar-collapsed{grid-template-columns:78px minmax(0, 1fr);}
.sidebar{
  background:var(--sidebar);
  border-right:1px solid var(--border);
  padding:20px 16px;
  position:sticky;
  top:0;
  height:100vh;
  max-height:100vh;
  align-self:start;
  display:flex;
  flex-direction:column;
  overflow-y:auto;
  user-select:none;
  overscroll-behavior:contain;
  z-index:90;
  transition:width .22s ease, padding .22s ease;
}
.main{
  min-width:0;
  max-width:100%;
  overflow-x:hidden;
  display:flex;
  flex-direction:column;
}
.app.sidebar-collapsed .sidebar{padding:20px 10px;align-items:center;}
.app.sidebar-collapsed .brand-name,
.app.sidebar-collapsed .nav-section,
.app.sidebar-collapsed .nav-item span:not(.icon),
.app.sidebar-collapsed .nav-item .dot,
.app.sidebar-collapsed .sidebar-footer{display:none !important;}
.app.sidebar-collapsed .nav-item{justify-content:center;padding:12px 10px;border-radius:10px;}
.app.sidebar-collapsed .brand{justify-content:center;padding:6px 0;margin-bottom:16px;}
.app.sidebar-collapsed .brand-logo{width:40px;height:40px;}

.sidebar-header-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.collapse-toggle-btn{
  width:30px;height:30px;border-radius:8px;border:1px solid var(--border);
  background:var(--card-2);color:var(--text-2);display:grid;place-items:center;
  cursor:pointer;transition:.15s;font-size:12px;font-weight:700;
}
.collapse-toggle-btn:hover{color:var(--brand);border-color:var(--brand);background:var(--card);}
.app.sidebar-collapsed .collapse-toggle-btn{margin:8px auto 0;}



/* Inline Table Editing Styles */
.table-inline-input{
  width:100%;padding:6px 9px;border:1.5px solid var(--brand);
  border-radius:7px;background:var(--card);color:var(--text);
  font-family:inherit;font-size:13.5px;font-weight:600;outline:0;
}
.table-inline-select{
  width:100%;padding:6px 9px;border:1.5px solid var(--brand);
  border-radius:7px;background:var(--card);color:var(--text);
  font-family:inherit;font-size:13px;font-weight:600;outline:0;
}
.tr-editing{background:var(--brand-soft) !important;}
.row-save-btn{
  background:var(--green);color:#fff;border:0;border-radius:7px;
  padding:5px 11px;font-weight:800;font-size:12.5px;cursor:pointer;
  box-shadow:0 2px 8px rgba(5,150,105,.3);
}
.row-save-btn:hover{background:#047857;}
.row-cancel-btn{
  background:var(--card);border:1px solid var(--border-2);color:var(--text-2);
  border-radius:7px;padding:5px 9px;font-size:12.5px;cursor:pointer;
}
.row-cancel-btn:hover{color:var(--text);background:var(--card-2);}


.brand{display:flex;align-items:center;gap:12px;padding:6px 0;text-decoration:none;color:inherit}
.brand-logo{
  width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
  background:linear-gradient(135deg,#4F46E5,#7C3AED 55%,#F97316);
  color:#fff;font-weight:800;font-size:18px;
  box-shadow:0 6px 16px rgba(79,70,229,.35);
  flex-shrink:0;
}
  color:#fff;font-weight:800;font-size:18px;
  box-shadow:0 6px 16px rgba(79,70,229,.35);
}
.brand-name{font-weight:800;font-size:16.5px;line-height:1.15}
.brand-name small{display:block;font-weight:500;color:var(--text-3);font-size:12px;margin-top:2px;}

.nav-section{
  font-size:12px;font-weight:800;letter-spacing:.08em;
  text-transform:uppercase;color:var(--text-3);padding:14px 12px 6px;
}
.nav-item{
  display:flex;align-items:center;gap:12px;padding:10.5px 13px;
  border-radius:10px;color:var(--text-2);font-weight:600;font-size:15px;
  cursor:pointer;transition:.18s;border:0;background:transparent;
  width:100%;text-align:left;text-decoration:none;margin-bottom:2px;
}
.nav-item:hover{background:var(--card-2);color:var(--text)}
.nav-item.active{background:var(--brand-soft);color:var(--brand);font-weight:700;}
.nav-item .dot{margin-left:auto;width:8px;height:8px;border-radius:50%;background:var(--accent)}
.icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.sidebar-footer{
  margin-top:auto;background:var(--card-2);border:1px solid var(--border);
  border-radius:14px;padding:14px;font-size:13px;color:var(--text-2);
}
.sidebar-footer b{color:var(--text);font-size:13.5px;}
.prog{height:7px;background:var(--border);border-radius:99px;margin-top:8px;overflow:hidden}
.prog i{display:block;height:100%;width:74%;border-radius:99px;background:linear-gradient(90deg,#4F46E5,#F97316)}

.topbar{
  display:flex;align-items:center;gap:16px;padding:16px 28px;
  border-bottom:1px solid var(--border);position:sticky;top:0;
  background:var(--bg);z-index:20;backdrop-filter:blur(8px);
}
.search{
  flex:1;max-width:440px;display:flex;align-items:center;gap:10px;
  background:var(--card);border:1px solid var(--border);border-radius:12px;
  padding:10px 14px;color:var(--text-3);
}
.search input{
  border:0;outline:0;background:transparent;color:var(--text);
  flex:1;font-family:inherit;font-size:14.5px;
}
.kbd{font-size:11.5px;border:1px solid var(--border-2);border-radius:6px;padding:2px 7px;color:var(--text-3)}

/* Currency Switcher Segment */
.currency-toggle{
  display:inline-flex;align-items:center;background:var(--card);
  border:1px solid var(--border-2);border-radius:11px;padding:3px;gap:2px;
}
.currency-opt{
  border:0;background:transparent;padding:6px 12px;border-radius:8px;
  font-size:13px;font-weight:700;color:var(--text-2);transition:.15s;
}
.currency-opt.active{
  background:var(--brand);color:#fff;box-shadow:0 2px 8px rgba(79,70,229,.3);
}
.rate-badge{
  font-size:12px;font-weight:700;color:var(--text-3);padding:4px 8px;
  background:var(--card-2);border:1px solid var(--border);border-radius:8px;
  white-space:nowrap;
}

.icon-btn{
  width:40px;height:40px;border-radius:11px;border:1px solid var(--border);
  background:var(--card);display:grid;place-items:center;color:var(--text-2);
  position:relative;transition:.18s;
}
.icon-btn:hover{color:var(--text);border-color:var(--border-2);transform:translateY(-1px)}
.icon-btn .ping{position:absolute;top:9px;right:10px;width:8px;height:8px;border-radius:50%;background:var(--red);box-shadow:0 0 0 3px var(--card)}
.avatar{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;color:#fff;font-weight:700;font-size:14px;background:linear-gradient(135deg,#F97316,#F43F5E)}

.btn{
  border:0;border-radius:11px;font-weight:700;font-size:14.5px;
  padding:10px 18px;display:inline-flex;align-items:center;justify-content:center;
  gap:8px;transition:.18s;text-decoration:none;
}
.btn-brand{background:var(--brand);color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.35)}
.btn-brand:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(79,70,229,.4)}
.btn-ghost{background:var(--card);border:1px solid var(--border);color:var(--text-2)}
.btn-ghost:hover{color:var(--text);border-color:var(--border-2)}
.btn-success{background:var(--green);color:#fff;}
.btn-danger{background:var(--red);color:#fff;}
.btn-amber{background:var(--amber);color:#fff;}
.btn-sm{padding:6px 12px;font-size:13px;border-radius:8px;}

.content{padding:28px 32px 64px;max-width:1440px}
.view{display:none;animation:fade .25s ease}
.view.active{display:block}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

.hello h1{font-size:26px;font-weight:800;margin:0;letter-spacing:-.02em}
.hello p{margin:5px 0 0;color:var(--text-2);font-size:15px}

.chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.chip{
  font-size:13px;font-weight:600;padding:7px 14px;border-radius:99px;
  background:var(--card);border:1px solid var(--border);color:var(--text-2);
  cursor:pointer;transition:.18s;
}
.chip:hover{border-color:var(--brand);color:var(--brand)}
.chip.hot{background:var(--accent-soft);color:var(--accent);border-color:transparent}

.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin:24px 0}
.kpi{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  padding:20px;box-shadow:var(--shadow);transition:.2s;position:relative;overflow:hidden;
}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.kpi .val{font-size:26px;font-weight:800;letter-spacing:-.02em;margin-top:8px;}
.kpi .lbl{color:var(--text-2);font-size:13.5px;font-weight:600}

.panel{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  box-shadow:var(--shadow);overflow:hidden;
}
.panel-head{
  display:flex;justify-content:space-between;align-items:center;
  padding:18px 22px;border-bottom:1px solid var(--border);
}
.panel-head h3{margin:0;font-size:16.5px;font-weight:700}

.table-responsive{
  width:100%;max-width:100%;overflow-x:auto !important;-webkit-overflow-scrolling:touch;
  overscroll-behavior-x:contain;
  touch-action:pan-x pan-y;
  scrollbar-width:thin;scrollbar-color:var(--brand) var(--card-2);
  padding-bottom:12px;margin-bottom:0;
}
.table-responsive::-webkit-scrollbar{height:8px;}
.table-responsive::-webkit-scrollbar-track{background:var(--card-2);border-radius:4px;}
.table-responsive::-webkit-scrollbar-thumb{background:var(--brand);border-radius:4px;}
#fleetTable{min-width:1180px;}
#auditTable{min-width:980px;}
#trucksTable{min-width:950px;}
#driversTable{min-width:900px;}
#expensesTable{min-width:950px;}
#customersTable{min-width:920px;}
table{width:100%;border-collapse:collapse;min-width:700px;}

/* Table Pagination Footer */
.table-pagination-footer{
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
  gap:12px;padding:14px 20px;border-top:1px solid var(--border);
  background:var(--card);font-size:13px;color:var(--text-2);user-select:none;
}
.pagination-left{
  display:flex;align-items:center;gap:14px;flex-wrap:wrap;
}
.pagination-size-select{
  padding:5px 9px;border-radius:7px;border:1px solid var(--border-2);
  background:var(--card-2);color:var(--text);font-size:12.5px;font-weight:700;
  outline:0;cursor:pointer;
}
.pagination-info{
  font-size:13px;font-weight:600;color:var(--text-2);
}
.pagination-controls{
  display:flex;align-items:center;gap:5px;flex-wrap:wrap;
}
.pagination-btn{
  border:1px solid var(--border);background:var(--card-2);color:var(--text-2);
  padding:5px 11px;border-radius:7px;font-size:12.5px;font-weight:700;
  cursor:pointer;transition:.15s;min-width:32px;text-align:center;
}
.pagination-btn:hover:not(:disabled){
  background:var(--card);border-color:var(--brand);color:var(--brand);
}
.pagination-btn.active{
  background:var(--brand);color:#fff;border-color:var(--brand);box-shadow:0 2px 8px rgba(79,70,229,.3);
}
.pagination-btn:disabled{
  opacity:.4;cursor:not-allowed;
}
th{
  font-size:12px;text-transform:uppercase;letter-spacing:.06em;
  color:var(--text-3);text-align:left;padding:13px 20px;font-weight:700;
  background:var(--card-2);
}
td{
  padding:14px 20px;border-top:1px solid var(--border);
  font-size:14.5px;vertical-align:middle;
}
tbody tr:hover{background:var(--card-2)}

.status{
  display:inline-flex;align-items:center;gap:7px;
  font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:99px;
  white-space:nowrap;
}
.status i{width:6px;height:6px;border-radius:50%;background:currentColor}
.s-transit{background:var(--accent-soft);color:var(--accent)}
.s-load{background:var(--amber-soft);color:var(--amber)}
.s-done{background:var(--green-soft);color:var(--green)}
.s-hold{background:var(--red-soft);color:var(--red)}
.s-plan{background:var(--blue-soft);color:var(--blue)}

/* Modal Styles */
.modal-backdrop{
  position:fixed;top:0;left:0;right:0;bottom:0;
  background:rgba(15,23,42,.6);backdrop-filter:blur(4px);
  display:none;place-items:center;z-index:999;padding:16px;
}
.modal-backdrop.active{display:grid}
.modal-card{
  background:var(--card);border:1px solid var(--border);border-radius:18px;
  width:100%;max-width:680px;max-height:90vh;overflow-y:auto;
  box-shadow:var(--shadow-lg);padding:26px;animation:modalUp .25s ease;
}
@keyframes modalUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes modalShake{
  0%, 100% { transform: scale(1); }
  25% { transform: scale(1.02); }
  75% { transform: scale(0.99); }
}
.modal-card.modal-shake{
  animation: modalShake .25s ease !important;
}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.modal-head h2{margin:0;font-size:20px;font-weight:800;}
.close-modal{background:transparent;border:0;font-size:22px;color:var(--text-3);cursor:pointer;}
.close-modal:hover{color:var(--text);}

.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
.form-grid.single{grid-template-columns:1fr;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group label{font-size:13.5px;font-weight:700;color:var(--text-2);}
.form-group input,.form-group select,.form-group textarea{
  padding:10px 14px;border:1px solid var(--border-2);border-radius:10px;
  background:var(--card);color:var(--text);font-size:14.5px;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
  outline:0;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft);
}

/* Advanced Features Accordion */
.nav-advanced-toggle{
  display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:11px;
  color:var(--text-2);text-decoration:none;font-weight:700;font-size:13.5px;
  transition:.15s;cursor:pointer;border:0;background:transparent;width:100%;text-align:left;
}
.nav-advanced-toggle:hover{background:var(--card-2);color:var(--text);}
.sidebar-collapsed .nav-advanced-toggle .adv-label,
.sidebar-collapsed .nav-advanced-toggle .adv-arrow,
.sidebar-collapsed .nav-advanced-menu{display:none !important;}

/* Mobile Responsiveness & Bottom Bar */
.mobile-bottom-nav{display:none;}
.mobile-menu-drawer{display:none;}

@media(max-width:1024px){
  .kpis{grid-template-columns:repeat(2,1fr)}
}

@media(max-width:768px){
  .app{grid-template-columns:1fr;}
  .sidebar{display:none;}
  .topbar{padding:14px 18px;}
  .content{padding:20px 16px 85px;}
  .hello h1{font-size:22px;}

  /* Stat cards smooth horizontal swipe/scroll on mobile */
  .kpis{
    display:flex !important;
    flex-wrap:nowrap !important;
    overflow-x:auto !important;
    -webkit-overflow-scrolling:touch !important;
    scroll-snap-type:x mandatory !important;
    gap:12px !important;
    padding-bottom:12px !important;
    margin-right:-16px !important;
    padding-right:16px !important;
  }
  .kpi{
    min-width:250px !important;
    max-width:290px !important;
    flex:0 0 auto !important;
    scroll-snap-align:start !important;
  }

  /* Full horizontal scrolling for overflowing tables */
  .table-responsive{
    overflow-x:auto !important;
    -webkit-overflow-scrolling:touch !important;
    width:100% !important;
  }

  /* Drop sidebar collapse toggle on mobile; show mobile drawer button */
  #sidebarToggleBtn, #sidebarCollapseBtn, #topbarCollapseBtn {
    display: none !important;
  }
  #mobileMenuBtn {
    display: inline-flex !important;
  }

  .form-grid{grid-template-columns:1fr;}

  /* Mobile Bottom Navigation Bar */
  .mobile-bottom-nav{
    position:fixed;bottom:0;left:0;right:0;height:66px;
    background:var(--sidebar);border-top:1px solid var(--border);
    display:grid;grid-template-columns:repeat(4,1fr);
    align-items:center;z-index:90;box-shadow:0 -4px 16px rgba(0,0,0,.08);
  }
  .mob-nav-item{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:4px;color:var(--text-2);text-decoration:none;font-size:12px;
    font-weight:700;height:100%;border:0;background:transparent;
  }
  .mob-nav-item.active{color:var(--brand);}
  .mob-nav-item svg{width:22px;height:22px;}

  /* Mobile Slide Drawer */
  .mobile-menu-drawer{
    position:fixed;top:0;left:0;right:0;bottom:0;
    background:rgba(15,23,42,.6);z-index:1000;display:none;
  }
  .mobile-menu-drawer.active{display:block;}
  .drawer-panel{
    background:var(--sidebar);width:82%;max-width:320px;height:100%;
    padding:20px 16px;overflow-y:auto;animation:drawerSlide .25s ease;
  }
  @keyframes drawerSlide{from{transform:translateX(-100%)}to{transform:none}}
}
</style>
<script>
/* Universal Client-Side Table Pagination Engine & Global Infrastructure */
window._activePaginators = [];

function initTablePagination(config) {
  const tableId = config.tableId;
  const footerId = config.footerId;
  const defaultPageSize = config.defaultPageSize || 10;
  const pageSizes = config.pageSizes || [10, 25, 50, 100];
  const countSpanId = config.countSpanId || null;
  const rowSelector = config.rowSelector || `#${tableId} tbody tr[id]`;

  const table = document.getElementById(tableId);
  const footer = document.getElementById(footerId);
  if (!table || !footer) return null;

  let currentPage = 1;
  let pageSize = defaultPageSize;

  function getEligibleRows() {
    const allRows = Array.from(table.querySelectorAll(rowSelector));
    const eligible = allRows.filter(r => r.dataset.matchedFilter !== 'false');
    return { all: allRows, eligible: eligible };
  }

  function render() {
    const { all, eligible } = getEligibleRows();
    const totalItems = eligible.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, totalItems);

    // Update row visibility
    all.forEach(r => {
      if (r.dataset.matchedFilter === 'false') {
        r.style.display = 'none';
      }
    });

    eligible.forEach((r, idx) => {
      if (idx >= startIndex && idx < endIndex) {
        r.style.display = '';
      } else {
        r.style.display = 'none';
      }
    });

    if (countSpanId) {
      const sp = document.getElementById(countSpanId);
      if (sp) sp.textContent = totalItems;
    }

    const startDisplay = totalItems === 0 ? 0 : startIndex + 1;
    const endDisplay = endIndex;

    let sizeOptionsHtml = pageSizes.map(s => 
      `<option value="${s}" ${s === pageSize ? 'selected' : ''}>${s} per page</option>`
    ).join('');

    let pagesHtml = '';
    const maxButtons = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage < maxButtons - 1) {
      startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (let p = startPage; p <= endPage; p++) {
      pagesHtml += `<button type="button" class="pagination-btn ${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
    }

    footer.innerHTML = `
      <div class="pagination-left">
        <select class="pagination-size-select" id="${footerId}-sizeSelect" title="Rows per page">
          ${sizeOptionsHtml}
        </select>
        <span class="pagination-info">
          Showing <b>${startDisplay}</b> to <b>${endDisplay}</b> of <b>${totalItems}</b> entries
          ${all.length > totalItems ? `<small style="color:var(--text-3);">(filtered from ${all.length})</small>` : ''}
        </span>
      </div>
      <div class="pagination-controls">
        <button type="button" class="pagination-btn" id="${footerId}-prevBtn" ${currentPage <= 1 ? 'disabled' : ''} title="Previous Page">‹ Prev</button>
        ${pagesHtml}
        <button type="button" class="pagination-btn" id="${footerId}-nextBtn" ${currentPage >= totalPages ? 'disabled' : ''} title="Next Page">Next ›</button>
      </div>
    `;

    footer.querySelector(`#${footerId}-sizeSelect`)?.addEventListener('change', (e) => {
      pageSize = parseInt(e.target.value, 10);
      currentPage = 1;
      render();
    });

    footer.querySelector(`#${footerId}-prevBtn`)?.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        render();
      }
    });

    footer.querySelector(`#${footerId}-nextBtn`)?.addEventListener('click', () => {
      if (currentPage < totalPages) {
        currentPage++;
        render();
      }
    });

    footer.querySelectorAll(`.pagination-btn[data-page]`).forEach(btn => {
      btn.addEventListener('click', () => {
        currentPage = parseInt(btn.dataset.page, 10);
        render();
      });
    });
  }

  render();

  const controller = {
    refresh: () => {
      currentPage = 1;
      render();
    },
    setPage: (p) => {
      currentPage = p;
      render();
    }
  };

  window._activePaginators.push(controller);
  return controller;
}



/* Modals must only be closed via X button: clicking outside (backdrop) does not close to prevent data loss */
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('click', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
      const card = e.target.querySelector('.modal-card');
      if (card) {
        card.classList.remove('modal-shake');
        void card.offsetWidth;
        card.classList.add('modal-shake');
      }
    }
  });
});
</script>
</head>
<body>
<?php $isLoggedIn = !empty($_SESSION['is_logged_in']); ?>
<div class="app" style="<?= !$isLoggedIn ? 'display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;' : '' ?>">
<?php if ($isLoggedIn): ?>


  <!-- Desktop Sidebar -->
  <aside class="sidebar">

    <div class="sidebar-header-row">
      <a class="brand" href="<?= url('dashboard') ?>">
        <div class="brand-logo">S</div>
        <div class="brand-name">Sarura Fuel<small>Operations Cloud</small></div>
      </a>
      <button type="button" class="collapse-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Collapse / Expand Sidebar">◀</button>
    </div>

    <div class="nav-section">Overview</div>
    <a class="nav-item <?= isActiveNav('dashboard') ?>" href="<?= url('dashboard') ?>" title="Dashboard">
      <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      <span>Dashboard</span>
    </a>
    <a class="nav-item <?= isActiveNav('fleet') ?>" href="<?= url('fleet') ?>" title="Fleet Management">
      <span class="icon"><svg viewBox="0 0 24 24"><path d="M3 15h18"/><path d="M5 15V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="16.5" cy="18.5" r="1.5"/></svg></span>
      <span>Fleet Management</span>
      <span class="dot"></span>
    </a>
    <a class="nav-item <?= isActiveNav('trucks') ?>" href="<?= url('trucks') ?>" title="Trucks & Tankers">
      <span class="icon"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
      <span>Trucks & Tankers</span>
    </a>
    <?php if (can_view_financials()): ?>
      <a class="nav-item <?= isActiveNav('drivers') ?>" href="<?= url('drivers') ?>" title="Drivers & Salaries">
        <span class="icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <span>Drivers & Salaries</span>
      </a>
    <?php endif; ?>

    <div class="nav-section">Operations & Analytics</div>
    <a class="nav-item <?= isActiveNav('products') ?>" href="<?= url('products') ?>" title="Fuel Products">
      <span class="icon"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
      <span>Fuel Products</span>
    </a>
    <?php if (can_view_financials()): ?>
      <a class="nav-item <?= isActiveNav('expenses') ?>" href="<?= url('expenses') ?>" title="Expenses">
        <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg></span>
        <span>Expenses</span>
      </a>
      <a class="nav-item <?= (isActiveNav('financial') ?: isActiveNav('finances')) ?>" href="<?= url('finances') ?>" title="Finances & Cash Flow">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 7H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
        <span>Finances</span>
      </a>
    <?php endif; ?>
    <a class="nav-item <?= isActiveNav('customers') ?>" href="<?= url('customers') ?>" title="Customers">
      <span class="icon"><svg viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>
      <span>Customers</span>
    </a>
    <a class="nav-item <?= isActiveNav('custom-tables') ?>" href="<?= url('custom-tables') ?>" title="Custom Tables & Datasets Hub">
      <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg></span>
      <span>Custom Tables 📑</span>
    </a>
    <?php if (can_view_financials()): ?>
      <a class="nav-item <?= isActiveNav('reports') ?>" href="<?= url('reports') ?>" title="Monthly Reports">
        <span class="icon"><svg viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg></span>
        <span>Monthly Reports</span>
      </a>
      <a class="nav-item <?= isActiveNav('reports/trucks') ?>" href="<?= url('reports/trucks') ?>" title="Truck Reports">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
        <span>Truck Reports</span>
      </a>
    <?php endif; ?>

    <!-- Collapsible Advanced Features Section -->
    <div class="nav-advanced-section" style="margin-top:6px;">
      <button type="button" class="nav-advanced-toggle" id="advancedNavToggle" onclick="toggleAdvancedNav()" title="Advanced Features: Documents, Audit Trail & Settings">
        <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
        <span class="adv-label">Advanced</span>
        <span class="adv-arrow" id="advArrow" style="margin-left:auto;font-size:10px;transition:transform .2s;">▼</span>
      </button>
      <div class="nav-advanced-menu" id="advancedNavMenu" style="display:none;padding-left:12px;border-left:2px solid var(--border-2);margin-left:14px;margin-top:2px;">
        <a class="nav-item <?= isActiveNav('documents') ?>" href="<?= url('documents') ?>" title="Company Documents">
          <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg></span>
          <span>Documents</span>
        </a>
        <a class="nav-item <?= isActiveNav('audit') ?>" href="<?= url('audit') ?>" title="System Audit Trail">
          <span class="icon"><svg viewBox="0 0 24 24"><path d="M12 2v8"/><path d="M5.4 19.4A8 8 0 0 1 12 4a8 8 0 0 1 6.6 3.4"/><path d="M19 15v6"/><path d="M16 18h6"/></svg></span>
          <span>Audit Trail</span>
        </a>
        <a class="nav-item <?= isActiveNav('users') ?>" href="<?= url('users') ?>" title="User Accounts & Permissions">
          <span class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
          <span>Users & Access</span>
        </a>
        <a class="nav-item <?= isActiveNav('settings') ?>" href="<?= url('settings') ?>" title="System Settings">
          <span class="icon"><svg viewBox="0 0 24 24"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg></span>
          <span>Settings</span>
        </a>
        <?php if (is_super_admin() || is_developer()): ?>
          <a class="nav-item <?= isActiveNav('dev') ?>" href="<?= url('dev') ?>" title="Developer & Security Console">
            <span class="icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></span>
            <span>Dev Console 🛡️</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div style="margin-top:auto;padding-top:14px;">
      <form method="POST" action="<?= url('logout') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="nav-item" style="color:var(--red);border-radius:10px;" title="Logout">
          <span class="icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
          <span>Logout</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- Mobile Slide Drawer -->
  <div class="mobile-menu-drawer" id="mobileDrawer" onclick="if(event.target===this)toggleMobileMenu()">
    <div class="drawer-panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div class="brand-name" style="font-size:18px;">Sarura Fuel<small>Menu Navigation</small></div>
        <button class="close-modal" onclick="toggleMobileMenu()">✕</button>
      </div>
      <a class="nav-item <?= isActiveNav('dashboard') ?>" href="<?= url('dashboard') ?>"><span>Dashboard</span></a>
      <a class="nav-item <?= isActiveNav('fleet') ?>" href="<?= url('fleet') ?>"><span>Fleet Management</span></a>
      <a class="nav-item <?= isActiveNav('trucks') ?>" href="<?= url('trucks') ?>"><span>Trucks & Tankers</span></a>
      <?php if (can_view_financials()): ?>
        <a class="nav-item <?= isActiveNav('drivers') ?>" href="<?= url('drivers') ?>"><span>Drivers & Salaries</span></a>
      <?php endif; ?>
      <div style="height:1px;background:var(--border);margin:10px 0;"></div>
      <a class="nav-item <?= isActiveNav('products') ?>" href="<?= url('products') ?>"><span>Fuel Products</span></a>
      <?php if (can_view_financials()): ?>
        <a class="nav-item <?= isActiveNav('expenses') ?>" href="<?= url('expenses') ?>"><span>Expenses</span></a>
        <a class="nav-item <?= (isActiveNav('financial') ?: isActiveNav('finances')) ?>" href="<?= url('finances') ?>"><span>Finances 💵</span></a>
      <?php endif; ?>
      <a class="nav-item <?= isActiveNav('customers') ?>" href="<?= url('customers') ?>"><span>Customers</span></a>
      <a class="nav-item <?= isActiveNav('custom-tables') ?>" href="<?= url('custom-tables') ?>"><span>Custom Tables 📑</span></a>
      <?php if (can_view_financials()): ?>
        <a class="nav-item <?= isActiveNav('reports') ?>" href="<?= url('reports') ?>"><span>Monthly Reports</span></a>
        <a class="nav-item <?= isActiveNav('reports/trucks') ?>" href="<?= url('reports/trucks') ?>"><span>Truck Reports</span></a>
      <?php endif; ?>
      <div style="height:1px;background:var(--border);margin:10px 0;"></div>
      <div style="font-size:12px;font-weight:800;color:var(--text-3);padding:6px 12px;text-transform:uppercase;">Advanced</div>
      <a class="nav-item <?= isActiveNav('documents') ?>" href="<?= url('documents') ?>"><span>Documents</span></a>
      <a class="nav-item <?= isActiveNav('audit') ?>" href="<?= url('audit') ?>"><span>Audit Trail</span></a>
      <a class="nav-item <?= isActiveNav('users') ?>" href="<?= url('users') ?>"><span>Users & Access</span></a>
      <a class="nav-item <?= isActiveNav('settings') ?>" href="<?= url('settings') ?>"><span>Settings</span></a>
      <?php if (is_super_admin() || is_developer()): ?>
        <a class="nav-item <?= isActiveNav('dev') ?>" href="<?= url('dev') ?>"><span>Dev Console 🛡️</span></a>
      <?php endif; ?>
      <div style="margin-top:20px;">
        <form method="POST" action="<?= url('logout') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-danger" style="width:100%;">Logout</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <!-- Main Workspace -->
  <div class="main" style="<?= !$isLoggedIn ? 'width:100%;max-width:540px;margin:0 auto;' : '' ?>">
<?php if ($isLoggedIn): ?>
    <header class="topbar">
      <!-- Mobile Hamburger Button (Only on Mobile screens) -->
      <button type="button" class="icon-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()" title="Open Navigation Menu">
        <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <!-- Currency Switcher Toggle -->
      <form method="POST" action="<?= url('currency/toggle') ?>" style="margin:0;display:inline-flex;align-items:center;gap:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/dashboard') ?>">
        <div class="currency-toggle" title="Click to toggle currency between USD and KES (1 USD = <?= exchange_rate() ?> KES)">
          <button type="submit" class="currency-opt <?= $activeCurrency === 'USD' ? 'active' : '' ?>"><b>$ USD</b></button>
          <button type="submit" class="currency-opt <?= $activeCurrency === 'KES' ? 'active' : '' ?>"><b>KES</b></button>
        </div>
        <button type="button" class="rate-badge" onclick="openExchangeRateModal()" style="cursor:pointer;background:var(--card-2);border:1px solid var(--border-2);padding:4px 8px;border-radius:6px;font-weight:700;font-size:12px;color:var(--text-2);display:inline-flex;align-items:center;gap:4px;" title="Click to adjust currency exchange rate">
          <span>1$ = <?= exchange_rate() ?> KES</span>
          <span style="font-size:11px;color:var(--brand);">✏️</span>
        </button>
      </form>



      <div class="search" style="margin-left:auto;">
        <span class="icon"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <input placeholder="Search fleet, trucks, drivers…" id="globalQuickSearch">
        <span class="kbd">⌘K</span>
      </div>

      <button class="icon-btn" id="themeBtn" title="Toggle dark mode"></button>
      <div class="avatar" title="<?= htmlspecialchars($currentUser['name'] ?? 'User') ?>"><?= $initials ?></div>
    </header>
<?php endif; ?>


    <main class="content">
      <!-- Flash notifications -->
      <?php if ($msg = flash('fleet_success') ?? flash('truck_success') ?? flash('product_success') ?? flash('expense_success') ?? flash('driver_success') ?? flash('salary_success') ?? flash('schema_success') ?? flash('custom_success')): ?>
        <div style="padding:14px 20px;background:var(--green-soft);border:1px solid var(--green);color:var(--green);border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px;">
          <span>✓</span>
          <div><?= htmlspecialchars($msg) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($err = flash('fleet_error') ?? flash('truck_error') ?? flash('product_error') ?? flash('expense_error') ?? flash('driver_error') ?? flash('salary_error') ?? flash('schema_error') ?? flash('custom_error')): ?>
        <div style="padding:14px 20px;background:var(--red-soft);border:1px solid var(--red);color:var(--red);border-radius:12px;margin-bottom:20px;font-weight:700;display:flex;align-items:center;gap:10px;">
          <span>⚠️</span>
          <div><?= htmlspecialchars($err) ?></div>
        </div>
      <?php endif; ?>

      <?php
        if (is_callable($content ?? null)) {
          echo $content();
        } elseif (isset($content)) {
          echo $content;
        }
      ?>
    </main>
  </div>
</div>

<?php if ($isLoggedIn): ?>
<!-- Mobile Quick Navigation Bar (Screens < 768px) -->
<nav class="mobile-bottom-nav">
  <a href="<?= url('fleet') ?>" class="mob-nav-item <?= isActiveNav('fleet') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 15h18"/><path d="M5 15V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="16.5" cy="18.5" r="1.5"/></svg>
    <span>Fleet</span>
  </a>
  <a href="<?= url('trucks') ?>" class="mob-nav-item <?= isActiveNav('trucks') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    <span>Trucks</span>
  </a>
  <a href="<?= url('expenses') ?>" class="mob-nav-item <?= isActiveNav('expenses') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
    <span>Expenses</span>
  </a>
  <button class="mob-nav-item" onclick="toggleMobileMenu()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    <span>Menu</span>
  </button>
</nav>
<?php endif; ?>


<script>
const themeBtn=document.getElementById('themeBtn');
const sun='<span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>';
const moon='<span class="icon"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>';
function setTheme(t){document.documentElement.dataset.theme=t;if(themeBtn)themeBtn.innerHTML=t==='dark'?sun:moon;localStorage.setItem('sf-theme',t);}
if(themeBtn){themeBtn.addEventListener('click',()=>setTheme(document.documentElement.dataset.theme==='dark'?'light':'dark'));setTheme(localStorage.getItem('sf-theme')||'light');}

/* Collapsible Sidebar Handler */
function toggleSidebar() {
  const app = document.querySelector('.app');
  if (!app) return;
  const isCollapsed = app.classList.toggle('sidebar-collapsed');
  localStorage.setItem('sf-sidebar-collapsed', isCollapsed ? 'true' : 'false');
  const btn = document.getElementById('sidebarToggleBtn');
  if (btn) btn.innerHTML = isCollapsed ? '▶' : '◀';
}
// Restore sidebar preference on load
if (localStorage.getItem('sf-sidebar-collapsed') === 'true' && window.innerWidth > 768) {
  document.querySelector('.app')?.classList.add('sidebar-collapsed');
  const btn = document.getElementById('sidebarToggleBtn');
  if (btn) btn.innerHTML = '▶';
}

// Screen resize resilience: smoothly adapt without breaking layout
window.addEventListener('resize', () => {
  const app = document.querySelector('.app');
  if (!app) return;
  if (window.innerWidth <= 768) {
    app.classList.remove('sidebar-collapsed');
  } else if (localStorage.getItem('sf-sidebar-collapsed') === 'true') {
    app.classList.add('sidebar-collapsed');
    const btn = document.getElementById('sidebarToggleBtn');
    if (btn) btn.innerHTML = '▶';
  }
});

function toggleMobileMenu(){
  const drawer = document.getElementById('mobileDrawer');
  if(drawer) drawer.classList.toggle('active');
}



/* Advanced Features Nav Accordion */
function toggleAdvancedNav(forceState) {
  const menu = document.getElementById('advancedNavMenu');
  const arrow = document.getElementById('advArrow');
  if (!menu) return;
  const isOpening = (forceState !== undefined) ? forceState : (menu.style.display === 'none');
  menu.style.display = isOpening ? 'block' : 'none';
  if (arrow) arrow.style.transform = isOpening ? 'rotate(180deg)' : 'none';
  localStorage.setItem('sf_advanced_nav_open', isOpening ? '1' : '0');
}

const isAdvPage = <?= json_encode(
  str_contains($_SERVER['REQUEST_URI'] ?? '', 'documents') || 
  str_contains($_SERVER['REQUEST_URI'] ?? '', 'audit') || 
  str_contains($_SERVER['REQUEST_URI'] ?? '', 'users') || 
  str_contains($_SERVER['REQUEST_URI'] ?? '', 'settings')
) ?>;
if (isAdvPage || localStorage.getItem('sf_advanced_nav_open') === '1') {
  toggleAdvancedNav(true);
}

// Global live search hook for pages with a table
const globalSearch = document.getElementById('globalQuickSearch');
if(globalSearch){
  globalSearch.addEventListener('input', function(e){
    const val = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr:not(.empty-row)');
    rows.forEach(r => {
      const matches = !val || r.textContent.toLowerCase().includes(val);
      r.dataset.matchedFilter = matches ? 'true' : 'false';
    });
    if (window._activePaginators) {
      window._activePaginators.forEach(p => p.refresh());
    }
  });
}



<!-- Exchange Rate Setting Modal -->
<div class="modal-backdrop" id="exchangeRateModal">
  <div class="modal-card" style="max-width:440px;">
    <div class="modal-head">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:24px;background:var(--brand-soft);padding:6px;border-radius:10px;">💱</span>
        <div>
          <h2 style="margin:0;font-size:18px;">Set Exchange Rate</h2>
          <div style="font-size:12px;color:var(--text-3);margin-top:2px;">Base rate for USD ($) to Kenya Shillings (KSh)</div>
        </div>
      </div>
      <button type="button" class="close-modal" onclick="document.getElementById('exchangeRateModal').classList.remove('active')">✕</button>
    </div>
    <form method="POST" action="<?= url('currency/set-rate') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/dashboard') ?>">
      <div style="padding:16px 20px;">
        <div class="form-group" style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;color:var(--text-2);display:block;margin-bottom:6px;">Exchange Rate: 1 USD ($) = ? KES (KSh) *</label>
          <div style="position:relative;">
            <input type="number" step="0.01" min="1" name="rate" id="navExchangeRateInput" value="<?= exchange_rate() ?>" required style="width:100%;font-size:18px;font-weight:900;padding:10px 12px;border:2px solid var(--brand);border-radius:10px;color:var(--brand);">
            <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--text-3);">KES / $</span>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
          <span style="font-size:11.5px;color:var(--text-3);font-weight:700;">Presets:</span>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('navExchangeRateInput').value='128.00'">128.00</button>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('navExchangeRateInput').value='129.50'">129.50</button>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('navExchangeRateInput').value='130.00'">130.00</button>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('navExchangeRateInput').value='131.00'">131.00</button>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('navExchangeRateInput').value='132.50'">132.50</button>
        </div>
        <div style="background:var(--card-2);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--text-2);margin-bottom:14px;">
          ℹ️ Updating this rate immediately recalculates currency conversions, product unit rates, fuel pump conversions, and financial summaries across the entire system.
        </div>
      </div>
      <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--card-2);display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('exchangeRateModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn btn-brand btn-sm" style="font-weight:800;">✓ Save Exchange Rate</button>
      </div>
    </form>
  </div>
</div>

<script>
window.openExchangeRateModal = function() {
  document.getElementById('exchangeRateModal')?.classList.add('active');
};
</script>
</body>
</html>

