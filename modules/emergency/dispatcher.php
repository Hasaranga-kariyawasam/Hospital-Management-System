<?php
/**
 * dispatcher.php — Dispatcher Portal
 * Auth: role = 'dispatcher'
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db_config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'dispatcher') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit;
}

$me = htmlspecialchars($_SESSION['full_name'] ?? 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $act = trim($_POST['action'] ?? '');

    if ($act === 'assign') {
        $eid = (int)($_POST['emergency_id'] ?? 0);
        $did = (int)($_POST['driver_id']    ?? 0);
        if (!$eid || !$did) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE emergency_requests SET status='dispatched',assigned_driver_id=?,dispatch_time=NOW() WHERE emergency_id=? AND status='pending'")->execute([$did,$eid]);
            $pdo->prepare("UPDATE drivers SET status='on_duty',assigned_emergency_id=? WHERE driver_id=? AND status='available'")->execute([$eid,$did]);
            $pdo->commit();
            echo json_encode(['ok'=>true]);
        } catch(Throwable $e) { $pdo->rollBack(); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    if ($act === 'cancel') {
        $eid = (int)($_POST['emergency_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE emergency_requests SET status='cancelled' WHERE emergency_id=? AND status='pending'")->execute([$eid]);
            echo json_encode(['ok'=>true]);
        } catch(Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

$pending    = $pdo->query("SELECT e.*,d.driver_id AS adr_id,u2.full_name AS driver_name FROM emergency_requests e LEFT JOIN drivers d ON d.driver_id=e.assigned_driver_id LEFT JOIN users u2 ON u2.user_id=d.user_id WHERE e.status='pending' ORDER BY e.submitted_at ASC")->fetchAll();
$active     = $pdo->query("SELECT e.*,u2.full_name AS driver_name,d.ambulance_number FROM emergency_requests e LEFT JOIN drivers d ON d.driver_id=e.assigned_driver_id LEFT JOIN users u2 ON u2.user_id=d.user_id WHERE e.status IN('dispatched','en_route','arrived') ORDER BY e.dispatch_time DESC")->fetchAll();
$drivers    = $pdo->query("SELECT d.driver_id,u.full_name,d.ambulance_number,d.ambulance_type,d.status FROM drivers d JOIN users u ON u.user_id=d.user_id WHERE d.status='available' ORDER BY u.full_name")->fetchAll();
$history    = $pdo->query("SELECT e.*,u2.full_name AS driver_name FROM emergency_requests e LEFT JOIN drivers d ON d.driver_id=e.assigned_driver_id LEFT JOIN users u2 ON u2.user_id=d.user_id WHERE e.status IN('resolved','cancelled') ORDER BY e.resolved_at DESC,e.submitted_at DESC LIMIT 20")->fetchAll();
$stats      = $pdo->query("SELECT SUM(status='pending') pending,SUM(status IN('dispatched','en_route','arrived')) active,SUM(status='resolved') resolved,SUM(status='cancelled') cancelled FROM emergency_requests")->fetch();
$allDrivers = $pdo->query("SELECT d.driver_id,u.full_name,d.ambulance_number,d.ambulance_type,d.status FROM drivers d JOIN users u ON u.user_id=d.user_id ORDER BY d.status,u.full_name")->fetchAll();

$typeMap = [
    'cardiac'  =>'<i class="ti ti-heart-rate-monitor"></i> Cardiac Arrest',
    'accident' =>'<i class="ti ti-car-crash"></i> Accident / Trauma',
    'breathing'=>'<i class="ti ti-lungs"></i> Breathing Difficulty',
    'stroke'   =>'<i class="ti ti-brain"></i> Stroke',
    'burn'     =>'<i class="ti ti-flame"></i> Severe Burns',
    'poisoning'=>'<i class="ti ti-pill"></i> Poisoning / Overdose',
    'fracture' =>'<i class="ti ti-bone"></i> Fracture',
    'other'    =>'<i class="ti ti-alert-triangle"></i> Other Critical',
];
$consMap = [
    'yes' =>'<i class="ti ti-circle-check" style="color:var(--grn)"></i> Conscious',
    'semi'=>'<i class="ti ti-alert-circle"  style="color:var(--org)"></i> Semi-Conscious',
    'no'  =>'<i class="ti ti-circle-x"      style="color:var(--red)"></i> Unconscious',
];
$asstMap = ['yes'=>'Someone helping','no'=>'Person alone','medical'=>'Medical professional'];

$pageTitle='Dispatcher Console'; $useSidebar=true; $isPublic=false;
include __DIR__.'/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
<style>
:root{--red:#dc2626;--grn:#16a34a;--blu:#1d4ed8;--org:#d97706;--bg:#f1f5f9;--bg2:#fff;--bdr:#e2e8f0;--tx:#0f172a;--tx2:#64748b}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--tx);font-size:14px}
.wrap{max-width:1100px;margin:0 auto;padding:24px 16px 60px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.topbar h1{font-size:22px;font-weight:700;display:flex;align-items:center;gap:8px}
.topbar small{color:var(--tx2);font-size:13px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat{background:var(--bg2);border:1px solid var(--bdr);border-radius:10px;padding:14px 16px;text-align:center}
.stat-n{font-size:26px;font-weight:700}
.stat-l{font-size:11px;color:var(--tx2);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.section{background:var(--bg2);border:1px solid var(--bdr);border-radius:10px;margin-bottom:18px;overflow:hidden}
.section-hd{padding:14px 18px;border-bottom:1px solid var(--bdr);font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;background:var(--bg)}
.req{border:1px solid var(--bdr);border-radius:10px;padding:16px;margin:12px 16px;transition:box-shadow .2s}
.req:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.req.critical{border-left:4px solid var(--red);background:#fff8f8}
.req-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.req-type{font-size:15px;font-weight:700;color:var(--tx);display:flex;align-items:center;gap:6px}
.req-time{font-size:11px;color:var(--tx2)}
.req-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px}
.req-field small{font-size:10px;color:var(--tx2);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px}
.req-field b{font-size:13px;color:var(--tx);display:flex;align-items:center;gap:4px}
.assign-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
select{font-family:inherit;font-size:13px;padding:6px 10px;border:1px solid var(--bdr);border-radius:7px;outline:none;background:#fff;color:var(--tx)}
select:focus{border-color:#93c5fd}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:7px;font-size:13px;font-weight:600;border:none;cursor:pointer;font-family:inherit;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-red{background:var(--red);color:#fff}
.btn-grn{background:var(--grn);color:#fff}
.btn-blu{background:var(--blu);color:#fff}
.btn-ghost{background:var(--bg);color:var(--tx2);border:1px solid var(--bdr)}
.drv-table,.hist-table{width:100%;border-collapse:collapse;font-size:13px}
.drv-table th,.hist-table th{background:var(--bg);font-size:10px;font-weight:600;color:var(--tx2);text-transform:uppercase;letter-spacing:.4px;padding:9px 16px;border-bottom:1px solid var(--bdr);text-align:left}
.drv-table td,.hist-table td{padding:10px 16px;border-bottom:1px solid var(--bdr)}
.drv-table tr:last-child td,.hist-table tr:last-child td{border-bottom:none}
.badge{display:inline-flex;align-items:center;gap:3px;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600}
.badge-available{background:#f0fdf4;color:var(--grn);border:1px solid #86efac}
.badge-on_duty{background:#fffbeb;color:var(--org);border:1px solid #fcd34d}
.badge-off_duty,.badge-on_leave{background:#f8fafc;color:var(--tx2);border:1px solid var(--bdr)}
.badge-pending{background:#fff7ed;color:var(--org);border:1px solid #fed7aa}
.badge-dispatched,.badge-en_route,.badge-arrived{background:#eff6ff;color:var(--blu);border:1px solid #bfdbfe}
.badge-resolved{background:#f0fdf4;color:var(--grn);border:1px solid #86efac}
.badge-cancelled{background:#fef2f2;color:var(--red);border:1px solid #fca5a5}
.active-card{border-left:4px solid var(--blu)}
.crit-warn{background:#fef2f2;border:1px solid #fca5a5;border-radius:7px;padding:7px 12px;font-size:12px;font-weight:600;color:var(--red);display:flex;align-items:center;gap:6px;margin-bottom:10px}
.no-drivers{background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;padding:7px 12px;font-size:12px;font-weight:600;color:var(--org);display:flex;align-items:center;gap:6px}
.toast{position:fixed;bottom:24px;right:24px;background:#0f172a;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.3);display:none;z-index:999}
.empty{padding:28px;text-align:center;color:var(--tx2);font-style:italic}
@media(max-width:640px){.stats{grid-template-columns:1fr 1fr}.req-grid{grid-template-columns:1fr}}
</style>

<main class="main-content"><div class="wrap">

<div class="topbar">
  <div>
    <h1><i class="ti ti-tower-broadcast"></i> Dispatcher Console</h1>
    <small>Welcome, <?= $me ?> &nbsp;&middot;&nbsp; <?= date('d M Y, H:i') ?></small>
  </div>
  <button class="btn btn-ghost" onclick="location.reload()"><i class="ti ti-refresh"></i> Refresh</button>
</div>

<div class="stats">
  <div class="stat"><div class="stat-n" style="color:var(--org)"><?= intval($stats['pending'])  ?></div><div class="stat-l">Pending</div></div>
  <div class="stat"><div class="stat-n" style="color:var(--blu)"><?= intval($stats['active'])   ?></div><div class="stat-l">Active</div></div>
  <div class="stat"><div class="stat-n" style="color:var(--grn)"><?= intval($stats['resolved']) ?></div><div class="stat-l">Resolved</div></div>
  <div class="stat"><div class="stat-n" style="color:var(--tx2)"><?= count($drivers) ?></div><div class="stat-l">Available Drivers</div></div>
</div>

<!-- PENDING -->
<div class="section">
  <div class="section-hd">
    <i class="ti ti-alert-circle" style="color:var(--org)"></i> Pending Requests
    <span class="badge badge-pending" style="margin-left:auto"><?= count($pending) ?></span>
  </div>
  <?php if (!$pending): ?>
  <div class="empty">No pending emergency requests.</div>
  <?php else: foreach($pending as $r): $crit=$r['is_conscious']==='no'; ?>
  <div class="req <?= $crit?'critical':'' ?>">
    <?php if ($crit): ?><div class="crit-warn"><i class="ti ti-alert-triangle"></i> CRITICAL — Patient is UNCONSCIOUS</div><?php endif; ?>
    <div class="req-top">
      <div class="req-type"><?= $typeMap[$r['emergency_type']] ?? htmlspecialchars($r['emergency_type']) ?></div>
      <div class="req-time"><?= date('d M Y, H:i',strtotime($r['submitted_at'])) ?> &nbsp;&middot;&nbsp; <strong><?= round((time()-strtotime($r['submitted_at']))/60) ?> min ago</strong></div>
    </div>
    <div class="req-grid">
      <div class="req-field"><small>Patient</small><b><i class="ti ti-user"></i><?= htmlspecialchars($r['patient_name']?:'—') ?></b></div>
      <div class="req-field"><small>Contact</small><b><i class="ti ti-phone"></i><a href="tel:<?= $r['contact_number'] ?>"><?= htmlspecialchars($r['contact_number']) ?></a></b></div>
      <div class="req-field"><small>Consciousness</small><b><?= $consMap[$r['is_conscious']] ?? '—' ?></b></div>
      <div class="req-field" style="grid-column:1/-1"><small>Address</small><b><i class="ti ti-map-pin"></i><?= htmlspecialchars($r['patient_address']?:'—') ?></b></div>
    </div>
    <div class="assign-row">
      <?php if ($drivers): ?>
      <select id="drv_<?= $r['emergency_id'] ?>">
        <option value="">— Select Driver —</option>
        <?php foreach($drivers as $d): ?>
        <option value="<?= $d['driver_id'] ?>"><?= htmlspecialchars($d['full_name']) ?> — <?= htmlspecialchars($d['ambulance_number']??'N/A') ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-grn" onclick="assign(<?= $r['emergency_id'] ?>)"><i class="ti ti-send"></i> Dispatch</button>
      <?php else: ?>
      <span class="no-drivers"><i class="ti ti-alert-triangle"></i> No available drivers</span>
      <?php endif; ?>
      <button class="btn btn-ghost" onclick="cancel(<?= $r['emergency_id'] ?>)"><i class="ti ti-x"></i> Cancel</button>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<!-- ACTIVE -->
<div class="section">
  <div class="section-hd">
    <i class="ti ti-ambulance" style="color:var(--blu)"></i> Active Responses
    <span class="badge badge-dispatched" style="margin-left:auto"><?= count($active) ?></span>
  </div>
  <?php if (!$active): ?>
  <div class="empty">No active responses.</div>
  <?php else: foreach($active as $r): ?>
  <div class="req active-card">
    <div class="req-top">
      <div class="req-type"><?= $typeMap[$r['emergency_type']] ?? htmlspecialchars($r['emergency_type']) ?></div>
      <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
    </div>
    <div class="req-grid">
      <div class="req-field"><small>Patient</small><b><i class="ti ti-user"></i><?= htmlspecialchars($r['patient_name']??'—') ?></b></div>
      <div class="req-field"><small>Driver</small><b><i class="ti ti-ambulance"></i><?= htmlspecialchars($r['driver_name']??'—') ?> (<?= htmlspecialchars($r['ambulance_number']??'N/A') ?>)</b></div>
      <div class="req-field"><small>Dispatched</small><b><i class="ti ti-clock"></i><?= $r['dispatch_time']?date('H:i',strtotime($r['dispatch_time'])):'—' ?></b></div>
      <div class="req-field" style="grid-column:1/-1"><small>Address</small><b><i class="ti ti-map-pin"></i><?= htmlspecialchars($r['patient_address']??'—') ?></b></div>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<!-- DRIVERS -->
<div class="section">
  <div class="section-hd"><i class="ti ti-users"></i> All Drivers</div>
  <table class="drv-table">
    <thead><tr><th>Name</th><th>Ambulance</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($allDrivers as $d): ?>
    <tr>
      <td><b><?= htmlspecialchars($d['full_name']) ?></b></td>
      <td><?= htmlspecialchars($d['ambulance_number']??'—') ?></td>
      <td style="color:var(--tx2);font-size:12px;text-transform:capitalize"><?= htmlspecialchars($d['ambulance_type']??'—') ?></td>
      <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst(str_replace('_',' ',$d['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- HISTORY -->
<?php if ($history): ?>
<div class="section">
  <div class="section-hd"><i class="ti ti-history"></i> Recent History</div>
  <div style="overflow-x:auto">
    <table class="hist-table">
      <thead><tr><th>Time</th><th>Emergency</th><th>Patient</th><th>Address</th><th>Driver</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($history as $h): ?>
      <tr>
        <td style="white-space:nowrap;color:var(--tx2);font-size:12px"><?= date('d M, H:i',strtotime($h['submitted_at'])) ?></td>
        <td><?= $typeMap[$h['emergency_type']] ?? htmlspecialchars($h['emergency_type']) ?></td>
        <td><?= htmlspecialchars($h['patient_name']??'—') ?></td>
        <td style="font-size:12px;color:var(--tx2)"><?= htmlspecialchars(substr($h['patient_address']??'—',0,35)) ?></td>
        <td><?= htmlspecialchars($h['driver_name']??'—') ?></td>
        <td><span class="badge badge-<?= $h['status'] ?>"><?= ucfirst($h['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div></main>
<div class="toast" id="toast"></div>
<script>
async function assign(eid){
  const sel=document.getElementById('drv_'+eid),did=sel?.value;
  if(!did){toast('Select a driver first','#dc2626');return;}
  const fd=new FormData();fd.append('action','assign');fd.append('emergency_id',eid);fd.append('driver_id',did);
  const r=await post(fd);
  if(r.ok){toast('Driver dispatched successfully','#16a34a');setTimeout(()=>location.reload(),1200);}
  else toast('Error: '+(r.msg||'Failed'),'#dc2626');
}
async function cancel(eid){
  if(!confirm('Cancel this emergency request?'))return;
  const fd=new FormData();fd.append('action','cancel');fd.append('emergency_id',eid);
  const r=await post(fd);
  if(r.ok){toast('Request cancelled','#64748b');setTimeout(()=>location.reload(),1000);}
  else toast('Error: '+(r.msg||'Failed'),'#dc2626');
}
async function post(fd){try{const r=await fetch('dispatcher.php',{method:'POST',body:fd});return await r.json();}catch{return{ok:false,msg:'Network error'};}}
function toast(msg,bg='#0f172a'){const t=document.getElementById('toast');t.textContent=msg;t.style.background=bg;t.style.display='block';setTimeout(()=>t.style.display='none',3500);}
setTimeout(()=>location.reload(),30000);
</script>
<?php include __DIR__.'/../../includes/footer.php'; ?>