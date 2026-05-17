<?php
/**
 * drug_catalog.php — Drug inventory with live search & category filter
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Drug Catalog'; $useSidebar = true; $isPublic = false; $pageCss = '';

if (trim($_GET['action']??'') === 'search') {
    header('Content-Type: application/json');
    $like = '%'.trim($_GET['q']??'').'%';
    $cat  = trim($_GET['cat']??'');
    $where = ['is_active=1'];
    $params = [];
    if (trim($_GET['q']??'') !== '') { $where[] = '(drug_name LIKE ? OR drug_id LIKE ? OR category LIKE ?)'; $params=[$like,$like,$like]; }
    if ($cat && $cat !== 'all')      { $where[] = 'category=?'; $params[] = $cat; }
    $s = db()->prepare('SELECT drug_id,drug_name,category,unit,unit_price,stock_qty,reorder_level FROM pharmacy_drugs WHERE '.implode(' AND ',$where).' ORDER BY drug_name');
    $s->execute($params);
    echo json_encode(['ok'=>true,'list'=>$s->fetchAll()]);
    exit;
}

$stats = db()->query("SELECT COUNT(*) total,SUM(stock_qty=0) oos,SUM(stock_qty>0 AND stock_qty<=reorder_level) low,SUM(stock_qty>reorder_level) ok FROM pharmacy_drugs WHERE is_active=1")->fetch();
$cats  = db()->query("SELECT DISTINCT category FROM pharmacy_drugs WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="pharmacy.css">
<main class="main-content"><div class="ph-wrap">

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card"><div class="stat-lbl">Total Drugs</div><div class="stat-val blue"><?= $stats['total'] ?></div></div>
  <div class="stat-card"><div class="stat-lbl">In Stock</div><div class="stat-val green"><?= $stats['ok'] ?></div></div>
  <div class="stat-card"><div class="stat-lbl">Low Stock</div><div class="stat-val yellow"><?= $stats['low'] ?></div></div>
  <div class="stat-card"><div class="stat-lbl">Out of Stock</div><div class="stat-val red"><?= $stats['oos'] ?></div></div>
</div>

<div class="card">
  <div class="card-hd"><i class="ti ti-pill"></i> Drug Catalog</div>

  <!-- Filters -->
  <div class="row-g" style="margin-bottom:14px;flex-wrap:wrap">
    <input id="q" placeholder="Search drug name, ID, category…" oninput="load()" style="max-width:280px">
    <button class="pill active" onclick="setCat('all',this)">All</button>
    <?php foreach($cats as $c): ?>
    <button class="pill" onclick="setCat('<?= htmlspecialchars($c) ?>',this)"><?= htmlspecialchars($c) ?></button>
    <?php endforeach; ?>
  </div>

  <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr>
        <th>Drug</th><th>Category</th><th>Unit</th>
        <th style="text-align:right">Price (LKR)</th>
        <th style="text-align:right">Stock</th>
        <th style="text-align:right">Reorder</th>
        <th>Status</th>
      </tr></thead>
      <tbody id="tb"><tr><td colspan="7" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
  <div class="muted" id="cnt" style="margin-top:8px;font-size:12px"></div>
</div>

</div></main>

<script>
let cat='all',t=null;

function setCat(c,btn){
  cat=c;
  document.querySelectorAll('.pill').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  load();
}

function load(){
  clearTimeout(t);
  t=setTimeout(async()=>{
    const q=document.getElementById('q').value;
    const d=await fetch(`?action=search&q=${encodeURIComponent(q)}&cat=${encodeURIComponent(cat)}`).then(r=>r.json());
    const tb=document.getElementById('tb');
    document.getElementById('cnt').textContent=`Showing ${(d.list||[]).length} drug(s)`;
    if(!d.list?.length){tb.innerHTML='<tr><td colspan="7" class="empty">No drugs found.</td></tr>';return;}
    tb.innerHTML=d.list.map(r=>{
      const s=parseInt(r.stock_qty),re=parseInt(r.reorder_level);
      const[cls,lbl]=s===0?['badge-out','Out of Stock']:s<=re?['badge-low','Low Stock']:['badge-in','In Stock'];
      return`<tr>
        <td><b>${r.drug_name}</b><br><span class="muted">${r.drug_id}</span></td>
        <td>${r.category}</td><td class="muted">${r.unit}</td>
        <td style="text-align:right;font-weight:500">${parseFloat(r.unit_price).toFixed(2)}</td>
        <td style="text-align:right;font-weight:700;color:${s===0?'#dc2626':s<=re?'#d97706':'inherit'}">${s}</td>
        <td style="text-align:right;color:var(--tx2)">${re}</td>
        <td><span class="badge ${cls}">${lbl}</span></td>
      </tr>`;
    }).join('');
  },250);
}
load();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>