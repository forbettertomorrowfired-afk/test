<?php
/**
 * NexusSync - Goal Creation & Editing
 * Handles: create new goal sheet, edit draft/returned sheets, save draft, submit
 */

$page_title = 'Goal Sheet';
$use_bootstrap = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('employee', 'manager');

$pdo = get_db();
$user_id = current_user_id();
$cycle = get_active_cycle();

if (!$cycle) {
    flash('error', 'No active appraisal cycle.');
    redirect('/employee/dashboard.php');
}

// Get or create goal sheet
$sheet = get_goal_sheet($user_id, $cycle['id']);

if (!$sheet) {
    // Create new draft
    $stmt = $pdo->prepare("INSERT INTO goal_sheets (user_id, cycle_id, status) VALUES (?, ?, 'draft') RETURNING id");
    $stmt->execute([$user_id, $cycle['id']]);
    $sheet_id = $stmt->fetchColumn();
    $sheet = ['id' => $sheet_id, 'status' => 'draft', 'version' => 1, 'user_id' => $user_id, 'cycle_id' => $cycle['id']];
    audit_log_row('goal_sheets', $sheet_id, 'INSERT', null, $sheet);
} else {
    $sheet_id = $sheet['id'];
}

// Only allow editing in draft or returned status
if (!in_array($sheet['status'], ['draft', 'returned'])) {
    flash('error', 'Goal sheet is ' . $sheet['status'] . ' and cannot be edited.');
    redirect('/employee/goal_sheet.php');
}

// Handle POST - Save Draft or Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $action = $_POST['action'] ?? 'save';
    $posted_version = (int)($_POST['version'] ?? 0);

    // Optimistic locking
    $current = $pdo->prepare("SELECT version FROM goal_sheets WHERE id = ?");
    $current->execute([$sheet_id]);
    $current_version = (int)$current->fetchColumn();

    if ($posted_version !== $current_version) {
        flash('error', 'This goal sheet was modified by another user. Please refresh and try again.');
        redirect('/employee/goal_create.php');
    }

    $posted_goals = $_POST['goals'] ?? [];
    $delete_ids = $_POST['delete_goals'] ?? [];

    // Soft-delete removed goals
    foreach ($delete_ids as $del_id) {
        $del_id = (int)$del_id;
        $stmt = $pdo->prepare("UPDATE goals SET is_deleted = TRUE WHERE id = ? AND goal_sheet_id = ?");
        $stmt->execute([$del_id, $sheet_id]);
        audit_log('goals', $del_id, 'SOFT_DELETE');
    }

    // Validate goals
    $goals_data = [];
    foreach ($posted_goals as $idx => $g) {
        if (!empty($g['is_shared']) && $g['is_shared'] === '1') {
            // Shared goal - only update weightage
            $w = max(MIN_WEIGHTAGE, min(MAX_WEIGHTAGE, (int)($g['weightage'] ?? MIN_WEIGHTAGE)));
            if (!empty($g['id'])) {
                $stmt = $pdo->prepare("UPDATE goals SET weightage = ? WHERE id = ? AND goal_sheet_id = ?");
                $stmt->execute([$w, (int)$g['id'], $sheet_id]);
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
            // Update existing goal
            $gid = (int)$g['id'];
            $stmt = $pdo->prepare("
                UPDATE goals SET thrust_area_id=?, title=?, description=?, uom_type=?,
                       target_value=?, target_date=?, weightage=?, sort_order=?
                WHERE id=? AND goal_sheet_id=?
            ");
            $stmt->execute([
                $goal_data['thrust_area_id'], $goal_data['title'], $goal_data['description'],
                $goal_data['uom_type'], $goal_data['target_value'], $goal_data['target_date'],
                $goal_data['weightage'], $goal_data['sort_order'], $gid, $sheet_id
            ]);
        } else {
            // Insert new goal
            $stmt = $pdo->prepare("
                INSERT INTO goals (goal_sheet_id, thrust_area_id, title, description, uom_type,
                                   target_value, target_date, weightage, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sheet_id, $goal_data['thrust_area_id'], $goal_data['title'], $goal_data['description'],
                $goal_data['uom_type'], $goal_data['target_value'], $goal_data['target_date'],
                $goal_data['weightage'], $goal_data['sort_order']
            ]);
        }
    }

    // Update version
    $pdo->prepare("UPDATE goal_sheets SET version = version + 1 WHERE id = ?")->execute([$sheet_id]);

    if ($action === 'submit') {
        // Full validation
        $errors = validate_goal_sheet($goals_data);
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect('/employee/goal_create.php');
        }

        // Submit
        $stmt = $pdo->prepare("UPDATE goal_sheets SET status = 'submitted', submitted_at = NOW(), version = version + 1 WHERE id = ?");
        $stmt->execute([$sheet_id]);
        audit_log('goal_sheets', $sheet_id, 'UPDATE', null, 'status', 'draft', 'submitted');

        // Notify manager
        $manager_id = $_SESSION['manager_id'] ?? null;
        if ($manager_id) {
            create_notification($manager_id, 'goal_submitted',
                h(current_user_name()) . ' has submitted their goal sheet for approval.',
                '/manager/approve.php?sheet_id=' . $sheet_id);
        }

        flash('success', 'Goal sheet submitted for approval.');
        redirect('/employee/dashboard.php');
    }

    flash('success', 'Draft saved.');
    redirect('/employee/goal_create.php');
}

// Load existing goals
$goals = get_goals_for_sheet($sheet_id);
$thrust_areas = get_thrust_areas($_SESSION['department'] ?? null);

// Refresh sheet data
$sheet = $pdo->prepare("SELECT * FROM goal_sheets WHERE id = ?");
$sheet->execute([$sheet_id]);
$sheet = $sheet->fetch();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Goal Sheet - <?= h($cycle['cycle_name']) ?></h1>

<?php if ($sheet['status'] === 'returned' && $sheet['return_comment']): ?>
<div class="alert alert-warning">
    <strong>Returned by Manager:</strong> <?= h($sheet['return_comment']) ?>
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
    <input type="hidden" name="version" value="<?= $sheet['version'] ?>">

    <div id="goalsContainer">
        <?php foreach ($goals as $i => $g): ?>
        <div class="goal-row <?= $g['is_shared'] ? 'shared' : '' ?>" data-goal-id="<?= $g['id'] ?>" data-index="<?= $i + 1 ?>">
            <div class="goal-row-header">
                <span class="goal-row-number">Goal #<?= $i + 1 ?></span>
                <?php if ($g['is_shared']): ?><span class="badge badge-info">Shared</span><?php endif; ?>
                <?php if (!$g['is_shared']): ?>
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
        <button type="submit" name="action" value="save" class="btn btn-secondary">Save Draft</button>
        <button type="submit" name="action" value="submit" id="submitBtn" class="btn btn-primary" disabled>Submit for Approval</button>
    </div>
</form>

<script>
// Pass data to JS
window._thrustAreas = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $thrust_areas)) ?>;
window._uomTypes = <?= json_encode(UOM_TYPES) ?>;
goalCounter = <?= count($goals) ?>;
</script>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
