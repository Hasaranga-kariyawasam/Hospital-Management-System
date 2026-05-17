<?php
declare(strict_types=1);

$requiredRoles = ['admin'];
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../config/db_config.php';

$pageTitle  = 'Staff Management';
$useSidebar = true;

// ── Handle status toggle action ───────────────────────────────────────────────
$actionMsg   = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $action   = $_POST['action'];

    // Prevent admin from deactivating themselves
    if ($targetId === (int)$_SESSION['user_id']) {
        $actionError = 'You cannot change your own account status.';
    } else {
        if ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role != 'patient'");
            $stmt->execute([$targetId]);
            $actionMsg = 'Account activated successfully.';
        } elseif ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ? AND role != 'patient'");
            $stmt->execute([$targetId]);
            $actionMsg = 'Account deactivated.';
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterRole   = $_GET['role']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');

$where  = ["u.role != 'patient'", "u.role != 'admin'"];
$params = [];

if ($filterRole !== '') {
    $where[]  = "u.role = ?";
    $params[] = $filterRole;
}
if ($filterStatus !== '') {
    $where[]  = "u.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch !== '') {
    $where[]  = "(u.full_name LIKE ? OR u.email LIKE ? OR u.staff_id LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$whereSQL = implode(' AND ', $where);

$staff = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.role, u.status,
           u.staff_id, u.department, u.created_at
    FROM users u
    WHERE $whereSQL
    ORDER BY u.status ASC, u.created_at DESC
");
$staff->execute($params);
$staffList = $staff->fetchAll();

// ── Summary counts ─────────────────────────────────────────────────────────
$counts = $pdo->query("
    SELECT
        COUNT(*)                                          AS total,
        SUM(status = 'active')                            AS active,
        SUM(status = 'inactive')                          AS inactive,
        SUM(role = 'doctor')                              AS doctors,
        SUM(role = 'reception')                           AS receptionists,
        SUM(role = 'pharmacist')                          AS pharmacists,
        SUM(role = 'dispatcher')                          AS dispatchers,
        SUM(role = 'driver')                              AS drivers
    FROM users
    WHERE role NOT IN ('patient','admin')
")->fetch();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* ── Staff Management Page Styles ── */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
}
.page-header-title h2 {
    font-family: var(--font-display);
    font-size: 1.7rem;
    color: var(--text);
    margin-bottom: 4px;
}
.page-header-title p { color: var(--muted); font-size: 0.9rem; }

/* Summary Strip */
.summary-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.summary-card {
    background: var(--surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: box-shadow var(--transition), transform var(--transition);
}
.summary-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}
.summary-card .s-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--muted);
}
.summary-card .s-value {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
}
.summary-card.highlight-active  { border-left: 4px solid var(--success); }
.summary-card.highlight-inactive { border-left: 4px solid var(--warning); }
.summary-card.highlight-total   { border-left: 4px solid var(--accent); }

/* Filters Bar */
.filters-bar {
    background: var(--surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    padding: 16px 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 20px;
}
.filter-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 160px; }
.filter-group label { font-size: 0.78rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.6px; }
.filter-input {
    padding: 9px 14px;
    border: 1.5px solid var(--border-light);
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: 0.88rem;
    color: var(--text);
    background: #f8fafc;
    transition: border-color var(--transition), box-shadow var(--transition);
    outline: none;
}
.filter-input:focus {
    border-color: var(--accent);
    background: white;
    box-shadow: 0 0 0 3px rgba(14,165,233,0.10);
}
select.filter-input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition);
    text-decoration: none;
    border: none;
    font-family: var(--font-body);
    white-space: nowrap;
}
.btn-primary   { background: var(--accent); color: white; box-shadow: 0 2px 8px rgba(14,165,233,0.25); }
.btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); }
.btn-secondary { background: #f1f5f9; color: var(--text-mid); border: 1px solid var(--border-light); }
.btn-secondary:hover { background: #e2e8f0; }
.btn-success   { background: var(--success); color: white; }
.btn-success:hover { background: #047857; transform: translateY(-1px); }
.btn-warning   { background: var(--warning); color: white; }
.btn-warning:hover { background: #b45309; transform: translateY(-1px); }
.btn-sm { padding: 6px 12px; font-size: 0.8rem; }

/* Alert */
.alert {
    padding: 13px 18px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
}
.alert-success { background: var(--success-light); border: 1px solid #6ee7b7; color: #065f46; }
.alert-error   { background: var(--danger-light);  border: 1px solid #fca5a5; color: #991b1b; }

/* Table */
.card {
    background: var(--surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.card-header {
    padding: 18px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-light);
    background: #fafbfc;
}
.card-header h3 { font-size: 1rem; font-weight: 700; color: var(--text); }
.card-header .result-count { font-size: 0.82rem; color: var(--muted); }

.table-wrap { overflow-x: auto; }
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.data-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--muted);
    background: #f8fafc;
    border-bottom: 1px solid var(--border-light);
    white-space: nowrap;
}
.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-mid);
    vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr {
    transition: background var(--transition);
}
.data-table tbody tr:hover { background: #f8fbff; }

/* Staff name block */
.staff-name-cell { display: flex; align-items: center; gap: 12px; }
.staff-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}
.avatar-doctor     { background: linear-gradient(135deg,#1a56db,#1e40af); }
.avatar-reception  { background: linear-gradient(135deg,#059669,#047857); }
.avatar-pharmacist { background: linear-gradient(135deg,#7c3aed,#6d28d9); }
.avatar-dispatcher { background: linear-gradient(135deg,#d97706,#b45309); }
.avatar-driver     { background: linear-gradient(135deg,#dc2626,#b91c1c); }

.staff-info-block .staff-name { font-weight: 600; color: var(--text); font-size: 0.9rem; }
.staff-info-block .staff-id   { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
.badge-active   { background: var(--success-light); color: #065f46; }
.badge-inactive { background: var(--warning-light); color: #92400e; }
.badge-role {
    background: #f1f5f9;
    color: var(--text-mid);
    border: 1px solid var(--border-light);
    text-transform: capitalize;
    font-size: 0.75rem;
}
.badge-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-dot-active   { background: var(--success); }
.badge-dot-inactive { background: var(--warning); }

/* Action buttons cell */
.action-cell { display: flex; gap: 8px; align-items: center; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 56px 32px;
    color: var(--muted);
}
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 14px; opacity: 0.5; }
.empty-state h4 { font-size: 1rem; color: var(--text-mid); margin-bottom: 6px; }
.empty-state p  { font-size: 0.88rem; }

/* Pending highlight */
.data-table tbody tr.row-pending {
    background: #fffbeb;
    border-left: 3px solid var(--warning);
}
.data-table tbody tr.row-pending:hover { background: #fef3c7; }

/* Responsive */
@media (max-width: 900px) {
    .summary-strip { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 600px) {
    .main-content { padding: 20px; }
    .summary-strip { grid-template-columns: 1fr 1fr; }
    .filters-bar   { flex-direction: column; }
    .filter-group  { min-width: 100%; }
}
</style>

<main class="main-content">

    <div class="page-header">
        <div class="page-header-title">
            <h2>Staff Management</h2>
            <p>Activate, deactivate, and manage staff accounts awaiting approval</p>
        </div>
    </div>

    <?php if ($actionMsg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($actionMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($actionError): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($actionError); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Strip -->
    <div class="summary-strip">
        <div class="summary-card highlight-total">
            <span class="s-label">Total Staff</span>
            <span class="s-value"><?php echo (int)$counts['total']; ?></span>
        </div>
        <div class="summary-card highlight-active">
            <span class="s-label">Active</span>
            <span class="s-value"><?php echo (int)$counts['active']; ?></span>
        </div>
        <div class="summary-card highlight-inactive">
            <span class="s-label">Pending / Inactive</span>
            <span class="s-value"><?php echo (int)$counts['inactive']; ?></span>
        </div>
        <div class="summary-card">
            <span class="s-label">Doctors</span>
            <span class="s-value"><?php echo (int)$counts['doctors']; ?></span>
        </div>
        <div class="summary-card">
            <span class="s-label">Receptionists</span>
            <span class="s-value"><?php echo (int)$counts['receptionists']; ?></span>
        </div>
        <div class="summary-card">
            <span class="s-label">Pharmacists</span>
            <span class="s-value"><?php echo (int)$counts['pharmacists']; ?></span>
        </div>
        <div class="summary-card">
            <span class="s-label">Dispatchers</span>
            <span class="s-value"><?php echo (int)$counts['dispatchers']; ?></span>
        </div>
        <div class="summary-card">
            <span class="s-label">Drivers</span>
            <span class="s-value"><?php echo (int)$counts['drivers']; ?></span>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="">
        <div class="filters-bar">
            <div class="filter-group" style="flex:2;min-width:200px;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="filter-input"
                    placeholder="Name, email, or staff ID…"
                    value="<?php echo htmlspecialchars($filterSearch); ?>">
            </div>
            <div class="filter-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="filter-input">
                    <option value="">All Roles</option>
                    <option value="doctor"     <?php echo ($filterRole === 'doctor'     ? 'selected' : ''); ?>>Doctor</option>
                    <option value="reception"  <?php echo ($filterRole === 'reception'  ? 'selected' : ''); ?>>Receptionist</option>
                    <option value="pharmacist" <?php echo ($filterRole === 'pharmacist' ? 'selected' : ''); ?>>Pharmacist</option>
                    <option value="dispatcher" <?php echo ($filterRole === 'dispatcher' ? 'selected' : ''); ?>>Dispatcher</option>
                    <option value="driver"     <?php echo ($filterRole === 'driver'     ? 'selected' : ''); ?>>Driver</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="inactive" <?php echo ($filterStatus === 'inactive' ? 'selected' : ''); ?>>Pending Activation</option>
                    <option value="active"   <?php echo ($filterStatus === 'active'   ? 'selected' : ''); ?>>Active</option>
                </select>
            </div>
            <div class="filter-group" style="flex:0;min-width:auto;justify-content:flex-end;">
                <label style="visibility:hidden">Go</label>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-header">
            <h3>
                <?php if ($filterStatus === 'inactive'): ?>
                    <i class="fas fa-hourglass-half" style="color:var(--warning);margin-right:6px;"></i>
                    Pending Activation
                <?php elseif ($filterStatus === 'active'): ?>
                    <i class="fas fa-user-check" style="color:var(--success);margin-right:6px;"></i>
                    Active Staff
                <?php else: ?>
                    <i class="fas fa-users" style="color:var(--accent);margin-right:6px;"></i>
                    All Staff
                <?php endif; ?>
            </h3>
            <span class="result-count"><?php echo count($staffList); ?> result<?php echo count($staffList) !== 1 ? 's' : ''; ?></span>
        </div>

        <?php if (empty($staffList)): ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <h4>No staff found</h4>
                <p>Try adjusting the filters or search term.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffList as $s):
                            $isPending  = $s['status'] === 'inactive';
                            $initials   = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $s['full_name']), 0, 2)));
                            $avatarClass = 'avatar-' . $s['role'];
                            $roleLabels = [
                                'doctor'     => 'Doctor',
                                'reception'  => 'Receptionist',
                                'pharmacist' => 'Pharmacist',
                                'dispatcher' => 'Dispatcher',
                                'driver'     => 'Driver',
                            ];
                        ?>
                        <tr class="<?php echo $isPending ? 'row-pending' : ''; ?>">
                            <td>
                                <div class="staff-name-cell">
                                    <div class="staff-avatar <?php echo $avatarClass; ?>"><?php echo $initials; ?></div>
                                    <div class="staff-info-block">
                                        <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                        <div class="staff-id">
                                            <?php echo $s['staff_id'] ? htmlspecialchars($s['staff_id']) : '<span style="color:#cbd5e1">No ID set</span>'; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--muted);"><?php echo htmlspecialchars($s['email']); ?></td>
                            <td>
                                <span class="badge badge-role">
                                    <?php echo htmlspecialchars($roleLabels[$s['role']] ?? ucfirst($s['role'])); ?>
                                </span>
                            </td>
                            <td style="color:var(--muted);">
                                <?php echo $s['department'] ? htmlspecialchars($s['department']) : '<span style="color:#cbd5e1">—</span>'; ?>
                            </td>
                            <td style="color:var(--muted);white-space:nowrap;">
                                <?php echo date('d M Y', strtotime($s['created_at'])); ?>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge badge-active">
                                        <span class="badge-dot badge-dot-active"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">
                                        <span class="badge-dot badge-dot-inactive"></span>
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-cell">
                                    <?php if ($s['status'] === 'inactive'): ?>
                                        <!-- Activate -->
                                        <form method="POST" action="" style="display:inline;"
                                              onsubmit="return confirm('Activate account for <?php echo htmlspecialchars(addslashes($s['full_name'])); ?>?');">
                                            <input type="hidden" name="action"  value="activate">
                                            <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-check"></i> Activate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Deactivate -->
                                        <form method="POST" action="" style="display:inline;"
                                              onsubmit="return confirm('Deactivate account for <?php echo htmlspecialchars(addslashes($s['full_name'])); ?>? They will not be able to log in.');">
                                            <input type="hidden" name="action"  value="deactivate">
                                            <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <i class="fas fa-user-slash"></i> Deactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $pendingCount = count(array_filter($staffList, fn($s) => $s['status'] === 'inactive'));
            if ($pendingCount > 0 && $filterStatus !== 'active'):
            ?>
            <div style="padding:14px 22px;background:#fffbeb;border-top:1px solid #fde68a;display:flex;align-items:center;gap:10px;font-size:0.85rem;color:#92400e;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong><?php echo $pendingCount; ?> account<?php echo $pendingCount !== 1 ? 's' : ''; ?></strong>&nbsp;awaiting activation — highlighted in yellow above.
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
