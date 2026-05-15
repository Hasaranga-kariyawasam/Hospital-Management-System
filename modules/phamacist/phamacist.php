<?php
/**
 * modules/pharmacy/phamacist.php
 * MediCare HMS — Pharmacy Dispensing Page
 * Connects: header.php · sidebar.php · footer.php · db.php · pharmacist.css
 *
 * Adapted to actual hospital_db column names:
 *   patients  → patient_id, user_id, nic, dob, gender, blood_type, phone, address
 *   doctors   → doctor_id, user_id, specialization, license_number
 *   users     → (assumed) user_id, full_name, phone, email
 *   appointments → appointment_id, patient_id, doctor_id, appt_date, appt_time, status, ref_number
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

// ── Page meta (consumed by header.php) ───────────────────────────────────────
$pageTitle  = 'Pharmacy';
$pageCss    = '/Web/Hospital-Management-System/modules/pharmacy/phamacist.css';
$useSidebar = true;
$isPublic   = false;

// ── 1. Pull sample patient IDs from DB for the hint pills ────────────────────
//    Picks up to 5 real patient_id values from the patients table
try {
    $sampleStmt = db()->query("
        SELECT patient_id
        FROM patients
        ORDER BY patient_id
        LIMIT 5
    ");
    $sampleIds = $sampleStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('[Pharmacy] sampleIds query failed: ' . $e->getMessage());
    $sampleIds = [];
}

// ── 2. AJAX endpoint — called by JS fetch() when user types a patient ID ──────
if (!empty($_GET['action']) && $_GET['action'] === 'lookup_patient') {
    header('Content-Type: application/json');

    $pid = strtoupper(trim($_GET['pid'] ?? ''));
    if ($pid === '') {
        echo json_encode(['ok' => false, 'error' => 'No patient ID supplied.']);
        exit;
    }

    try {
        // ── Patient row (join patients → users for full_name & phone) ────────
        $patStmt = db()->prepare("
            SELECT
                p.patient_id,
                p.user_id,
                p.nic,
                p.dob,
                p.gender,
                p.blood_type,
                p.phone,
                u.full_name
            FROM patients p
            LEFT JOIN users u ON u.user_id = p.user_id
            WHERE p.patient_id = ?
            LIMIT 1
        ");
        $patStmt->execute([$pid]);
        $patient = $patStmt->fetch();

        if (!$patient) {
            echo json_encode(['ok' => false, 'error' => "Patient '{$pid}' not found."]);
            exit;
        }

        // Calculate age from dob
        $age = '—';
        if (!empty($patient['dob'])) {
            $dob = new DateTime($patient['dob']);
            $age = (int) $dob->diff(new DateTime())->y;
        }

        // ── Latest appointment for this patient ──────────────────────────────
        $apptStmt = db()->prepare("
            SELECT
                a.appointment_id,
                a.ref_number,
                a.appt_date,
                a.appt_time,
                a.status,
                a.notes,
                d.doctor_id,
                d.specialization,
                d.license_number,
                u.full_name AS doctor_name
            FROM appointments a
            LEFT JOIN doctors d ON d.doctor_id = a.doctor_id
            LEFT JOIN users   u ON u.user_id   = d.user_id
            WHERE a.patient_id = ?
            ORDER BY a.appt_date DESC, a.appt_time DESC
            LIMIT 1
        ");
        $apptStmt->execute([$patient['patient_id']]);
        $appt = $apptStmt->fetch();

        // ── Prescription (optional — only if table exists) ──────────────────
        $rx    = null;
        $items = [];
        try {
            $rxStmt = db()->prepare("
                SELECT
                    pr.id            AS rx_id,
                    pr.prescription_code,
                    pr.diagnosis,
                    pr.notes,
                    pr.created_at
                FROM prescriptions pr
                WHERE pr.patient_id = ?
                ORDER BY pr.created_at DESC
                LIMIT 1
            ");
            $rxStmt->execute([$patient['patient_id']]);
            $rx = $rxStmt->fetch() ?: null;

            if ($rx) {
                $itemStmt = db()->prepare("
                    SELECT
                        pi.drug_name,
                        pi.category,
                        pi.dose_morning   AS morning,
                        pi.dose_afternoon AS afternoon,
                        pi.dose_evening   AS evening,
                        pi.dose_night     AS night,
                        pi.duration_days  AS days,
                        pi.unit_price
                    FROM prescription_items pi
                    WHERE pi.prescription_id = ?
                    ORDER BY pi.id
                ");
                $itemStmt->execute([$rx['rx_id']]);
                $items = $itemStmt->fetchAll();
            }
        } catch (Throwable $e) {
            // prescriptions table may not exist yet — safe to skip
            error_log('[Pharmacy] prescriptions query skipped: ' . $e->getMessage());
        }

        // ── Build JSON response ──────────────────────────────────────────────
        echo json_encode([
            'ok'      => true,
            'patient' => [
                'name'   => $patient['full_name']  ?? '—',
                'code'   => $patient['patient_id'],
                'age'    => $age,
                'nic'    => $patient['nic']         ?? '—',
                'phone'  => $patient['phone']       ?? '—',
                'blood'  => $patient['blood_type']  ?? '—',
                'gender' => $patient['gender']      ?? '—',
                'appt'   => $appt['ref_number']     ?? '—',
            ],
            'prescription' => [
                'ref'        => $rx['prescription_code']        ?? null,
                'date'       => $rx['created_at']               ?? ($appt['appt_date'] ?? null),
                'diagnosis'  => $rx['diagnosis']                ?? ($appt['notes'] ?? '—'),
                'notes'      => $rx['notes']                    ?? '',
                'doctorName' => $appt['doctor_name']            ?? '—',
                'specialty'  => $appt['specialization']         ?? '—',
                'docReg'     => $appt['license_number']         ?? '—',
                'medicines'  => $items,
            ],
        ]);

    } catch (Throwable $e) {
        error_log('[Pharmacy] lookup_patient: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
    }
    exit;
}

// ── Include header (renders <head>, topbar, opens .app-layout) ────────────────
require_once __DIR__ . '/../../includes/header.php';

// ── Include sidebar ────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ══════════════════════════════════════════════════════════
     MAIN CONTENT — sits inside .app-layout beside the sidebar
══════════════════════════════════════════════════════════ -->
<main class="main-content">
<div class="ph-page-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/Web/Hospital-Management-System/modules/admin/dashboard.php">Dashboard</a>
        <span class="sep">›</span>
        <span>Pharmacy</span>
    </nav>

    <!-- ── Patient Lookup Card ──────────────────────────────────── -->
    <div class="section-card no-print" id="lookupCard">
        <div class="card-title">
            <i class="ti ti-building-hospital" aria-hidden="true"></i>
            Pharmacy — Patient Lookup
        </div>

        <div class="lookup-row">
            <div class="field-group">
                <label for="pidIn">Patient ID</label>
                <input type="text" id="pidIn"
                       placeholder="e.g. <?php echo htmlspecialchars($sampleIds[0] ?? 'PT-0001'); ?>"
                       oninput="onPidInput()"
                       onkeydown="if(event.key==='Enter') lookupPid()">
            </div>
            <button class="btn btn-outline" onclick="lookupPid()">
                <i class="ti ti-search" aria-hidden="true"></i> Find Patient
            </button>
        </div>

        <div id="errPid" class="err-msg" role="alert"></div>

        <!-- Sample IDs pulled live from DB -->
        <?php if (!empty($sampleIds)): ?>
        <div class="hint-pills">
            <span class="hint-label">Sample IDs:</span>
            <?php foreach ($sampleIds as $sid): ?>
            <span class="hint-pill" onclick="quickFill('<?php echo htmlspecialchars($sid); ?>')">
                <?php echo htmlspecialchars($sid); ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading indicator -->
    <div id="loadingState" style="display:none; text-align:center; padding:30px; color:#5A6677; font-size:13px;">
        <i class="ti ti-loader" style="font-size:20px; animation:spin 1s linear infinite;"></i>
        <div style="margin-top:8px;">Looking up patient…</div>
    </div>

    <!-- ── Receipt ──────────────────────────────────────────────── -->
    <div id="receiptSection" style="display:none">

        <div class="receipt-wrap" id="receiptDoc">

            <!-- Receipt Header -->
            <div class="rx-header">
                <div class="rx-logo-row">
                    <div class="rx-logo-mark">M</div>
                    <div>
                        <div class="rx-hosp-name">MediCare General Hospital</div>
                        <div class="rx-hosp-sub">42 Medical Centre Road, Colombo 07 &nbsp;·&nbsp; +94 11 234 5678</div>
                    </div>
                </div>
                <div class="rx-meta-row">
                    <div>
                        <div class="rx-meta-label">Pharmacy Receipt</div>
                        <div class="rx-ref-no" id="rxRef">—</div>
                    </div>
                    <div class="rx-date-block" id="rxDate">—</div>
                </div>
            </div>

            <!-- Receipt Body -->
            <div class="rx-body">

                <!-- Patient + Prescription 2-col -->
                <div class="rx-2col">
                    <div>
                        <div class="section-label">Patient Details</div>
                        <div class="patient-card">
                            <div class="pat-head">
                                <div class="pat-avatar" id="rxAvatar">—</div>
                                <div>
                                    <div class="pat-name" id="rxPatName">—</div>
                                    <div class="pat-id"   id="rxPatId">—</div>
                                </div>
                            </div>
                            <div class="pat-grid">
                                <div>
                                    <div class="pat-label">Age</div>
                                    <div class="pat-val" id="rxAge">—</div>
                                </div>
                                <div>
                                    <div class="pat-label">NIC</div>
                                    <div class="pat-val" id="rxNic">—</div>
                                </div>
                                <div>
                                    <div class="pat-label">Blood Type</div>
                                    <div class="pat-val" id="rxBlood">—</div>
                                </div>
                                <div>
                                    <div class="pat-label">Appointment Ref</div>
                                    <div class="pat-val" id="rxAppt">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="section-label">Prescription / Diagnosis</div>
                        <div class="diag-card">
                            <div class="diag-val" id="rxDiag">—</div>
                            <div class="notes-val" id="rxNotes" style="display:none"></div>
                            <hr class="inner-divider">
                            <div class="doc-row">
                                <div class="doc-avatar" id="docAvatar">—</div>
                                <div>
                                    <div class="doc-name" id="docName">—</div>
                                    <div class="doc-dept" id="docDept">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="rx-divider">

                <!-- Medicine table -->
                <div class="section-label" style="margin-bottom:10px">Prescribed Medicines</div>
                <div class="tbl-wrap">
                    <table class="med-tbl">
                        <thead>
                            <tr>
                                <th style="width:180px">Medicine</th>
                                <th style="width:88px">Dosage<br><span style="font-weight:400;font-size:9px;opacity:.7">M–A–E–N</span></th>
                                <th style="width:50px">Days</th>
                                <th style="width:50px">Qty</th>
                                <th style="width:90px">Unit Price</th>
                                <th style="width:90px;text-align:right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="medBody">
                            <tr>
                                <td colspan="6" style="text-align:center;color:#5A6677;font-size:12px;padding:20px">
                                    No prescription items on record. Add medicines via the prescription module.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="totals-block">
                    <table class="totals-tbl">
                        <tr>
                            <td class="t-label">Subtotal</td>
                            <td class="t-val" id="tSubtotal">LKR 0.00</td>
                        </tr>
                        <tr>
                            <td class="t-label">Tax (0%)</td>
                            <td class="t-val">LKR 0.00</td>
                        </tr>
                        <tr class="grand-row">
                            <td class="grand-label">Total Payable</td>
                            <td class="grand-val" id="tTotal">LKR 0.00</td>
                        </tr>
                    </table>
                </div>

            </div><!-- /.rx-body -->

            <!-- Receipt Footer -->
            <div class="rx-footer">
                <div class="rx-footer-note">
                    Thank you for choosing MediCare General Hospital.<br>
                    Please follow dosage instructions carefully and complete the full course.
                </div>
                <span class="dispensed-badge">
                    <i class="ti ti-circle-check" aria-hidden="true"></i> Dispensed
                </span>
            </div>

        </div><!-- /.receipt-wrap -->

        <!-- Print / New patient actions -->
        <div class="action-bar no-print">
            <button class="btn btn-ghost" onclick="newLookup()">
                <i class="ti ti-refresh" aria-hidden="true"></i> New Patient
            </button>
            <button class="btn btn-print" onclick="printReceipt()">
                <i class="ti ti-printer" aria-hidden="true"></i> Print Receipt
            </button>
        </div>

    </div><!-- /#receiptSection -->

</div><!-- /.ph-page-wrap -->
</main>

<!-- ══════════════════════════════════════════════════════════
     INLINE STYLES — scoped extras that complement pharmacist.css
══════════════════════════════════════════════════════════ -->
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.main-content {
    flex: 1;
    overflow-y: auto;
    min-width: 0;
}

/* Receipt 2-col grid */
.rx-2col  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 1rem; }
.rx-body  { padding: 1.1rem 1.35rem; }

/* rx-meta-row */
.rx-meta-row   { display:flex; align-items:flex-end; justify-content:space-between; }
.rx-meta-label { font-size:10px; color:rgba(255,255,255,.6); text-transform:uppercase; letter-spacing:.5px; }
.rx-ref-no     { font-size:15px; font-weight:600; color:#fff; }
.rx-date-block { font-size:11px; color:rgba(255,255,255,.65); text-align:right; line-height:1.6; }

/* Patient card */
.patient-card {
    background: var(--color-success-bg);
    border: 0.5px solid var(--color-success-border);
    border-radius: var(--border-radius-md);
    padding: .75rem 1rem;
    height: 100%;
}
.pat-head   { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.pat-avatar {
    width:40px; height:40px; border-radius:50%;
    background:var(--color-success-fill); color:var(--color-success-dark);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:600; flex-shrink:0;
}
.pat-name   { font-size:14px; font-weight:600; color:var(--color-text-primary); }
.pat-id     { font-size:11px; color:var(--color-text-secondary); }
.pat-grid   { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.pat-label  { font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:var(--color-text-secondary); margin-bottom:2px; }
.pat-val    { font-size:12px; font-weight:500; color:var(--color-text-primary); }

.section-label {
    font-size:10px; text-transform:uppercase; letter-spacing:.6px;
    color:var(--color-text-secondary); font-weight:500; margin-bottom:8px;
}

@media (max-width: 640px) {
    .rx-2col { grid-template-columns: 1fr; }
}
</style>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT — fetches data from the AJAX endpoint above
══════════════════════════════════════════════════════════ -->
<script>
/* ── Helpers ──────────────────────────────────────────────────────── */
function initials(n) {
    return (n || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}
function tpd(m)  { return (+m.morning||0)+(+m.afternoon||0)+(+m.evening||0)+(+m.night||0); }
function tqty(m) { return tpd(m) * (+m.days || 1); }
function fmt(n)  { return 'LKR ' + (+n).toFixed(2); }
function doseStr(m) { return `${+m.morning}-${+m.afternoon}-${+m.evening}-${+m.night}`; }

function showErr(msg) {
    const el = document.getElementById('errPid');
    if (msg) { el.textContent = msg; el.style.display = 'block'; }
    else     { el.textContent = '';  el.style.display = 'none'; }
}
function setLoading(on) {
    document.getElementById('loadingState').style.display = on ? 'block' : 'none';
}

/* ── Input handlers ───────────────────────────────────────────────── */
let debounceTimer = null;

function onPidInput() {
    const v = document.getElementById('pidIn').value.trim().toUpperCase();
    showErr(null);
    clearTimeout(debounceTimer);
    if (v.length >= 3) {
        debounceTimer = setTimeout(() => lookupPid(v), 400);
    } else {
        document.getElementById('receiptSection').style.display = 'none';
    }
}

function lookupPid(pid) {
    pid = pid || document.getElementById('pidIn').value.trim().toUpperCase();
    if (!pid) { showErr('Please enter a Patient ID.'); return; }
    fetchPatient(pid);
}

function quickFill(pid) {
    document.getElementById('pidIn').value = pid;
    showErr(null);
    fetchPatient(pid);
}

/* ── AJAX fetch ───────────────────────────────────────────────────── */
async function fetchPatient(pid) {
    setLoading(true);
    document.getElementById('receiptSection').style.display = 'none';
    showErr(null);

    try {
        const url = `?action=lookup_patient&pid=${encodeURIComponent(pid)}`;
        const res  = await fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const data = await res.json();

        if (!data.ok) {
            showErr(data.error || 'Patient not found.');
        } else {
            renderReceipt(data.patient, data.prescription);
        }
    } catch (err) {
        showErr('Network error. Please check your connection and try again.');
        console.error(err);
    } finally {
        setLoading(false);
    }
}

/* ── Render receipt ───────────────────────────────────────────────── */
function renderReceipt(p, rx) {
    const now = new Date();

    /* Reference number */
    const rxRef = rx.ref
        || ('PHM-' + now.toISOString().slice(0,10).replace(/-/g,'') + '-' + p.code.replace(/\D/g,''));
    document.getElementById('rxRef').textContent = rxRef;

    /* Date / cashier */
    const cashier = <?php echo json_encode(htmlspecialchars($_SESSION['full_name'] ?? 'Pharmacy Counter 1')); ?>;
    const dateStr = now.toLocaleDateString('en-GB', {weekday:'short',day:'2-digit',month:'short',year:'numeric'});
    const timeStr = now.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit'});
    document.getElementById('rxDate').innerHTML =
        `Issued: ${dateStr} · ${timeStr}<br>Cashier: ${cashier}`;

    /* Patient */
    document.getElementById('rxAvatar').textContent  = initials(p.name);
    document.getElementById('rxPatName').textContent = p.name;
    document.getElementById('rxPatId').textContent   = p.code;
    document.getElementById('rxAge').textContent     = p.age !== '—' ? p.age + ' yrs' : '—';
    document.getElementById('rxNic').textContent     = p.nic   || '—';
    document.getElementById('rxBlood').textContent   = p.blood || '—';
    document.getElementById('rxAppt').textContent    = p.appt  || '—';

    /* Doctor */
    document.getElementById('docAvatar').textContent = initials(rx.doctorName || '?');
    document.getElementById('docName').textContent   = rx.doctorName ? 'Dr. ' + rx.doctorName : '—';
    document.getElementById('docDept').textContent   =
        (rx.specialty || 'General Medicine') + (rx.docReg ? ' · ' + rx.docReg : '');

    /* Diagnosis */
    document.getElementById('rxDiag').textContent = rx.diagnosis || '—';
    const notesEl = document.getElementById('rxNotes');
    if (rx.notes) {
        notesEl.textContent = 'Note: ' + rx.notes;
        notesEl.style.display = 'block';
    } else {
        notesEl.style.display = 'none';
    }

    /* Medicine rows */
    let subtotal = 0;
    const meds = rx.medicines || [];

    if (meds.length === 0) {
        document.getElementById('medBody').innerHTML =
            '<tr><td colspan="6" style="text-align:center;color:#5A6677;font-size:12px;padding:20px">' +
            'No prescription items found. Add medicines via the prescription module.</td></tr>';
    } else {
        const rows = meds.map((m, i) => {
            const qty = tqty(m);
            const amt = qty * (+m.unit_price || 0);
            subtotal += amt;
            const rowBg = i % 2 === 1 ? 'background:var(--color-background-secondary)' : '';
            return `<tr style="${rowBg}">
                <td>
                    <div class="drug-name">${escHtml(m.drug_name)}</div>
                    <div class="drug-cat">${escHtml(m.category || '')}</div>
                </td>
                <td><span class="dose-pill">${doseStr(m)}</span></td>
                <td class="muted-cell">${m.days}d</td>
                <td class="muted-cell">${qty}</td>
                <td class="muted-cell">${fmt(m.unit_price || 0)}</td>
                <td class="amount-cell">${fmt(amt)}</td>
            </tr>`;
        }).join('');
        document.getElementById('medBody').innerHTML = rows;
    }

    /* Totals */
    document.getElementById('tSubtotal').textContent = fmt(subtotal);
    document.getElementById('tTotal').textContent    = fmt(subtotal);

    /* Show receipt */
    document.getElementById('receiptSection').style.display = 'block';
    document.getElementById('receiptSection').scrollIntoView({behavior: 'smooth', block: 'start'});
}

/* ── Actions ─────────────────────────────────────────────────────── */
function newLookup() {
    document.getElementById('pidIn').value = '';
    document.getElementById('receiptSection').style.display = 'none';
    showErr(null);
    document.getElementById('pidIn').focus();
}

function printReceipt() {
    window.print();
}

/* ── XSS-safe helper ─────────────────────────────────────────────── */
function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
