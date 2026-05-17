<?php
/**
 * doctor_prescription.php
 * Doctor: search patient by appointment → add medicines → submit to pharmacy
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Issue Prescription'; $useSidebar = true; $isPublic = false; $pageCss = '';

/* ── AJAX ──────────────────────────────────────────────────────────────── */
$act = trim($_GET['action'] ?? $_POST['action'] ?? '');
if ($act) {
    header('Content-Type: application/json');

    if ($act === 'find_patient') {
        $ref = strtoupper(trim($_GET['ref'] ?? ''));
        if (!$ref) { echo json_encode(['ok'=>false,'msg'=>'Enter appointment number']); exit; }
        $r = db()->prepare("
            SELECT a.appointment_id,a.ref_number,a.appt_date,
                   u.full_name AS name,p.patient_id,p.nic,p.phone,p.gender,p.blood_type,
                   TIMESTAMPDIFF(YEAR,p.dob,CURDATE()) AS age
            FROM appointments a
            JOIN patients p ON p.patient_id=a.patient_id
            JOIN users    u ON u.user_id=p.user_id
            WHERE a.ref_number=? OR a.appointment_id=? LIMIT 1");
        $r->execute([$ref,$ref]);
        $row = $r->fetch();
        echo json_encode($row ? ['ok'=>true,'p'=>$row] : ['ok'=>false,'msg'=>'Not found']);
        exit;
    }

    if ($act === 'drugs') {
        $like = '%'.trim($_GET['q']??'').'%';
        $s = db()->prepare("SELECT drug_id id,drug_name name,category cat,unit,unit_price price,stock_qty stock
            FROM pharmacy_drugs WHERE is_active=1 AND (drug_name LIKE ? OR category LIKE ?) ORDER BY drug_name LIMIT 40");
        $s->execute([$like,$like]);
        echo json_encode(['ok'=>true,'list'=>$s->fetchAll()]);
        exit;
    }

    if ($act === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $d   = json_decode(file_get_contents('php://input'),true)??[];
        $ref = strtoupper(trim($d['ref']??'')); $diag = trim($d['diag']??''); $meds = $d['meds']??[];
        if (!$ref||!$diag||!$meds) { echo json_encode(['ok'=>false,'msg'=>'Fill all required fields']); exit; }
        try {
            $pdo = db();
            $appt = $pdo->prepare("SELECT a.appointment_id FROM appointments a WHERE a.ref_number=? OR a.appointment_id=? LIMIT 1");
            $appt->execute([$ref,$ref]); $a = $appt->fetch();
            if (!$a) { echo json_encode(['ok'=>false,'msg'=>'Appointment not found']); exit; }
            $pdo->beginTransaction();
            foreach ($meds as $m) {
                $tpd = array_sum([$m['M']??0,$m['A']??0,$m['E']??0,$m['N']??0]);
                $pdo->prepare("INSERT INTO prescriptions(prescription_id,appointment_id,drug_id,dosage,frequency,duration_days,status)
                    VALUES(?,?,?,?,?,?,'pending')")
                    ->execute(['RX-'.strtoupper(substr(md5(uniqid('',true)),0,8)),$a['appointment_id'],$m['id'],
                        "{$m['M']}-{$m['A']}-{$m['E']}-{$m['N']}", $m['instr']??'As directed', intval($m['days']??1)]);
                $pdo->prepare("UPDATE pharmacy_drugs SET stock_qty=stock_qty-? WHERE drug_id=?")
                    ->execute([$tpd*intval($m['days']??1),$m['id']]);
            }
            $pdo->prepare("UPDATE appointments SET status='completed' WHERE appointment_id=?")->execute([$a['appointment_id']]);
            $pdo->commit();
            echo json_encode(['ok'=>true,'ref'=>'RX-'.date('Ymd').'-'.strtoupper(substr(md5($ref),0,5))]);
        } catch(Throwable $e) {
            if(db()->inTransaction()) db()->rollBack();
            echo json_encode(['ok'=>false,'msg'=>'DB error. Try again.']);
        }
        exit;
    }
    exit;
}

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="pharmacy.css">
<main class="main-content"><div class="ph-wrap">

<div class="ph-topbar">
  <div>
    <div class="ph-title"><?= htmlspecialchars($_SESSION['full_name']??'Doctor') ?></div>
    <div class="ph-sub"><?= htmlspecialchars($_SESSION['department']??'General Medicine') ?> &middot; <?= htmlspecialchars($_SESSION['staff_id']??'') ?></div>
  </div>
  <div class="ph-date" id="dt"></div>
</div>

<!-- Patient Search -->
<div class="card">
  <div class="card-hd"><i class="ti ti-user-search"></i> Patient Lookup</div>
  <div class="row-g">
    <div class="fg"><label>Appointment Reference</label>
      <input id="refIn" placeholder="e.g. APT-001" onkeydown="if(event.key==='Enter')findPat()">
    </div>
    <button class="btn btn-blue" onclick="findPat()"><i class="ti ti-search"></i> Search</button>
  </div>
  <div class="alert" id="patErr"></div>
  <div id="patInfo" style="display:none;margin-top:12px">
    <div class="grid4">
      <div class="fg"><label>Name</label><input id="pN" readonly></div>
      <div class="fg"><label>Age</label><input id="pA" readonly></div>
      <div class="fg"><label>Gender</label><input id="pG" readonly></div>
      <div class="fg"><label>Blood</label><input id="pB" readonly></div>
      <div class="fg"><label>Patient ID</label><input id="pI" readonly></div>
      <div class="fg"><label>NIC</label><input id="pNic" readonly></div>
      <div class="fg"><label>Phone</label><input id="pPh" readonly></div>
      <div class="fg"><label>Appt Date</label><input id="pD" readonly></div>
    </div>
  </div>
</div>

<!-- Diagnosis -->
<div class="card">
  <div class="card-hd"><i class="ti ti-stethoscope"></i> Diagnosis</div>
  <div class="row-g">
    <div class="fg"><label>Diagnosis *</label><input id="diag" placeholder="e.g. Respiratory infection"></div>
    <div class="fg"><label>Notes</label><input id="notes" placeholder="Optional"></div>
  </div>
</div>

<!-- Medicines -->
<div class="card">
  <div class="card-hd"><i class="ti ti-pill"></i> Medicines</div>
  <div class="search-wrap" style="margin-bottom:12px">
    <input id="drugQ" placeholder="Search drug name or category…" oninput="searchDrug(this.value)" autocomplete="off">
    <div class="drop" id="drugDrop"></div>
  </div>
  <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr>
        <th>Medicine</th><th>M</th><th>A</th><th>E</th><th>N</th>
        <th>Days</th><th>Qty</th><th>Instruction</th><th></th>
      </tr></thead>
      <tbody id="medTb"><tr><td colspan="9" class="empty">No medicines added yet.</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Submit -->
<div class="card row-g" style="justify-content:flex-end">
  <div id="saveMsg" class="alert" style="flex:1"></div>
  <button class="btn btn-ghost" onclick="reset()"><i class="ti ti-refresh"></i> Reset</button>
  <button class="btn btn-blue" onclick="save()"><i class="ti ti-send"></i> Send to Pharmacy</button>
</div>

</div></main>

<script>
let meds=[],ref='',dTimer=null;

document.getElementById('dt').textContent=new Date().toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short',year:'numeric'});

/* Patient */
async function findPat(){
  const v=document.getElementById('refIn').value.trim().toUpperCase();
  if(!v){showAlert('patErr','Enter appointment reference');return;}
  showAlert('patErr','');
  const d=await api(`?action=find_patient&ref=${enc(v)}`);
  if(!d.ok){showAlert('patErr',d.msg);return;}
  const p=d.p;
  ref=p.ref_number||v;
  document.getElementById('pN').value=p.name||'—';
  document.getElementById('pA').value=(p.age??'—')+' yrs';
  document.getElementById('pG').value=p.gender||'—';
  document.getElementById('pB').value=p.blood_type||'—';
  document.getElementById('pI').value=p.patient_id||'—';
  document.getElementById('pNic').value=p.nic||'—';
  document.getElementById('pPh').value=p.phone||'—';
  document.getElementById('pD').value=p.appt_date||'—';
  document.getElementById('patInfo').style.display='block';
}

/* Drug search */
function searchDrug(q){
  clearTimeout(dTimer);
  if(q.length<1){closeDrop();return;}
  dTimer=setTimeout(async()=>{
    const d=await api(`?action=drugs&q=${enc(q)}`);
    const drop=document.getElementById('drugDrop');
    if(!d.ok||!d.list.length){drop.innerHTML='<div class="drop-item muted">No results</div>';drop.style.display='block';return;}
    drop.innerHTML=d.list.map(x=>`<div class="drop-item" onclick='addMed(${JSON.stringify(x)})'><b>${x.name}</b> <span class="muted">${x.cat}</span><small>LKR ${parseFloat(x.price).toFixed(2)} · Stock: ${x.stock}</small></div>`).join('');
    drop.style.display='block';
  },250);
}
function closeDrop(){document.getElementById('drugDrop').style.display='none';}
document.addEventListener('click',e=>{if(!e.target.closest('.search-wrap'))closeDrop();});

/* Med rows */
function addMed(x){
  closeDrop(); document.getElementById('drugQ').value='';
  if(meds.find(m=>m.id===x.id)){alert(x.name+' already added');return;}
  meds.push({id:x.id,name:x.name,cat:x.cat,unit:x.unit,M:0,A:0,E:0,N:0,days:5,instr:''});
  render();
}
function del(i){meds.splice(i,1);render();}
function upd(i,f,v){meds[i][f]=f==='instr'?v:(parseFloat(v)||0);if(f!=='instr')render();}

function render(){
  const tb=document.getElementById('medTb');
  if(!meds.length){tb.innerHTML='<tr><td colspan="9" class="empty">No medicines added yet.</td></tr>';return;}
  tb.innerHTML=meds.map((m,i)=>{
    const qty=(m.M+m.A+m.E+m.N)*m.days;
    return`<tr>
      <td><b>${m.name}</b><br><span class="muted">${m.cat} · ${m.unit}</span></td>
      ${['M','A','E','N'].map(f=>`<td><input class="tiny" type="number" min="0" step=".5" value="${m[f]}" onchange="upd(${i},'${f}',this.value)"></td>`).join('')}
      <td><input class="tiny" type="number" min="1" max="365" value="${m.days}" onchange="upd(${i},'days',this.value)"></td>
      <td><b>${qty}</b></td>
      <td><input style="width:120px;height:30px;font-size:12px" value="${m.instr}" placeholder="Before meal…" onchange="meds[${i}].instr=this.value"></td>
      <td><button class="btn btn-red btn-sm" onclick="del(${i})"><i class="ti ti-x"></i></button></td>
    </tr>`;
  }).join('');
}

/* Save */
async function save(){
  const ref2=document.getElementById('refIn').value.trim().toUpperCase();
  const diag=document.getElementById('diag').value.trim();
  showAlert('saveMsg','');
  if(!ref2||!ref){showAlert('saveMsg','Search a patient first.');return;}
  if(!diag){showAlert('saveMsg','Diagnosis is required.');return;}
  if(!meds.length){showAlert('saveMsg','Add at least one medicine.');return;}
  const d=await api('?action=save',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({ref:ref2,diag,notes:document.getElementById('notes').value,meds})});
  if(!d.ok){showAlert('saveMsg',d.msg);return;}
  showAlert('saveMsg','✓ Sent to pharmacy. Ref: '+d.ref,'ok');
  reset();
}

function reset(){
  document.getElementById('refIn').value='';
  document.getElementById('diag').value='';
  document.getElementById('notes').value='';
  document.getElementById('patInfo').style.display='none';
  meds=[];ref='';render();showAlert('saveMsg','');
}

/* Shared helpers */
async function api(url,opts={}){try{const r=await fetch(url,opts);return await r.json();}catch{return{ok:false,msg:'Network error'};}}
function showAlert(id,msg,type='err'){const el=document.getElementById(id);el.textContent=msg;el.className='alert'+(msg?(type==='ok'?' alert-ok':' alert-err'):'');el.style.display=msg?'block':'none';}
function enc(s){return encodeURIComponent(s);}
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>