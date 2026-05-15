<?php
/**
 * modules/pharmacy/pharmacist.php
 * MediCare HMS — Pharmacy Dispensing Page
 *
 * Visual style  : identical to phamacy.php (card, receipt, input, CSS)
 * Infrastructure: header.php · sidebar.php · footer.php · db.php
 * DB columns    : matches actual hospital_db schema
 *   patients    → patient_id, user_id, nic, dob, gender, blood_type, phone
 *   doctors     → doctor_id, user_id, specialization, license_number
 *   appointments→ patient_id, doctor_id, appt_date, ref_number, notes, status
 *   users       → user_id, full_name
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

// ── Page meta consumed by header.php ─────────────────────────────────────────
$pageTitle  = 'Pharmacy';
// ↓ Adjust this path to match exactly where phamacist.css sits on your server
$pageCss    = '/Web/Hospital-Management-System/modules/pharmacist/phamacist.css';
$useSidebar = true;
$isPublic   = false;

// ── Sample patient IDs for hint pills (live from DB) ─────────────────────────
try {
    $sampleIds = db()
        ->query("SELECT patient_id FROM patients ORDER BY patient_id LIMIT 5")
        ->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('[Pharmacy] sampleIds: ' . $e->getMessage());
    $sampleIds = [];
}

// ── AJAX endpoint ─────────────────────────────────────────────────────────────
if (!empty($_GET['action']) && $_GET['action'] === 'lookup_patient') {
    header('Content-Type: application/json');

    $pid = strtoupper(trim($_GET['pid'] ?? ''));
    if ($pid === '') {
        echo json_encode(['ok' => false, 'error' => 'No patient ID supplied.']);
        exit;
    }

    try {
        // ── Patient ──────────────────────────────────────────────────────────
        $ps = db()->prepare("
            SELECT p.patient_id, p.nic, p.dob, p.gender, p.blood_type, p.phone,
                   u.full_name
            FROM   patients p
            LEFT JOIN users u ON u.user_id = p.user_id
            WHERE  p.patient_id = ?
            LIMIT  1
        ");
        $ps->execute([$pid]);
        $patient = $ps->fetch();

        if (!$patient) {
            echo json_encode(['ok' => false, 'error' => "Patient '{$pid}' not found."]);
            exit;
        }

        // Age from dob
        $age = '—';
        if (!empty($patient['dob'])) {
            $age = (int)(new DateTime($patient['dob']))->diff(new DateTime())->y;
        }

        // ── Latest appointment ────────────────────────────────────────────────
        $as = db()->prepare("
            SELECT a.ref_number, a.appt_date, a.appt_time, a.status, a.notes,
                   d.specialization, d.license_number,
                   u.full_name AS doctor_name
            FROM   appointments a
            LEFT JOIN doctors d ON d.doctor_id = a.doctor_id
            LEFT JOIN users   u ON u.user_id   = d.user_id
            WHERE  a.patient_id = ?
            ORDER  BY a.appt_date DESC, a.appt_time DESC
            LIMIT  1
        ");
        $as->execute([$patient['patient_id']]);
        $appt = $as->fetch() ?: [];

        // ── Prescriptions via appointments → prescriptions → pharmacy_drugs ───
        $items = [];
        try {
            $is = db()->prepare("
                SELECT
                    pr.prescription_id,
                    pr.appointment_id,
                    pr.drug_id,
                    pr.dosage,
                    pr.frequency,
                    pr.duration_days  AS days,
                
                    pd.drug_name,
                    pd.category,
                    pd.unit,
                    pd.unit_price,
                    pd.stock_qty,
                    ap.appt_date
                FROM   appointments  ap
                JOIN   prescriptions pr ON pr.appointment_id = ap.appointment_id
                JOIN   pharmacy_drugs pd ON pd.drug_id       = pr.drug_id
                WHERE  ap.patient_id = ?
                ORDER  BY ap.appt_date DESC, pr.prescription_id ASC
            ");
            $is->execute([$patient['patient_id']]);
            $items = $is->fetchAll();
        } catch (Throwable $e) {
            error_log('[Pharmacy] prescriptions query failed: ' . $e->getMessage());
        }

        echo json_encode([
            'ok'      => true,
            'patient' => [
                'name'  => $patient['full_name'] ?? '—',
                'code'  => $patient['patient_id'],
                'age'   => $age,
                'nic'   => $patient['nic']        ?? '—',
                'phone' => $patient['phone']       ?? '—',
                'blood' => $patient['blood_type']  ?? '—',
                'appt'  => $appt['ref_number']     ?? '—',
            ],
            'prescription' => [
                'ref'        => null,
                'date'       => $appt['appt_date']      ?? null,
                'diagnosis'  => $appt['notes']           ?? '—',
                'notes'      => '',
                'doctorName' => $appt['doctor_name']     ?? '—',
                'specialty'  => $appt['specialization']  ?? '—',
                'docReg'     => $appt['license_number']  ?? '—',
                'medicines'  => $items,   // each row: drug_name, category, unit, unit_price, dosage, frequency, days, status
            ],
        ]);

    } catch (Throwable $e) {
        error_log('[Pharmacy] lookup_patient: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
    }
    exit;
}

// ── Render page ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!-- Tabler Icons — needed for ti-* icon classes used throughout this page -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">

<!--
    SAFETY-NET CSS
    Loaded inline so the page is always styled even if $pageCss path is wrong.
    Once you confirm phamacist.css loads correctly you can remove this block.
-->
<style>
/* ── CSS Variables (mirrors phamacist.css :root) ── */
:root{
    --color-primary:#0C447C; --color-primary-light:#E6F1FB;
    --color-primary-mid:#85B7EB; --color-primary-hover:#B5D4F4;
    --color-primary-dark:#185FA5;
    --color-success-bg:#EAF3DE; --color-success-text:#3B6D11;
    --color-success-dark:#27500A; --color-success-border:#97C459;
    --color-success-fill:#C0DD97;
    --color-background-primary:#FFFFFF; --color-background-secondary:#F5F7FA;
    --color-background-page:#EEF1F6; --color-border-tertiary:#DDE3EC;
    --color-text-primary:#0F1924; --color-text-secondary:#5A6677;
    --color-text-info:#1A6FC4;
    --border-radius-sm:6px; --border-radius-md:10px; --border-radius-lg:14px;
    --font-sans:'DM Sans',system-ui,-apple-system,sans-serif;
    --font-display:'Playfair Display',Georgia,serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font-sans);background:var(--color-background-page);color:var(--color-text-primary);}
input,select,textarea{
    font-family:var(--font-sans);font-size:13px;color:var(--color-text-primary);
    background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-sm);padding:0 10px;height:34px;outline:none;
    width:100%;transition:border-color .15s,box-shadow .15s;
}
input:focus{border-color:var(--color-primary-mid);box-shadow:0 0 0 3px rgba(12,68,124,.08);}
button,.btn{
    font-family:var(--font-sans);display:inline-flex;align-items:center;
    gap:6px;cursor:pointer;border-radius:var(--border-radius-md);
    white-space:nowrap;transition:background .15s;
}
.btn-outline{
    background:var(--color-primary-light);color:var(--color-primary);
    border:0.5px solid var(--color-primary-mid);padding:0 16px;height:36px;
    font-size:13px;font-weight:500;
}
.btn-outline:hover{background:var(--color-primary-hover);}
.btn-ghost{
    background:none;border:0.5px solid var(--color-border-tertiary);
    color:var(--color-text-secondary);padding:0 14px;height:36px;font-size:13px;
}
.btn-ghost:hover{background:var(--color-background-secondary);}
.btn-print{
    background:var(--color-primary);color:#fff;border:none;
    padding:0 20px;height:36px;font-size:13px;font-weight:500;
}
.btn-print:hover{background:#0a375f;}
.ph-page-wrap{max-width:900px;margin:0 auto;padding:28px 20px 60px;}
.breadcrumb{
    display:flex;align-items:center;gap:6px;font-size:12px;
    color:var(--color-text-secondary);margin-bottom:20px;
}
.breadcrumb a{color:var(--color-primary);text-decoration:none;}
.breadcrumb a:hover{text-decoration:underline;}
.breadcrumb .sep{opacity:.4;}
.section-card{
    background:var(--color-background-primary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-lg);
    padding:1.1rem 1.35rem;margin-bottom:1rem;
}
.card-title{
    font-size:11px;font-weight:500;color:var(--color-text-secondary);
    text-transform:uppercase;letter-spacing:.6px;margin-bottom:.85rem;
    display:flex;align-items:center;gap:7px;
}
.lookup-row{display:flex;gap:8px;align-items:flex-end;margin-bottom:10px;}
.field-group{display:flex;flex-direction:column;gap:4px;flex:1;}
.field-group label{font-size:11px;font-weight:500;color:var(--color-text-secondary);}
.err-msg{
    font-size:11px;color:#A32D2D;background:#FCEBEB;
    border:0.5px solid #F09595;border-radius:var(--border-radius-sm);
    padding:6px 10px;margin-top:6px;display:none;
}
.hint-pills{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:10px;}
.hint-label{font-size:11px;color:var(--color-text-secondary);}
.hint-pill{
    font-size:11px;color:var(--color-primary);background:var(--color-primary-light);
    border:0.5px solid var(--color-primary-mid);border-radius:20px;
    padding:2px 10px;cursor:pointer;transition:background .15s;
}
.hint-pill:hover{background:var(--color-primary-hover);}
.receipt-wrap{
    background:var(--color-background-primary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-lg);overflow:hidden;margin-bottom:0;
}
.rx-header{background:#0C447C;padding:1.1rem 1.35rem;color:#fff;}
.rx-logo-row{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.rx-logo-mark{
    width:38px;height:38px;border-radius:10px;background:#185FA5;
    display:flex;align-items:center;justify-content:center;
    font-family:var(--font-display);font-size:22px;font-weight:700;color:#fff;flex-shrink:0;
}
.rx-hosp-name{font-size:15px;font-weight:500;color:#fff;line-height:1.3;}
.rx-hosp-sub{font-size:11px;color:#85B7EB;}
.diag-card{
    background:var(--color-background-secondary);
    border:0.5px solid var(--color-border-tertiary);
    border-radius:var(--border-radius-md);padding:.75rem 1rem;height:100%;
}
.diag-val{font-size:13px;font-weight:500;color:var(--color-text-primary);margin-bottom:4px;}
.notes-val{font-size:12px;color:var(--color-text-secondary);font-style:italic;}
.inner-divider{border:none;border-top:0.5px solid var(--color-border-tertiary);margin:10px 0;}
.doc-row{display:flex;align-items:center;gap:8px;}
.doc-avatar{
    width:30px;height:30px;border-radius:50%;background:var(--color-primary-light);
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:500;color:var(--color-primary);flex-shrink:0;
}
.doc-name{font-size:12px;font-weight:500;color:var(--color-text-primary);}
.doc-dept{font-size:11px;color:var(--color-text-secondary);}
.rx-divider{border:none;border-top:0.5px solid var(--color-border-tertiary);margin:0 0 1rem;}
.tbl-wrap{overflow-x:auto;margin-bottom:0;}
.med-tbl{width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;}
.med-tbl th{
    font-size:10px;font-weight:500;color:var(--color-text-secondary);text-align:left;
    padding:8px 10px;border-bottom:0.5px solid var(--color-border-tertiary);
    background:var(--color-background-secondary);text-transform:uppercase;
    letter-spacing:.4px;white-space:nowrap;
}
.med-tbl td{padding:8px 10px;border-bottom:0.5px solid var(--color-border-tertiary);vertical-align:middle;}
.med-tbl tbody tr:last-child td{border-bottom:none;}
.drug-name{font-size:12px;font-weight:500;color:var(--color-text-primary);}
.drug-cat{font-size:11px;color:var(--color-text-secondary);}
.dose-pill{
    display:inline-block;background:var(--color-primary-light);color:var(--color-primary);
    border-radius:20px;font-size:11px;padding:2px 8px;font-weight:500;
}
.muted-cell{font-size:12px;color:var(--color-text-secondary);}
.amount-cell{font-size:13px;font-weight:500;color:var(--color-text-primary);text-align:right;}
.totals-block{display:flex;justify-content:flex-end;margin-top:1rem;padding-top:1rem;border-top:0.5px solid var(--color-border-tertiary);}
.totals-tbl{font-size:13px;min-width:230px;}
.totals-tbl td{padding:5px 0;}
.t-label{color:var(--color-text-secondary);padding-right:24px;}
.t-val{text-align:right;font-weight:500;color:var(--color-text-primary);}
.grand-row td{border-top:0.5px solid var(--color-border-tertiary);padding-top:10px;}
.grand-label{font-size:14px;font-weight:500;color:var(--color-text-primary);}
.grand-val{font-size:17px;font-weight:500;color:var(--color-primary);text-align:right;}
.rx-footer{
    background:var(--color-background-secondary);border-top:0.5px solid var(--color-border-tertiary);
    padding:.85rem 1.35rem;display:flex;align-items:center;
    justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.rx-footer-note{font-size:11px;color:var(--color-text-secondary);line-height:1.6;}
.dispensed-badge{
    display:inline-flex;align-items:center;gap:5px;
    background:var(--color-success-bg);color:var(--color-success-text);
    border:0.5px solid var(--color-success-border);
    border-radius:20px;font-size:11px;font-weight:500;padding:4px 12px;
}
.action-bar{display:flex;justify-content:flex-end;gap:8px;margin-top:.85rem;}
</style>

<main class="main-content">
<div class="ph-page-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/Web/Hospital-Management-System/modules/admin/dashboard.php">Dashboard</a>
        <span class="sep">›</span>
        <span>Pharmacy</span>
    </nav>

    <!-- ══════════════════════════════════════════════════════
         PATIENT LOOKUP CARD  — exact phamacy.php structure
    ══════════════════════════════════════════════════════ -->
    <div class="section-card no-print" id="lookupCard">
        <div class="card-title">
            <i class="ti ti-building-hospital" aria-hidden="true"></i>
            Pharmacy — patient lookup
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
                <i class="ti ti-search" aria-hidden="true"></i> Find patient
            </button>
        </div>

        <div id="errPid" class="err-msg" role="alert"></div>

        <!-- Sample IDs from DB -->
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

    <!-- Loading spinner -->
    <div id="loadingState" style="display:none;text-align:center;padding:30px;color:#5A6677;font-size:13px;">
        <i class="ti ti-loader" style="font-size:20px;animation:spin 1s linear infinite;"></i>
        <div style="margin-top:8px;">Looking up patient…</div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         RECEIPT — exact phamacy.php structure
    ══════════════════════════════════════════════════════ -->
    <div id="receiptSection" style="display:none">

        <div class="receipt-wrap" id="receiptDoc">

            <!-- Header -->
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
                        <div class="rx-meta-label">Pharmacy receipt</div>
                        <div class="rx-ref-no" id="rxRef">—</div>
                    </div>
                    <div class="rx-date-block" id="rxDate">—</div>
                </div>
            </div>

            <!-- Body -->
            <div class="rx-body">

                <div class="rx-2col">

                    <!-- Patient details -->
                    <div>
                        <div class="section-label">Patient details</div>
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
                                    <div class="pat-label">Blood type</div>
                                    <div class="pat-val" id="rxBlood">—</div>
                                </div>
                                <div>
                                    <div class="pat-label">Appointment</div>
                                    <div class="pat-val" id="rxAppt">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prescription -->
                    <div>
                        <div class="section-label">Prescription</div>
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

                </div><!-- /.rx-2col -->

                <hr class="rx-divider">

                <!-- Medicine table -->
                <div class="section-label" style="margin-bottom:10px">Prescribed medicines</div>
                <div class="tbl-wrap">
                    <table class="med-tbl">
                        <thead>
                            <tr>
                                <th style="width:170px">Medicine</th>
                                <th style="width:88px">Dosage</th>
                                <th style="width:50px">Days</th>
                                <th style="width:50px">Qty</th>
                                <th style="width:90px">Unit price</th>
                                <th style="width:90px;text-align:right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="medBody"></tbody>
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
                            <td class="grand-label">Total payable</td>
                            <td class="grand-val" id="tTotal">LKR 0.00</td>
                        </tr>
                    </table>
                </div>

            </div><!-- /.rx-body -->

            <!-- Receipt footer -->
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

        <!-- Action buttons -->
        <div class="action-bar no-print">
            <button class="btn btn-ghost" onclick="newLookup()">
                <i class="ti ti-refresh" aria-hidden="true"></i> New patient
            </button>
            <button class="btn btn-print" onclick="printReceipt()">
                <i class="ti ti-printer" aria-hidden="true"></i> Print receipt
            </button>
        </div>

    </div><!-- /#receiptSection -->

</div><!-- /.ph-page-wrap -->
</main>

<!-- ══════════════════════════════════════════════════════════
     BRIDGE STYLES — sidebar layout + receipt 2-col + print
     (pharmacist.css handles all base colours/typography)
══════════════════════════════════════════════════════════ -->
<style>
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

/* sidebar layout */
.main-content { flex:1; overflow-y:auto; min-width:0; }

/* receipt inner layout */
.rx-2col { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:1rem; }
.rx-body  { padding:1.1rem 1.35rem; }

/* receipt meta */
.rx-meta-row   { display:flex; align-items:flex-end; justify-content:space-between; }
.rx-meta-label { font-size:10px; color:rgba(255,255,255,.6); text-transform:uppercase; letter-spacing:.5px; }
.rx-ref-no     { font-size:15px; font-weight:600; color:#fff; }
.rx-date-block { font-size:11px; color:rgba(255,255,255,.65); text-align:right; line-height:1.6; }

/* patient card */
.patient-card {
    background:var(--color-success-bg);
    border:0.5px solid var(--color-success-border);
    border-radius:var(--border-radius-md);
    padding:.75rem 1rem; height:100%;
}
.pat-head { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.pat-avatar {
    width:40px; height:40px; border-radius:50%;
    background:var(--color-success-fill); color:var(--color-success-dark);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:600; flex-shrink:0;
}
.pat-name { font-size:14px; font-weight:600; color:var(--color-text-primary); }
.pat-id   { font-size:11px; color:var(--color-text-secondary); }
.pat-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.pat-label{ font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:var(--color-text-secondary); margin-bottom:2px; }
.pat-val  { font-size:12px; font-weight:500; color:var(--color-text-primary); }

/* section label */
.section-label {
    font-size:10px; text-transform:uppercase; letter-spacing:.6px;
    color:var(--color-text-secondary); font-weight:500; margin-bottom:8px;
}

/* print: hide chrome, show only receipt */
@media print {
    .no-print, .topbar, .sidebar, .breadcrumb, .site-footer { display:none !important; }
    .app-layout { display:block !important; }
    .main-content { overflow:visible; }
    body { background:#fff; }
    .ph-page-wrap { padding:0; max-width:100%; }
    .receipt-wrap { border:none; border-radius:0; box-shadow:none; }
    .rx-header        { background:#0C447C !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .patient-card     { background:#EAF3DE !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .pat-avatar       { background:#C0DD97 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .diag-card, .rx-footer, .med-tbl th { background:#F5F7FA !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    @page { size:A4; margin:1.5cm 2cm; }
    .rx-2col, .med-tbl, .totals-block, .rx-footer { page-break-inside:avoid; }
}

@media (max-width:640px) { .rx-2col { grid-template-columns:1fr; } }
</style>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════ -->
<script>
/* helpers */
function initials(n){ return (n||'?').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase(); }
function fmt(n)     { return 'LKR '+(+n).toFixed(2); }
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showErr(msg){
    const el=document.getElementById('errPid');
    if(msg){el.textContent=msg;el.style.display='block';}
    else   {el.textContent=''; el.style.display='none'; }
}
function setLoading(on){
    document.getElementById('loadingState').style.display=on?'block':'none';
}

/* input handlers */
let _t=null;
function onPidInput(){
    const v=document.getElementById('pidIn').value.trim().toUpperCase();
    showErr(null); clearTimeout(_t);
    if(v.length>=3) _t=setTimeout(()=>lookupPid(v),400);
    else document.getElementById('receiptSection').style.display='none';
}
function lookupPid(pid){
    pid=pid||document.getElementById('pidIn').value.trim().toUpperCase();
    if(!pid){showErr('Please enter a Patient ID.');return;}
    fetchPatient(pid);
}
function quickFill(pid){
    document.getElementById('pidIn').value=pid;
    showErr(null); fetchPatient(pid);
}

/* AJAX */
async function fetchPatient(pid){
    setLoading(true);
    document.getElementById('receiptSection').style.display='none';
    showErr(null);
    try{
        const res =await fetch(`?action=lookup_patient&pid=${encodeURIComponent(pid)}`,
                               {headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data=await res.json();
        if(!data.ok) showErr(data.error||'Patient not found.');
        else          renderReceipt(data.patient,data.prescription);
    }catch(e){
        showErr('Network error. Please check your connection and try again.');
        console.error(e);
    }finally{setLoading(false);}
}

/* render receipt — mirrors phamacy.php showReceipt() exactly */
function renderReceipt(p,rx){
    const now=new Date();

    /* ref */
    const rxRef=rx.ref||('PHM-'+now.toISOString().slice(0,10).replace(/-/g,'')+'-'+p.code.replace(/\D/g,''));
    document.getElementById('rxRef').textContent=rxRef;

    /* date / cashier */
    const cashier=<?php echo json_encode(htmlspecialchars($_SESSION['full_name'] ?? 'Pharmacy Counter 1')); ?>;
    const dateStr=now.toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short',year:'numeric'});
    const timeStr=now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
    document.getElementById('rxDate').innerHTML=`Issued: ${dateStr} · ${timeStr}<br>Cashier: ${cashier}`;

    /* patient */
    document.getElementById('rxAvatar').textContent =initials(p.name);
    document.getElementById('rxPatName').textContent=p.name;
    document.getElementById('rxPatId').textContent  =p.code;
    document.getElementById('rxAge').textContent    =p.age!=='—'?p.age+' yrs':'—';
    document.getElementById('rxNic').textContent    =p.nic  ||'—';
    document.getElementById('rxBlood').textContent  =p.blood||'—';
    document.getElementById('rxAppt').textContent   =p.appt ||'—';

    /* doctor */
    document.getElementById('docAvatar').textContent=initials(rx.doctorName||'?');
    document.getElementById('docName').textContent  =rx.doctorName?'Dr. '+rx.doctorName:'—';
    document.getElementById('docDept').textContent  =(rx.specialty||'General Medicine')+(rx.docReg?' · '+rx.docReg:'');

    /* diagnosis */
    document.getElementById('rxDiag').textContent=rx.diagnosis||'—';
    const notesEl=document.getElementById('rxNotes');
    if(rx.notes){notesEl.textContent='Note: '+rx.notes;notesEl.style.display='block';}
    else         notesEl.style.display='none';

    /* medicines */
    let subtotal=0;
    const meds=rx.medicines||[];
    if(meds.length===0){
        document.getElementById('medBody').innerHTML=
            '<tr><td colspan="6" style="text-align:center;color:#5A6677;font-size:12px;padding:20px">'+
            'No prescriptions found for this patient.</td></tr>';
    }else{
        document.getElementById('medBody').innerHTML=meds.map((m,i)=>{
            // qty = duration_days (stock dispensed per prescription row)
            const days   = parseInt(m.days)  || 1;
            const price  = parseFloat(m.unit_price) || 0;
            // For dosage like "1-0-1-0" try to sum, otherwise treat as 1 unit/day
            const doseNum = (()=>{
                const parts = String(m.dosage||'1').split('-').map(Number);
                const sum   = parts.reduce((a,b)=>a+(isNaN(b)?0:b),0);
                return sum > 0 ? sum : 1;
            })();
            const qty    = doseNum * days;
            const amt    = qty * price;
            subtotal    += amt;
            const bg     = i%2===1?'background:var(--color-background-secondary)':'';

            // Status badge colour
            const statusColor = m.status==='dispensed'
                ? 'color:#3B6D11;background:#EAF3DE;border:0.5px solid #97C459'
                : 'color:#92400e;background:#fffbeb;border:0.5px solid #fcd34d';

            return `<tr style="${bg}">
                <td>
                    <div class="drug-name">${escHtml(m.drug_name)}</div>
                    <div class="drug-cat">${escHtml(m.category||'')}</div>
                </td>
                <td>
                    <span class="dose-pill">${escHtml(m.dosage||'—')}</span>
                    <div style="font-size:10px;color:#5A6677;margin-top:3px">${escHtml(m.frequency||'')}</div>
                </td>
                <td class="muted-cell">${days}d</td>
                <td class="muted-cell">${qty}</td>
                <td class="muted-cell">${fmt(price)}</td>
                <td class="amount-cell">
                    ${fmt(amt)}
                    
                </td>
            </tr>`;
        }).join('');
    }


    /* totals */
    document.getElementById('tSubtotal').textContent=fmt(subtotal);
    document.getElementById('tTotal').textContent   =fmt(subtotal);

    /* show */
    document.getElementById('receiptSection').style.display='block';
    document.getElementById('receiptSection').scrollIntoView({behavior:'smooth',block:'start'});
}

/* actions */
function newLookup(){
    document.getElementById('pidIn').value='';
    document.getElementById('receiptSection').style.display='none';
    showErr(null);
    document.getElementById('pidIn').focus();
}
function printReceipt(){ window.print(); }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
