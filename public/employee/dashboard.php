<?php
/**
 * NexusSync - Employee Dashboard
 */

$page_title = 'My Dashboard';
require_once __DIR__ . '/../../includes/auth.php';
require_role('employee', 'manager');

$pdo = get_db();
$user_id = current_user_id();
$cycle = get_active_cycle();

// Get or create goal sheet for active cycle
$sheet = null;
$goals = [];
if ($cycle) {
    $sheet = get_goal_sheet($user_id, $cycle['id']);
    if ($sheet) {
        $goals = get_goals_for_sheet($sheet['id']);
    }
}

// Determine current quarter
$current_quarter = $cycle ? get_current_quarter($cycle) : null;
$goal_setting_open = $cycle ? is_goal_setting_open($cycle) : false;

// Get achievements for current quarter
$achievements = [];
if ($sheet && $current_quarter) {
    $stmt = $pdo->prepare("
        SELECT a.* FROM achievements a
        JOIN goals g ON g.id = a.goal_id
        WHERE g.goal_sheet_id = ? AND a.quarter = ? AND g.is_deleted = FALSE
    ");
    $stmt->execute([$sheet['id'], $current_quarter]);
    foreach ($stmt->fetchAll() as $a) {
        $achievements[$a['goal_id']] = $a;
    }
}

// Weighted score
$weighted_score = null;
if ($sheet && $current_quarter) {
    $weighted_score = compute_weighted_score($sheet['id'], $current_quarter);
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>My Dashboard</h1>

<?php if (!$cycle): ?>
    <div class="alert alert-info">No active appraisal cycle. Contact your administrator.</div>
<?php else: ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= h($cycle['cycle_name']) ?></div>
        <div class="stat-label">Active Cycle</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $sheet ? count($goals) : 0 ?></div>
        <div class="stat-label">Goals</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $sheet ? status_badge($sheet['status']) : '<span class="text-muted">-</span>' ?></div>
        <div class="stat-label">Goal Sheet Status</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $weighted_score !== null ? $weighted_score . '%' : '-' ?></div>
        <div class="stat-label"><?= $current_quarter ?? 'N/A' ?> Score</div>
    </div>
</div>

<!-- Actions -->
<div class="card">
    <div class="card-header">
        <h2>Quick Actions</h2>
    </div>
    <div class="btn-group">
        <?php if (!$sheet && $goal_setting_open): ?>
            <a href="/employee/goal_create.php" class="btn btn-primary">Create Goal Sheet</a>
        <?php elseif ($sheet && in_array($sheet['status'], ['draft', 'returned'])): ?>
            <a href="/employee/goal_create.php" class="btn btn-primary">Edit Goal Sheet</a>
        <?php elseif ($sheet && in_array($sheet['status'], ['approved', 'locked'])): ?>
            <a href="/employee/goal_sheet.php" class="btn btn-outline">View Goal Sheet</a>
            <?php if ($current_quarter): ?>
                <a href="/employee/achievement.php?quarter=<?= $current_quarter ?>" class="btn btn-success">
                    Log <?= $current_quarter ?> Achievement
                </a>
            <?php endif; ?>
        <?php elseif ($sheet && $sheet['status'] === 'submitted'): ?>
            <a href="/employee/goal_sheet.php" class="btn btn-outline">View Submitted Sheet</a>
        <?php endif; ?>
    </div>
</div>

<!-- Goals Summary -->
<?php if ($sheet && !empty($goals)): ?>
<div class="card">
    <div class="card-header">
        <h2>My Goals</h2>
        <span class="text-muted"><?= h($cycle['cycle_name']) ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Goal</th>
                    <th>Thrust Area</th>
                    <th>UoM</th>
                    <th>Target</th>
                    <th>Weight</th>
                    <?php if ($current_quarter): ?><th><?= $current_quarter ?> Actual</th><th>Score</th><th>Status</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goals as $i => $g): ?>
                <?php $ach = $achievements[$g['id']] ?? null; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?= h($g['title']) ?>
                        <?php if ($g['is_shared']): ?> <span class="badge badge-info">Shared</span><?php endif; ?>
                    </td>
                    <td><?= h($g['thrust_area_name']) ?></td>
                    <td><?= h(uom_label($g['uom_type'])) ?></td>
                    <td><?= $g['uom_type'] === 'timeline' ? h($g['target_date']) : h($g['target_value']) ?></td>
                    <td><?= $g['weightage'] ?>%</td>
                    <?php if ($current_quarter): ?>
                    <td><?= $ach ? ($g['uom_type'] === 'timeline' ? h($ach['completion_date']) : h($ach['actual_value'])) : '-' ?></td>
                    <td><?= $ach && $ach['computed_score'] !== null ? $ach['computed_score'] . '%' : '-' ?></td>
                    <td>
                        <?= $ach ? status_badge($ach['status']) : '<span class="text-muted">-</span>' ?>
                        <?php if ($ach && $ach['is_late_entry']): ?><span class="badge badge-late">LATE</span><?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($sheet && $sheet['status'] === 'returned' && $sheet['return_comment']): ?>
<div class="alert alert-warning">
    <strong>Manager's Feedback:</strong> <?= h($sheet['return_comment']) ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
