<?php
/**
 * AtomQuest — View Goal Sheet (Read-only)
 */

$page_title = 'Goal Sheet';
require_once __DIR__ . '/../../includes/auth.php';
require_role('employee', 'manager', 'admin');

$pdo = get_db();
$user_id = current_user_id();
$cycle = get_active_cycle();

if (!$cycle) {
    flash('error', 'No active cycle.');
    redirect('/employee/dashboard.php');
}

$sheet = get_goal_sheet($user_id, $cycle['id']);
if (!$sheet) {
    flash('error', 'No goal sheet found.');
    redirect('/employee/dashboard.php');
}

$goals = get_goals_for_sheet($sheet['id']);

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Goal Sheet — <?= h($cycle['cycle_name']) ?></h1>

<div class="card">
    <div class="card-header">
        <h2>Status: <?= status_badge($sheet['status']) ?></h2>
        <?php if ($sheet['approved_by']): ?>
            <?php
            $approver = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $approver->execute([$sheet['approved_by']]);
            $approver_name = $approver->fetchColumn();
            ?>
            <span class="text-muted">Approved by <?= h($approver_name) ?> on <?= h(date('d M Y', strtotime($sheet['approved_at']))) ?></span>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Thrust Area</th>
                    <th>Goal Title</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Target</th>
                    <th>Weightage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goals as $i => $g): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= h($g['thrust_area_name']) ?></td>
                    <td>
                        <?= h($g['title']) ?>
                        <?php if ($g['is_shared']): ?><span class="badge badge-info">Shared</span><?php endif; ?>
                    </td>
                    <td><?= h($g['description']) ?></td>
                    <td><?= h(uom_label($g['uom_type'])) ?></td>
                    <td><?= $g['uom_type'] === 'timeline' ? h($g['target_date']) : h($g['target_value']) ?></td>
                    <td><?= $g['weightage'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right"><strong>Total Weightage:</strong></td>
                    <td><strong><?= array_sum(array_column($goals, 'weightage')) ?>%</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($sheet['return_comment']): ?>
<div class="alert alert-warning">
    <strong>Manager's Feedback:</strong> <?= h($sheet['return_comment']) ?>
</div>
<?php endif; ?>

<div class="btn-group">
    <a href="/employee/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <?php if (in_array($sheet['status'], ['draft', 'returned'])): ?>
        <a href="/employee/goal_create.php" class="btn btn-primary">Edit Goals</a>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
