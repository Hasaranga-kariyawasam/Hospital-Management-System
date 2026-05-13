<?php
declare(strict_types=1);

$requiredRoles = ['doctor'];
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../config/db_config.php';

$pageTitle  = 'Issue Prescription';
$useSidebar = true;
$pageCss    = '/Web/Hospital-Management-System/modules/doctor/prescription.css';

// ── Auto-load logged-in doctor details ─────────────────────
$doctorId = $_SESSION['doctor_id'];

$doctor = $pdo->prepare("
    SELECT d.doctor_id, u.full_name, d.specialization, d.reg_number
    FROM   doctors d
    JOIN   users   u ON u.user_id = d.user_id
    WHERE  d.doctor_id = ?
");
$doctor->execute([$doctorId]);
$doctor = $doctor->fetch();

// ── AJAX: patient lookup by appointment number ──────────────
if (isset($_GET['action']) && $_GET['action'] === 'lookup_appointment') {
    header('Content-Type: application/json');
    $ref = trim($_GET['ref'] ?? '');

    $appt = $pdo->prepare("
        SELECT a.appointment_id, a.ref_number, a.appt_date, a.appt_time,
               p.patient_id,
               u.full_name  AS patient_name,
               p.nic,
               TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
        FROM   appointments a
        JOIN   patients     p ON p.patient_id = a.patient_id
        JOIN   users        u ON u.user_id    = p.user_id
        WHERE  a.ref_number = ?
          AND  a.doctor_id  = ?
          AND  a.appt_date  = CURDATE()
          AND  a.status     NOT IN ('cancelled', 'completed')
    ");
    $appt->execute([$ref, $doctorId]);
    $row = $appt->fetch();

    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or not assigned to you today.']);
    }
    exit;
}

// ── AJAX: drug search ───────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'search_drugs') {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';

    $drugs = $pdo->prepare("
        SELECT drug_id, drug_name, category, unit, stock_qty, reorder_level
        FROM   pharmacy_drugs
        WHERE  is_active = 1
          AND (drug_name LIKE ? OR category LIKE ?)
        ORDER  BY drug_name
        LIMIT  10
    ");
    $drugs->execute([$q, $q]);
    echo json_encode($drugs->fetchAll());
    exit;
}

// ── POST: save prescription ─────────────────────────────────
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prescription'])) {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $patientId     = (int)($_POST['patient_id']     ?? 0);
    $diagnosis     = trim($_POST['diagnosis']        ?? '');
    $clinicalNotes = trim($_POST['clinical_notes']   ?? '');
    $drugIds       = $_POST['drug_id']       ?? [];
    $dosages       = $_POST['dosage']        ?? [];   // "M-A-E-N" string built in JS
    $mornings      = $_POST['morning']       ?? [];
    $afternoons    = $_POST['afternoon']     ?? [];
    $evenings      = $_POST['evening']       ?? [];
    $nights        = $_POST['night']         ?? [];
    $days          = $_POST['days']          ?? [];
    $qtys          = $_POST['quantity']      ?? [];
    $instrs        = $_POST['instruction']   ?? [];

    if (!$appointmentId || !$patientId || !$diagnosis || empty($drugIds)) {
        $errorMsg = 'Please complete all required fields and add at least one medicine.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert prescription header
            $ins = $pdo->prepare("
                INSERT INTO prescriptions
                    (appointment_id, patient_id, doctor_id, diagnosis, clinical_notes, status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $ins->execute([$appointmentId, $patientId, $doctorId, $diagnosis, $clinicalNotes]);
            $prescriptionId = (int)$pdo->lastInsertId();

            // Insert prescription items
            $insItem = $pdo->prepare("
                INSERT INTO prescription_items
                    (prescription_id, drug_id, dosage, frequency, days, quantity,
                     availability_snapshot, remarks, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($drugIds as $i => $drugId) {
                $drugId   = (int)$drugId;
                $M        = (int)($mornings[$i]    ?? 0);
                $A        = (int)($afternoons[$i]  ?? 0);
                $E        = (int)($evenings[$i]    ?? 0);
                $N        = (int)($nights[$i]      ?? 0);
                $d        = (int)($days[$i]        ?? 1);
                $qty      = (int)($qtys[$i]        ?? (($M+$A+$E+$N) * $d));
                $instr    = trim($instrs[$i]        ?? '');
                $dosage   = "{$M}-{$A}-{$E}-{$N}";
                $freq     = ($M+$A+$E+$N) . ' time(s) per day';

                // Availability snapshot at time of prescribing
                $stockRow = $pdo->prepare("
                    SELECT stock_qty, reorder_level FROM pharmacy_drugs WHERE drug_id = ?
                ");
                $stockRow->execute([$drugId]);
                $stockRow = $stockRow->fetch();
                $snap = 'available';
                if ($stockRow) {
                    if ($stockRow['stock_qty'] == 0)                              $snap = 'out_of_stock';
                    elseif ($stockRow['stock_qty'] <= $stockRow['reorder_level']) $snap = 'low_stock';
                }

                $insItem->execute([
                    $prescriptionId, $drugId, $dosage, $freq, $d, $qty, $snap, $instr
                ]);
            }

            // Mark appointment status as prescription_issued (optional)
            $pdo->prepare("
                UPDATE appointments SET status = 'prescription_issued' WHERE appointment_id = ?
            ")->execute([$appointmentId]);

            $pdo->commit();
            $successMsg = 'Prescription submitted successfully to the pharmacy queue.';

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Diagnosis list ──────────────────────────────────────────
$diagnosisGroups = [
    'Infectious diseases' => [
        'Upper respiratory tract infection',
        'Lower respiratory tract infection',
        'Urinary tract infection',
        'Gastroenteritis',
        'Dengue fever',
        'Typhoid fever',
        'Malaria',
    ],
    'Chronic / non-communicable' => [
        'Type 2 diabetes mellitus',
        'Type 1 diabetes mellitus',
        'Essential hypertension',
        'Ischemic heart disease',
        'Bronchial asthma',
        'COPD',
        'Hypothyroidism',
        'Hyperthyroidism',
        'Dyslipidaemia',
        'Chronic kidney disease',
    ],
    'Gastrointestinal' => [
        'Peptic ulcer disease',
        'GERD / acid reflux',
        'Irritable bowel syndrome',
        'Constipation',
    ],
    'Musculoskeletal' => [
        'Osteoarthritis',
        'Rheumatoid arthritis',
        'Low back pain',
        'Gout',
    ],
    'Neurological / mental health' => [
        'Migraine',
        'Epilepsy',
        'Anxiety disorder',
        'Depression',
        'Insomnia',
    ],
    'Skin' => [
        'Eczema / atopic dermatitis',
        'Fungal skin infection',
        'Allergic reaction',
        'Scabies',
    ],
    'Eye / ENT' => [
        'Conjunctivitis',
        'Acute otitis media',
        'Allergic rhinitis',
        'Tonsillitis',
    ],
    'Other' => [
        'Anaemia',
        'Vitamin D deficiency',
        'Nutritional deficiency',
        'Post-operative care',
        'Other / unspecified',
    ],
];

$instructions = [
    'After meals',
    'Before meals',
    'With meals',
    'On empty stomach',
    'At bedtime',
    'In the morning',
    'Every 8 hours',
    'Every 12 hours',
    'With plenty of water',
    'Avoid sunlight',
    'Avoid alcohol',
    'Chew before swallowing',
    'Do not crush or chew',
    'Apply to affected area',
    'As directed',
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>Issue Prescription</h2>
            <p>Doctor portal — <?php echo date('l, d F Y'); ?></p>
        </div>
        <a href="/Web/Hospital-Management-System/modules/doctor/my_appointments.php" class="btn btn-secondary">
            ← My Appointments
        </a>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <!-- ── Doctor session bar (auto from session) ── -->
    <div class="doctor-session-bar">
        <div class="doctor-info">
            <div class="doctor-avatar">
                <?php
                    $words    = explode(' ', $doctor['full_name']);
                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    echo htmlspecialchars($initials);
                ?>
            </div>
            <div>
                <div class="doc-name"><?php echo htmlspecialchars($doctor['full_name']); ?></div>
                <div class="doc-dept">
                    <?php echo htmlspecialchars($doctor['specialization']); ?>
                    &nbsp;·&nbsp;
                    <?php echo htmlspecialchars($doctor['reg_number']); ?>
                </div>
            </div>
        </div>
        <div class="session-meta">
            📅 <?php echo date('d M Y, g:i A'); ?>
            <span class="badge badge-success" style="margin-left:8px">Session active</span>
        </div>
    </div>

    <form method="POST" id="rxForm" autocomplete="off">

        <!-- ── 1. Patient lookup ── -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3>Patient lookup</h3>
            </div>
            <div class="card-body">
                <div class="lookup-row">
                    <div class="form-group">
                        <label for="apptRef">Appointment number <span class="text-danger">*</span></label>
                        <input type="text"
                               id="apptRef"
                               class="form-control"
                               placeholder="e.g. APT-2026-0089"
                               maxlength="30">
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="lookupAppointment()">
                        🔍 Lookup
                    </button>
                </div>
                <div id="lookupError" class="alert alert-danger" style="display:none;margin-top:8px"></div>

                <!-- Hidden real fields submitted with form -->
                <input type="hidden" name="appointment_id" id="appointmentId">
                <input type="hidden" name="patient_id"     id="patientId">

                <div id="patientDetails" style="display:none">
                    <div class="patient-filled-row">
                        <div class="form-group">
                            <label>Patient name</label>
                            <input type="text" id="showPatientName" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="text" id="showPatientAge" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="text" id="showPatientRef" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>NIC</label>
                            <input type="text" id="showPatientNic" class="form-control" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 2. Diagnosis & notes ── -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3>Diagnosis &amp; notes</h3>
            </div>
            <div class="card-body">
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis <span class="text-danger">*</span></label>
                        <select name="diagnosis" id="diagnosis" class="form-control" required>
                            <option value="">— select diagnosis —</option>
                            <?php foreach ($diagnosisGroups as $group => $items): ?>
                                <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item); ?>">
                                            <?php echo htmlspecialchars($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clinical_notes">Clinical notes</label>
                        <input type="text"
                               name="clinical_notes"
                               id="clinical_notes"
                               class="form-control"
                               placeholder="Optional — allergies, special instructions…">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 3. Add medicine (search) ── -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3>Add medicine</h3>
            </div>
            <div class="card-body">
                <div class="lookup-row">
                    <div class="form-group">
                        <label for="drugSearch">Search drug name or category</label>
                        <input type="text"
                               id="drugSearch"
                               class="form-control"
                               placeholder="e.g. Paracetamol, Antibiotic…"
                               oninput="searchDrugs()">
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="searchDrugs()">
                        🔍 Search
                    </button>
                </div>
                <div class="drug-search-results" id="drugSearchResults"></div>
            </div>
        </div>

        <!-- ── 4. Prescribed medicines table ── -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3>Prescribed medicines</h3>
            </div>
            <div class="card-body">
                <div class="rx-table-wrap">
                    <table class="rx-table" id="rxTable">
                        <thead>
                            <tr>
                                <th style="width:150px">Drug name</th>
                                <th style="width:55px">Unit</th>
                                <th style="width:60px">Status</th>
                                <th style="width:200px">Dosage (Morning / Afternoon / Evening / Night)</th>
                                <th style="width:50px">Days</th>
                                <th style="width:60px">Total qty</th>
                                <th style="width:120px">Instruction</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody id="rxTableBody">
                            <tr id="emptyRxRow">
                                <td colspan="8">
                                    <div class="rx-empty">
                                        <i>💊</i>
                                        No medicines added yet — search above and click a drug to add
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── Footer: stats + actions ── -->
                <div class="rx-footer">
                    <div class="rx-stats">
                        <div class="rx-stat-pill">Medicines: <b id="statMeds">0</b></div>
                        <div class="rx-stat-pill">Total items: <b id="statQty">0</b></div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center">
                        <div id="submitError" class="text-danger" style="font-size:13px"></div>
                        <button type="button" class="btn btn-secondary" onclick="clearForm()">
                            ↺ Clear
                        </button>
                        <button type="button" class="btn btn-primary" onclick="validateAndSubmit()">
                            ✔ Submit to pharmacy queue
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form><!-- end #rxForm -->

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     JavaScript — all DOM interaction
     ══════════════════════════════════════════════════════════ -->
<script>
const INSTRUCTIONS = <?php echo json_encode(array_merge(['— select instruction —'], $instructions), JSON_UNESCAPED_UNICODE); ?>;

let rxItems = [];   // { drug_id, drug_name, unit, stock_qty, reorder_level, M, A, E, N, days, instr }

/* ── helpers ─────────────────────────────────────────────── */
function stockStatus(item) {
    if (item.stock_qty == 0)                              return { lbl: 'Out of stock', cls: 'badge-out',       ok: false };
    if (item.stock_qty <= item.reorder_level)             return { lbl: 'Low stock',    cls: 'badge-low',       ok: true  };
    return                                                       { lbl: 'Available',    cls: 'badge-available', ok: true  };
}

function tpd(r)  { return (r.M||0) + (r.A||0) + (r.E||0) + (r.N||0); }
function tqty(r) { return tpd(r) * (r.days || 1); }

/* ── patient lookup ──────────────────────────────────────── */
function lookupAppointment() {
    const ref = document.getElementById('apptRef').value.trim();
    const err = document.getElementById('lookupError');
    err.style.display = 'none';

    if (!ref) { err.textContent = 'Please enter an appointment number.'; err.style.display = 'block'; return; }

    fetch(`?action=lookup_appointment&ref=${encodeURIComponent(ref)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const d = res.data;
                document.getElementById('appointmentId').value   = d.appointment_id;
                document.getElementById('patientId').value       = d.patient_id;
                document.getElementById('showPatientName').value = d.patient_name;
                document.getElementById('showPatientAge').value  = d.age + ' yrs';
                document.getElementById('showPatientRef').value  = 'PT-' + String(d.patient_id).padStart(4, '0');
                document.getElementById('showPatientNic').value  = d.nic;
                document.getElementById('patientDetails').style.display = 'block';
            } else {
                document.getElementById('patientDetails').style.display = 'none';
                document.getElementById('appointmentId').value = '';
                document.getElementById('patientId').value     = '';
                err.textContent = res.message;
                err.style.display = 'block';
            }
        })
        .catch(() => { err.textContent = 'Server error. Please try again.'; err.style.display = 'block'; });
}

/* lookup on Enter key */
document.getElementById('apptRef').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); lookupAppointment(); }
});

/* ── drug search ─────────────────────────────────────────── */
let searchTimer = null;
function searchDrugs() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(_doSearch, 220);
}

function _doSearch() {
    const q   = document.getElementById('drugSearch').value.trim();
    const box = document.getElementById('drugSearchResults');
    if (q.length < 1) { box.style.display = 'none'; return; }

    fetch(`?action=search_drugs&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(drugs => {
            if (!drugs.length) { box.style.display = 'none'; return; }
            box.style.display = 'block';
            box.innerHTML = drugs.map(d => {
                const s       = stockStatus(d);
                const already = rxItems.find(r => r.drug_id == d.drug_id);
                const dis     = (!s.ok || already) ? 'disabled' : '';
                const note    = already ? '<span style="font-size:11px;color:var(--warning,#b45309);margin-left:6px">(already added)</span>' : '';
                return `<div class="drug-result-item ${dis}" onclick="addDrug(${JSON.stringify(d).replace(/"/g,'&quot;')})">
                    <div>
                        <span class="drug-title">${d.drug_name}</span>${note}
                        <br>
                        <span class="drug-sub">${d.category} · ${d.unit}</span>
                    </div>
                    <span class="badge ${s.cls}">${s.lbl}</span>
                    <span style="font-size:20px;color:var(--primary)">＋</span>
                </div>`;
            }).join('');
        });
}

/* ── add drug to table ───────────────────────────────────── */
function addDrug(drug) {
    if (rxItems.find(r => r.drug_id == drug.drug_id)) return;
    if (!stockStatus(drug).ok) return;
    rxItems.push({ ...drug, M:1, A:0, E:1, N:0, days:5, instr:'' });
    renderRxTable();
    document.getElementById('drugSearch').value = '';
    document.getElementById('drugSearchResults').style.display = 'none';
}

/* ── remove drug ─────────────────────────────────────────── */
function removeRow(drugId) {
    rxItems = rxItems.filter(r => r.drug_id != drugId);
    renderRxTable();
}

/* ── update field ────────────────────────────────────────── */
function updField(drugId, field, val) {
    const r = rxItems.find(x => x.drug_id == drugId);
    if (!r) return;
    const nums = ['M','A','E','N','days'];
    r[field] = nums.includes(field) ? (Math.max(0, parseInt(val) || 0)) : val;
    if (field === 'days') r[field] = Math.max(1, r[field]);
    /* update summary text only */
    const sumEl = document.getElementById('sum_' + drugId);
    if (sumEl) sumEl.textContent = `${tpd(r)}/day × ${r.days} days = ${tqty(r)}`;
    const qtyEl = document.getElementById('qty_' + drugId);
    if (qtyEl) qtyEl.textContent = tqty(r);
    updateStats();
}

/* ── render table ────────────────────────────────────────── */
function renderRxTable() {
    const body = document.getElementById('rxTableBody');

    if (!rxItems.length) {
        body.innerHTML = `<tr id="emptyRxRow">
            <td colspan="8">
                <div class="rx-empty"><i>💊</i>No medicines added yet — search above and click a drug to add</div>
            </td>
        </tr>`;
        updateStats();
        return;
    }

    body.innerHTML = rxItems.map((r, idx) => {
        const s = stockStatus(r);
        const instrOpts = INSTRUCTIONS.map(o =>
            `<option value="${o === '— select instruction —' ? '' : o}"${r.instr === o ? ' selected' : ''}>${o}</option>`
        ).join('');

        return `<tr>
            <td>
                <strong style="font-size:13px">${r.drug_name}</strong>
                <input type="hidden" name="drug_id[]"   value="${r.drug_id}">
                <input type="hidden" name="morning[]"   id="hM_${r.drug_id}"   value="${r.M}">
                <input type="hidden" name="afternoon[]" id="hA_${r.drug_id}"   value="${r.A}">
                <input type="hidden" name="evening[]"   id="hE_${r.drug_id}"   value="${r.E}">
                <input type="hidden" name="night[]"     id="hN_${r.drug_id}"   value="${r.N}">
                <input type="hidden" name="days[]"      id="hD_${r.drug_id}"   value="${r.days}">
                <input type="hidden" name="quantity[]"  id="hQ_${r.drug_id}"   value="${tqty(r)}">
                <input type="hidden" name="instruction[]" id="hI_${r.drug_id}" value="${r.instr}">
            </td>
            <td><span style="font-size:12px;color:var(--muted)">${r.unit}</span></td>
            <td><span class="badge ${s.cls}">${s.lbl}</span></td>
            <td>
                <div class="dosage-slots">
                    <div class="dosage-slot">
                        <span>Morning</span>
                        <input type="number" min="0" max="10" value="${r.M}"
                               onchange="updField(${r.drug_id},'M',this.value);document.getElementById('hM_${r.drug_id}').value=this.value">
                    </div>
                    <div class="dosage-slot">
                        <span>Afternoon</span>
                        <input type="number" min="0" max="10" value="${r.A}"
                               onchange="updField(${r.drug_id},'A',this.value);document.getElementById('hA_${r.drug_id}').value=this.value">
                    </div>
                    <div class="dosage-slot">
                        <span>Evening</span>
                        <input type="number" min="0" max="10" value="${r.E}"
                               onchange="updField(${r.drug_id},'E',this.value);document.getElementById('hE_${r.drug_id}').value=this.value">
                    </div>
                    <div class="dosage-slot">
                        <span>Night</span>
                        <input type="number" min="0" max="10" value="${r.N}"
                               onchange="updField(${r.drug_id},'N',this.value);document.getElementById('hN_${r.drug_id}').value=this.value">
                    </div>
                </div>
                <div class="dose-summary" id="sum_${r.drug_id}">${tpd(r)}/day × ${r.days} days = ${tqty(r)}</div>
            </td>
            <td>
                <input type="number" min="1" max="365" value="${r.days}" style="width:44px"
                       onchange="updField(${r.drug_id},'days',this.value);document.getElementById('hD_${r.drug_id}').value=this.value;document.getElementById('hQ_${r.drug_id}').value=tqty(rxItems.find(x=>x.drug_id==${r.drug_id}))">
            </td>
            <td>
                <span id="qty_${r.drug_id}" style="font-weight:600">${tqty(r)}</span>
                <span style="font-size:11px;color:var(--muted)"> ${r.unit}s</span>
            </td>
            <td>
                <select style="font-size:11px;width:100%"
                        onchange="updField(${r.drug_id},'instr',this.value);document.getElementById('hI_${r.drug_id}').value=this.value">
                    ${instrOpts}
                </select>
            </td>
            <td>
                <button type="button" class="btn-icon-danger" onclick="removeRow(${r.drug_id})" title="Remove">
                    🗑
                </button>
            </td>
        </tr>`;
    }).join('');

    updateStats();
}

/* ── stats ───────────────────────────────────────────────── */
function updateStats() {
    document.getElementById('statMeds').textContent = rxItems.length;
    document.getElementById('statQty').textContent  = rxItems.reduce((s, r) => s + tqty(r), 0);
}

/* ── validate and submit ─────────────────────────────────── */
function validateAndSubmit() {
    const errEl = document.getElementById('submitError');
    errEl.textContent = '';

    if (!document.getElementById('appointmentId').value) {
        errEl.textContent = 'Please look up a valid appointment first.'; return;
    }
    if (!document.getElementById('diagnosis').value) {
        errEl.textContent = 'Please select a diagnosis.'; return;
    }
    if (!rxItems.length) {
        errEl.textContent = 'Please add at least one medicine.'; return;
    }

    /* sync all hidden fields before submit */
    rxItems.forEach(r => {
        const hQ = document.getElementById('hQ_' + r.drug_id);
        if (hQ) hQ.value = tqty(r);
    });

    /* add hidden submit flag and submit */
    let flag = document.getElementById('__submitFlag');
    if (!flag) {
        flag = document.createElement('input');
        flag.type = 'hidden';
        flag.name = 'submit_prescription';
        flag.id   = '__submitFlag';
        flag.value = '1';
        document.getElementById('rxForm').appendChild(flag);
    }
    document.getElementById('rxForm').submit();
}

/* ── clear form ──────────────────────────────────────────── */
function clearForm() {
    rxItems = [];
    renderRxTable();
    ['apptRef','drugSearch','clinical_notes'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('diagnosis').selectedIndex = 0;
    document.getElementById('patientDetails').style.display = 'none';
    document.getElementById('appointmentId').value = '';
    document.getElementById('patientId').value     = '';
    document.getElementById('lookupError').style.display  = 'none';
    document.getElementById('drugSearchResults').style.display = 'none';
    document.getElementById('submitError').textContent = '';
}
</script>
