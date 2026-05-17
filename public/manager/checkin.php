<?php
/**
 * AtomQuest — Manager Check-in Page
 */

$page_title = 'Quarterly Check-in';
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager', 'admin');

$pdo = get_db();
$manager_id = current_user_id();
$sheet_id = (int)($_GET['sheet_id'] ?? 0);
$cycle = get_active_cycle();

if (!$sheet_id || !$cycle) {
    flash('error', 'Invalid request.');
    redirect('/manager/dashboard.php');
}

$stmt = $pdo->prepare("SELECT gs.*, u.name AS employee_name, u.employee_id
                        FROM goal_sheets gs JOIN users u ON u.id = gs.user_id WHERE gs.id = ?");
$stmt->execute([$sheet_id]);
$sheet = $stmt->fetch();

if (!$sheet || !in_array($sheet['status'], ['approved', 'locked'])) {
    flash('error', 'Goal sheet must be approved for check-in.');
    redirect('/manager/dashboard.php');
}

$quarter = $_GET['quarter'] ?? get_current_quarter($cycle) ?? 'Q1';
if (!in_array($quarter, QUARTERS)) $quarter = 'Q1';

// Handle comment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $comment = sanitize($_POST['comment'] ?? '');
    if (!empty($comment)) {
        $stmt = $pdo->prepare("
            INSERT INTO checkin_comments (goal_sheet_id, quarter, manager_id, comment)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sheet_id, $quarter, $manager_id, $comment]);
        audit_log('checkin_comments', $sheet_id, 'INSERT', $manager_id, null, null, $comment);
        create_notification($sheet['user_id'], 'checkin_completed',
            current_user_name() . " has completed your $quarter check-in.",
            '/employee/dashboard.php');
        flash('success', "$quarter check-in comment saved.");
        redirect('/manager/checkin.php?sheet_id=' . $sheet_id . '&quarter=' . $quarter);
    }
}

$goals = get_goals_for_sheet($sheet_id);

// Load achievements
$achievements = [];
$stmt = $pdo->prepare("SELECT a.* FROM achievements a JOIN goals g ON g.id = a.goal_id
                        WHERE g.goal_sheet_id = ? AND a.quarter = ? AND g.is_deleted = FALSE");
$stmt->execute([$sheet_id, $quarter]);
foreach ($stmt->fetchAll() as $a) {
    $achievements[$a['goal_id']] = $a;
}

$weighted_score = compute_weighted_score($sheet_id, $quarter);

// Existing comments
$stmt = $pdo->prepare("SELECT cc.*, u.name AS manager_name FROM checkin_comments cc
                        JOIN users u ON u.id = cc.manager_id
                        WHERE cc.goal_sheet_id = ? AND cc.quarter = ? ORDER BY cc.created_at DESC");
$stmt->execute([$sheet_id, $quarter]);
$comments = $stmt->fetchAll();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Check-in: <?= h($sheet['employee_name']) ?> — <?= $quarter ?></h1>

<!-- Quarter selector -->
<div class="btn-group mb-2">
    <?php foreach (QUARTERS as $q): ?>
        <a href="/manager/checkin.php?sheet_id=<?= $sheet_id ?>&quarter=<?= $q ?>"
           class="btn <?= $q === $quarter ? 'btn-primary' : 'btn-outline' ?>"><?= $q ?></a>
    <?php endforeach; ?>
</div>

<!-- Score Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $weighted_score !== null ? $weighted_score . '%' : '—' ?></div>
        <div class="stat-label"><?= $quarter ?> Weighted Score</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($achievements, fn($a) => $a['computed_score'] !== null)) ?> / <?= count($goals) ?></div>
        <div class="stat-label">Goals with Actuals</div>
    </div>
</div>

<!-- Planned vs Actual -->
<div class="card">
    <div class="card-header">
        <h2>Planned vs Actual — <?= $quarter ?></h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Goal</th>
                    <th>UoM</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Score</th>
                    <th>Weight</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goals as $i => $g):
                    $ach = $achievements[$g['id']] ?? null;
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= h($g['title']) ?> <?= $g['is_shared'] ? '<span class="badge badge-info">Shared</span>' : '' ?></td>
                    <td><?= h(uom_label($g['uom_type'])) ?></td>
                    <td><?= $g['uom_type'] === 'timeline' ? h($g['target_date']) : h($g['target_value']) ?></td>
                    <td>
                        <?php if ($ach): ?>
                            <?= $g['uom_type'] === 'timeline' ? h($ach['completion_date'] ?? '—') : h($ach['actual_value'] ?? '—') ?>
                            <?php if (!empty($ach['is_late_entry'])): ?><span class="badge badge-late">LATE</span><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $ach && $ach['computed_score'] !== null ? $ach['computed_score'] . '%' : '—' ?></td>
                    <td><?= $g['weightage'] ?>%</td>
                    <td><?= $ach ? status_badge($ach['status']) : '<span class="text-muted">—</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Check-in Comment -->
<div class="card">
    <div class="card-header"><h2>Check-in Comments</h2></div>

    <?php foreach ($comments as $c): ?>
    <div style="padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:var(--radius);">
        <strong><?= h($c['manager_name']) ?></strong>
        <span class="text-muted" style="font-size:0.8rem"> — <?= date('d M Y H:i', strtotime($c['created_at'])) ?></span>
        <p style="margin-top:4px;"><?= h($c['comment']) ?></p>
    </div>
    <?php endforeach; ?>

    <form method="POST" class="mt-1">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Add Check-in Comment</label>
            <textarea name="comment" class="form-control" rows="3" required
                      placeholder="Document the check-in discussion..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Comment</button>
    </form>
</div>

<a href="/manager/dashboard.php" class="btn btn-secondary mt-2">← Back to Team</a>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
