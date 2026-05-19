<?php
/**
 * NexusSync - Manager Approval Page
 * Inline editing of targets/weightage, approve or return for rework
 */

$page_title = 'Review Goals';
$use_bootstrap = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager', 'admin');

$pdo = get_db();
$manager_id = current_user_id();
$sheet_id = (int)($_GET['sheet_id'] ?? 0);

if (!$sheet_id) {
    flash('error', 'No goal sheet specified.');
    redirect('/manager/dashboard.php');
}

// Load sheet
$stmt = $pdo->prepare("SELECT gs.*, u.name as employee_name, u.department, u.employee_id
                        FROM goal_sheets gs JOIN users u ON u.id = gs.user_id
                        WHERE gs.id = ?");
$stmt->execute([$sheet_id]);
$sheet = $stmt->fetch();

if (!$sheet) {
    flash('error', 'Goal sheet not found.');
    redirect('/manager/dashboard.php');
}

// EC-1: Manager cannot approve their own sheet
if ($sheet['user_id'] === $manager_id) {
    flash('error', 'You cannot approve your own goal sheet.');
    redirect('/manager/dashboard.php');
}

// Verify this employee reports to this manager (unless admin)
if (current_role() !== 'admin') {
    $stmt = $pdo->prepare("SELECT manager_id FROM users WHERE id = ?");
    $stmt->execute([$sheet['user_id']]);
    $emp_manager = (int)$stmt->fetchColumn();
    if ($emp_manager !== $manager_id) {
        flash('error', 'This employee does not report to you.');
        redirect('/manager/dashboard.php');
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    $posted_version = (int)($_POST['version'] ?? 0);

    // Optimistic locking
    $stmt = $pdo->prepare("SELECT version FROM goal_sheets WHERE id = ?");
    $stmt->execute([$sheet_id]);
    if ((int)$stmt->fetchColumn() !== $posted_version) {
        flash('error', 'Goal sheet was modified. Please refresh.');
        redirect('/manager/approve.php?sheet_id=' . $sheet_id);
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE goal_sheets SET status = 'approved', approved_at = NOW(), approved_by = ?,
                   version = version + 1 WHERE id = ? AND status = 'submitted'
        ");
        $stmt->execute([$manager_id, $sheet_id]);

        // Auto-lock
        $pdo->prepare("UPDATE goal_sheets SET status = 'locked' WHERE id = ? AND status = 'approved'")
            ->execute([$sheet_id]);

        audit_log('goal_sheets', $sheet_id, 'UPDATE', $manager_id, 'status', 'submitted', 'locked');
        create_notification($sheet['user_id'], 'goal_approved',
            'Your goal sheet has been approved by ' . h(current_user_name()) . '.',
            '/employee/goal_sheet.php');

        flash('success', 'Goal sheet approved and locked.');
        redirect('/manager/dashboard.php');

    } elseif ($action === 'return') {
        $comment = sanitize($_POST['return_comment'] ?? '');
        if (empty($comment)) {
            flash('error', 'A comment is required when returning for rework.');
            redirect('/manager/approve.php?sheet_id=' . $sheet_id);
        }

        $stmt = $pdo->prepare("
            UPDATE goal_sheets SET status = 'returned', return_comment = ?,
                   version = version + 1 WHERE id = ? AND status = 'submitted'
        ");
        $stmt->execute([$comment, $sheet_id]);

        audit_log('goal_sheets', $sheet_id, 'UPDATE', $manager_id, 'status', 'submitted', 'returned');
        create_notification($sheet['user_id'], 'goal_returned',
            'Your goal sheet has been returned for rework. Reason: ' . $comment,
            '/employee/goal_create.php');

        flash('success', 'Goal sheet returned for rework.');
        redirect('/manager/dashboard.php');
    }
}

$goals = get_goals_for_sheet($sheet_id);

// Refresh sheet
$stmt = $pdo->prepare("SELECT * FROM goal_sheets WHERE id = ?");
$stmt->execute([$sheet_id]);
$sheet_fresh = $stmt->fetch();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Review: <?= h($sheet['employee_name']) ?>'s Goals</h1>

<div class="card">
    <div class="card-header">
        <div>
            <strong><?= h($sheet['employee_name']) ?></strong> (<?= h($sheet['employee_id']) ?>)
            · <?= h($sheet['department']) ?>
        </div>
        <div><?= status_badge($sheet_fresh['status']) ?></div>
    </div>

    <?php if ($sheet_fresh['status'] !== 'submitted'): ?>
        <div class="alert alert-info">This goal sheet is <strong><?= $sheet_fresh['status'] ?></strong> and cannot be reviewed.</div>
    <?php else: ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Thrust Area</th>
                    <th>Goal Title</th>
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
                    <td><?= h(uom_label($g['uom_type'])) ?></td>
                    <td class="editable" onclick="makeEditable(this, 'target_value', <?= $g['id'] ?>, 'number')">
                        <?= $g['uom_type'] === 'timeline' ? h($g['target_date']) : h($g['target_value']) ?>
                    </td>
                    <td class="editable" onclick="makeEditable(this, 'weightage', <?= $g['id'] ?>, 'number')">
                        <?= $g['weightage'] ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total Weightage:</strong></td>
                    <td><strong><?= array_sum(array_column($goals, 'weightage')) ?>%</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="text-muted mt-1" style="font-size:0.8rem">Click on Target or Weightage cells to edit inline.</p>

    <!-- Approve / Return actions -->
    <div style="margin-top:20px; display:flex; gap:16px; flex-wrap:wrap;">
        <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= $sheet_fresh['version'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Approve and lock this goal sheet?')">✓ Approve & Lock</button>
        </form>

        <form method="POST" style="flex:1; min-width:300px;">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= $sheet_fresh['version'] ?>">
            <input type="hidden" name="action" value="return">
            <div class="form-inline">
                <div class="form-group" style="flex:1;">
                    <input type="text" name="return_comment" class="form-control"
                           placeholder="Reason for returning (required)" required>
                </div>
                <button type="submit" class="btn btn-warning">↩ Return for Rework</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<a href="/manager/dashboard.php" class="btn btn-secondary mt-2">← Back to Team</a>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
