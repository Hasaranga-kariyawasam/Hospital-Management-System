<?php
/**
 * pharmacist_portal.php
 * Tab 1: View & dispense doctor prescriptions
 * Tab 2: Walk-in patient — search drug, cart, confirm sale
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Pharmacist Portal'; $useSidebar = true; $isPublic = false; $pageCss = '';

/* ── AJAX ──────────────────────────────────────────────────────────────── */
$act = trim($_GET['action'] ?? $_POST['action'] ?? '');
if ($act) {
    header('Content-Type: application/json');

    /* List all prescriptions */
    if ($act === 'list') {
        $rows = db()->query("
            SELECT pr.prescription_id,pr.dosage,pr.frequency,pr.duration_days,pr.status,
                   a.ref_number,a.appt_date,
                   u.full_name patient_name,p.patient_id,
                   d.drug_name,d.category,d.unit,d.unit_price
            FROM prescriptions pr
            JOIN appointments a   ON a.appointment_id=pr.appointment_id
            JOIN patients p       ON p.patient_id=a.patient_id
            JOIN users u          ON u.user_id=p.user_id
            JOIN pharmacy_drugs d ON d.drug_id=pr.drug_id
            ORDER BY pr.status ASC,a.appt_date DESC LIMIT 200
        ")->fetchAll();
        echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }

    /* Mark dispensed */
    if ($act === 'dispense') {
        $id = trim($_POST['id']??'');
        db()->prepare("UPDATE prescriptions SET status='dispensed',dispensed_by=?,dispensed_at=NOW() WHERE prescription_id=?")
            ->execute([$_SESSION['user_id']??null,$id]);
        echo json_encode(['ok'=>true]); exit;
    }

    /* Search drugs (walk-in) */
    if ($act === 'drugs') {
        $like='%'.trim($_GET['q']??'').'%';
        $s=db()->prepare("SELECT drug_id,drug_name name,category,unit,unit_price,stock_qty stock
            FROM pharmacy_drugs WHERE is_active=1 AND stock_qty>0 AND (drug_name LIKE ? OR category LIKE ?) ORDER BY drug_name LIMIT 40");
        $s->execute([$like,$like]);
        echo json_encode(['ok'=>true,'list'=>$s->fetchAll()]); exit;
    }

    /* Confirm walk-in sale */
    if ($act === 'sale') {
        $items = json_decode(file_get_contents('php://input'),true)['items']??[];
        if (!$items) { echo json_encode(['ok'=>false,'msg'=>'Cart empty']); exit; }
        $ref='SALE-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-5));
        $total=array_sum(array_map(fn($i)=>$i['price']*$i['qty'],$items));
        try {
            $pdo=db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS pharmacy_sales(id INT AUTO_INCREMENT PRIMARY KEY,sale_ref VARCHAR(30),cashier VARCHAR(120),sale_date DATE,total_amount DECIMAL(10,2),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS pharmacy_sale_items(id INT AUTO_INCREMENT PRIMARY KEY,sale_id INT,drug_id VARCHAR(10),drug_name VARCHAR(120),unit_price DECIMAL(8,2),quantity INT,line_total DECIMAL(10,2),FOREIGN KEY(sale_id)REFERENCES pharmacy_sales(id))ENGINE=InnoDB");
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO pharmacy_sales(sale_ref,cashier,sale_date,total_amount)VALUES(?,?,CURDATE(),?)")
                ->execute([$ref,$_SESSION['full_name']??'Counter',$total]);
            $sid=$pdo->lastInsertId();
            foreach($items as $i){
                $pdo->prepare("INSERT INTO pharmacy_sale_items(sale_id,drug_id,drug_name,unit_price,quantity,line_total)VALUES(?,?,?,?,?,?)")
                    ->execute([$sid,$i['drug_id'],$i['name'],$i['price'],$i['qty'],$i['price']*$i['qty']]);
                $pdo->prepare("UPDATE pharmacy_drugs SET stock_qty=stock_qty-? WHERE drug_id=?")->execute([$i['qty'],$i['drug_id']]);
            }
            $pdo->commit();
            echo json_encode(['ok'=>true,'ref'=>$ref,'total'=>$total]);
        } catch(Throwable $e){ if(db()->inTransaction())db()->rollBack(); echo json_encode(['ok'=>false,'msg'=>'DB error']); }
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

<!-- Tabs -->
<div class="tabs">
  <button class="tab active" onclick="tab('rx',this)"><i class="ti ti-prescription"></i> Prescriptions</button>
  <button class="tab" onclick="tab('wi',this)"><i class="ti ti-shopping-cart"></i> Walk-in</button>
</div>

<!-- ── TAB 1: PRESCRIPTIONS ── -->
<div id="tab-rx">
  <div class="card">
    <div class="row-g" style="margin-bottom:12px">
      <input id="rxQ" placeholder="Search patient, drug, ref…" oninput="renderRx()" style="flex:1;max-width:260px">
      <button class="pill active" id="f-all"     onclick="filt('all',this)">All</button>
      <button class="pill"        id="f-pending"  onclick="filt('pending',this)">Pending</button>
      <button class="pill"        id="f-dispensed"onclick="filt('dispensed',this)">Dispensed</button>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="loadRx()"><i class="ti ti-refresh"></i></button>
    </div>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr>
          <th>Ref</th><th>Patient</th><th>Drug</th>
          <th>Dose (M-A-E-N)</th><th>Days</th><th>Instruction</th>
          <th>Qty</th><th>Amount</th><th>Status</th><th></th>
        </tr></thead>
        <tbody id="rxTb"><tr><td colspan="10" class="empty">Loading…</td></tr></tbody>
      </table>
    </div>
    <div id="rxTotal" style="display:none;text-align:right;padding:10px 4px 0;border-top:1px solid var(--bdr)">
      Pending total: <strong id="rxTotalVal" style="color:var(--p);font-size:15px"></strong>
    </div>
  </div>
  <div class="alert" id="rxMsg"></div>
</div>

<!-- ── TAB 2: WALK-IN ── -->
<div id="tab-wi" style="display:none">
  <div class="card">
    <div class="card-hd"><i class="ti ti-search"></i> Search Drug</div>
    <div class="search-wrap">
      <input id="wiQ" placeholder="Drug name or category…" oninput="wiSearch(this.value)" autocomplete="off">
      <div class="drop" id="wiDrop"></div>
    </div>
  </div>
  <div class="card">
    <div class="card-hd"><i class="ti ti-shopping-cart"></i> Cart</div>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>Drug</th><th>Category</th><th>Unit</th><th style="width:80px;text-align:center">Qty</th><th>Unit Price</th><th>Total</th><th></th></tr></thead>
        <tbody id="cartTb"><tr id="cartEmpty"><td colspan="7" class="empty">Cart empty.</td></tr></tbody>
        <tfoot>
          <tr id="cartFoot" style="display:none">
            <td colspan="5" style="text-align:right;padding:10px 8px;color:var(--tx2)">Grand Total</td>
            <td colspan="2" style="padding:10px 8px;font-size:16px;font-weight:700;color:var(--p)" id="cartTotal"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div class="alert" id="wiMsg"></div>
  <div class="row-g" style="justify-content:flex-end">
    <button class="btn btn-ghost" onclick="clearCart()"><i class="ti ti-trash"></i> Clear</button>
    <button class="btn btn-blue"  onclick="confirmSale()"><i class="ti ti-check"></i> Confirm &amp; Pay</button>
  </div>
</div>

</div></main>

<script>
/* ── Tabs ── */
function tab(id,btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-rx').style.display='none';
  document.getElementById('tab-wi').style.display='none';
  btn.classList.add('active');
  document.getElementById('tab-'+id).style.display='block';
  if(id==='rx') loadRx();
}

/* ── Prescriptions ── */
let allRx=[],rxFilt='all';

async function loadRx(){
  document.getElementById('rxTb').innerHTML='<tr><td colspan="10" class="empty">Loading…</td></tr>';
  const d=await api('?action=list');
  allRx=d.rows||[];
  renderRx();
}

function filt(f,btn){rxFilt=f;document.querySelectorAll('.pill').forEach(p=>p.classList.remove('active'));btn.classList.add('active');renderRx();}

function renderRx(){
  const q=document.getElementById('rxQ').value.toLowerCase();
  const rows=allRx.filter(r=>{
    if(rxFilt!=='all'&&r.status!==rxFilt)return false;
    if(q&&![r.patient_name,r.drug_name,r.prescription_id,r.ref_number].join('|').toLowerCase().includes(q))return false;
    return true;
  });
  const tb=document.getElementById('rxTb');
  if(!rows.length){tb.innerHTML='<tr><td colspan="10" class="empty">No prescriptions.</td></tr>';document.getElementById('rxTotal').style.display='none';return;}
  let pend=0;
  tb.innerHTML=rows.map(r=>{
    const parts=(r.dosage||'0-0-0-0').split('-').map(Number);
    const qty=parts.reduce((a,b)=>a+b,0)*parseInt(r.duration_days||1);
    const amt=qty*parseFloat(r.unit_price||0);
    if(r.status==='pending')pend+=amt;
    return`<tr>
      <td><small style="color:var(--tx2)">${r.ref_number}</small></td>
      <td><b>${r.patient_name}</b><br><small class="muted">${r.patient_id}</small></td>
      <td><b>${r.drug_name}</b><br><small class="muted">${r.category}</small></td>
      <td style="font-family:monospace">${r.dosage}</td>
      <td>${r.duration_days}d</td>
      <td><small class="muted">${r.frequency||'—'}</small></td>
      <td><b>${qty}</b></td>
      <td><b style="color:var(--p)">LKR ${amt.toFixed(2)}</b></td>
      <td><span class="badge badge-${r.status}">${r.status==='pending'?'⏳ Pending':'✓ Done'}</span></td>
      <td>${r.status==='pending'?`<button class="btn btn-green btn-sm" onclick="dispense('${r.prescription_id}',this)"><i class="ti ti-check"></i> Done</button>`:'—'}</td>
    </tr>`;
  }).join('');
  document.getElementById('rxTotalVal').textContent='LKR '+pend.toFixed(2);
  document.getElementById('rxTotal').style.display='block';
}

async function dispense(id,btn){
  btn.disabled=true; btn.innerHTML='…';
  const fd=new FormData(); fd.append('action','dispense'); fd.append('id',id);
  const d=await api('?',{method:'POST',body:fd});
  if(d.ok){showAlert('rxMsg','✓ Dispensed.','ok');loadRx();}
  else{showAlert('rxMsg',d.msg||'Error');btn.disabled=false;btn.innerHTML='Done';}
}

/* ── Walk-in ── */
let cart=[],wTimer=null;

function wiSearch(q){
  clearTimeout(wTimer);
  if(q.length<1){document.getElementById('wiDrop').style.display='none';return;}
  wTimer=setTimeout(async()=>{
    const d=await api(`?action=drugs&q=${enc(q)}`);
    const drop=document.getElementById('wiDrop');
    if(!d.list?.length){drop.innerHTML='<div class="drop-item muted">No results</div>';drop.style.display='block';return;}
    drop.innerHTML=d.list.map(x=>`<div class="drop-item" onclick='addCart(${JSON.stringify(x)})'><b>${x.name}</b> <span class="muted">${x.category}</span><small>LKR ${parseFloat(x.unit_price).toFixed(2)} · Stock: ${x.stock}</small></div>`).join('');
    drop.style.display='block';
  },250);
}
document.addEventListener('click',e=>{if(!e.target.closest('.search-wrap'))document.getElementById('wiDrop').style.display='none';});

function addCart(x){
  document.getElementById('wiDrop').style.display='none'; document.getElementById('wiQ').value='';
  const ex=cart.find(i=>i.drug_id===x.drug_id);
  ex ? ex.qty++ : cart.push({drug_id:x.drug_id,name:x.name,category:x.category,unit:x.unit,price:x.unit_price,stock:x.stock,qty:1});
  renderCart();
}
function delCart(i){cart.splice(i,1);renderCart();}
function updQty(i,v){cart[i].qty=Math.max(1,Math.min(parseInt(v)||1,cart[i].stock));renderCart();}

function renderCart(){
  const tb=document.getElementById('cartTb');
  const ft=document.getElementById('cartFoot');
  if(!cart.length){tb.innerHTML='<tr id="cartEmpty"><td colspan="7" class="empty">Cart empty.</td></tr>';ft.style.display='none';return;}
  let grand=0;
  tb.innerHTML=cart.map((it,i)=>{const line=it.qty*parseFloat(it.price);grand+=line;return`<tr>
    <td><b>${it.name}</b></td><td class="muted">${it.category}</td><td class="muted">${it.unit}</td>
    <td style="text-align:center"><input class="tiny" type="number" min="1" max="${it.stock}" value="${it.qty}" onchange="updQty(${i},this.value)"></td>
    <td>LKR ${parseFloat(it.price).toFixed(2)}</td>
    <td><b>LKR ${line.toFixed(2)}</b></td>
    <td><button class="btn btn-red btn-sm" style="padding:0;width:28px;height:28px;justify-content:center" onclick="delCart(${i})"><i class="ti ti-x"></i></button></td>
  </tr>`;}).join('');
  document.getElementById('cartTotal').textContent='LKR '+grand.toFixed(2);
  ft.style.display='table-row';
}

function clearCart(){cart=[];renderCart();showAlert('wiMsg','');}

async function confirmSale(){
  if(!cart.length){showAlert('wiMsg','Cart is empty');return;}
  const total=cart.reduce((s,i)=>s+i.qty*parseFloat(i.price),0);
  if(!confirm(`Confirm sale — LKR ${total.toFixed(2)}?`))return;
  const d=await api('?action=sale',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({items:cart.map(i=>({drug_id:i.drug_id,name:i.name,price:i.price,qty:i.qty}))})});
  if(!d.ok){showAlert('wiMsg',d.msg||'Failed');return;}
  showAlert('wiMsg',`✓ Sale done! Ref: ${d.ref} — LKR ${parseFloat(d.total).toFixed(2)}`,'ok');
  clearCart();
}

/* helpers */
async function api(url,opts={}){try{const r=await fetch(url,opts);return await r.json();}catch{return{ok:false,msg:'Network error'};}}
function showAlert(id,msg,type='err'){const el=document.getElementById(id);el.textContent=msg;el.className='alert'+(msg?(type==='ok'?' alert-ok':' alert-err'):'');el.style.display=msg?'block':'none';}
function enc(s){return encodeURIComponent(s);}

loadRx();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>