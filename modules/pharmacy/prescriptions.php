<?php
/**
 * modules/pharmacy/prescription.php
 * MediCare HMS — Doctor: Issue Prescription
 * Group 05 | ICT1242 Web Development Practicum
 *
 * Requires: includes/db.php, includes/header.php,
 *           includes/sidebar.php, includes/footer.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

// ── Session / auth ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Uncomment to enforce doctor login:
// if (($_SESSION['role'] ?? '') !== 'doctor') {
//     header('Location: /Web/Hospital-Management-System/login.php');
//     exit;
// }

// ── Doctor info from session (or DB fallback) ─────────────────────────────
$doctorName = htmlspecialchars($_SESSION['full_name'] ?? 'Dr. Saman Perera');
$doctorReg  = htmlspecialchars($_SESSION['staff_id']  ?? 'REG-DOC-0042');
$doctorDept = htmlspecialchars($_SESSION['department'] ?? 'General Medicine');
$initials   = implode('', array_map(
    fn($w) => $w[0],
    array_slice(explode(' ', strip_tags($doctorName)), -2)
));

// ── Load drug catalogue from DB ───────────────────────────────────────────
$drugs = [];
try {
    $stmt = db()->query("
        SELECT drug_id AS id, drug_name AS name, category AS cat,
               unit, unit_price, stock_qty AS stock, reorder_level AS reorder
        FROM pharmacy_drugs
        WHERE is_active = 1
        ORDER BY category, drug_name
    ");
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Drug load failed: ' . $e->getMessage());
}

// ── Page config for header.php ────────────────────────────────────────────
$pageTitle  = 'Issue Prescription';
$pageCss    = '/Web/Hospital-Management-System/modules/pharmacy/prescription.css';
$useSidebar = true;
$isPublic   = false;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- MAIN CONTENT (fits inside existing main container from header/sidebar) -->
<div class="rx-page-wrap">

    <!-- Breadcrumb -->
    <nav class="rx-breadcrumb" aria-label="Breadcrumb">
        <a href="/Web/Hospital-Management-System/modules/admin/dashboard.php">Dashboard</a>
        <span class="sep">›</span>
        <a href="/Web/Hospital-Management-System/modules/appointments/appointments.php">Appointments</a>
        <span class="sep">›</span>
        <span>Issue Prescription</span>
    </nav>

    <h1 class="sr-only">Doctor Prescription Form</h1>

    <!-- Doctor info bar -->
    <div class="doc-bar">
        <div class="doc-info">
            <div class="doc-avatar"><?php echo strtoupper($initials); ?></div>
            <div>
                <div class="doc-name"><?php echo $doctorName; ?></div>
                <div class="doc-dept"><?php echo $doctorDept; ?> · <?php echo $doctorReg; ?></div>
            </div>
        </div>
        <div class="doc-meta">
            <i class="ti ti-calendar" aria-hidden="true"></i>
            <span id="todayDate"></span>
            <span class="session-badge">Session active</span>
        </div>
    </div>

    <!-- Patient lookup -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-user-search" aria-hidden="true"></i>
            Patient Lookup
        </div>

        <div class="appt-row">
            <div class="field-group">
                <label for="apptNo">Appointment reference number</label>
                <input type="text" id="apptNo" placeholder="e.g. APT-2026-0089"
                       oninput="onApptInput()" onkeydown="if(event.key==='Enter')lookupPatient()">
            </div>
            <button class="btn btn-outline btn-sm" onclick="lookupPatient()">
                <i class="ti ti-search" aria-hidden="true"></i> Lookup
            </button>
        </div>

        <div id="errAppt" class="err-msg" role="alert"></div>

        <div id="patientFields" style="display:none;margin-top:12px">
            <div class="field-grid fg-4" id="patientGrid">
                <div class="field-group">
                    <label>Patient name</label>
                    <input type="text" id="patientName" readonly>
                </div>
                <div class="field-group">
                    <label>Age</label>
                    <input type="text" id="patientAge" readonly>
                </div>
                <div class="field-group">
                    <label>Patient ID</label>
                    <input type="text" id="patientId" readonly>
                </div>
                <div class="field-group">
                    <label>NIC</label>
                    <input type="text" id="patientNic" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagnosis & notes -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-stethoscope" aria-hidden="true"></i>
            Diagnosis &amp; Notes
        </div>
        <div class="field-grid fg-2">
            <div class="field-group">
                <label for="diagnosisSel">Diagnosis</label>
                <select id="diagnosisSel">
                    <option value="">— select diagnosis —</option>
                    <optgroup label="Infectious diseases">
                        <option>Upper respiratory tract infection</option>
                        <option>Lower respiratory tract infection</option>
                        <option>Urinary tract infection</option>
                        <option>Gastroenteritis</option>
                        <option>Dengue fever</option>
                        <option>Typhoid fever</option>
                        <option>Malaria</option>
                    </optgroup>
                    <optgroup label="Chronic / non-communicable">
                        <option>Type 2 diabetes mellitus</option>
                        <option>Type 1 diabetes mellitus</option>
                        <option>Essential hypertension</option>
                        <option>Ischemic heart disease</option>
                        <option>Bronchial asthma</option>
                        <option>COPD</option>
                        <option>Hypothyroidism</option>
                        <option>Hyperthyroidism</option>
                        <option>Dyslipidaemia</option>
                        <option>Chronic kidney disease</option>
                    </optgroup>
                    <optgroup label="Gastrointestinal">
                        <option>Peptic ulcer disease</option>
                        <option>GERD / acid reflux</option>
                        <option>Irritable bowel syndrome</option>
                        <option>Constipation</option>
                    </optgroup>
                    <optgroup label="Musculoskeletal">
                        <option>Osteoarthritis</option>
                        <option>Rheumatoid arthritis</option>
                        <option>Low back pain</option>
                        <option>Gout</option>
                    </optgroup>
                    <optgroup label="Neurological / mental health">
                        <option>Migraine</option>
                        <option>Epilepsy</option>
                        <option>Anxiety disorder</option>
                        <option>Depression</option>
                        <option>Insomnia</option>
                    </optgroup>
                    <optgroup label="Skin">
                        <option>Eczema / atopic dermatitis</option>
                        <option>Fungal skin infection</option>
                        <option>Allergic reaction</option>
                        <option>Scabies</option>
                    </optgroup>
                    <optgroup label="Eye / ENT">
                        <option>Conjunctivitis</option>
                        <option>Acute otitis media</option>
                        <option>Allergic rhinitis</option>
                        <option>Tonsillitis</option>
                    </optgroup>
                    <optgroup label="Other">
                        <option>Anaemia</option>
                        <option>Vitamin D deficiency</option>
                        <option>Nutritional deficiency</option>
                        <option>Post-operative care</option>
                        <option>Other / unspecified</option>
                    </optgroup>
                </select>
            </div>
            <div class="field-group">
                <label for="clinicalNotes">Clinical notes</label>
                <input type="text" id="clinicalNotes" placeholder="Brief note (optional)">
            </div>
        </div>
    </div>

    <!-- Add medicine -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-pill" aria-hidden="true"></i>
            Add Medicine
        </div>
        <?php if (empty($drugs)): ?>
            <div class="err-msg" style="margin-bottom:10px">
                ⚠️ Could not load drug catalogue from database. Check DB connection.
            </div>
        <?php endif; ?>
        <div class="search-wrap">
            <input type="text" id="drugQ"
                   placeholder="Search drug name or category…"
                   oninput="searchDrugs()"
                   onkeydown="if(event.key==='Escape')closeDrugList()">
            <button class="btn btn-outline btn-sm" onclick="searchDrugs()">
                <i class="ti ti-search" aria-hidden="true"></i> Search
            </button>
        </div>
        <div class="drug-list" id="drugList" role="listbox"></div>
    </div>

    <!-- Prescribed medicines table -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-clipboard-list" aria-hidden="true"></i>
            Prescribed Medicines
        </div>

        <div class="rx-wrap">
            <table class="rx-tbl" id="rxTbl">
                <thead>
                    <tr>
                        <th style="width:145px">Drug name</th>
                        <th style="width:65px">Unit</th>
                        <th style="width:58px">Status</th>
                        <th style="width:195px">Dosage — Morning / Afternoon / Evening / Night</th>
                        <th style="width:48px">Days</th>
                        <th style="width:55px">Total</th>
                        <th style="width:115px">Instruction</th>
                        <th style="width:30px"></th>
                    </tr>
                </thead>
                <tbody id="rxBody">
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="ti ti-pill empty-icon" aria-hidden="true"></i>
                                No medicines added — search above and click a drug to add
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="foot-row">
            <div class="stat-pills">
                <div class="stat-pill">Medicines: <b id="cntMed">0</b></div>
                <div class="stat-pill">Total items: <b id="cntQty">0</b></div>
            </div>
            <div class="btn-row">
                <button class="btn btn-ghost" onclick="clearAll()">
                    <i class="ti ti-refresh" aria-hidden="true"></i> Clear
                </button>
                <button class="btn btn-primary" onclick="submitRx()" id="submitBtn">
                    <i class="ti ti-send" aria-hidden="true"></i> Submit to Pharmacy
                </button>
            </div>
        </div>

        <div id="errSubmit" class="err-msg" style="text-align:right;margin-top:8px" role="alert"></div>
    </div>

</div><!-- /.rx-page-wrap -->

<!-- Confirmation modal -->
<div id="confirmModal" class="modal-backdrop" style="display:none" role="dialog"
     aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-icon-wrap">
                <i class="ti ti-circle-check" style="font-size:32px;color:var(--color-success)"></i>
            </div>
            <h2 id="modalTitle">Prescription Submitted</h2>
            <p id="modalSub" class="modal-sub"></p>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="window.print()">
                <i class="ti ti-printer"></i> Print
            </button>
            <button class="btn btn-primary" onclick="closeModal()">
                <i class="ti ti-check"></i> Done
            </button>
        </div>
    </div>
</div>

<script>
// Drug data from PHP/DB
const DRUGS = <?php echo json_encode(array_values($drugs), JSON_UNESCAPED_UNICODE); ?>;

const INSTRUCTIONS = [
    '— select —','After meals','Before meals','With meals','On empty stomach',
    'At bedtime','In the morning','Every 8 hours','Every 12 hours',
    'With plenty of water','Avoid sunlight','Avoid alcohol',
    'Chew before swallowing','Do not crush or chew',
    'Apply to affected area','As directed',
];

const LOW = 20;
let rxItems = [];

// Utilities
function stockInfo(d) {
    const qty = parseInt(d.stock ?? d.stock_qty ?? 0);
    if (qty === 0) return {lbl:'Out of stock', cls:'badge-ot', ok:false};
    if (qty <= LOW)  return {lbl:'Low stock',   cls:'badge-lw', ok:true};
    return               {lbl:'Available',    cls:'badge-av', ok:true};
}
function tpd(r)  { return (r.M||0)+(r.A||0)+(r.E||0)+(r.N||0); }
function tqty(r) { return tpd(r) * (r.days||1); }
function instrOpts(sel) {
    return INSTRUCTIONS.map(o => `<option${o===sel?' selected':''}>${o}</option>`).join('');
}

// Set today's date
(function setDate() {
    const d = new Date();
    const el = document.getElementById('todayDate');
    if (el) el.textContent =
        d.toLocaleDateString('en-GB', {weekday:'short', day:'2-digit', month:'short', year:'numeric'});
})();

// Patient lookup
function onApptInput() {
    showErr('errAppt', null);
    const v = document.getElementById('apptNo').value.trim().toUpperCase();
    if (v.length >= 12) lookupPatient(v);
}

async function lookupPatient(val) {
    const v = val ?? document.getElementById('apptNo').value.trim().toUpperCase();
    if (!v) { showErr('errAppt', 'Enter an appointment number.'); return; }

    try {
        const res  = await fetch(`prescription_actions.php?action=lookup_patient&appt=${encodeURIComponent(v)}`);
        const data = await res.json();
        if (data.ok) {
            fillPatient(data.patient);
            showErr('errAppt', null);
        } else {
            hidePatient();
            showErr('errAppt', data.error ?? 'Appointment not found.');
        }
    } catch {
        showErr('errAppt', 'Network error — could not reach server.');
    }
}

function fillPatient(p) {
    document.getElementById('patientName').value = p.name        ?? p.full_name ?? '';
    document.getElementById('patientAge').value  = (p.age ?? '') + ' yrs';
    document.getElementById('patientId').value   = p.patient_id  ?? p.id ?? '';
    document.getElementById('patientNic').value  = p.nic         ?? '';
    const pf = document.getElementById('patientFields');
    pf.style.display = 'block';
    pf.querySelectorAll('.field-group').forEach(g => g.classList.add('patient-filled'));
}

function hidePatient() {
    document.getElementById('patientFields').style.display = 'none';
    ['patientName','patientAge','patientId','patientNic']
        .forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    document.getElementById('patientFields')
        .querySelectorAll('.field-group').forEach(g => g.classList.remove('patient-filled'));
}

// Drug search
function searchDrugs() {
    const q    = document.getElementById('drugQ').value.toLowerCase();
    const list = document.getElementById('drugList');
    if (q.length < 1) { list.style.display = 'none'; return; }
    const hits = DRUGS.filter(d =>
        d.name.toLowerCase().includes(q) || d.cat.toLowerCase().includes(q)
    ).slice(0, 9);
    if (!hits.length) { list.style.display = 'none'; return; }
    list.style.display = 'block';
    list.innerHTML = hits.map(d => {
        const s       = stockInfo(d);
        const already = rxItems.find(r => r.id === d.id);
        const dis     = (!s.ok || already) ? 'disabled' : '';
        const note    = already ? '<span style="font-size:10px;color:#854F0B;margin-left:4px">(added)</span>' : '';
        return `<div class="drug-item ${dis}" role="option" tabindex="0"
                     onclick="addDrug('${d.id}')"
                     onkeydown="if(event.key==='Enter')addDrug('${d.id}')">
            <div>
                <span class="d-name">${d.name}${note}</span><br>
                <span class="d-cat">${d.cat} · ${d.unit}</span>
            </div>
            <span class="badge ${s.cls}">${s.lbl}</span>
            <i class="ti ti-circle-plus" style="font-size:18px;color:var(--color-text-info)" aria-hidden="true"></i>
        </div>`;
    }).join('');
}

function closeDrugList() { document.getElementById('drugList').style.display = 'none'; }

document.addEventListener('click', e => {
    if (!e.target.closest('#drugList') && !e.target.closest('#drugQ')) closeDrugList();
});

function addDrug(id) {
    if (rxItems.find(r => r.id === id)) return;
    const d = DRUGS.find(x => x.id == id);
    if (!d || !stockInfo(d).ok) return;
    rxItems.push({...d, M:1, A:0, E:1, N:0, days:5, instr:''});
    renderRx();
    document.getElementById('drugQ').value = '';
    closeDrugList();
}

function removeDrug(id) {
    rxItems = rxItems.filter(r => r.id !== id);
    renderRx();
}

function upd(id, field, val) {
    const r = rxItems.find(x => x.id == id);
    if (!r) return;
    const numFields = ['M','A','E','N','days'];
    r[field] = numFields.includes(field) ? (parseFloat(val) || 0) : val;
    if (field === 'days') r[field] = Math.max(1, parseInt(val) || 1);
    renderRx();
}

// Render prescription table (using slot-row/slot-cell from prescription.css)
function renderRx() {
    const body = document.getElementById('rxBody');
    if (!rxItems.length) {
        body.innerHTML = `</table><td colspan="8">
            <div class="empty-state">
                <i class="ti ti-pill empty-icon" aria-hidden="true"></i>
                No medicines added — search above and click a drug to add
            </div>
        </td></tr>`;
        updateStats();
        return;
    }
    body.innerHTML = rxItems.map((r, i) => {
        const s     = stockInfo(r);
        const rowBg = i % 2 !== 0 ? 'background:var(--color-background-secondary)' : '';
        return `<tr style="${rowBg}">
            <td><span style="font-size:12px;font-weight:600;color:var(--color-text-primary);line-height:1.4">${r.name}</span></td>
            <td><span style="font-size:11px;color:var(--color-text-secondary)">${r.unit}</span></td>
            <td><span class="badge ${s.cls}">${s.lbl}</span></td>
            <td>
                <div class="slot-row">
                    <div class="slot-cell">
                        <span>M</span>
                        <input type="number" value="${r.M}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','M',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>A</span>
                        <input type="number" value="${r.A}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','A',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>E</span>
                        <input type="number" value="${r.E}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','E',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>N</span>
                        <input type="number" value="${r.N}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','N',this.value)">
                    </div>
                </div>
                <div class="slot-summary">Daily: ${tpd(r)} unit(s)</div>
            </td>
            <td>
                <input type="number" class="days-input" value="${r.days}" min="1" max="365"
                       onchange="upd('${r.id}','days',this.value)">
            </td>
            <td style="font-size:12px;font-weight:600;color:var(--color-text-primary);text-align:center">
                ${tqty(r)}
            </td>
            <td>
                <select class="instr-sel" onchange="upd('${r.id}','instr',this.value)">
                    ${instrOpts(r.instr)}
                </select>
            </td>
            <td>
                <button class="del-btn" onclick="removeDrug('${r.id}')" aria-label="Remove ${r.name}">
                    <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
    updateStats();
}

function updateStats() {
    document.getElementById('cntMed').textContent = rxItems.length;
    document.getElementById('cntQty').textContent = rxItems.reduce((s, r) => s + tqty(r), 0);
}

function clearAll() {
    rxItems = [];
    renderRx();
    document.getElementById('apptNo').value       = '';
    document.getElementById('diagnosisSel').value = '';
    document.getElementById('clinicalNotes').value = '';
    hidePatient();
    showErr('errAppt', null);
    showErr('errSubmit', null);
}

function showErr(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg ?? '';
    el.style.display = msg ? 'block' : 'none';
}

// Submit prescription
async function submitRx() {
    showErr('errSubmit', null);

    const appt      = document.getElementById('apptNo').value.trim().toUpperCase();
    const diagnosis = document.getElementById('diagnosisSel').value;
    const notes     = document.getElementById('clinicalNotes').value.trim();

    if (!appt)      { showErr('errSubmit', 'Please look up a patient first.'); return; }
    if (!diagnosis) { showErr('errSubmit', 'Please select a diagnosis.'); return; }
    if (!rxItems.length) { showErr('errSubmit', 'Add at least one medicine.'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 ti-spin" aria-hidden="true"></i> Submitting…';

    const payload = {
        appt, diagnosis, notes,
        medicines: rxItems.map(r => ({
            id:    r.id,
            name:  r.name,
            M:     r.M, A: r.A, E: r.E, N: r.N,
            days:  r.days,
            instr: r.instr,
        })),
    };

    try {
        const res  = await fetch('prescription_actions.php?action=submit_rx', {
            method:  'POST',
            headers: {'Content-Type':'application/json'},
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.ok) {
            showModal(data);
        } else {
            const msgs = data.errors ?? [data.error ?? 'Submission failed.'];
            showErr('errSubmit', msgs.join(' · '));
        }
    } catch {
        showErr('errSubmit', 'Network error — could not reach server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-send" aria-hidden="true"></i> Submit to Pharmacy';
    }
}

// Confirmation modal
function showModal(d) {
    document.getElementById('modalSub').textContent  = `${d.rx_ref} · ${d.issued_at}`;
    document.getElementById('modalBody').innerHTML = `
        <div class="modal-grid">
            <div class="modal-info-block">
                <div class="mib-label">Patient</div>
                <div class="mib-val">${d.patient.name} · ${d.patient.id ?? d.patient.patient_id}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Doctor</div>
                <div class="mib-val">${d.doctor.name}</div>
            </div>
            <div class="modal-info-block" style="grid-column:1/-1">
                <div class="mib-label">Diagnosis</div>
                <div class="mib-val">${d.diagnosis}</div>
            </div>
        </div>
        <table class="modal-rx-tbl">
            <thead>
                <tr><th>Drug</th><th>Dosage (M-A-E-N)</th><th>Days</th><th>Total</th><th>Instruction</th></tr>
            </thead>
            <tbody>
                ${d.medicines.map(m => `
                    <tr>
                        <td>${m.name}</td>
                        <td style="font-family:monospace">${m.dosage}</td>
                        <td>${m.days}</td>
                        <td><b>${m.total_qty}</b></td>
                        <td>${m.instruction || '—'}</td>
                    </tr>`).join('')}
            </tbody>
        </table>
        <p class="modal-msg"><i class="ti ti-circle-check"></i> ${d.message}</p>
    `;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    clearAll();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>