<?php
/**
 * AtomQuest — Achievement Entry (Quarterly)
 */

$page_title = 'Log Achievement';
require_once __DIR__ . '/../../includes/auth.php';
require_role('employee', 'manager');

$pdo = get_db();
$user_id = current_user_id();
$cycle = get_active_cycle();

if (!$cycle) {
    flash('error', 'No active cycle.');
    redirect('/employee/dashboard.php');
}

$sheet = get_goal_sheet($user_id, $cycle['id']);
if (!$sheet || !in_array($sheet['status'], ['approved', 'locked'])) {
    flash('error', 'Goal sheet must be approved before logging achievements.');
    redirect('/employee/dashboard.php');
}

// Determine quarter
$quarter = $_GET['quarter'] ?? get_current_quarter($cycle);
if (!$quarter || !in_array($quarter, QUARTERS)) {
    flash('error', 'Invalid quarter.');
    redirect('/employee/dashboard.php');
}

$is_late = is_quarter_past($quarter, $cycle);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $achievements = $_POST['achievements'] ?? [];
    $goals = get_goals_for_sheet($sheet['id']);

    foreach ($goals as $g) {
        $a = $achievements[$g['id']] ?? null;
        if (!$a) continue;

        $actual_value = null;
        $completion_date = null;

        if (in_array($g['uom_type'], ['numeric_min', 'numeric_max', 'percent_min', 'percent_max'])) {
            $actual_value = validate_numeric($a['actual_value'] ?? null);
        } elseif ($g['uom_type'] === 'timeline') {
            $completion_date = !empty($a['completion_date']) ? $a['completion_date'] : null;
        } elseif ($g['uom_type'] === 'zero') {
            $actual_value = validate_numeric($a['actual_value'] ?? null, 0, 999999);
        }

        $status = in_array($a['status'] ?? '', ACHIEVEMENT_STATUSES) ? $a['status'] : 'not_started';

        // Compute score
        $target = $g['uom_type'] === 'timeline' ? $g['target_date'] : $g['target_value'];
        $actual = $g['uom_type'] === 'timeline' ? $completion_date : $actual_value;
        $score = compute_score($g['uom_type'], $target, $actual);

        $stmt = $pdo->prepare("
            INSERT INTO achievements (goal_id, quarter, actual_value, completion_date, status, computed_score, is_late_entry, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (goal_id, quarter) DO UPDATE SET
                actual_value = EXCLUDED.actual_value,
                completion_date = EXCLUDED.completion_date,
                status = EXCLUDED.status,
                computed_score = EXCLUDED.computed_score,
                is_late_entry = EXCLUDED.is_late_entry,
                updated_at = NOW(),
                updated_by = EXCLUDED.updated_by
        ");
        $stmt->execute([
            $g['id'], $quarter, $actual_value, $completion_date,
            $status, $score, $is_late ? 't' : 'f', $user_id
        ]);

        // Sync shared achievements if this is a primary owner goal
        $check = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE shared_source_id = ? AND is_deleted = FALSE");
        $check->execute([$g['id']]);
        if ((int)$check->fetchColumn() > 0) {
            sync_shared_achievements($g['id'], $quarter);
        }
    }

    flash('success', "$quarter achievements saved." . ($is_late ? ' (Flagged as late entry)' : ''));
    redirect('/employee/dashboard.php');
}

// Load goals and existing achievements
$goals = get_goals_for_sheet($sheet['id']);
$existing = [];
$stmt = $pdo->prepare("
    SELECT a.* FROM achievements a
    JOIN goals g ON g.id = a.goal_id
    WHERE g.goal_sheet_id = ? AND a.quarter = ? AND g.is_deleted = FALSE
");
$stmt->execute([$sheet['id'], $quarter]);
foreach ($stmt->fetchAll() as $a) {
    $existing[$a['goal_id']] = $a;
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Log Achievement — <?= h($quarter) ?> <?= h($cycle['cycle_name']) ?></h1>

<?php if ($is_late): ?>
<div class="alert alert-warning">
    <strong>Late Entry:</strong> The <?= $quarter ?> window has closed. Your entries will be flagged as late.
</div>
<?php endif; ?>

<form method="POST">
    <?= csrf_field() ?>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Goal</th>
                        <th>UoM</th>
                        <th>Target</th>
                        <th>Weight</th>
                        <th>Actual</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($goals as $i => $g):
                        $ach = $existing[$g['id']] ?? null;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?= h($g['title']) ?>
                            <?php if ($g['is_shared']): ?><span class="badge badge-info">Shared</span><?php endif; ?>
                        </td>
                        <td><?= h(uom_label($g['uom_type'])) ?></td>
                        <td><?= $g['uom_type'] === 'timeline' ? h($g['target_date']) : h($g['target_value']) ?></td>
                        <td><?= $g['weightage'] ?>%</td>
                        <td>
                            <?php if ($g['uom_type'] === 'timeline'): ?>
                                <input type="date" name="achievements[<?= $g['id'] ?>][completion_date]"
                                       class="form-control" value="<?= h($ach['completion_date'] ?? '') ?>">
                            <?php elseif ($g['uom_type'] === 'zero'): ?>
                                <input type="number" name="achievements[<?= $g['id'] ?>][actual_value]"
                                       class="form-control" min="0" step="1"
                                       value="<?= h($ach['actual_value'] ?? '') ?>"
                                       placeholder="0 = success">
                            <?php else: ?>
                                <input type="number" name="achievements[<?= $g['id'] ?>][actual_value]"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= h($ach['actual_value'] ?? '') ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="achievements[<?= $g['id'] ?>][status]" class="form-control">
                                <?php foreach (ACHIEVEMENT_STATUSES as $s): ?>
                                <option value="<?= $s ?>" <?= ($ach['status'] ?? 'not_started') === $s ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <?php if ($ach && $ach['computed_score'] !== null): ?>
                                <?= $ach['computed_score'] ?>%
                                <?php if (!empty($ach['is_late_entry'])): ?><span class="badge badge-late">LATE</span><?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="btn-group">
        <a href="/employee/dashboard.php" class="btn btn-secondary">← Back</a>
        <button type="submit" class="btn btn-success">Save Achievements</button>
    </div>
</form>

<!-- Quarter selector -->
<div class="card mt-2">
    <div class="card-header"><h3>View Other Quarters</h3></div>
    <div class="btn-group">
        <?php foreach (QUARTERS as $q): ?>
            <a href="/employee/achievement.php?quarter=<?= $q ?>"
               class="btn <?= $q === $quarter ? 'btn-primary' : 'btn-outline' ?>"><?= $q ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
