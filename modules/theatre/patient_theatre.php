<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal — MediCare HMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <style>
        /* ── CSS Variables — identical to layout.css ── */
        :root {
            --primary:       #0a2540;
            --primary-mid:   #0d3361;
            --accent:        #0ea5e9;
            --accent-dark:   #0284c7;
            --accent-light:  #e0f2fe;
            --success:       #059669;
            --success-light: #d1fae5;
            --danger:        #dc2626;
            --danger-light:  #fee2e2;
            --warning:       #d97706;
            --warning-light: #fef3c7;
            --bg:            #f0f4f8;
            --surface:       #ffffff;
            --border:        #cbd5e1;
            --border-light:  #e2e8f0;
            --text:          #0f172a;
            --text-mid:      #334155;
            --muted:         #64748b;
            --shadow-sm:     0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05);
            --shadow:        0 4px 16px rgba(10,37,64,0.10);
            --shadow-lg:     0 12px 40px rgba(10,37,64,0.14);
            --radius-sm:     8px;
            --radius:        14px;
            --radius-lg:     20px;
            --font-body:     'DM Sans', sans-serif;
            --font-display:  'Playfair Display', serif;
            --transition:    0.2s ease;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            line-height: 1.65;
            font-size: 15px;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: var(--accent); transition: color var(--transition); }
        a:hover { color: var(--accent-dark); }
        h1,h2,h3,h4,h5 { font-weight:700; line-height:1.25; color:var(--text); }
        h3 { font-size:1.1rem; }

        /* ── Topbar — same as layout.css ── */
        .topbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: #fff;
            padding: 0 28px;
            height: 68px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(10,37,64,0.20);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-brand { display: flex; align-items: center; gap: 12px; }
        .topbar-logo {
            width: 38px; height: 38px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .topbar-title  { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
        .topbar-subtitle { font-size: 11px; color: rgba(255,255,255,0.65); margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .portal-chip {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            display: flex; align-items: center; gap: 7px;
        }

        /* ── Layout ── */
        .app-layout { display: flex; min-height: calc(100vh - 68px); }

        /* ── Sidebar ── */
        .sidebar {
            width: 258px;
            background: var(--surface);
            border-right: 1px solid var(--border-light);
            padding: 24px 16px;
            flex-shrink: 0;
        }
        .sidebar-section { margin-bottom: 6px; }
        .sidebar-heading {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: var(--muted); padding: 10px 12px 6px;
        }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: var(--radius-sm);
            color: var(--text-mid); font-size: 14px; font-weight: 500;
            transition: all var(--transition);
        }
        .sidebar-menu a:hover { background: var(--accent-light); color: var(--accent-dark); }
        .sidebar-menu a.active { background: var(--accent); color: #fff; }
        .sidebar-menu .icon { font-size: 16px; width: 20px; text-align: center; }
        .sidebar-divider { height: 1px; background: var(--border-light); margin: 12px 0; }
        .patient-info-box {
            background: var(--bg);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 16px;
            text-align: center;
        }
        .patient-avatar {
            width: 52px; height: 52px; border-radius: 50%;
            background: var(--accent-light);
            color: var(--accent-dark);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 18px;
            margin: 0 auto 8px;
        }
        .patient-avatar.hidden { display: none; }

        /* ── Main content ── */
        .main-content { flex: 1; padding: 32px; overflow: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
        .page-header h2 { font-size: 1.6rem; margin-bottom: 4px; }
        .page-header p { color: var(--muted); font-size: 14px; }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            margin-bottom: 20px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 16px;
            border-bottom: 1px solid var(--border-light);
        }
        .card-header h3 { font-size: 1.05rem; }

        /* ── Stat Cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 22px 24px;
            display: flex; align-items: center; gap: 18px;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition);
        }
        .stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue   { background: var(--accent-light); }
        .stat-icon.green  { background: var(--success-light); }
        .stat-icon.yellow { background: var(--warning-light); }
        .stat-icon.red    { background: var(--danger-light); }
        .stat-label { font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 1.9rem; font-weight: 700; line-height: 1.2; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 14px; font-weight: 600;
            border: none; cursor: pointer; transition: all var(--transition);
            text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(14,165,233,0.35); }
        .btn-secondary { background: var(--surface); color: var(--text-mid); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg); color: var(--text); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #047857; color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-sm { padding: 6px 14px; font-size: 13px; }

        /* ── Badges — same as project ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600; letter-spacing: 0.02em;
        }
        .badge::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
        .badge-info    { background: var(--accent-light);   color: var(--accent-dark);  }
        .badge-info::before    { background: var(--accent); }
        .badge-success { background: var(--success-light);  color: var(--success);      }
        .badge-success::before { background: var(--success); }
        .badge-warning { background: var(--warning-light);  color: var(--warning);      }
        .badge-warning::before { background: var(--warning); }
        .badge-danger  { background: var(--danger-light);   color: var(--danger);       }
        .badge-danger::before  { background: var(--danger); }
        .badge-neutral { background: var(--bg);             color: var(--muted);        }
        .badge-neutral::before { background: var(--muted); }

        /* ── Search Box ── */
        .search-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            border-radius: var(--radius);
            padding: 28px 28px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        .search-section h3 { color: #fff; margin-bottom: 6px; font-size: 1.1rem; }
        .search-section p  { color: rgba(255,255,255,0.7); font-size: 13px; margin-bottom: 16px; }
        .search-row { display: flex; gap: 10px; }
        .search-row input {
            flex: 1;
            padding: 11px 16px;
            border-radius: var(--radius-sm);
            border: 1.5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-family: var(--font-body);
            font-size: 14px;
            outline: none;
            transition: all var(--transition);
        }
        .search-row input::placeholder { color: rgba(255,255,255,0.55); }
        .search-row input:focus { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.5); }
        .hint-text { font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 10px; }
        .hint-text span { cursor:pointer; color: rgba(255,255,255,0.8); text-decoration: underline; }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
        }
        .info-box {
            padding: 14px;
            background: var(--bg);
            border-radius: var(--radius-sm);
        }
        .info-box label {
            font-size: 11px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px;
        }
        .info-box span { font-weight: 600; color: var(--text); font-size: 14px; }

        /* ── Operations Table ── */
        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .data-table th {
            text-align: left; background: var(--bg);
            padding: 12px 14px; font-size: 11px; font-weight: 700;
            color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-light);
        }
        .data-table td {
            padding: 14px; border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: var(--bg); }

        /* ── Theatre Badge ── */
        .theatre-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: var(--radius-sm);
            background: var(--accent-light); color: var(--accent-dark);
            font-size: 12px; font-weight: 600; white-space: nowrap;
        }

        /* ── Blood Badge ── */
        .blood-badge {
            display: inline-block;
            padding: 3px 10px; border-radius: 6px;
            background: var(--danger-light); color: var(--danger);
            font-weight: 700; font-size: 13px;
        }

        /* ── Team Row ── */
        .team-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px; background: var(--bg); border-radius: var(--radius-sm);
            margin-bottom: 10px;
        }
        .team-row:last-child { margin-bottom: 0; }
        .team-icon { font-size: 22px; width: 34px; text-align: center; }

        /* ── Notes Section ── */
        .note-block {
            padding: 14px; border-radius: var(--radius-sm);
            font-size: 14px; color: var(--text-mid); line-height: 1.7;
            margin-bottom: 12px;
        }
        .note-label {
            font-size: 11px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;
        }

        /* ── AI Summary ── */
        .ai-panel {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: var(--radius);
            padding: 20px 22px;
            margin-bottom: 20px;
        }
        .ai-label {
            font-size: 11px; font-weight: 700; color: var(--accent-dark);
            text-transform: uppercase; letter-spacing: 0.6px;
            margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
        }
        .ai-text { font-size: 14px; color: var(--text-mid); line-height: 1.75; min-height: 24px; }

        /* ── Loading dots ── */
        .loading-dots { display: inline-flex; gap: 4px; align-items: center; }
        .loading-dots span {
            width: 7px; height: 7px; border-radius: 50%; background: var(--accent);
            animation: ldot 1.2s ease-in-out infinite;
        }
        .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes ldot { 0%,100%{opacity:0.3;transform:scale(0.8)} 50%{opacity:1;transform:scale(1)} }

        /* ── Empty / Error states ── */
        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty-state .big-icon { font-size: 48px; margin-bottom: 14px; }
        .alert {
            padding: 14px 18px; border-radius: var(--radius-sm);
            font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .alert-danger  { background: var(--danger-light);  color: var(--danger); border: 1px solid #fca5a5; }
        .alert-success { background: var(--success-light); color: var(--success); border: 1px solid #6ee7b7; }

        /* ── Detail view layout ── */
        .detail-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; }
        .detail-col  { display: flex; flex-direction: column; gap: 20px; }
        .schedule-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 14px; background: var(--bg); border-radius: var(--radius-sm);
            margin-bottom: 8px;
        }
        .schedule-row:last-child { margin-bottom: 0; }
        .schedule-key { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        .schedule-val { font-weight: 700; font-size: 14px; }

        /* ── Download button ── */
        #downloadBtn { display: none; }

        /* ── Animations ── */
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideIn 0.35s ease both; }

        /* ── Responsive ── */
        @media (max-width: 960px) {
            .detail-grid { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
        @media (max-width: 640px) {
            .main-content { padding: 20px 16px; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- ── Topbar ── -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="topbar-logo">M</div>
        <div>
            <div class="topbar-title">MediCare HMS</div>
            <div class="topbar-subtitle">Hospital Management System</div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="portal-chip">🏥 Patient Portal</div>
    </div>
</header>

<div class="app-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div id="sidebarPatientBox" class="patient-info-box" style="display:none;">
            <div class="patient-avatar" id="sidebarAvatar"></div>
            <div style="font-weight:700; font-size:14px;" id="sidebarName"></div>
            <div style="font-size:12px; color:var(--muted); margin-top:3px;" id="sidebarID"></div>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-heading">Navigation</div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active" onclick="showView('search'); return false;">
                    <span class="icon">🔍</span> Search Patient
                </a></li>
                <li><a href="#" id="navProfile" onclick="showView('profile'); return false;" style="display:none;">
                    <span class="icon">👤</span> My Profile
                </a></li>
                <li><a href="#" id="navOps" onclick="showView('operations'); return false;" style="display:none;">
                    <span class="icon">🔬</span> My Operations
                </a></li>
            </ul>
        </div>

        <div class="sidebar-divider"></div>

        <div class="sidebar-section">
            <div class="sidebar-heading">Theatre Info</div>
            <ul class="sidebar-menu">
                <li><a href="#" onclick="return false;"><span class="icon">🏥</span> Theatre 1 – General</a></li>
                <li><a href="#" onclick="return false;"><span class="icon">🚨</span> Theatre 2 – Emergency</a></li>
                <li><a href="#" onclick="return false;"><span class="icon">👶</span> Theatre 3 – Labour</a></li>
                <li><a href="#" onclick="return false;"><span class="icon">🔧</span> Theatre 4 – Minor</a></li>
            </ul>
        </div>

        <div class="sidebar-divider"></div>

        <div style="padding:12px; font-size:12px; color:var(--muted); line-height:1.8;">
            <div><strong>Helpline:</strong> 011-220-3040</div>
            <div><strong>Emergency:</strong> 011-220-9999</div>
            <div style="margin-top:6px;">MediCare HMS · Group 05</div>
        </div>
    </aside>

    <!-- ── Main Content ── -->
    <main class="main-content">

        <!-- ═══════════════════════════════════════════
             VIEW 1 — SEARCH
        ════════════════════════════════════════════ -->
        <div id="view-search">
            <div class="page-header">
                <div>
                    <h2>🔍 Patient Theatre Portal</h2>
                    <p>Search by Patient ID or NIC to view your theatre schedule and operation details</p>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-section">
                <h3>Find Your Schedule</h3>
                <p>Enter your Patient ID or National Identity Card number below</p>
                <div class="search-row">
                    <input type="text" id="searchInput" placeholder="e.g. P001 or 199012345678" autocomplete="off">
                    <button class="btn btn-primary" onclick="runSearch()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="hint-text">
                    Demo — try:
                    <span onclick="quickSearch('P001')">P001</span> ·
                    <span onclick="quickSearch('P002')">P002</span> ·
                    <span onclick="quickSearch('200435800944')">NIC: 200435800944</span> ·
                    <span onclick="quickSearch('P004')">P004</span> ·
                    <span onclick="quickSearch('P005')">P005</span>
                </div>
            </div>

            <!-- Error -->
            <div id="searchError" class="alert alert-danger" style="display:none;">
                <i class="fas fa-exclamation-circle"></i>
                <span id="searchErrorMsg"></span>
            </div>

            <!-- Stats (visible after search) -->
            <div id="statsSection" style="display:none;" class="fade-in">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">🔬</div>
                        <div>
                            <div class="stat-label">Total Operations</div>
                            <div class="stat-value" id="statTotal">0</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow">📅</div>
                        <div>
                            <div class="stat-label">Upcoming</div>
                            <div class="stat-value" id="statUpcoming">0</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">✅</div>
                        <div>
                            <div class="stat-label">Completed</div>
                            <div class="stat-value" id="statCompleted">0</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">🏥</div>
                        <div>
                            <div class="stat-label">Assigned Theatre</div>
                            <div class="stat-value" id="statTheatre" style="font-size:1.2rem;">–</div>
                        </div>
                    </div>
                </div>

                <!-- Quick summary card -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h3>👤 Patient Overview</h3>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-secondary btn-sm" onclick="showView('profile')">
                                <i class="fas fa-id-card"></i> Full Profile
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="showView('operations')">
                                <i class="fas fa-list"></i> View Operations
                            </button>
                            <button class="btn btn-success btn-sm" id="downloadBtn" onclick="downloadPDF()">
                                <i class="fas fa-file-pdf"></i> Download Report
                            </button>
                        </div>
                    </div>
                    <div class="info-grid">
                        <div class="info-box"><label>Patient ID</label><span id="qPatID"></span></div>
                        <div class="info-box"><label>NIC Number</label><span id="qNIC"></span></div>
                        <div class="info-box"><label>Gender</label><span id="qGender"></span></div>
                        <div class="info-box"><label>Blood Type</label><span id="qBlood"></span></div>
                        <div class="info-box"><label>Contact</label><span id="qPhone"></span></div>
                        <div class="info-box"><label>Emergency Contact</label><span id="qEmergency"></span></div>
                    </div>
                </div>

                <!-- AI Panel -->
                <div class="ai-panel fade-in">
                    <div class="ai-label">✨ AI Summary</div>
                    <div class="ai-text" id="aiText">
                        <div class="loading-dots"><span></span><span></span><span></span></div>
                    </div>
                </div>

                <!-- Operations quick list -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h3>🔬 Scheduled Operations</h3>
                        <span class="badge badge-info" id="opsCountBadge">0 records</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Operation Type</th>
                                    <th>Theatre</th>
                                    <th>Date & Time</th>
                                    <th>Lead Surgeon</th>
                                    <th>Anaesthetist</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="opTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════
             VIEW 2 — FULL PROFILE
        ════════════════════════════════════════════ -->
        <div id="view-profile" style="display:none;">
            <div class="page-header">
                <div>
                    <h2>👤 Patient Profile</h2>
                    <p id="profSubtitle"></p>
                </div>
                <button class="btn btn-secondary" onclick="showView('search')">← Back</button>
            </div>
            <div class="detail-grid">
                <div class="detail-col">
                    <div class="card">
                        <div class="card-header"><h3>Personal Information</h3></div>
                        <div class="info-grid">
                            <div class="info-box"><label>Full Name</label><span id="pName"></span></div>
                            <div class="info-box"><label>Patient ID</label><span id="pID"></span></div>
                            <div class="info-box"><label>NIC</label><span id="pNIC"></span></div>
                            <div class="info-box"><label>Date of Birth</label><span id="pDOB"></span></div>
                            <div class="info-box"><label>Gender</label><span id="pGender"></span></div>
                            <div class="info-box"><label>Blood Type</label><span id="pBlood"></span></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Contact Details</h3></div>
                        <div class="info-grid">
                            <div class="info-box"><label>Phone</label><span id="pPhone"></span></div>
                            <div class="info-box"><label>Email</label><span id="pEmail"></span></div>
                            <div class="info-box" style="grid-column:1/-1;"><label>Address</label><span id="pAddress"></span></div>
                            <div class="info-box" style="grid-column:1/-1;"><label>Emergency Contact</label><span id="pEmergency"></span></div>
                        </div>
                    </div>
                </div>
                <div class="detail-col">
                    <div class="card" style="text-align:center; padding:32px 24px;">
                        <div class="patient-avatar" id="profAvatar" style="width:72px;height:72px;font-size:26px;margin:0 auto 12px;"></div>
                        <div style="font-weight:700;font-size:1.1rem;" id="profAvatarName"></div>
                        <div style="color:var(--muted);font-size:13px;margin-top:4px;" id="profAvatarSub"></div>
                        <div style="margin-top:16px;" id="profAvatarBlood"></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>🏥 Operations Summary</h3></div>
                        <div style="display:flex;flex-direction:column;gap:10px;" id="profOpsSummary"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════
             VIEW 3 — OPERATIONS DETAIL
        ════════════════════════════════════════════ -->
        <div id="view-operations" style="display:none;">
            <div class="page-header">
                <div>
                    <h2>🔬 My Operations</h2>
                    <p id="opsSubtitle"></p>
                </div>
                <button class="btn btn-secondary" onclick="showView('search')">← Back</button>
            </div>
            <div id="opDetailList"></div>
        </div>

    </main>
</div>

<!-- ══════════════════════════════════════
     DEMO DATABASE (mirrors hospital_db.sql schema)
══════════════════════════════════════════ -->
<script>
// ── Theatre labels — identical to PHP theatreLabels map ──
const THEATRE_LABELS = {
    1: { icon:'🏥', name:'Theatre 1', type:'General' },
    2: { icon:'🚨', name:'Theatre 2', type:'Emergency' },
    3: { icon:'👶', name:'Theatre 3', type:'Labour' },
    4: { icon:'🔧', name:'Theatre 4', type:'Minor' },
};

// ── Badge class map — identical to PHP $badgeMap ──
const BADGE_MAP = {
    scheduled:   'badge-info',
    confirmed:   'badge-success',
    in_progress: 'badge-warning',
    completed:   'badge-success',
    cancelled:   'badge-danger',
    transferred: 'badge-neutral',
};

// ── Demo data (mirrors patients + users + theatre_operations tables) ──
const PATIENTS_DB = {
    'P001': {
        patient_id: 1, patient_code: 'P001',
        nic: '199012345678', full_name: 'Kamal Perera',
        dob: '1990-03-15', gender: 'Male',
        blood_type: 'O+', phone: '071-234-5678',
        email: 'kamal.p@gmail.com',
        address: '42 Kandy Road, Colombo 10',
        emergency_contact: 'Sumedha Perera · 077-111-2222',
        ops: [
            {
                operation_id: 101, operation_type: 'Appendectomy',
                theatre_number: 2, scheduled_date: '2026-05-30',
                scheduled_time: '08:30', status: 'confirmed',
                surgeon_name: 'Dr. Nimal Silva', surgeon_spec: 'General Surgery',
                anaesthetist_name: 'Dr. Shani Weerasinghe',
                assistant_name: null,
                pre_op_notes: 'Patient fasted 12 hours. Vitals stable. Pre-op bloods done.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'General Ward',
                created_by_name: 'Admin',
            },
            {
                operation_id: 102, operation_type: 'Post-op Suture Removal',
                theatre_number: 2, scheduled_date: '2026-06-07',
                scheduled_time: '10:00', status: 'scheduled',
                surgeon_name: 'Dr. Nimal Silva', surgeon_spec: 'General Surgery',
                anaesthetist_name: null,
                assistant_name: null,
                pre_op_notes: 'Review wound healing and remove sutures.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: null,
                created_by_name: 'Admin',
            }
        ]
    },
    'P002': {
        patient_id: 2, patient_code: 'P002',
        nic: '198756789012', full_name: 'Nilanthi Silva',
        dob: '1987-11-08', gender: 'Female',
        blood_type: 'A-', phone: '077-876-5432',
        email: 'nilanthi.s@yahoo.com',
        address: '15 Galle Road, Dehiwala',
        emergency_contact: 'Rohan Silva · 071-333-4444',
        ops: [
            {
                operation_id: 201, operation_type: 'Right Knee Replacement',
                theatre_number: 1, scheduled_date: '2026-06-03',
                scheduled_time: '07:00', status: 'confirmed',
                surgeon_name: 'Dr. Kamal Perera', surgeon_spec: 'Orthopedics',
                anaesthetist_name: 'Dr. Shani Weerasinghe',
                assistant_name: 'Dr. Anura Bandara',
                pre_op_notes: 'Physiotherapy completed. X-rays reviewed. Consent signed.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'ICU',
                created_by_name: 'Admin',
            },
            {
                operation_id: 202, operation_type: 'Physiotherapy Assessment',
                theatre_number: 1, scheduled_date: '2026-06-20',
                scheduled_time: '14:00', status: 'scheduled',
                surgeon_name: 'Dr. Kamal Perera', surgeon_spec: 'Orthopedics',
                anaesthetist_name: null,
                assistant_name: null,
                pre_op_notes: 'First post-op physiotherapy session.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'General Ward',
                created_by_name: 'Admin',
            }
        ]
    },
    '199012345678': 'P001',  // NIC alias
    '198756789012': 'P002',
    '200435800944': 'P003',
    '199534561230': 'P004',
    '198023456789': 'P005',
    'P003': {
        patient_id: 3, patient_code: 'P003',
        nic: '200435800944', full_name: 'Ashan Fernando',
        dob: '2004-02-14', gender: 'Male',
        blood_type: 'B+', phone: '076-543-2109',
        email: 'ashan.f@gmail.com',
        address: '88 Temple Road, Nugegoda',
        emergency_contact: 'Kumari Fernando · 070-555-6666',
        ops: [
            {
                operation_id: 301, operation_type: 'Tonsillectomy',
                theatre_number: 3, scheduled_date: '2026-06-10',
                scheduled_time: '09:00', status: 'scheduled',
                surgeon_name: 'Dr. Kumari Jayawardena', surgeon_spec: 'Gynecology',
                anaesthetist_name: 'Dr. Shani Weerasinghe',
                assistant_name: null,
                pre_op_notes: 'ENT evaluation complete. No contraindications found.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'General Ward',
                created_by_name: 'Reception',
            }
        ]
    },
    'P004': {
        patient_id: 4, patient_code: 'P004',
        nic: '199534561230', full_name: 'Chamari Jayawardena',
        dob: '1995-07-22', gender: 'Female',
        blood_type: 'AB+', phone: '070-112-3344',
        email: 'chamari.j@hotmail.com',
        address: '5 Lake View, Battaramulla',
        emergency_contact: 'Dinesh Jayawardena · 071-777-8888',
        ops: [
            {
                operation_id: 401, operation_type: 'Laparoscopic Cholecystectomy',
                theatre_number: 2, scheduled_date: '2026-06-05',
                scheduled_time: '11:00', status: 'confirmed',
                surgeon_name: 'Dr. Kumari Jayawardena', surgeon_spec: 'Gynecology',
                anaesthetist_name: 'Dr. Shani Weerasinghe',
                assistant_name: 'Dr. Nimal Silva',
                pre_op_notes: 'Ultrasound confirmed gallstones. Anaesthetic clearance done.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'HDU',
                created_by_name: 'Admin',
            },
            {
                operation_id: 402, operation_type: 'Cardiac Clearance',
                theatre_number: 2, scheduled_date: '2026-05-14',
                scheduled_time: '08:00', status: 'completed',
                surgeon_name: 'Dr. Anura Bandara', surgeon_spec: 'Cardiology',
                anaesthetist_name: null,
                assistant_name: null,
                pre_op_notes: 'ECG and echocardiogram completed.',
                post_op_notes: 'Clearance granted. No cardiac contraindications.',
                recovery_instructions: 'No special restrictions.',
                post_op_room_type: null,
                created_by_name: 'Admin',
            }
        ]
    },
    'P005': {
        patient_id: 5, patient_code: 'P005',
        nic: '198023456789', full_name: 'Ruwan Bandara',
        dob: '1980-09-05', gender: 'Male',
        blood_type: 'O-', phone: '078-900-1122',
        email: 'ruwan.b@gmail.com',
        address: '22 Hill Street, Kandy',
        emergency_contact: 'Sandhya Bandara · 077-222-3333',
        ops: [
            {
                operation_id: 501, operation_type: 'Coronary Artery Bypass Graft',
                theatre_number: 1, scheduled_date: '2026-06-15',
                scheduled_time: '06:30', status: 'confirmed',
                surgeon_name: 'Dr. Anura Bandara', surgeon_spec: 'Cardiology',
                anaesthetist_name: 'Dr. Shani Weerasinghe',
                assistant_name: 'Dr. Kamal Perera',
                pre_op_notes: 'Angiogram done. 3-vessel disease confirmed. High risk consent obtained.',
                post_op_notes: null,
                recovery_instructions: null,
                post_op_room_type: 'Cardiac ICU',
                created_by_name: 'Admin',
            }
        ]
    }
};

// ── State ──
let currentPatient = null;

// ── Helpers ──
function initials(name) {
    return name.split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase();
}
function fmtDate(d) {
    return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
}
function fmtTime(t) {
    const [h,m] = t.split(':');
    const hh = parseInt(h);
    return (hh%12||12)+':'+m+' '+(hh<12?'AM':'PM');
}
function theatreLabel(n) {
    const t = THEATRE_LABELS[n];
    return t ? `${t.icon} ${t.name} (${t.type})` : `Theatre ${n}`;
}
function theatreChip(n) {
    const t = THEATRE_LABELS[n];
    return t ? `<span class="theatre-chip">${t.icon} ${t.name}</span>` : `Theatre ${n}`;
}
function badgeHtml(status) {
    const cls = BADGE_MAP[status] || 'badge-neutral';
    const label = status.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
    return `<span class="badge ${cls}">${label}</span>`;
}
function avatarColors(idx) {
    const sets = [
        ['#dbeafe','#1d4ed8'],['#fce7f3','#be185d'],
        ['#dcfce7','#15803d'],['#fef3c7','#b45309'],['#ede9fe','#6d28d9']
    ];
    return sets[idx % sets.length];
}

// ── Quick search hints ──
function quickSearch(val) {
    document.getElementById('searchInput').value = val;
    runSearch();
}

// ── Show / hide views ──
function showView(v) {
    ['search','profile','operations'].forEach(id => {
        document.getElementById('view-'+id).style.display = id===v ? '' : 'none';
    });
}

// ── Main search ──
function runSearch() {
    const q = document.getElementById('searchInput').value.trim();
    document.getElementById('searchError').style.display = 'none';
    document.getElementById('statsSection').style.display = 'none';

    if (!q) {
        showErr('Please enter a Patient ID or NIC number.');
        return;
    }

    // Resolve NIC alias
    let key = q.toUpperCase();
    if (!PATIENTS_DB[key]) key = q; // try original (NIC is lowercase)
    let p = PATIENTS_DB[key];
    if (typeof p === 'string') p = PATIENTS_DB[p]; // resolve alias

    if (!p) {
        showErr(`No patient found for "${q}". Please check your Patient ID or NIC and try again.`);
        return;
    }

    currentPatient = p;
    renderResults(p);
    buildProfileView(p);
    buildOperationsView(p);
    document.getElementById('statsSection').style.display = '';
    document.getElementById('downloadBtn').style.display = '';

    // Sidebar
    const [bg,fg] = avatarColors(p.patient_id);
    const sb = document.getElementById('sidebarPatientBox');
    sb.style.display = '';
    document.getElementById('sidebarAvatar').textContent = initials(p.full_name);
    document.getElementById('sidebarAvatar').style.cssText = `background:${bg};color:${fg};width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;margin:0 auto 8px;`;
    document.getElementById('sidebarName').textContent = p.full_name;
    document.getElementById('sidebarID').textContent = p.patient_code + ' · ' + p.blood_type;
    document.getElementById('navProfile').style.display = '';
    document.getElementById('navOps').style.display = '';

    // Trigger AI summary
    callAI(p);
}

function showErr(msg) {
    document.getElementById('searchErrorMsg').textContent = msg;
    document.getElementById('searchError').style.display = 'flex';
}

// ── Render search results ──
function renderResults(p) {
    const upcoming  = p.ops.filter(o=>['scheduled','confirmed','in_progress'].includes(o.status)).length;
    const completed = p.ops.filter(o=>o.status==='completed').length;
    const theatres  = [...new Set(p.ops.map(o=>o.theatre_number))];
    const tLabel    = theatres.length===1 ? `T${theatres[0]}` : `T${theatres.join(', T')}`;

    document.getElementById('statTotal').textContent    = p.ops.length;
    document.getElementById('statUpcoming').textContent = upcoming;
    document.getElementById('statCompleted').textContent= completed;
    document.getElementById('statTheatre').textContent  = tLabel;

    document.getElementById('qPatID').textContent     = p.patient_code;
    document.getElementById('qNIC').textContent       = p.nic;
    document.getElementById('qGender').textContent    = p.gender;
    document.getElementById('qBlood').innerHTML       = `<span class="blood-badge">${p.blood_type}</span>`;
    document.getElementById('qPhone').textContent     = p.phone;
    document.getElementById('qEmergency').textContent = p.emergency_contact;
    document.getElementById('opsCountBadge').textContent = p.ops.length + ' records';

    // Operations table
    const tbody = document.getElementById('opTableBody');
    tbody.innerHTML = p.ops.map(op => `
        <tr>
            <td><strong>#${op.operation_id}</strong></td>
            <td><strong>${op.operation_type}</strong></td>
            <td>${theatreChip(op.theatre_number)}</td>
            <td>
                <strong>${fmtDate(op.scheduled_date)}</strong><br>
                <span style="color:var(--muted);font-size:12px;">${fmtTime(op.scheduled_time)}</span>
            </td>
            <td>${op.surgeon_name}</td>
            <td>${op.anaesthetist_name || '<span style="color:var(--muted)">–</span>'}</td>
            <td>${badgeHtml(op.status)}</td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="showView('operations')">
                    <i class="fas fa-eye"></i> Details
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Build Profile View ──
function buildProfileView(p) {
    document.getElementById('profSubtitle').textContent = p.patient_code + ' · ' + p.full_name;
    document.getElementById('pName').textContent    = p.full_name;
    document.getElementById('pID').textContent      = p.patient_code;
    document.getElementById('pNIC').textContent     = p.nic;
    document.getElementById('pDOB').textContent     = fmtDate(p.dob);
    document.getElementById('pGender').textContent  = p.gender;
    document.getElementById('pBlood').innerHTML     = `<span class="blood-badge">${p.blood_type}</span>`;
    document.getElementById('pPhone').textContent   = p.phone;
    document.getElementById('pEmail').textContent   = p.email;
    document.getElementById('pAddress').textContent = p.address;
    document.getElementById('pEmergency').textContent = p.emergency_contact;

    const [bg,fg] = avatarColors(p.patient_id);
    const av = document.getElementById('profAvatar');
    av.textContent = initials(p.full_name);
    av.style.cssText = `background:${bg};color:${fg};width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:26px;margin:0 auto 12px;`;
    document.getElementById('profAvatarName').textContent = p.full_name;
    document.getElementById('profAvatarSub').textContent  = p.patient_code + ' · ' + p.gender;
    document.getElementById('profAvatarBlood').innerHTML  = `<span class="blood-badge" style="font-size:15px;padding:5px 14px;">${p.blood_type}</span>`;

    document.getElementById('profOpsSummary').innerHTML = p.ops.map(op=>`
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--bg);border-radius:var(--radius-sm);">
            <div>
                <div style="font-weight:600;font-size:13px;">${op.operation_type}</div>
                <div style="font-size:12px;color:var(--muted);">${fmtDate(op.scheduled_date)} · ${theatreLabel(op.theatre_number)}</div>
            </div>
            ${badgeHtml(op.status)}
        </div>
    `).join('');
}

// ── Build Operations Detail View ──
function buildOperationsView(p) {
    document.getElementById('opsSubtitle').textContent = p.full_name + ' · ' + p.ops.length + ' operation(s)';

    document.getElementById('opDetailList').innerHTML = p.ops.map(op => {
        const tl = THEATRE_LABELS[op.theatre_number] || {icon:'🔬',name:'Theatre '+op.theatre_number,type:''};
        const badgeCls = BADGE_MAP[op.status] || 'badge-neutral';
        const isCompleted = op.status === 'completed';

        return `
        <div class="card fade-in" style="margin-bottom:24px;">

            <!-- Status Banner -->
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;background:var(--bg);border-radius:var(--radius-sm);margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:14px;">
                    <span style="font-size:32px;">${tl.icon}</span>
                    <div>
                        <div style="font-size:17px;font-weight:700;">${op.operation_type}</div>
                        <div style="color:var(--muted);font-size:13px;">${tl.name} (${tl.type}) &mdash; ${fmtDate(op.scheduled_date)} at ${fmtTime(op.scheduled_time)}</div>
                    </div>
                </div>
                <span class="badge ${badgeCls}" style="font-size:13px;padding:6px 14px;">${op.status.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase())}</span>
            </div>

            <div class="detail-grid">
                <!-- Left -->
                <div class="detail-col">

                    <!-- Surgical Team -->
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">👨‍⚕️ Surgical Team</div>
                        <div class="team-row">
                            <span class="team-icon">🔬</span>
                            <div>
                                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.4px;">Lead Surgeon</div>
                                <div style="font-weight:600;">${op.surgeon_name} — ${op.surgeon_spec}</div>
                            </div>
                        </div>
                        <div class="team-row">
                            <span class="team-icon">💉</span>
                            <div>
                                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.4px;">Anaesthesiologist</div>
                                <div style="font-weight:600;color:${op.anaesthetist_name?'var(--text)':'var(--muted)'};">${op.anaesthetist_name || 'Not Assigned'}</div>
                            </div>
                        </div>
                        <div class="team-row">
                            <span class="team-icon">🤝</span>
                            <div>
                                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.4px;">Assisting Doctor</div>
                                <div style="font-weight:600;color:${op.assistant_name?'var(--text)':'var(--muted)'};">${op.assistant_name || 'Not Assigned'}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Clinical Notes -->
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">📋 Clinical Notes</div>
                        <div class="note-label">Pre-Op Notes</div>
                        <div class="note-block" style="background:var(--bg);">${op.pre_op_notes || '<span style="color:var(--muted)">No pre-op notes recorded.</span>'}</div>
                        ${op.post_op_notes ? `
                            <div class="note-label">Post-Op Notes</div>
                            <div class="note-block" style="background:var(--success-light);">${op.post_op_notes}</div>
                        ` : ''}
                        ${op.recovery_instructions ? `
                            <div class="note-label">Recovery Instructions</div>
                            <div class="note-block" style="background:var(--accent-light);">${op.recovery_instructions}</div>
                        ` : ''}
                    </div>
                </div>

                <!-- Right -->
                <div class="detail-col">

                    <!-- Schedule -->
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">🗓️ Schedule Info</div>
                        <div class="schedule-row"><span class="schedule-key">Date</span><span class="schedule-val">${fmtDate(op.scheduled_date)}</span></div>
                        <div class="schedule-row"><span class="schedule-key">Time</span><span class="schedule-val">${fmtTime(op.scheduled_time)}</span></div>
                        <div class="schedule-row"><span class="schedule-key">Theatre</span><span class="schedule-val">${tl.icon} ${tl.name}</span></div>
                        <div class="schedule-row"><span class="schedule-key">Type</span><span class="schedule-val">${tl.type}</span></div>
                    </div>

                    <!-- Post-op room -->
                    ${op.post_op_room_type ? `
                    <div style="padding:16px;background:var(--success-light);border:1.5px solid var(--success);border-radius:var(--radius-sm);">
                        <div style="font-weight:700;color:#065f46;margin-bottom:6px;">🏥 Post-Op Transfer</div>
                        <div style="font-size:13px;color:#065f46;margin-bottom:10px;">Patient transferred to:</div>
                        <span class="badge badge-success" style="font-size:13px;padding:6px 14px;">${op.post_op_room_type}</span>
                    </div>
                    ` : ''}

                    <!-- Billing (if completed) -->
                    ${isCompleted ? `
                    <div style="padding:16px;background:var(--accent-light);border:1.5px solid var(--accent);border-radius:var(--radius-sm);">
                        <div style="font-weight:700;color:#075985;margin-bottom:6px;">💳 Billing Auto-Added</div>
                        <div style="font-size:13px;color:#075985;margin-bottom:10px;">Theatre fees added to patient invoice:</div>
                        ${['Surgery Fee','Theatre Usage Fee','Anaesthesia Fee','Recovery Charge'].map(c=>`
                            <div style="font-size:12px;color:#075985;display:flex;align-items:center;gap:6px;margin-bottom:4px;">✓ ${c}</div>
                        `).join('')}
                    </div>
                    ` : ''}

                    <!-- Meta -->
                    <div style="font-size:12px;color:var(--muted);line-height:2;padding:14px;background:var(--bg);border-radius:var(--radius-sm);">
                        <div><strong>Operation ID:</strong> #${op.operation_id}</div>
                        <div><strong>Created By:</strong> ${op.created_by_name || '–'}</div>
                    </div>
                </div>
            </div>
        </div>
        `;
    }).join('');
}

// ── AI Summary (Anthropic API) ──
async function callAI(p) {
    document.getElementById('aiText').innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';

    const prompt = `You are a warm, caring patient-facing assistant at MediCare Hospital in Sri Lanka. A patient is checking their operation theatre schedule on the patient portal.

Write a short (2–3 sentence), friendly, reassuring summary of their upcoming operations. Use simple English — no medical jargon. Be warm and supportive.

Patient: ${p.full_name} (${p.gender}, Blood Type: ${p.blood_type})
Operations:
${p.ops.map(o=>`- ${o.operation_type} on ${fmtDate(o.scheduled_date)} at ${fmtTime(o.scheduled_time)} in Theatre ${o.theatre_number} by ${o.surgeon_name}. Status: ${o.status}.`).join('\n')}`;

    try {
        const res = await fetch('https://api.anthropic.com/v1/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: 'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages: [{ role: 'user', content: prompt }]
            })
        });
        const data = await res.json();
        const text = (data.content||[]).map(b=>b.text||'').join('') || 'Summary unavailable.';
        // Typewriter effect
        const el = document.getElementById('aiText');
        el.textContent = '';
        let i = 0;
        const iv = setInterval(()=>{
            el.textContent = text.slice(0,i);
            i += 4;
            if (i >= text.length) { el.textContent = text; clearInterval(iv); }
        }, 16);
    } catch(e) {
        document.getElementById('aiText').textContent = 'AI summary unavailable at this time.';
    }
}

// ── Download PDF (jsPDF — matches theatre_report.php style) ──
function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const p = currentPatient;
    const doc = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });

    // Header
    doc.setFillColor(10, 37, 64);
    doc.rect(0, 0, 210, 28, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16); doc.setFont('helvetica','bold');
    doc.text('MediCare HMS — Patient Theatre Report', 14, 12);
    doc.setFontSize(9); doc.setFont('helvetica','normal');
    doc.text('Generated: ' + new Date().toLocaleString('en-GB'), 14, 20);
    doc.text('Hospital Management System | Group 05', 210-14, 20, { align:'right' });

    // Patient info
    doc.setTextColor(0); doc.setFontSize(12); doc.setFont('helvetica','bold');
    doc.text('Patient Information', 14, 38);
    doc.setDrawColor(200); doc.line(14, 40, 196, 40);

    const rows = [
        ['Name', p.full_name], ['Patient ID', p.patient_code],
        ['NIC', p.nic], ['Date of Birth', fmtDate(p.dob)],
        ['Gender', p.gender], ['Blood Type', p.blood_type],
        ['Phone', p.phone], ['Address', p.address],
        ['Emergency Contact', p.emergency_contact]
    ];
    doc.autoTable({
        startY: 43, head: [], body: rows,
        theme: 'plain',
        styles: { fontSize: 10, cellPadding: 3 },
        columnStyles: { 0: { fontStyle:'bold', cellWidth: 55, textColor:[100,116,139] }, 1:{ cellWidth:130 } }
    });

    // Operations table
    let y = doc.lastAutoTable.finalY + 10;
    doc.setFontSize(12); doc.setFont('helvetica','bold'); doc.setTextColor(0);
    doc.text('Scheduled Operations', 14, y);
    doc.line(14, y+2, 196, y+2);

    doc.autoTable({
        startY: y + 5,
        head: [['#ID','Operation','Theatre','Date','Time','Surgeon','Status']],
        body: p.ops.map(o=>[
            '#'+o.operation_id, o.operation_type,
            'T'+o.theatre_number+' ('+THEATRE_LABELS[o.theatre_number]?.type+')',
            fmtDate(o.scheduled_date), fmtTime(o.scheduled_time),
            o.surgeon_name, o.status.replace('_',' ').toUpperCase()
        ]),
        headStyles: { fillColor:[10,37,64], textColor:255, fontStyle:'bold', fontSize:9 },
        bodyStyles: { fontSize:9 },
        alternateRowStyles: { fillColor:[240,244,248] },
    });

    // Footer
    const pageH = doc.internal.pageSize.height;
    doc.setFillColor(240,244,248);
    doc.rect(0, pageH-14, 210, 14, 'F');
    doc.setFontSize(8); doc.setTextColor(100,116,139);
    doc.text('MediCare Hospital Management System — Confidential Patient Document', 14, pageH-5);
    doc.text('Page 1', 196, pageH-5, {align:'right'});

    doc.save(`MediCare_${p.patient_code}_Theatre_Report.pdf`);
}

// ── Enter key search ──
document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') runSearch();
});
</script>

</body>
</html>