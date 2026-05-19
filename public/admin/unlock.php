<?php
/**
 * NexusSync - Admin Unlock Goals
 */

$page_title = 'Unlock Goals';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $sheet_id = (int)($_POST['sheet_id'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');

    if (!$sheet_id || empty($reason)) {
        flash('error', 'Sheet ID and reason are required.');
        redirect('/admin/unlock.php');
    }

    $stmt = $pdo->prepare("SELECT * FROM goal_sheets WHERE id = ? AND status IN ('approved','locked')");
    $stmt->execute([$sheet_id]);
    $sheet = $stmt->fetch();

    if (!$sheet) {
        flash('error', 'Goal sheet not found or not in locked/approved state.');
        redirect('/admin/unlock.php');
    }

    // EC-6: Reset to draft, clear approval, require re-submission
    $stmt = $pdo->prepare("UPDATE goal_sheets SET status = 'draft', approved_at = NULL, approved_by = NULL, version = version + 1 WHERE id = ?");
    $stmt->execute([$sheet_id]);

    audit_log('goal_sheets', $sheet_id, 'UNLOCK', current_user_id(), 'status', $sheet['status'], 'draft', $reason);

    // Notify employee and their manager
    create_notification($sheet['user_id'], 'goal_unlocked',
        'Your goal sheet has been unlocked by Admin. Reason: ' . $reason . '. Please re-submit.',
        '/employee/goal_create.php');

    $emp = $pdo->prepare("SELECT manager_id FROM users WHERE id = ?");
    $emp->execute([$sheet['user_id']]);
    $mgr_id = $emp->fetchColumn();
    if ($mgr_id) {
        create_notification($mgr_id, 'goal_unlocked',
            'A goal sheet in your team has been unlocked by Admin and will require re-approval.',
            '/manager/dashboard.php');
    }

    flash('success', 'Goal sheet #' . $sheet_id . ' unlocked. Employee must re-submit for approval.');
    redirect('/admin/unlock.php');
}

// Search for locked sheets
$search = $_GET['search'] ?? '';
$locked_sheets = [];
if ($search) {
    $stmt = $pdo->prepare("SELECT gs.*, u.name, u.employee_id FROM goal_sheets gs JOIN users u ON u.id = gs.user_id
                           WHERE gs.status IN ('approved','locked') AND (u.name ILIKE ? OR u.employee_id ILIKE ?)
                           ORDER BY gs.updated_at DESC");
    $stmt->execute(["%$search%", "%$search%"]);
    $locked_sheets = $stmt->fetchAll();
} else {
    $locked_sheets = $pdo->query("SELECT gs.*, u.name, u.employee_id FROM goal_sheets gs JOIN users u ON u.id = gs.user_id
                                  WHERE gs.status IN ('approved','locked') ORDER BY gs.updated_at DESC LIMIT 20")->fetchAll();
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Unlock Goal Sheets</h1>

<div class="alert alert-warning">
    <strong>Caution:</strong> Unlocking a goal sheet resets it to draft. The employee must re-submit and the manager must re-approve.
    All changes are logged in the audit trail.
</div>

<!-- Search -->
<div class="card">
    <form method="GET" class="form-inline">
        <div class="form-group" style="flex:1">
            <input type="text" name="search" class="form-control" placeholder="Search by name or employee ID..."
                   value="<?= h($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<!-- Results -->
<div class="card">
    <div class="card-header"><h2>Locked / Approved Sheets</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Employee</th><th>ID</th><th>Status</th><th>Approved At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($locked_sheets as $s): ?>
                <tr>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['employee_id']) ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td><?= $s['approved_at'] ? date('d M Y', strtotime($s['approved_at'])) : '-' ?></td>
                    <td>
                        <form method="POST" style="display:flex; gap:8px; align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sheet_id" value="<?= $s['id'] ?>">
                            <input type="text" name="reason" class="form-control" placeholder="Reason (required)" required style="width:200px">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Unlock this sheet? This requires re-approval.')">🔓 Unlock</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($locked_sheets)): ?>
                <tr><td colspan="5" class="text-center text-muted">No locked sheets found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
