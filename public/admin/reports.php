<?php
/**
 * AtomQuest — Admin Reports & Export
 */

$page_title = 'Reports';
$use_bootstrap = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();
$cycle = get_active_cycle();
$quarter = $_GET['quarter'] ?? get_current_quarter($cycle) ?? 'Q1';
$dept_filter = $_GET['department'] ?? '';

// Fetch all departments
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Build report
$report = [];
if ($cycle) {
    $dept_where = $dept_filter ? "AND u.department = ?" : "";
    $params = [$cycle['id']];
    if ($dept_filter) $params[] = $dept_filter;

    $stmt = $pdo->prepare("
        SELECT u.name, u.employee_id, u.department, gs.status AS sheet_status, gs.id AS sheet_id,
               g.title, g.uom_type, g.target_value, g.target_date, g.weightage, g.is_shared,
               t.name AS thrust_area,
               a.actual_value, a.completion_date, a.computed_score, a.status AS achievement_status, a.is_late_entry
        FROM users u
        LEFT JOIN goal_sheets gs ON gs.user_id = u.id AND gs.cycle_id = ?
        LEFT JOIN goals g ON g.goal_sheet_id = gs.id AND g.is_deleted = FALSE
        LEFT JOIN thrust_areas t ON t.id = g.thrust_area_id
        LEFT JOIN achievements a ON a.goal_id = g.id AND a.quarter = '$quarter'
        WHERE u.role IN ('employee','manager') AND u.is_active = TRUE $dept_where
        ORDER BY u.department, u.name, g.sort_order
    ");
    $stmt->execute($params);
    $report = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Achievement Report — <?= $quarter ?></h1>

<!-- Filters -->
<div class="card">
    <form method="GET" class="form-inline">
        <div class="form-group">
            <label>Quarter</label>
            <select name="quarter" class="form-control">
                <?php foreach (QUARTERS as $q): ?>
                <option value="<?= $q ?>" <?= $q === $quarter ? 'selected' : '' ?>><?= $q ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Department</label>
            <select name="department" class="form-control">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= h($d) ?>" <?= $dept_filter === $d ? 'selected' : '' ?>><?= h($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/api/export.php?quarter=<?= $quarter ?>&department=<?= urlencode($dept_filter) ?>" class="btn btn-success">📥 Export CSV</a>
    </form>
</div>

<!-- Report Table -->
<div class="card">
    <div class="card-header"><h2><?= count($report) ?> rows</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th><th>Dept</th><th>Goal</th><th>Thrust Area</th>
                    <th>UoM</th><th>Target</th><th>Actual</th><th>Score</th><th>Weight</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $r): ?>
                <tr>
                    <td><?= h($r['name']) ?></td>
                    <td><?= h($r['department']) ?></td>
                    <td>
                        <?= h($r['title'] ?? '—') ?>
                        <?php if (!empty($r['is_shared'])): ?><span class="badge badge-info">S</span><?php endif; ?>
                    </td>
                    <td><?= h($r['thrust_area'] ?? '—') ?></td>
                    <td><?= $r['uom_type'] ? h(uom_label($r['uom_type'])) : '—' ?></td>
                    <td><?= $r['uom_type'] === 'timeline' ? h($r['target_date'] ?? '—') : h($r['target_value'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['actual_value'] !== null): ?>
                            <?= h($r['uom_type'] === 'timeline' ? ($r['completion_date'] ?? '—') : $r['actual_value']) ?>
                            <?php if (!empty($r['is_late_entry'])): ?><span class="badge badge-late">LATE</span><?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $r['computed_score'] !== null ? $r['computed_score'] . '%' : '—' ?></td>
                    <td><?= $r['weightage'] ? $r['weightage'] . '%' : '—' ?></td>
                    <td><?= $r['achievement_status'] ? status_badge($r['achievement_status']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
