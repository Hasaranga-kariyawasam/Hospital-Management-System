<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Prescription — MediCare General Hospital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <!-- Tabler Icons (same CDN used by prescription form) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">

    <!-- MediCare shared layout  -->
    <link rel="stylesheet" href="includes/layout.css">
    <!-- Prescription-specific styles (prescription.css) -->
    <link rel="stylesheet" href="prescription.css">
</head>
<body>

<!-- ═══════════════════════════════════════════
     NAV BAR  (identical to home.php)
════════════════════════════════════════════ -->
<nav class="pub-nav" id="mainNav">
    <div class="pub-nav-inner">
        <a href="home.php" class="pub-nav-logo">
            <div class="pub-logo-mark">M</div>
            <div>
                <span class="pub-logo-name">MediCare</span>
                <span class="pub-logo-sub">General Hospital</span>
            </div>
        </a>

        <ul class="pub-nav-links">
            <li><a href="home.php#about">About</a></li>
            <li><a href="home.php#departments">Departments</a></li>
            <li><a href="home.php#doctors">Doctors</a></li>
            <li><a href="home.php#services">Services</a></li>
            <li><a href="home.php#contact">Contact</a></li>
        </ul>

        <div class="pub-nav-actions">
            <?php
            // Replace with real session-check in production
            $isLoggedIn = true;   // assume doctor is logged in
            $doctorName = 'Dr. Saman Perera';
            if ($isLoggedIn): ?>
                <span style="font-size:13px;color:#5A6677">👤 <?php echo htmlspecialchars($doctorName); ?></span>
                <a href="logout.php" class="btn-nav-outline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-nav-outline">Staff Login</a>
                <a href="login.php" class="btn-nav-solid">Patient Portal</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Emergency bar -->
<div class="emergency-bar">
    <div class="emergency-bar-inner">
        <span class="emergency-pulse"></span>
        <strong>24/7 Emergency Services</strong>
        <span>•</span>
        <span>Call Ambulance: <strong>+94 11 234 5678</strong></span>
        <span>•</span>
        <span>Emergency Ward: <strong>Ground Floor, Block A</strong></span>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     PRESCRIPTION FORM CONTENT
════════════════════════════════════════════ -->
<main>
<div class="rx-page-wrap">

    <!-- Breadcrumb -->
    <nav class="rx-breadcrumb" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <span class="sep">›</span>
        <a href="doctor_dashboard.php">Doctor Dashboard</a>
        <span class="sep">›</span>
        <span>Issue Prescription</span>
    </nav>

    <h2 class="sr-only">Doctor prescription form — issue medicines to patient</h2>

    <!-- ── Doctor info bar ─────────────────────────────────── -->
    <?php
    $DOCTOR = ['name'=>'Dr. Saman Perera', 'reg'=>'REG-DOC-0042', 'dept'=>'General Medicine'];
    $initials = implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $DOCTOR['name']), -2)));
    ?>
    <div class="doc-bar">
        <div class="doc-info">
            <div class="doc-avatar"><?php echo strtoupper($initials); ?></div>
            <div>
                <div class="doc-name"><?php echo htmlspecialchars($DOCTOR['name']); ?></div>
                <div class="doc-dept"><?php echo htmlspecialchars($DOCTOR['dept']); ?> · <?php echo htmlspecialchars($DOCTOR['reg']); ?></div>
            </div>
        </div>
        <div class="doc-meta">
            <i class="ti ti-calendar" aria-hidden="true"></i>
            <span id="todayDate"></span>
            <span class="session-badge">Session active</span>
        </div>
    </div>

    <!-- ── Patient lookup ──────────────────────────────────── -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-user-search" aria-hidden="true"></i>
            Patient Lookup
        </div>

        <div class="appt-row">
            <div class="field-group">
                <label for="apptNo">Appointment number</label>
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

    <!-- ── Diagnosis & notes ───────────────────────────────── -->
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

    <!-- ── Add medicine ────────────────────────────────────── -->
    <div class="section-card">
        <div class="card-title">
            <i class="ti ti-pill" aria-hidden="true"></i>
            Add Medicine
        </div>
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

    <!-- ── Prescribed medicines table ─────────────────────── -->
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
</main>

<!-- ── Success toast ─────────────────────────────────────── -->
<div class="toast" id="toast" role="status" aria-live="polite">
    <span class="toast-icon">✅</span>
    <span id="toastMsg">Prescription submitted successfully!</span>
</div>

<!-- ═══════════════════════════════════════════
     FOOTER  (identical to home.php)
════════════════════════════════════════════ -->
<footer class="pub-footer">
    <div class="container">
        <div class="pub-footer-top">
            <div>
                <div class="pub-footer-brand">
                    <div class="pub-footer-logo">M</div>
                    <div>
                        <div class="pub-footer-name">MediCare General Hospital</div>
                        <div class="pub-footer-tagline">Excellence in Healthcare</div>
                    </div>
                </div>
                <p style="font-size:12px;color:rgba(255,255,255,.45);max-width:260px;line-height:1.6">
                    Delivering world-class medical care with compassion since 2008.
                </p>
            </div>
            <div class="pub-footer-links">
                <h5>Quick Links</h5>
                <a href="home.php#about">About Us</a>
                <a href="home.php#departments">Departments</a>
                <a href="home.php#doctors">Our Doctors</a>
                <a href="home.php#contact">Contact</a>
            </div>
            <div class="pub-footer-links">
                <h5>Patient Services</h5>
                <a href="register_patient.php">Register as Patient</a>
                <a href="login.php">Patient Portal Login</a>
                <a href="login.php">Book Appointment</a>
            </div>
            <div class="pub-footer-links">
                <h5>Staff Access</h5>
                <a href="login.php">Staff Login</a>
                <a href="register_staff.php">Staff Registration</a>
            </div>
        </div>
        <div class="pub-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> MediCare General Hospital. All rights reserved.</span>
            <span>ICT1242 Web Development Practicum — Group 05</span>
        </div>
    </div>
</footer>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════ -->
<script>
// ── Doctor & static data ───────────────────────────────────────────────
const DOCTOR = {name:'Dr. Saman Perera', reg:'REG-DOC-0042', dept:'General Medicine'};

// Patient data (mirrors prescription_actions.php; real app uses AJAX lookup)
const PATIENTS = {
    'APT-2026-0089':{name:'Kamal Jayasinghe',  age:48, id:'PT-0024', nic:'198012345678'},
    'APT-2026-0090':{name:'Nimali Fernando',    age:34, id:'PT-0031', nic:'199034567890'},
    'APT-2026-0091':{name:'Ruwan Silva',        age:62, id:'PT-0018', nic:'196278901234'},
    'APT-2026-0092':{name:'Dilani Rathnayake',  age:27, id:'PT-0055', nic:'199756781234'},
    'APT-2026-0093':{name:'Suresh Perera',      age:55, id:'PT-0039', nic:'196901234567'},
};

const DRUGS = [
    {id:1, name:'Paracetamol 500mg',         cat:'Analgesic',         unit:'tablet',  stock:500, reorder:50},
    {id:2, name:'Ibuprofen 400mg',           cat:'Analgesic',         unit:'tablet',  stock:350, reorder:40},
    {id:3, name:'Aspirin 75mg',              cat:'Analgesic',         unit:'tablet',  stock:300, reorder:30},
    {id:4, name:'Diclofenac Sodium 50mg',    cat:'Analgesic',         unit:'tablet',  stock:200, reorder:30},
    {id:5, name:'Tramadol 50mg',             cat:'Analgesic',         unit:'capsule', stock:80,  reorder:20},
    {id:6, name:'Amoxicillin 250mg',         cat:'Antibiotic',        unit:'capsule', stock:250, reorder:30},
    {id:7, name:'Amoxicillin 500mg',         cat:'Antibiotic',        unit:'capsule', stock:200, reorder:30},
    {id:8, name:'Azithromycin 500mg',        cat:'Antibiotic',        unit:'tablet',  stock:120, reorder:20},
    {id:9, name:'Ciprofloxacin 500mg',       cat:'Antibiotic',        unit:'tablet',  stock:150, reorder:25},
    {id:10,name:'Metronidazole 400mg',       cat:'Antibiotic',        unit:'tablet',  stock:180, reorder:30},
    {id:11,name:'Doxycycline 100mg',         cat:'Antibiotic',        unit:'capsule', stock:90,  reorder:20},
    {id:12,name:'Cephalexin 500mg',          cat:'Antibiotic',        unit:'capsule', stock:60,  reorder:15},
    {id:13,name:'Cloxacillin 500mg',         cat:'Antibiotic',        unit:'capsule', stock:40,  reorder:15},
    {id:14,name:'Metformin 500mg',           cat:'Antidiabetic',      unit:'tablet',  stock:400, reorder:50},
    {id:15,name:'Metformin 1000mg',          cat:'Antidiabetic',      unit:'tablet',  stock:300, reorder:40},
    {id:16,name:'Glibenclamide 5mg',         cat:'Antidiabetic',      unit:'tablet',  stock:250, reorder:30},
    {id:17,name:'Glimepiride 2mg',           cat:'Antidiabetic',      unit:'tablet',  stock:180, reorder:25},
    {id:18,name:'Sitagliptin 100mg',         cat:'Antidiabetic',      unit:'tablet',  stock:50,  reorder:10},
    {id:19,name:'Amlodipine 5mg',            cat:'Antihypertensive',  unit:'tablet',  stock:350, reorder:40},
    {id:20,name:'Amlodipine 10mg',           cat:'Antihypertensive',  unit:'tablet',  stock:280, reorder:35},
    {id:21,name:'Lisinopril 10mg',           cat:'Antihypertensive',  unit:'tablet',  stock:220, reorder:30},
    {id:22,name:'Atenolol 50mg',             cat:'Antihypertensive',  unit:'tablet',  stock:300, reorder:35},
    {id:23,name:'Losartan 50mg',             cat:'Antihypertensive',  unit:'tablet',  stock:200, reorder:25},
    {id:24,name:'Hydrochlorothiazide 25mg',  cat:'Antihypertensive',  unit:'tablet',  stock:260, reorder:30},
    {id:25,name:'Omeprazole 20mg',           cat:'Antacid',           unit:'capsule', stock:400, reorder:50},
    {id:26,name:'Omeprazole 40mg',           cat:'Antacid',           unit:'capsule', stock:300, reorder:40},
    {id:27,name:'Ranitidine 150mg',          cat:'Antacid',           unit:'tablet',  stock:350, reorder:40},
    {id:28,name:'Pantoprazole 40mg',         cat:'Antacid',           unit:'tablet',  stock:250, reorder:30},
    {id:29,name:'Domperidone 10mg',          cat:'Antacid',           unit:'tablet',  stock:300, reorder:35},
    {id:30,name:'Loperamide 2mg',            cat:'Antidiarrheal',     unit:'capsule', stock:150, reorder:20},
    {id:31,name:'Lactulose 10g/15ml',        cat:'Laxative',          unit:'ml',      stock:60,  reorder:10},
    {id:32,name:'Cetirizine 10mg',           cat:'Antihistamine',     unit:'tablet',  stock:400, reorder:50},
    {id:33,name:'Loratadine 10mg',           cat:'Antihistamine',     unit:'tablet',  stock:350, reorder:45},
    {id:34,name:'Chlorpheniramine 4mg',      cat:'Antihistamine',     unit:'tablet',  stock:500, reorder:60},
    {id:35,name:'Salbutamol 2mg',            cat:'Bronchodilator',    unit:'tablet',  stock:200, reorder:25},
    {id:36,name:'Salbutamol 100mcg Inhaler', cat:'Bronchodilator',    unit:'inhaler', stock:30,  reorder:10},
    {id:37,name:'Prednisolone 5mg',          cat:'Corticosteroid',    unit:'tablet',  stock:180, reorder:20},
    {id:38,name:'Vitamin C 500mg',           cat:'Vitamin',           unit:'tablet',  stock:600, reorder:60},
    {id:39,name:'Vitamin D3 1000IU',         cat:'Vitamin',           unit:'capsule', stock:400, reorder:50},
    {id:40,name:'Ferrous Sulphate 200mg',    cat:'Supplement',        unit:'tablet',  stock:350, reorder:40},
    {id:41,name:'Folic Acid 5mg',            cat:'Supplement',        unit:'tablet',  stock:400, reorder:50},
    {id:42,name:'Calcium Carbonate 500mg',   cat:'Supplement',        unit:'tablet',  stock:300, reorder:35},
    {id:43,name:'Zinc Sulphate 20mg',        cat:'Supplement',        unit:'tablet',  stock:250, reorder:30},
    {id:44,name:'B-Complex Tablet',          cat:'Vitamin',           unit:'tablet',  stock:500, reorder:60},
    {id:45,name:'Atorvastatin 10mg',         cat:'Lipid-Lowering',    unit:'tablet',  stock:200, reorder:25},
    {id:46,name:'Atorvastatin 20mg',         cat:'Lipid-Lowering',    unit:'tablet',  stock:160, reorder:20},
    {id:47,name:'Simvastatin 20mg',          cat:'Lipid-Lowering',    unit:'tablet',  stock:180, reorder:20},
    {id:48,name:'Gentamicin Eye Drops 0.3%', cat:'Ophthalmic',        unit:'ml',      stock:40,  reorder:10},
    {id:49,name:'Chloramphenicol Eye Drops', cat:'Ophthalmic',        unit:'ml',      stock:35,  reorder:10},
    {id:50,name:'Betamethasone Cream 0.1%',  cat:'Topical',           unit:'g',       stock:50,  reorder:10},
];

const INSTRUCTIONS = [
    '— select —','After meals','Before meals','With meals','On empty stomach',
    'At bedtime','In the morning','Every 8 hours','Every 12 hours',
    'With plenty of water','Avoid sunlight','Avoid alcohol',
    'Chew before swallowing','Do not crush or chew','Apply to affected area','As directed',
];

const LOW = 20;
let rxItems = [];

// ── Utilities ──────────────────────────────────────────────────────────
function stockInfo(d) {
    if (d.stock === 0) return {lbl:'Out of stock', cls:'badge-ot', ok:false};
    if (d.stock <= LOW) return {lbl:'Low stock',    cls:'badge-lw', ok:true};
    return {lbl:'Available',    cls:'badge-av', ok:true};
}

function tpd(r)  { return (r.M||0)+(r.A||0)+(r.E||0)+(r.N||0); }
function tqty(r) { return tpd(r) * (r.days||1); }

function instrOpts(sel) {
    return INSTRUCTIONS.map(o => `<option${o===sel?' selected':''}>${o}</option>`).join('');
}

// ── Date ───────────────────────────────────────────────────────────────
function setDate() {
    const d = new Date();
    document.getElementById('todayDate').textContent =
        d.toLocaleDateString('en-GB', {weekday:'short', day:'2-digit', month:'short', year:'numeric'});
}

// ── Patient lookup ─────────────────────────────────────────────────────
function onApptInput() {
    const v = document.getElementById('apptNo').value.trim().toUpperCase();
    showErr('errAppt', null);
    if (PATIENTS[v]) fillPatient(v);
    else if (v.length >= 12) hidePatient();   // only hide if looks like a full number
}

function lookupPatient() {
    const v = document.getElementById('apptNo').value.trim().toUpperCase();
    if (PATIENTS[v]) { fillPatient(v); showErr('errAppt', null); }
    else { hidePatient(); showErr('errAppt', 'Appointment not found. Please check the number.'); }
}

function fillPatient(key) {
    const p = PATIENTS[key];
    document.getElementById('patientName').value = p.name;
    document.getElementById('patientAge').value  = p.age + ' yrs';
    document.getElementById('patientId').value   = p.id;
    document.getElementById('patientNic').value  = p.nic;
    const pf = document.getElementById('patientFields');
    pf.style.display = 'block';
    pf.querySelectorAll('.field-group').forEach(g => g.classList.add('patient-filled'));
    showErr('errAppt', null);
}

function hidePatient() {
    document.getElementById('patientFields').style.display = 'none';
    ['patientName','patientAge','patientId','patientNic']
        .forEach(id => document.getElementById(id).value = '');
    document.getElementById('patientFields')
        .querySelectorAll('.field-group').forEach(g => g.classList.remove('patient-filled'));
}

// ── Drug search ────────────────────────────────────────────────────────
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
                     onclick="addDrug(${d.id})"
                     onkeydown="if(event.key==='Enter')addDrug(${d.id})">
            <div>
                <span class="d-name">${d.name}${note}</span><br>
                <span class="d-cat">${d.cat} · ${d.unit}</span>
            </div>
            <span class="badge ${s.cls}">${s.lbl}</span>
            <i class="ti ti-circle-plus" style="font-size:18px;color:var(--color-text-info)" aria-hidden="true"></i>
        </div>`;
    }).join('');
}

function closeDrugList() {
    document.getElementById('drugList').style.display = 'none';
}

// Close drug list on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('#drugList') && !e.target.closest('#drugQ')) closeDrugList();
});

function addDrug(id) {
    if (rxItems.find(r => r.id === id)) return;
    const d = DRUGS.find(x => x.id === id);
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
    const r = rxItems.find(x => x.id === id);
    if (!r) return;
    const numFields = ['M','A','E','N','days'];
    r[field] = numFields.includes(field) ? (parseFloat(val) || 0) : val;
    if (field === 'days') r[field] = Math.max(1, parseInt(val) || 1);
    renderRx();
}

// ── Render prescription table ──────────────────────────────────────────
function renderRx() {
    const body = document.getElementById('rxBody');
    if (!rxItems.length) {
        body.innerHTML = `<tr><td colspan="8">
            <div class="empty-state">
                <i class="ti ti-pill empty-icon" aria-hidden="true"></i>
                No medicines added — search above and click a drug to add
            </div></td></tr>`;
        updateStats(); return;
    }
    body.innerHTML = rxItems.map((r, i) => {
        const s      = stockInfo(r);
        const rowBg  = i % 2 !== 0 ? 'background:var(--color-background-secondary)' : '';
        return `<tr style="${rowBg}">
            <td><span style="font-size:12px;font-weight:600;color:var(--color-text-primary);line-height:1.4">${r.name}</span></td>
            <td><span style="font-size:11px;color:var(--color-text-secondary)">${r.unit}</span></td>
            <td><span class="badge ${s.cls}">${s.lbl}</span></td>
            <td>
                <div class="slot-row">
                    <div class="slot-cell">
                        <span>Morning</span>
                        <input type="number" min="0" max="10" value="${r.M}"
                               onchange="upd(${r.id},'M',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>Afternoon</span>
                        <input type="number" min="0" max="10" value="${r.A}"
                               onchange="upd(${r.id},'A',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>Evening</span>
                        <input type="number" min="0" max="10" value="${r.E}"
                               onchange="upd(${r.id},'E',this.value)">
                    </div>
                    <div class="slot-cell">
                        <span>Night</span>
                        <input type="number" min="0" max="10" value="${r.N}"
                               onchange="upd(${r.id},'N',this.value)">
                    </div>
                </div>
                <div class="slot-summary">${tpd(r)}/day × ${r.days} days = <b>${tqty(r)}</b></div>
            </td>
            <td><input type="number" min="1" max="365" value="${r.days}"
                       style="width:44px"
                       onchange="upd(${r.id},'days',this.value)"></td>
            <td>
                <span style="font-size:13px;font-weight:600;color:var(--color-text-primary)">${tqty(r)}</span>
                <span style="font-size:10px;color:var(--color-text-secondary)"> ${r.unit}s</span>
            </td>
            <td><select onchange="upd(${r.id},'instr',this.value)"
                        style="font-size:11px">${instrOpts(r.instr)}</select></td>
            <td><button class="del-btn" onclick="removeDrug(${r.id})"
                        aria-label="Remove ${r.name}">
                    <i class="ti ti-trash" aria-hidden="true"></i>
                </button></td>
        </tr>`;
    }).join('');
    updateStats();
}

function updateStats() {
    document.getElementById('cntMed').textContent = rxItems.length;
    document.getElementById('cntQty').textContent = rxItems.reduce((s,r) => s + tqty(r), 0);
}

// ── Clear all ──────────────────────────────────────────────────────────
function clearAll() {
    rxItems = [];
    renderRx();
    ['apptNo','drugQ','clinicalNotes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('diagnosisSel').selectedIndex = 0;
    hidePatient();
    closeDrugList();
    showErr('errAppt', null);
    showErr('errSubmit', null);
}

// ── Error helper ───────────────────────────────────────────────────────
function showErr(id, msg) {
    const el = document.getElementById(id);
    if (msg) { el.textContent = msg; el.classList.add('visible'); }
    else      { el.textContent = '';  el.classList.remove('visible'); }
}

// ── Submit to pharmacy (AJAX → prescription_actions.php) ───────────────
async function submitRx() {
    showErr('errSubmit', null);

    const appt = document.getElementById('apptNo').value.trim().toUpperCase();
    if (!PATIENTS[appt]) {
        showErr('errSubmit', 'Please look up a valid appointment first.');
        return;
    }
    const diag = document.getElementById('diagnosisSel').value;
    if (!diag) {
        showErr('errSubmit', 'Please select a diagnosis.');
        return;
    }
    if (!rxItems.length) {
        showErr('errSubmit', 'Add at least one medicine before submitting.');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 ti-spin" aria-hidden="true"></i> Submitting…';

    const payload = {
        appt,
        diagnosis : diag,
        notes     : document.getElementById('clinicalNotes').value.trim(),
        medicines : rxItems.map(r => ({
            id   : r.id,
            name : r.name,
            M    : r.M, A: r.A, E: r.E, N: r.N,
            days : r.days,
            instr: r.instr,
        })),
    };

    try {
        const res  = await fetch('prescription_actions.php?action=submit_rx', {
            method  : 'POST',
            headers : {'Content-Type':'application/json'},
            body    : JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.ok) {
            showToast(`✅ Prescription ${data.rx_ref} sent to pharmacy queue.`);
            clearAll();
        } else {
            const errList = data.errors ? data.errors.join(' • ') : (data.error || 'Submission failed.');
            showErr('errSubmit', errList);
        }
    } catch (err) {
        showErr('errSubmit', 'Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-send" aria-hidden="true"></i> Submit to Pharmacy';
    }
}

// ── Toast ──────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 4000);
}

// ── Sticky nav (same as home.php) ──────────────────────────────────────
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
});

// ── Init ───────────────────────────────────────────────────────────────
setDate();
renderRx();
</script>

</body>
</html>
