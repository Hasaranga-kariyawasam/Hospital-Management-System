<?php
/**
 * modules/pharmacy/drug_catalog.php
 * MediCare HMS — Drug Catalog
 * Group 05 | ICT1242 Web Development Practicum
 *
 * Displays all drugs from pharmacy_drugs table.
 * Supports:
 *   - Live search by drug name (AJAX)
 *   - Filter by category
 *   - Stock level indicators (In Stock / Low Stock / Out of Stock)
 *   - Full table view with unit price, stock qty, reorder level
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

$pageTitle  = 'Drug Catalog';
$pageCss    = '/Web/Hospital-Management-System/modules/pharmacist/phamacist.css';
$useSidebar = true;
$isPublic   = false;

// ── AJAX: search / filter ─────────────────────────────────────────────────────
if (!empty($_GET['action']) && $_GET['action'] === 'search_drugs') {
    header('Content-Type: application/json');

    $q        = trim($_GET['q']        ?? '');
    $category = trim($_GET['category'] ?? '');

    try {
        $where  = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(drug_name LIKE ? OR drug_id LIKE ? OR category LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($category !== '' && $category !== 'all') {
            $where[]  = 'category = ?';
            $params[] = $category;
        }

        $sql  = 'SELECT drug_id, drug_name, category, unit, unit_price, stock_qty, reorder_level, is_active
                   FROM pharmacy_drugs
                  WHERE ' . implode(' AND ', $where) . '
                  ORDER BY drug_name ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $drugs = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'drugs' => $drugs, 'total' => count($drugs)]);

    } catch (Throwable $e) {
        error_log('[Drug Catalog] search_drugs: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
    }
    exit;
}

// ── Load categories for filter pills ─────────────────────────────────────────
try {
    $categories = db()
        ->query("SELECT DISTINCT category FROM pharmacy_drugs WHERE category IS NOT NULL ORDER BY category")
        ->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('[Drug Catalog] categories: ' . $e->getMessage());
    $categories = [];
}

// ── Load summary stats ────────────────────────────────────────────────────────
try {
    $stats = db()->query("
        SELECT
            COUNT(*)                                          AS total,
            SUM(stock_qty = 0)                               AS out_of_stock,
            SUM(stock_qty > 0 AND stock_qty <= reorder_level) AS low_stock,
            SUM(stock_qty  > reorder_level)                   AS in_stock
        FROM pharmacy_drugs
        WHERE is_active = 1
    ")->fetch();
} catch (Throwable $e) {
    $stats = ['total' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'in_stock' => 0];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">

<style>
/* ── CSS Variables (mirrors phamacist.css) ── */
:root {
    --color-primary:#0C447C; --color-primary-light:#E6F1FB;
    --color-primary-mid:#85B7EB; --color-primary-hover:#B5D4F4;
    --color-primary-dark:#185FA5;
    --color-success-bg:#EAF3DE; --color-success-text:#3B6D11;
    --color-success-border:#97C459; --color-success-fill:#C0DD97;
    --color-warn-bg:#FFF8E6;   --color-warn-text:#92600A;
    --color-warn-border:#F5C842;
    --color-danger-bg:#FCEBEB; --color-danger-text:#A32D2D;
    --color-danger-border:#F09595;
    --color-background-primary:#FFFFFF; --color-background-secondary:#F5F7FA;
    --color-background-page:#EEF1F6; --color-border-tertiary:#DDE3EC;
    --color-text-primary:#0F1924; --color-text-secondary:#5A6677;
    --color-text-info:#1A6FC4;
    --border-radius-sm:6px; --border-radius-md:10px; --border-radius-lg:14px;
    --font-sans:'DM Sans',system-ui,sans-serif;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:var(--font-sans); background:var(--color-background-page); color:var(--color-text-primary); }

/* ── Page tab strip ── */
.ph-tab-strip{
    display:flex; gap:4px; margin-bottom:18px;
    border-bottom:1.5px solid var(--color-border-tertiary);
    padding-bottom:0;
}
.ph-tab{
    display:inline-flex; align-items:center; gap:6px;
    font-size:13px; font-weight:500; text-decoration:none;
    color:var(--color-text-secondary);
    padding:8px 16px 10px;
    border-radius:var(--border-radius-sm) var(--border-radius-sm) 0 0;
    border:0.5px solid transparent;
    border-bottom:2px solid transparent;
    margin-bottom:-1.5px;
    transition:color .15s, background .15s;
}
.ph-tab:hover{
    color:var(--color-primary);
    background:var(--color-primary-light);
}
.ph-tab.active{
    color:var(--color-primary);
    background:var(--color-background-primary);
    border-color:var(--color-border-tertiary);
    border-bottom-color:var(--color-background-primary);
    font-weight:600;
}

/* ── Layout ── */
.main-content { flex:1; overflow-y:auto; min-width:0; }
.dc-wrap       { max-width:1100px; margin:0 auto; padding:28px 24px 60px; }

/* ── Breadcrumb ── */
.breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--color-text-secondary); margin-bottom:20px; }
.breadcrumb a { color:var(--color-primary); text-decoration:none; }
.breadcrumb a:hover { text-decoration:underline; }
.breadcrumb .sep { opacity:.4; }

/* ── Page title row ── */
.page-title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.page-title h1  { font-size:20px; font-weight:600; color:var(--color-text-primary); line-height:1.3; }
.page-title p   { font-size:13px; color:var(--color-text-secondary); margin-top:3px; }

/* ── Stat cards ── */
.stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
@media(max-width:700px){ .stat-grid { grid-template-columns:repeat(2,1fr); } }
.stat-card {
    background:var(--color-background-primary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-md);
    padding:14px 16px;
    display:flex; align-items:center; gap:12px;
}
.stat-icon {
    width:38px; height:38px; border-radius:var(--border-radius-sm);
    display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;
}
.stat-icon.blue   { background:var(--color-primary-light);  color:var(--color-primary); }
.stat-icon.green  { background:var(--color-success-bg);     color:var(--color-success-text); }
.stat-icon.yellow { background:var(--color-warn-bg);        color:var(--color-warn-text); }
.stat-icon.red    { background:var(--color-danger-bg);      color:var(--color-danger-text); }
.stat-num  { font-size:22px; font-weight:600; color:var(--color-text-primary); line-height:1; }
.stat-lbl  { font-size:11px; color:var(--color-text-secondary); margin-top:3px; }

/* ── Search & filter bar ── */
.search-bar-card {
    background:var(--color-background-primary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-lg);
    padding:16px 18px; margin-bottom:16px;
}
.search-row { display:flex; gap:8px; align-items:center; margin-bottom:12px; }
.search-input-wrap { position:relative; flex:1; }
.search-input-wrap i {
    position:absolute; left:11px; top:50%; transform:translateY(-50%);
    color:var(--color-text-secondary); font-size:15px; pointer-events:none;
}
.search-input-wrap input {
    width:100%; height:38px; padding:0 12px 0 34px;
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-md);
    font-family:var(--font-sans); font-size:13px;
    color:var(--color-text-primary); background:var(--color-background-secondary);
    outline:none; transition:border-color .15s, box-shadow .15s;
}
.search-input-wrap input:focus {
    border-color:var(--color-primary-mid);
    box-shadow:0 0 0 3px rgba(12,68,124,.08);
    background:#fff;
}
.result-count { font-size:12px; color:var(--color-text-secondary); white-space:nowrap; }

/* ── Category filter pills ── */
.filter-pills { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.filter-label { font-size:11px; color:var(--color-text-secondary); margin-right:2px; }
.filter-pill {
    font-size:11px; font-weight:500;
    padding:4px 12px; border-radius:20px; cursor:pointer;
    border:0.5px solid var(--color-border-tertiary);
    background:var(--color-background-secondary);
    color:var(--color-text-secondary);
    transition:all .15s;
}
.filter-pill:hover { background:var(--color-primary-hover); border-color:var(--color-primary-mid); color:var(--color-primary); }
.filter-pill.active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; }

/* ── Table card ── */
.table-card {
    background:var(--color-background-primary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-lg);
    overflow:hidden;
}
.table-wrap { overflow-x:auto; }
.drug-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.drug-tbl th {
    font-size:10px; font-weight:600; text-transform:uppercase;
    letter-spacing:.5px; color:var(--color-text-secondary);
    background:var(--color-background-secondary);
    padding:10px 14px; text-align:left; white-space:nowrap;
    border-bottom:0.5px solid var(--color-border-tertiary);
}
.drug-tbl td {
    padding:11px 14px; border-bottom:0.5px solid var(--color-border-tertiary);
    vertical-align:middle;
}
.drug-tbl tbody tr:last-child td { border-bottom:none; }
.drug-tbl tbody tr { transition:background .12s; }
.drug-tbl tbody tr:hover { background:var(--color-background-secondary); }

/* ── Drug name cell ── */
.drug-name-cell .dn  { font-size:13px; font-weight:500; color:var(--color-text-primary); }
.drug-name-cell .did { font-size:11px; color:var(--color-text-secondary); margin-top:1px; }

/* ── Category badge ── */
.cat-badge {
    display:inline-block; font-size:10px; font-weight:500;
    padding:3px 9px; border-radius:20px;
    background:var(--color-primary-light); color:var(--color-primary);
    border:0.5px solid var(--color-primary-mid);
    white-space:nowrap;
}

/* ── Stock indicator ── */
.stock-cell { display:flex; align-items:center; gap:7px; }
.stock-dot  { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.stock-dot.in   { background:#4CAF50; }
.stock-dot.low  { background:#F59E0B; }
.stock-dot.out  { background:#EF4444; }
.stock-num      { font-size:13px; font-weight:500; }
.stock-num.in   { color:var(--color-success-text); }
.stock-num.low  { color:var(--color-warn-text); }
.stock-num.out  { color:var(--color-danger-text); }
.stock-badge {
    font-size:10px; font-weight:500; padding:2px 8px; border-radius:20px;
}
.stock-badge.in  { background:var(--color-success-bg); color:var(--color-success-text); border:0.5px solid var(--color-success-border); }
.stock-badge.low { background:var(--color-warn-bg);    color:var(--color-warn-text);    border:0.5px solid var(--color-warn-border); }
.stock-badge.out { background:var(--color-danger-bg);  color:var(--color-danger-text);  border:0.5px solid var(--color-danger-border); }

/* ── Reorder bar ── */
.reorder-bar-wrap { display:flex; align-items:center; gap:6px; }
.reorder-bar-bg   { flex:1; height:4px; background:var(--color-border-tertiary); border-radius:2px; min-width:60px; max-width:100px; overflow:hidden; }
.reorder-bar-fill { height:100%; border-radius:2px; transition:width .3s; }
.reorder-bar-fill.in  { background:#4CAF50; }
.reorder-bar-fill.low { background:#F59E0B; }
.reorder-bar-fill.out { background:#EF4444; }
.reorder-txt { font-size:11px; color:var(--color-text-secondary); white-space:nowrap; }

/* ── Price cell ── */
.price-cell { font-size:13px; font-weight:500; color:var(--color-text-primary); }

/* ── Unit cell ── */
.unit-cell { font-size:12px; color:var(--color-text-secondary); text-transform:capitalize; }

/* ── Inactive badge ── */
.inactive-badge { font-size:10px; color:#999; background:#f0f0f0; border:0.5px solid #ddd; padding:2px 8px; border-radius:20px; }

/* ── Empty / loading state ── */
.state-row td { text-align:center; padding:40px 20px; color:var(--color-text-secondary); font-size:13px; }
.state-icon   { font-size:28px; margin-bottom:8px; opacity:.4; }

/* ── Loading spinner ── */
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
.ti-loader-2 { animation:spin 1s linear infinite; display:inline-block; }
</style>

<main class="main-content">
<div class="dc-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/Web/Hospital-Management-System/modules/admin/dashboard.php">Dashboard</a>
        <span class="sep">›</span>
        <a href="phamacist.php">Pharmacy</a>
        <span class="sep">›</span>
        <span>Drug Catalog</span>
    </nav>

    <!-- ── Page tab strip ──────────────────────────────────────────────── -->
    <div class="ph-tab-strip">
        <a class="ph-tab" href="phamacist.php">
            <i class="ti ti-file-prescription" aria-hidden="true"></i>
            Dispense Prescription
        </a>
        <a class="ph-tab active" href="/Web/Hospital-Management-System/modules/pharmacy/drugs.php">
            <i class="ti ti-pill" aria-hidden="true"></i>
            Drug Catalog
        </a>
    </div>

    <!-- Page title -->
    <div class="page-title-row">
        <div class="page-title">
            <h1><i class="ti ti-pill" style="color:var(--color-primary);margin-right:6px"></i>Drug Catalog</h1>
            <p>Browse and search the current drug inventory from the pharmacy stock.</p>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="ti ti-package"></i></div>
            <div>
                <div class="stat-num"><?= (int)($stats['total'] ?? 0) ?></div>
                <div class="stat-lbl">Total Drugs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="ti ti-circle-check"></i></div>
            <div>
                <div class="stat-num"><?= (int)($stats['in_stock'] ?? 0) ?></div>
                <div class="stat-lbl">In Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="ti ti-alert-triangle"></i></div>
            <div>
                <div class="stat-num"><?= (int)($stats['low_stock'] ?? 0) ?></div>
                <div class="stat-lbl">Low Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="ti ti-circle-x"></i></div>
            <div>
                <div class="stat-num"><?= (int)($stats['out_of_stock'] ?? 0) ?></div>
                <div class="stat-lbl">Out of Stock</div>
            </div>
        </div>
    </div>

    <!-- Search & filter bar -->
    <div class="search-bar-card">
        <div class="search-row">
            <div class="search-input-wrap">
                <i class="ti ti-search"></i>
                <input
                    type="text"
                    id="drugSearch"
                    placeholder="Search by drug name, ID or category…"
                    autocomplete="off"
                    oninput="onSearchInput()"
                >
            </div>
            <span class="result-count" id="resultCount">— drugs</span>
        </div>

        <!-- Category filter pills -->
        <div class="filter-pills">
            <span class="filter-label">Filter:</span>
            <span class="filter-pill active" data-cat="all" onclick="setCategory(this, 'all')">All</span>
            <?php foreach ($categories as $cat): ?>
            <span class="filter-pill" data-cat="<?= htmlspecialchars($cat) ?>"
                  onclick="setCategory(this, '<?= htmlspecialchars($cat, ENT_QUOTES) ?>')">
                <?= htmlspecialchars($cat) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Drug table -->
    <div class="table-card">
        <div class="table-wrap">
            <table class="drug-tbl">
                <thead>
                    <tr>
                        <th style="width:220px">Drug Name</th>
                        <th style="width:120px">Category</th>
                        <th style="width:70px">Unit</th>
                        <th style="width:100px">Unit Price</th>
                        <th style="width:130px">Stock Qty</th>
                        <th style="width:160px">Stock Level</th>
                        <th style="width:80px">Status</th>
                    </tr>
                </thead>
                <tbody id="drugBody">
                    <!-- Loaded by JS on page load -->
                    <tr class="state-row">
                        <td colspan="7">
                            <div class="state-icon"><i class="ti ti-loader-2"></i></div>
                            <div>Loading drug catalog…</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

<script>
let _searchTimer = null;
let _activeCategory = 'all';

/* ── On page load: fetch all drugs immediately ── */
window.addEventListener('DOMContentLoaded', () => {
    fetchDrugs('', 'all');
});

/* ── Search input handler with debounce ── */
function onSearchInput() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => {
        fetchDrugs(
            document.getElementById('drugSearch').value.trim(),
            _activeCategory
        );
    }, 280);
}

/* ── Category filter ── */
function setCategory(el, cat) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    _activeCategory = cat;
    fetchDrugs(document.getElementById('drugSearch').value.trim(), cat);
}

/* ── AJAX fetch ── */
async function fetchDrugs(q, category) {
    showLoading();
    try {
        const url = `?action=search_drugs&q=${encodeURIComponent(q)}&category=${encodeURIComponent(category === 'all' ? '' : category)}`;
        const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();

        if (!data.ok) {
            showError(data.error || 'Failed to load drugs.');
            return;
        }
        renderDrugs(data.drugs);
        document.getElementById('resultCount').textContent =
            data.total + ' drug' + (data.total !== 1 ? 's' : '');
    } catch (e) {
        showError('Network error. Please try again.');
        console.error(e);
    }
}

/* ── Render drug rows ── */
function renderDrugs(drugs) {
    const tbody = document.getElementById('drugBody');

    if (!drugs || drugs.length === 0) {
        tbody.innerHTML = `
            <tr class="state-row">
                <td colspan="7">
                    <div class="state-icon"><i class="ti ti-mood-empty"></i></div>
                    <div>No drugs found matching your search.</div>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = drugs.map((d, i) => {
        const stock    = parseInt(d.stock_qty)     || 0;
        const reorder  = parseInt(d.reorder_level) || 0;
        const price    = parseFloat(d.unit_price)  || 0;
        const isActive = parseInt(d.is_active)     === 1;

        /* stock level class */
        let lvl = 'in';
        if (stock === 0)            lvl = 'out';
        else if (stock <= reorder)  lvl = 'low';

        const lvlLabel = lvl === 'in' ? 'In Stock' : lvl === 'low' ? 'Low Stock' : 'Out of Stock';

        /* reorder bar percentage (cap at 100%) */
        const maxBar  = Math.max(reorder * 2, stock, 1);
        const barPct  = Math.min(100, Math.round((stock / maxBar) * 100));

        const rowBg = i % 2 === 1 ? 'style="background:var(--color-background-secondary)"' : '';

        return `<tr ${rowBg}>
            <td>
                <div class="drug-name-cell">
                    <div class="dn">${esc(d.drug_name)}</div>
                    <div class="did">${esc(d.drug_id)}</div>
                </div>
            </td>
            <td><span class="cat-badge">${esc(d.category || '—')}</span></td>
            <td><span class="unit-cell">${esc(d.unit || '—')}</span></td>
            <td><span class="price-cell">LKR ${price.toFixed(2)}</span></td>
            <td>
                <div class="stock-cell">
                    <span class="stock-dot ${lvl}"></span>
                    <span class="stock-num ${lvl}">${stock.toLocaleString()}</span>
                    <span class="stock-badge ${lvl}">${lvlLabel}</span>
                </div>
            </td>
            <td>
                <div class="reorder-bar-wrap">
                    <div class="reorder-bar-bg">
                        <div class="reorder-bar-fill ${lvl}" style="width:${barPct}%"></div>
                    </div>
                    <span class="reorder-txt">Min: ${reorder}</span>
                </div>
            </td>
            <td>
                ${isActive
                    ? '<span class="stock-badge in">Active</span>'
                    : '<span class="inactive-badge">Inactive</span>'}
            </td>
        </tr>`;
    }).join('');
}

/* ── Loading state ── */
function showLoading() {
    document.getElementById('drugBody').innerHTML = `
        <tr class="state-row">
            <td colspan="7">
                <div class="state-icon"><i class="ti ti-loader-2" style="animation:spin 1s linear infinite;display:inline-block"></i></div>
                <div>Loading…</div>
            </td>
        </tr>`;
}

/* ── Error state ── */
function showError(msg) {
    document.getElementById('drugBody').innerHTML = `
        <tr class="state-row">
            <td colspan="7">
                <div class="state-icon" style="color:#A32D2D"><i class="ti ti-alert-circle"></i></div>
                <div style="color:#A32D2D">${esc(msg)}</div>
            </td>
        </tr>`;
}

/* ── HTML escape ── */
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
