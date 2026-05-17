<?php
/**
 * AtomQuest — Manager Dashboard
 */

$page_title = 'Team Dashboard';
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager', 'admin');

$pdo = get_db();
$manager_id = current_user_id();
$cycle = get_active_cycle();
$team = get_team_members($manager_id);

// Team stats
$pending_approvals = 0;
$submitted_count = 0;
$approved_count = 0;
$team_sheets = [];

if ($cycle) {
    foreach ($team as $member) {
        $sheet = get_goal_sheet($member['id'], $cycle['id']);
        $team_sheets[$member['id']] = $sheet;
        if ($sheet) {
            if ($sheet['status'] === 'submitted') $pending_approvals++;
            if (in_array($sheet['status'], ['approved', 'locked'])) $approved_count++;
            $submitted_count++;
        }
    }
}

$current_quarter = $cycle ? get_current_quarter($cycle) : null;

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Team Dashboard</h1>

<?php if (!$cycle): ?>
    <div class="alert alert-info">No active appraisal cycle.</div>
<?php else: ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($team) ?></div>
        <div class="stat-label">Team Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: <?= $pending_approvals > 0 ? 'var(--warning)' : 'var(--success)' ?>">
            <?= $pending_approvals ?>
        </div>
        <div class="stat-label">Pending Approvals</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $approved_count ?> / <?= count($team) ?></div>
        <div class="stat-label">Goals Approved</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $current_quarter ?? '—' ?></div>
        <div class="stat-label">Current Quarter</div>
    </div>
</div>

<!-- Team Goal Sheet Status -->
<div class="card">
    <div class="card-header">
        <h2>Team Goal Sheets</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Goals</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team as $member):
                    $sheet = $team_sheets[$member['id']] ?? null;
                    $goal_count = 0;
                    if ($sheet) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE goal_sheet_id = ? AND is_deleted = FALSE");
                        $stmt->execute([$sheet['id']]);
                        $goal_count = (int)$stmt->fetchColumn();
                    }
                ?>
                <tr>
                    <td><?= h($member['name']) ?></td>
                    <td><?= h($member['department']) ?></td>
                    <td><?= $sheet ? status_badge($sheet['status']) : '<span class="text-muted">Not started</span>' ?></td>
                    <td><?= $goal_count ?></td>
                    <td><?= $sheet && $sheet['submitted_at'] ? date('d M Y', strtotime($sheet['submitted_at'])) : '—' ?></td>
                    <td>
                        <?php if ($sheet && $sheet['status'] === 'submitted'): ?>
                            <a href="/manager/approve.php?sheet_id=<?= $sheet['id'] ?>" class="btn btn-sm btn-primary">Review</a>
                        <?php elseif ($sheet && in_array($sheet['status'], ['approved', 'locked'])): ?>
                            <a href="/manager/checkin.php?sheet_id=<?= $sheet['id'] ?>" class="btn btn-sm btn-success">Check-in</a>
                        <?php elseif ($sheet && in_array($sheet['status'], ['draft', 'returned'])): ?>
                            <a href="/manager/edit_goals.php?sheet_id=<?= $sheet['id'] ?>" class="btn btn-sm btn-outline">Edit Goals</a>
                        <?php elseif (!$sheet): ?>
                            <span class="text-muted">No sheet yet</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($team)): ?>
                <tr><td colspan="6" class="text-center text-muted">No team members found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Links -->
<div class="card">
    <div class="card-header"><h2>Quick Links</h2></div>
    <div class="btn-group">
        <a href="/employee/dashboard.php" class="btn btn-outline">My Own Goals</a>
        <a href="/manager/reports.php" class="btn btn-outline">Team Reports</a>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
