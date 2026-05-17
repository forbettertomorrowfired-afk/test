<?php
/**
 * AtomQuest — Manager: Edit Employee Goal Sheet
 * Allows a manager to add goals to, or delete goals from,
 * a direct report's goal sheet while it is still in draft/returned status.
 */

$page_title = 'Edit Employee Goals';
$use_bootstrap = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager', 'admin');

$pdo        = get_db();
$manager_id = current_user_id();
$sheet_id   = (int)($_GET['sheet_id'] ?? 0);

if (!$sheet_id) {
    flash('error', 'No goal sheet specified.');
    redirect('/manager/dashboard.php');
}

// Load sheet + employee info
$stmt = $pdo->prepare("
    SELECT gs.*, u.name AS employee_name, u.department, u.employee_id, u.manager_id AS emp_manager_id
    FROM goal_sheets gs
    JOIN users u ON u.id = gs.user_id
    WHERE gs.id = ?
");
$stmt->execute([$sheet_id]);
$sheet = $stmt->fetch();

if (!$sheet) {
    flash('error', 'Goal sheet not found.');
    redirect('/manager/dashboard.php');
}

// Verify ownership: employee must report to this manager (unless admin)
if (current_role() !== 'admin' && (int)$sheet['emp_manager_id'] !== $manager_id) {
    flash('error', 'This employee does not report to you.');
    redirect('/manager/dashboard.php');
}

// A manager cannot edit their own sheet here
if ((int)$sheet['user_id'] === $manager_id) {
    flash('error', 'Use the Employee section to edit your own goals.');
    redirect('/employee/goal_create.php');
}

// Only allow editing when sheet is editable
$editable_statuses = ['draft', 'returned'];
if (!in_array($sheet['status'], $editable_statuses)) {
    flash('error', 'Goal sheet is "' . $sheet['status'] . '" and cannot be edited by manager. Only draft/returned sheets are editable.');
    redirect('/manager/dashboard.php');
}

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $action         = $_POST['action'] ?? 'save';
    $posted_version = (int)($_POST['version'] ?? 0);

    // Optimistic locking
    $ver_stmt = $pdo->prepare("SELECT version FROM goal_sheets WHERE id = ?");
    $ver_stmt->execute([$sheet_id]);
    if ((int)$ver_stmt->fetchColumn() !== $posted_version) {
        flash('error', 'Goal sheet was modified concurrently. Please refresh.');
        redirect('/manager/edit_goals.php?sheet_id=' . $sheet_id);
    }

    $posted_goals = $_POST['goals'] ?? [];
    $delete_ids   = $_POST['delete_goals'] ?? [];

    // Soft-delete goals marked for removal
    foreach ($delete_ids as $del_id) {
        $del_id = (int)$del_id;
        if ($del_id < 1) continue;
        $stmt = $pdo->prepare("UPDATE goals SET is_deleted = TRUE WHERE id = ? AND goal_sheet_id = ? AND is_shared = FALSE");
        $stmt->execute([$del_id, $sheet_id]);
        audit_log('goals', $del_id, 'SOFT_DELETE', $manager_id, null, null, json_encode(['deleted_by' => 'manager']));
    }

    // Save / insert goals
    $goals_data = [];
    foreach ($posted_goals as $idx => $g) {
        if (!empty($g['is_shared']) && $g['is_shared'] === '1') {
            // Shared goal — only weightage update allowed
            $w = max(MIN_WEIGHTAGE, min(MAX_WEIGHTAGE, (int)($g['weightage'] ?? MIN_WEIGHTAGE)));
            if (!empty($g['id'])) {
                $pdo->prepare("UPDATE goals SET weightage = ? WHERE id = ? AND goal_sheet_id = ?")
                    ->execute([$w, (int)$g['id'], $sheet_id]);
            }
            $goals_data[] = ['weightage' => $w, 'title' => $g['title'] ?? '', 'thrust_area_id' => $g['thrust_area_id'] ?? 0, 'uom_type' => $g['uom_type'] ?? ''];
            continue;
        }

        $goal_data = [
            'thrust_area_id' => (int)($g['thrust_area_id'] ?? 0),
            'title'          => sanitize($g['title'] ?? ''),
            'description'    => sanitize($g['description'] ?? ''),
            'uom_type'       => $g['uom_type'] ?? '',
            'target_value'   => validate_numeric($g['target_value'] ?? null),
            'target_date'    => !empty($g['target_date']) ? $g['target_date'] : null,
            'weightage'      => max(MIN_WEIGHTAGE, min(MAX_WEIGHTAGE, (int)($g['weightage'] ?? MIN_WEIGHTAGE))),
            'sort_order'     => (int)$idx,
        ];
        $goals_data[] = $goal_data;

        if (!empty($g['id'])) {
            $gid = (int)$g['id'];
            $pdo->prepare("
                UPDATE goals SET thrust_area_id=?, title=?, description=?, uom_type=?,
                       target_value=?, target_date=?, weightage=?, sort_order=?
                WHERE id=? AND goal_sheet_id=?
            ")->execute([
                $goal_data['thrust_area_id'], $goal_data['title'], $goal_data['description'],
                $goal_data['uom_type'], $goal_data['target_value'], $goal_data['target_date'],
                $goal_data['weightage'], $goal_data['sort_order'], $gid, $sheet_id
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO goals (goal_sheet_id, thrust_area_id, title, description, uom_type,
                                   target_value, target_date, weightage, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $sheet_id, $goal_data['thrust_area_id'], $goal_data['title'], $goal_data['description'],
                $goal_data['uom_type'], $goal_data['target_value'], $goal_data['target_date'],
                $goal_data['weightage'], $goal_data['sort_order']
            ]);
            audit_log('goals', null, 'INSERT', $manager_id, null, null, json_encode(['added_by' => 'manager', 'for_sheet' => $sheet_id]));
        }
    }

    // Bump version
    $pdo->prepare("UPDATE goal_sheets SET version = version + 1 WHERE id = ?")->execute([$sheet_id]);

    // Notify employee
    create_notification(
        $sheet['user_id'], 'manager_edit',
        'Your manager ' . h(current_user_name()) . ' has updated your goal sheet.',
        '/employee/goal_sheet.php'
    );

    flash('success', 'Goal sheet updated successfully.');
    redirect('/manager/edit_goals.php?sheet_id=' . $sheet_id);
}

// ── Load data for rendering ──────────────────────────────────────────────────
$goals       = get_goals_for_sheet($sheet_id);
$thrust_areas = get_thrust_areas($sheet['department'] ?? null);

// Refresh sheet
$stmt = $pdo->prepare("SELECT * FROM goal_sheets WHERE id = ?");
$stmt->execute([$sheet_id]);
$sheet_fresh = $stmt->fetch();

include __DIR__ . '/../../includes/layout/header.php';
?>

<div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
    <a href="/manager/dashboard.php" class="btn btn-secondary btn-sm">← Back to Team</a>
    <h1 style="margin:0;">Edit Goals: <?= h($sheet['employee_name']) ?></h1>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Manager Edit Mode</strong> — You are editing <strong><?= h($sheet['employee_name']) ?></strong>'s goal sheet
    on their behalf. Status: <?= status_badge($sheet_fresh['status']) ?>
    &nbsp;· Department: <?= h($sheet['department']) ?>
</div>

<?php if ($sheet_fresh['return_comment']): ?>
<div class="alert alert-warning">
    <strong>Return Comment:</strong> <?= h($sheet_fresh['return_comment']) ?>
</div>
<?php endif; ?>

<!-- Weightage Tracker -->
<div class="card">
    <div class="weightage-info">
        <span id="weightageLabel">0% / 100%</span>
        <span id="goalCountLabel">0 / 8 goals</span>
    </div>
    <div class="weightage-bar">
        <div class="weightage-bar-fill under" style="width:0%"></div>
    </div>
</div>

<form method="POST" id="goalForm">
    <?= csrf_field() ?>
    <input type="hidden" name="version" value="<?= $sheet_fresh['version'] ?>">

    <div id="goalsContainer">
        <?php foreach ($goals as $i => $g): ?>
        <div class="goal-row <?= $g['is_shared'] ? 'shared' : '' ?>"
             data-goal-id="<?= $g['id'] ?>" data-index="<?= $i + 1 ?>">
            <div class="goal-row-header">
                <span class="goal-row-number">Goal #<?= $i + 1 ?></span>
                <?php if ($g['is_shared']): ?>
                    <span class="badge badge-info">Shared</span>
                <?php else: ?>
                    <button type="button" class="btn-remove" onclick="removeGoalRow(this)" title="Remove goal">✕</button>
                <?php endif; ?>
            </div>
            <input type="hidden" name="goals[<?= $i ?>][id]" value="<?= $g['id'] ?>">
            <input type="hidden" name="goals[<?= $i ?>][is_shared]" value="<?= $g['is_shared'] ? '1' : '0' ?>">
            <input type="hidden" name="goals[<?= $i ?>][shared_source_id]" value="<?= $g['shared_source_id'] ?>">
            <div class="goal-row-fields">
                <div class="form-group">
                    <label>Thrust Area</label>
                    <select name="goals[<?= $i ?>][thrust_area_id]" class="form-control" required <?= $g['is_shared'] ? 'disabled' : '' ?>>
                        <option value="">Select...</option>
                        <?php foreach ($thrust_areas as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id'] == $g['thrust_area_id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($g['is_shared']): ?><input type="hidden" name="goals[<?= $i ?>][thrust_area_id]" value="<?= $g['thrust_area_id'] ?>"><?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Goal Title</label>
                    <input type="text" name="goals[<?= $i ?>][title]" class="form-control" maxlength="255"
                           value="<?= h($g['title']) ?>" required <?= $g['is_shared'] ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="goals[<?= $i ?>][description]" class="form-control" <?= $g['is_shared'] ? 'readonly' : '' ?>><?= h($g['description']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Unit of Measurement</label>
                    <select name="goals[<?= $i ?>][uom_type]" class="form-control uom-select" required <?= $g['is_shared'] ? 'disabled' : '' ?>
                            onchange="toggleTargetField(this)">
                        <option value="">Select...</option>
                        <?php foreach (UOM_TYPES as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $k === $g['uom_type'] ? 'selected' : '' ?>><?= h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($g['is_shared']): ?><input type="hidden" name="goals[<?= $i ?>][uom_type]" value="<?= $g['uom_type'] ?>"><?php endif; ?>
                </div>
                <div class="form-group target-value-group" style="<?= in_array($g['uom_type'], ['timeline','zero']) ? 'display:none' : '' ?>">
                    <label>Target Value</label>
                    <input type="number" name="goals[<?= $i ?>][target_value]" class="form-control"
                           step="0.01" min="0" value="<?= h($g['target_value']) ?>" <?= $g['is_shared'] ? 'readonly' : '' ?>>
                </div>
                <div class="form-group target-date-group" style="<?= $g['uom_type'] === 'timeline' ? '' : 'display:none' ?>">
                    <label>Target Date</label>
                    <input type="date" name="goals[<?= $i ?>][target_date]" class="form-control"
                           value="<?= h($g['target_date']) ?>" <?= $g['is_shared'] ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Weightage (%)</label>
                    <input type="number" name="goals[<?= $i ?>][weightage]" class="form-control goal-weightage"
                           min="10" max="100" step="5" value="<?= $g['weightage'] ?>"
                           onchange="updateWeightageBar()" oninput="updateWeightageBar()">
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mb-2">
        <button type="button" id="addGoalBtn" class="btn btn-outline" onclick="addGoalRow()">+ Add Goal</button>
    </div>

    <div class="btn-group mt-2">
        <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
        <a href="/manager/dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
window._thrustAreas = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $thrust_areas)) ?>;
window._uomTypes    = <?= json_encode(UOM_TYPES) ?>;
goalCounter         = <?= count($goals) ?>;
</script>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
