<?php
/**
 * modules/pharmacy/prescriptions.php
 * MediCare HMS — Doctor: Issue Prescription
 * Group 05 | ICT1242 Web Development Practicum
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Uncomment in production:
// if (($_SESSION['role'] ?? '') !== 'doctor') {
//     header('Location: /Web/Hospital-Management-System/login.php');
//     exit;
// }

// ── Doctor info from session ──────────────────────────────────────────────
$doctorName = htmlspecialchars($_SESSION['full_name']  ?? 'Dr. Saman Perera');
$doctorReg  = htmlspecialchars($_SESSION['staff_id']   ?? 'REG-DOC-0042');
$doctorDept = htmlspecialchars($_SESSION['department'] ?? 'General Medicine');
$doctorId   = htmlspecialchars($_SESSION['user_id']    ?? '');
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

$pageTitle  = 'Issue Prescription';
$pageCss    = '/Web/Hospital-Management-System/modules/pharmacy/prescription.css';
$useSidebar = true;
$isPublic   = false;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ══ PRESCRIPTION PAGE ═══════════════════════════════════════════════════ -->
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

    <!-- ── Doctor bar ───────────────────────────────────────────────────── -->
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

    <!-- ── 1. Patient Lookup ─────────────────────────────────────────────── -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-user-search" aria-hidden="true"></i>
            Patient Lookup
        </div>

        <div class="appt-row">
            <div class="field-group">
                <label for="apptNo">Appointment Reference Number</label>
                <input type="text" id="apptNo" placeholder="e.g. APT-2026-0089"
                       oninput="onApptInput()"
                       onkeydown="if(event.key==='Enter')lookupPatient()">
            </div>
            <button class="btn btn-outline btn-sm" onclick="lookupPatient()">
                <i class="ti ti-search" aria-hidden="true"></i> Lookup
            </button>
        </div>

        <div id="errAppt" class="err-msg" role="alert"></div>

        <!-- Patient detail fields — shown after successful lookup -->
        <div id="patientFields" style="display:none; margin-top:14px">

            <!-- Row 1: Name, Age, Gender, Blood Type -->
            <div class="field-grid fg-4" style="margin-bottom:10px">
                <div class="field-group patient-filled">
                    <label>Patient Name</label>
                    <input type="text" id="patientName" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>Age</label>
                    <input type="text" id="patientAge" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>Gender</label>
                    <input type="text" id="patientGender" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>Blood Type</label>
                    <input type="text" id="patientBlood" readonly>
                </div>
            </div>

            <!-- Row 2: Patient ID, NIC, Phone, Appointment Date -->
            <div class="field-grid fg-4">
                <div class="field-group patient-filled">
                    <label>Patient ID</label>
                    <input type="text" id="patientId" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>NIC</label>
                    <input type="text" id="patientNic" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>Phone</label>
                    <input type="text" id="patientPhone" readonly>
                </div>
                <div class="field-group patient-filled">
                    <label>Appointment Date</label>
                    <input type="text" id="apptDate" readonly>
                </div>
            </div>

            <!-- Hidden: store raw IDs for submit payload -->
            <input type="hidden" id="hiddenPatientId">
            <input type="hidden" id="hiddenUserId">
            <input type="hidden" id="hiddenApptId">
        </div>
    </div>

    <!-- ── 2. Diagnosis & Notes ──────────────────────────────────────────── -->
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
                <label for="clinicalNotes">Clinical Notes</label>
                <input type="text" id="clinicalNotes" placeholder="Brief note (optional)">
            </div>
        </div>
    </div>

    <!-- ── 3. Add Medicine (Drug Search) ────────────────────────────────── -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-pill" aria-hidden="true"></i>
            Add Medicine
        </div>
        <?php if (empty($drugs)): ?>
            <div class="err-msg" style="display:block; margin-bottom:10px">
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

    <!-- ── 4. Prescribed Medicines Table ────────────────────────────────── -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-clipboard-list" aria-hidden="true"></i>
            Prescribed Medicines
        </div>

        <div class="rx-wrap">
            <table class="rx-tbl" id="rxTbl">
                <thead>
                    <tr>
                        <th style="width:145px">Drug Name</th>
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

<!-- ══ CONFIRMATION MODAL ═══════════════════════════════════════════════════ -->
<div id="confirmModal" class="modal-backdrop" style="display:none" role="dialog"
     aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-icon-wrap">
                <i class="ti ti-circle-check" style="font-size:32px;color:var(--color-success-text)"></i>
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

<!-- ══ EXTRA STYLES (modal + sidebar active state) ══════════════════════════ -->
<style>
/* ── Modal ── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000;
    padding: 16px;
}
.modal-box {
    background: var(--color-background-primary);
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-lg);
    width: 100%; max-width: 700px;
    max-height: 90vh; overflow-y: auto;
    animation: slideUp .3s cubic-bezier(.34,1.56,.64,1);
}
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } }

.modal-header {
    text-align: center;
    padding: 28px 28px 16px;
    border-bottom: 0.5px solid var(--color-border-tertiary);
}
.modal-icon-wrap {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--color-success-bg);
    border: 2px solid var(--color-success-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
}
.modal-header h2 {
    font-size: 18px; font-weight: 700;
    color: var(--color-text-primary);
}
.modal-sub {
    font-size: 12px; color: var(--color-text-secondary); margin-top: 4px;
}
.modal-body { padding: 20px 28px; }
.modal-footer {
    padding: 16px 28px;
    border-top: 0.5px solid var(--color-border-tertiary);
    display: flex; gap: 8px; justify-content: flex-end;
}

/* ── Prescription summary table inside modal ── */
.rx-summary-table {
    width: 100%; border-collapse: collapse;
    margin-top: 16px; font-size: 12px;
}
.rx-summary-table th {
    background: var(--color-background-secondary);
    font-size: 10px; font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase; letter-spacing: .5px;
    padding: 7px 10px;
    border: 0.5px solid var(--color-border-tertiary);
    text-align: left;
}
.rx-summary-table td {
    padding: 7px 10px;
    border: 0.5px solid var(--color-border-tertiary);
    color: var(--color-text-primary);
    vertical-align: middle;
}
.rx-summary-table tr:nth-child(even) td {
    background: var(--color-background-secondary);
}

/* ── Info grid inside modal ── */
.modal-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 6px;
}
.modal-info-block {
    background: var(--color-background-secondary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-sm);
    padding: 8px 12px;
}
.modal-info-block.full { grid-column: 1 / -1; }
.mib-label { font-size: 10px; font-weight: 600; color: var(--color-text-secondary);
             text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px; }
.mib-val   { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }

.modal-msg {
    margin-top: 14px;
    padding: 10px 14px;
    background: var(--color-success-bg);
    color: var(--color-success-text);
    border: 0.5px solid var(--color-success-border);
    border-radius: var(--border-radius-sm);
    font-size: 12px; font-weight: 500;
    display: flex; align-items: center; gap: 7px;
}

/* ── Sidebar active link highlight ── */
.sidebar a.active,
.sidebar-menu li a.active {
    background: var(--color-primary-light);
    color: var(--color-primary);
    font-weight: 600;
}
</style>

<!-- ══ JAVASCRIPT ════════════════════════════════════════════════════════════ -->
<script>
// ── Drug data injected from PHP/DB ────────────────────────────────────────
const DRUGS = <?php echo json_encode(array_values($drugs), JSON_UNESCAPED_UNICODE); ?>;

// ── Doctor info from PHP session ──────────────────────────────────────────
const DOCTOR = {
    name: <?php echo json_encode($doctorName); ?>,
    reg:  <?php echo json_encode($doctorReg); ?>,
    dept: <?php echo json_encode($doctorDept); ?>,
    id:   <?php echo json_encode($doctorId); ?>,
};

const INSTRUCTIONS = [
    '— select —','After meals','Before meals','With meals','On empty stomach',
    'At bedtime','In the morning','Every 8 hours','Every 12 hours',
    'With plenty of water','Avoid sunlight','Avoid alcohol',
    'Chew before swallowing','Do not crush or chew',
    'Apply to affected area','As directed',
];

const LOW = 20;
let rxItems     = [];
let currentAppt = null; // stores full patient+appt object from lookup

// ── Utilities ─────────────────────────────────────────────────────────────
function stockInfo(d) {
    const qty = parseInt(d.stock ?? d.stock_qty ?? 0);
    if (qty === 0) return { lbl: 'Out of stock', cls: 'badge-ot', ok: false };
    if (qty <= LOW) return { lbl: 'Low stock',   cls: 'badge-lw', ok: true };
    return               { lbl: 'Available',    cls: 'badge-av', ok: true };
}
function tpd(r)  { return (r.M||0)+(r.A||0)+(r.E||0)+(r.N||0); }
function tqty(r) { return tpd(r) * (r.days||1); }
function instrOpts(sel) {
    return INSTRUCTIONS.map(o =>
        `<option${o === sel ? ' selected' : ''}>${o}</option>`
    ).join('');
}
function showErr(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg ?? '';
    el.style.display = msg ? 'block' : 'none';
}

// ── Today's date in doc-bar ───────────────────────────────────────────────
(function setDate() {
    const el = document.getElementById('todayDate');
    if (el) el.textContent = new Date().toLocaleDateString('en-GB',
        { weekday:'short', day:'2-digit', month:'short', year:'numeric' });
})();

// ── Mark sidebar link active ───────────────────────────────────────────────
(function markSidebarActive() {
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar a, .sidebar-menu a').forEach(a => {
        if (a.getAttribute('href') && path.endsWith(a.getAttribute('href').split('/').pop())) {
            a.classList.add('active');
        }
    });
})();

// ══ PATIENT LOOKUP ════════════════════════════════════════════════════════

function onApptInput() {
    showErr('errAppt', null);
    const v = document.getElementById('apptNo').value.trim().toUpperCase();
    if (v.length >= 12) lookupPatient(v);
}

async function lookupPatient(val) {
    const v = val ?? document.getElementById('apptNo').value.trim().toUpperCase();
    if (!v) { showErr('errAppt', 'Enter an appointment number.'); return; }

    try {
        const res  = await fetch(`http://localhost/Web/Hospital-Management-System/modules/pharmacy/prescription_action.php?action=lookup_patient&appt=${encodeURIComponent(v)}`);
        const data = await res.json();
        if (data.ok) {
            currentAppt = data;
            fillPatient(data.patient, data.appt);
            showErr('errAppt', null);
        } else {
            currentAppt = null;
            hidePatient();
            showErr('errAppt', data.error ?? 'Appointment not found.');
        }
    } catch {
        showErr('errAppt', 'Network error — could not reach server.');
    }
}

function fillPatient(p, apptRef) {
    // Row 1
    document.getElementById('patientName').value   = p.name        ?? p.full_name ?? '';
    document.getElementById('patientAge').value    = (p.age        ?? '—') + ' yrs';
    document.getElementById('patientGender').value = capitalize(p.gender ?? '—');
    document.getElementById('patientBlood').value  = p.blood_type  ?? '—';
    // Row 2
    document.getElementById('patientId').value     = (p.patient_id ?? p.id ?? '');
    document.getElementById('patientNic').value    = p.nic         ?? '—';
    document.getElementById('patientPhone').value  = p.phone       ?? '—';
    document.getElementById('apptDate').value      = formatDate(p.appt_date ?? '') + (p.appt_time ? ' · ' + p.appt_time : '');

    // Hidden raw values for submit
    document.getElementById('hiddenPatientId').value = p.patient_id ?? '';
    document.getElementById('hiddenUserId').value    = p.user_id    ?? '';
    document.getElementById('hiddenApptId').value    = p.appointment_id ?? '';

    const pf = document.getElementById('patientFields');
    pf.style.display = 'block';
    pf.querySelectorAll('.field-group').forEach(g => g.classList.add('patient-filled'));
}

function hidePatient() {
    currentAppt = null;
    document.getElementById('patientFields').style.display = 'none';
    ['patientName','patientAge','patientGender','patientBlood',
     'patientId','patientNic','patientPhone','apptDate',
     'hiddenPatientId','hiddenUserId','hiddenApptId']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    document.getElementById('patientFields')
        .querySelectorAll('.field-group').forEach(g => g.classList.remove('patient-filled'));
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}
function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
}

// ══ DRUG SEARCH ═══════════════════════════════════════════════════════════

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
        const s      = stockInfo(d);
        const already = rxItems.find(r => r.id === d.id);
        const dis    = (!s.ok || already) ? 'disabled' : '';
        const note   = already
            ? '<span style="font-size:10px;color:#854F0B;margin-left:4px">(added)</span>' : '';
        return `<div class="drug-item ${dis}" role="option" tabindex="0"
                     onclick="addDrug('${d.id}')"
                     onkeydown="if(event.key==='Enter')addDrug('${d.id}')">
            <div>
                <span class="d-name">${d.name}${note}</span><br>
                <span class="d-cat">${d.cat} · ${d.unit}
                    ${d.unit_price ? ' · LKR ' + parseFloat(d.unit_price).toFixed(2) : ''}
                </span>
            </div>
            <span class="badge ${s.cls}">${s.lbl} (${parseInt(d.stock)})</span>
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
    rxItems.push({ ...d, M: 1, A: 0, E: 1, N: 0, days: 5, instr: '' });
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

// ══ PRESCRIPTION TABLE RENDER ══════════════════════════════════════════════

function renderRx() {
    const body = document.getElementById('rxBody');
    if (!rxItems.length) {
        body.innerHTML = `<tr><td colspan="8">
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
            <td>
                <span style="font-size:12px;font-weight:600;color:var(--color-text-primary);line-height:1.4">${r.name}</span>
                <br><span style="font-size:10px;color:var(--color-text-secondary)">${r.cat}</span>
            </td>
            <td><span style="font-size:11px;color:var(--color-text-secondary)">${r.unit}</span></td>
            <td><span class="badge ${s.cls}">${s.lbl}</span></td>
            <td>
                <div class="slot-row">
                    <div class="slot-cell"><span>M</span>
                        <input type="number" value="${r.M}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','M',this.value)">
                    </div>
                    <div class="slot-cell"><span>A</span>
                        <input type="number" value="${r.A}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','A',this.value)">
                    </div>
                    <div class="slot-cell"><span>E</span>
                        <input type="number" value="${r.E}" min="0" max="10" step="0.5"
                               onchange="upd('${r.id}','E',this.value)">
                    </div>
                    <div class="slot-cell"><span>N</span>
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

// ══ CLEAR ALL ══════════════════════════════════════════════════════════════

function clearAll() {
    rxItems = [];
    renderRx();
    document.getElementById('apptNo').value        = '';
    document.getElementById('diagnosisSel').value  = '';
    document.getElementById('clinicalNotes').value = '';
    hidePatient();
    showErr('errAppt',   null);
    showErr('errSubmit', null);
}

// ══ SUBMIT PRESCRIPTION ════════════════════════════════════════════════════

async function submitRx() {
    showErr('errSubmit', null);

    const appt      = document.getElementById('apptNo').value.trim().toUpperCase();
    const diagnosis = document.getElementById('diagnosisSel').value;
    const notes     = document.getElementById('clinicalNotes').value.trim();

    if (!appt)      { showErr('errSubmit', 'Please look up a patient first.');    return; }
    if (!diagnosis) { showErr('errSubmit', 'Please select a diagnosis.');          return; }
    if (!rxItems.length) { showErr('errSubmit', 'Add at least one medicine.'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 ti-spin" aria-hidden="true"></i> Submitting…';

    const payload = {
        appt, diagnosis, notes,
        medicines: rxItems.map(r => ({
            id: r.id, name: r.name,
            M: r.M, A: r.A, E: r.E, N: r.N,
            days: r.days, instr: r.instr,
        })),
    };

    try {
        const res  = await fetch('http://localhost/Web/Hospital-Management-System/modules/pharmacy/prescription_action.php?action=submit_rx', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
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

// ══ CONFIRMATION MODAL ════════════════════════════════════════════════════

function showModal(d) {
    const p   = currentAppt?.patient ?? {};
    const pid = document.getElementById('hiddenPatientId').value || (p.patient_id ?? '—');
    const did = DOCTOR.id || '—';

    document.getElementById('modalSub').textContent = `${d.rx_ref} · ${d.issued_at}`;

    document.getElementById('modalBody').innerHTML = `
        <!-- Info grid: Patient + Doctor IDs prominent -->
        <div class="modal-info-grid">
            <div class="modal-info-block">
                <div class="mib-label">Patient</div>
                <div class="mib-val">${d.patient?.name ?? p.name ?? '—'}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Patient ID</div>
                <div class="mib-val" style="font-family:monospace">PAT-${pid}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Doctor</div>
                <div class="mib-val">${DOCTOR.name}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Doctor ID / Reg</div>
                <div class="mib-val" style="font-family:monospace">${DOCTOR.reg}${did && did !== '—' ? ' (UID: '+did+')' : ''}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Appointment Ref</div>
                <div class="mib-val" style="font-family:monospace">${d.appt ?? '—'}</div>
            </div>
            <div class="modal-info-block">
                <div class="mib-label">Department</div>
                <div class="mib-val">${DOCTOR.dept}</div>
            </div>
            <div class="modal-info-block full">
                <div class="mib-label">Diagnosis</div>
                <div class="mib-val">${d.diagnosis}</div>
            </div>
            ${d.notes ? `<div class="modal-info-block full">
                <div class="mib-label">Clinical Notes</div>
                <div class="mib-val">${d.notes}</div>
            </div>` : ''}
        </div>

        <!-- Prescription detail table -->
        <table class="rx-summary-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Drug Name</th>
                    <th>Drug ID</th>
                    <th>Dosage (M-A-E-N)</th>
                    <th>Days</th>
                    <th>Total Qty</th>
                    <th>Instruction</th>
                </tr>
            </thead>
            <tbody>
                ${d.medicines.map((m, i) => `
                    <tr>
                        <td style="color:var(--color-text-secondary);font-size:11px">${i+1}</td>
                        <td style="font-weight:600">${m.name}</td>
                        <td style="font-family:monospace;font-size:11px;color:var(--color-text-secondary)">${m.drug_id}</td>
                        <td style="font-family:monospace">${m.dosage}</td>
                        <td style="text-align:center">${m.days}</td>
                        <td style="text-align:center"><b>${m.total_qty}</b></td>
                        <td style="font-size:11px">${m.instruction || '—'}</td>
                    </tr>`).join('')}
            </tbody>
        </table>

        <p class="modal-msg">
            <i class="ti ti-circle-check"></i>
            ${d.message}
        </p>
    `;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    clearAll();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
