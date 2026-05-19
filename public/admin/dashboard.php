<?php
/**
 * NexusSync - Admin Dashboard
 */

$page_title = 'Admin Dashboard';
$use_chartjs = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();
$cycle = get_active_cycle();

// Stats
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE")->fetchColumn();
$total_employees = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('employee','manager') AND is_active = TRUE")->fetchColumn();
$total_managers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND is_active = TRUE")->fetchColumn();

$sheets_submitted = 0; $sheets_approved = 0; $sheets_total = 0;
if ($cycle) {
    $sheets_total = (int)$pdo->prepare("SELECT COUNT(*) FROM goal_sheets WHERE cycle_id = ?")->execute([$cycle['id']]) ? $pdo->query("SELECT COUNT(*) FROM goal_sheets WHERE cycle_id = {$cycle['id']}")->fetchColumn() : 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goal_sheets WHERE cycle_id = ?");
    $stmt->execute([$cycle['id']]);
    $sheets_total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goal_sheets WHERE cycle_id = ? AND status IN ('submitted','approved','locked')");
    $stmt->execute([$cycle['id']]);
    $sheets_submitted = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goal_sheets WHERE cycle_id = ? AND status IN ('approved','locked')");
    $stmt->execute([$cycle['id']]);
    $sheets_approved = (int)$stmt->fetchColumn();
}

$current_quarter = $cycle ? get_current_quarter($cycle) : null;

// Completion by department
$dept_stats = [];
if ($cycle) {
    $stmt = $pdo->prepare("
        SELECT u.department,
               COUNT(gs.id) AS total,
               COUNT(CASE WHEN gs.status IN ('approved','locked') THEN 1 END) AS approved,
               COUNT(CASE WHEN gs.status = 'submitted' THEN 1 END) AS submitted
        FROM users u
        LEFT JOIN goal_sheets gs ON gs.user_id = u.id AND gs.cycle_id = ?
        WHERE u.role IN ('employee','manager') AND u.is_active = TRUE
        GROUP BY u.department ORDER BY u.department
    ");
    $stmt->execute([$cycle['id']]);
    $dept_stats = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Admin Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $total_users ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $cycle ? h($cycle['cycle_name']) : '-' ?></div>
        <div class="stat-label">Active Cycle</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $sheets_submitted ?> / <?= $total_employees ?></div>
        <div class="stat-label">Goals Submitted</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $sheets_approved ?> / <?= $total_employees ?></div>
        <div class="stat-label">Goals Approved</div>
    </div>
</div>

<!-- Completion by Department -->
<?php if (!empty($dept_stats)): ?>
<div class="card">
    <div class="card-header"><h2>Completion by Department</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Department</th><th>Total Sheets</th><th>Submitted</th><th>Approved</th><th>Progress</th></tr>
            </thead>
            <tbody>
                <?php foreach ($dept_stats as $d):
                    $pct = $d['total'] > 0 ? round($d['approved'] / $d['total'] * 100) : 0;
                ?>
                <tr>
                    <td><?= h($d['department'] ?: 'Unassigned') ?></td>
                    <td><?= $d['total'] ?></td>
                    <td><?= $d['submitted'] ?></td>
                    <td><?= $d['approved'] ?></td>
                    <td>
                        <div class="progress" style="width:150px">
                            <div class="progress-bar progress-bar-<?= $pct >= 80 ? 'success' : ($pct >= 40 ? 'primary' : 'warning') ?>"
                                 style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Quick Links -->
<div class="card">
    <div class="card-header"><h2>Administration</h2></div>
    <div class="btn-group" style="flex-wrap:wrap">
        <a href="/admin/cycles.php" class="btn btn-outline">Manage Cycles</a>
        <a href="/admin/users.php" class="btn btn-outline">Manage Users</a>
        <a href="/admin/shared_goals.php" class="btn btn-outline">Shared Goals</a>
        <a href="/admin/unlock.php" class="btn btn-outline">Unlock Goals</a>
        <a href="/admin/reports.php" class="btn btn-outline">Reports & Export</a>
        <a href="/admin/audit_log.php" class="btn btn-outline">Audit Trail</a>
        <a href="/admin/escalation_rules.php" class="btn btn-outline">Escalation Rules</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
