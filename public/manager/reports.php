<?php
/**
 * NexusSync - Manager Reports
 */

$page_title = 'Team Reports';
$use_chartjs = true;
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager');

$pdo = get_db();
$manager_id = current_user_id();
$cycle = get_active_cycle();
$team = get_team_members($manager_id);
$quarter = $_GET['quarter'] ?? get_current_quarter($cycle) ?? 'Q1';

// Build report data
$report = [];
foreach ($team as $member) {
    $sheet = $cycle ? get_goal_sheet($member['id'], $cycle['id']) : null;
    $score = ($sheet) ? compute_weighted_score($sheet['id'], $quarter) : null;
    $report[] = [
        'name'       => $member['name'],
        'department' => $member['department'],
        'status'     => $sheet ? $sheet['status'] : 'Not started',
        'score'      => $score,
    ];
}

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Team Reports - <?= h($quarter) ?></h1>

<div class="btn-group mb-2">
    <?php foreach (QUARTERS as $q): ?>
        <a href="/manager/reports.php?quarter=<?= $q ?>"
           class="btn <?= $q === $quarter ? 'btn-primary' : 'btn-outline' ?>"><?= $q ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h2>Team Scores - <?= $quarter ?></h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Employee</th><th>Department</th><th>Sheet Status</th><th><?= $quarter ?> Score</th></tr>
            </thead>
            <tbody>
                <?php foreach ($report as $r): ?>
                <tr>
                    <td><?= h($r['name']) ?></td>
                    <td><?= h($r['department']) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <?php if ($r['score'] !== null): ?>
                            <div class="progress" style="width:150px; display:inline-block; vertical-align:middle;">
                                <div class="progress-bar progress-bar-<?= $r['score'] >= 100 ? 'success' : ($r['score'] >= 50 ? 'primary' : 'danger') ?>"
                                     style="width:<?= min($r['score'], 100) ?>%"><?= $r['score'] ?>%</div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart -->
<div class="card">
    <div class="card-header"><h2>Score Distribution</h2></div>
    <canvas id="scoreChart" height="200"></canvas>
</div>

<?php
$chart_labels = json_encode(array_column($report, 'name'));
$chart_data = json_encode(array_map(fn($r) => $r['score'] ?? 0, $report));
$page_scripts = "
const ctx = document.getElementById('scoreChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: $chart_labels,
            datasets: [{
                label: '$quarter Weighted Score (%)',
                data: $chart_data,
                backgroundColor: 'rgba(37, 99, 235, 0.6)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 150 } }
        }
    });
}
";
?>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
