<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db_config.php';

// ── Only admin can access this page ──────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Web/Hospital-Management-System/login.php');
    exit();
}

$pageTitle  = 'Registered Doctors';
$useSidebar = true;

// ── Fetch all doctors: users JOIN doctors ─────────────────────
$stmt = $pdo->query("
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.status,
        u.created_at,
        d.doctor_id,
        d.specialization,
        d.qualifications,
        d.license_number,
        d.consultation_fee
    FROM users u
    INNER JOIN doctors d ON d.user_id = u.user_id
    WHERE u.role = 'doctor'
    ORDER BY u.created_at DESC
");
$doctors = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
.page-wrap   { padding: 32px 28px; }
.page-title  { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
.page-sub    { color: var(--muted); font-size: 14px; margin-bottom: 28px; }

.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-active   { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #fee2e2; color: #991b1b; }

.doc-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card, #fff);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.doc-table th {
    background: var(--surface, #f8f9fa);
    padding: 13px 16px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted, #6b7280);
    border-bottom: 1px solid var(--border, #e5e7eb);
}
.doc-table td {
    padding: 14px 16px;
    font-size: 14px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    vertical-align: top;
}
.doc-table tr:last-child td { border-bottom: none; }
.doc-table tr:hover td { background: var(--surface, #f8f9fa); }

.qual-text {
    max-width: 220px;
    white-space: pre-wrap;
    font-size: 13px;
    color: var(--muted, #6b7280);
}
.fee { font-weight: 600; color: var(--primary, #2563eb); }
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted, #6b7280);
}
.empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
</style>

<div class="page-wrap">
    <div class="page-title">🩺 Registered Doctors</div>
    <p class="page-sub">
        All doctor accounts created via registration — from <code>users</code> + <code>doctors</code> tables.
        Total: <strong><?php echo count($doctors); ?></strong>
    </p>

    <?php if (empty($doctors)): ?>
        <div class="empty-state">
            <div class="icon">🔍</div>
            <p>No doctor registrations found yet.</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto">
        <table class="doc-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Specialization</th>
                    <th>Qualifications</th>
                    <th>License No.</th>
                    <th>Fee (LKR)</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $i => $doc): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($doc['full_name']); ?></strong>
                        <div style="font-size:12px;color:var(--muted)">
                            ID: <?php echo $doc['doctor_id']; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($doc['email']); ?></td>
                    <td><?php echo htmlspecialchars($doc['specialization']); ?></td>
                    <td>
                        <div class="qual-text">
                            <?php echo $doc['qualifications']
                                ? htmlspecialchars($doc['qualifications'])
                                : '<span style="color:var(--muted)">—</span>'; ?>
                        </div>
                    </td>
                    <td><?php echo $doc['license_number']
                        ? htmlspecialchars($doc['license_number'])
                        : '<span style="color:var(--muted)">—</span>'; ?></td>
                    <td class="fee">
                        <?php echo number_format((float)$doc['consultation_fee'], 2); ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $doc['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo ucfirst($doc['status']); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;color:var(--muted)">
                        <?php echo date('d M Y', strtotime($doc['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>