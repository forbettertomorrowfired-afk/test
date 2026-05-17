<?php
/**
 * AtomQuest — Cycle Management
 */

$page_title = 'Manage Cycles';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'cycle_name'          => sanitize($_POST['cycle_name'] ?? ''),
            'goal_setting_opens'  => $_POST['goal_setting_opens'] ?? '',
            'goal_setting_closes' => $_POST['goal_setting_closes'] ?? '',
            'q1_opens' => $_POST['q1_opens'] ?? '', 'q1_closes' => $_POST['q1_closes'] ?? '',
            'q2_opens' => $_POST['q2_opens'] ?? '', 'q2_closes' => $_POST['q2_closes'] ?? '',
            'q3_opens' => $_POST['q3_opens'] ?? '', 'q3_closes' => $_POST['q3_closes'] ?? '',
            'q4_opens' => $_POST['q4_opens'] ?? '', 'q4_closes' => $_POST['q4_closes'] ?? '',
            'is_active' => !empty($_POST['is_active']),
        ];

        if (empty($data['cycle_name'])) {
            flash('error', 'Cycle name is required.');
            redirect('/admin/cycles.php');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO appraisal_cycles (cycle_name, goal_setting_opens, goal_setting_closes,
                q1_opens, q1_closes, q2_opens, q2_closes, q3_opens, q3_closes, q4_opens, q4_closes, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['cycle_name'], $data['goal_setting_opens'], $data['goal_setting_closes'],
                $data['q1_opens'], $data['q1_closes'], $data['q2_opens'], $data['q2_closes'],
                $data['q3_opens'], $data['q3_closes'], $data['q4_opens'], $data['q4_closes'],
                $data['is_active'] ? 't' : 'f'
            ]);
            flash('success', 'Cycle created.');
        } else {
            $stmt = $pdo->prepare("UPDATE appraisal_cycles SET cycle_name=?, goal_setting_opens=?, goal_setting_closes=?,
                q1_opens=?, q1_closes=?, q2_opens=?, q2_closes=?, q3_opens=?, q3_closes=?,
                q4_opens=?, q4_closes=?, is_active=? WHERE id=?");
            $stmt->execute([
                $data['cycle_name'], $data['goal_setting_opens'], $data['goal_setting_closes'],
                $data['q1_opens'], $data['q1_closes'], $data['q2_opens'], $data['q2_closes'],
                $data['q3_opens'], $data['q3_closes'], $data['q4_opens'], $data['q4_closes'],
                $data['is_active'] ? 't' : 'f', $id
            ]);
            flash('success', 'Cycle updated.');
        }
        redirect('/admin/cycles.php');
    }
}

$cycles = $pdo->query("SELECT * FROM appraisal_cycles ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Manage Appraisal Cycles</h1>

<!-- Existing Cycles -->
<div class="card">
    <div class="card-header">
        <h2>All Cycles</h2>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('newCycleForm').style.display='block'">+ New Cycle</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Goal Setting</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Active</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cycles as $c): ?>
                <tr>
                    <td><?= h($c['cycle_name']) ?></td>
                    <td><?= h($c['goal_setting_opens']) ?> — <?= h($c['goal_setting_closes']) ?></td>
                    <td><?= h($c['q1_opens']) ?> — <?= h($c['q1_closes']) ?></td>
                    <td><?= h($c['q2_opens']) ?> — <?= h($c['q2_closes']) ?></td>
                    <td><?= h($c['q3_opens']) ?> — <?= h($c['q3_closes']) ?></td>
                    <td><?= h($c['q4_opens']) ?> — <?= h($c['q4_closes']) ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                    <td><button class="btn btn-sm btn-outline" onclick="editCycle(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New/Edit Cycle Form -->
<div class="card" id="newCycleForm" style="display:none;">
    <div class="card-header"><h2 id="cycleFormTitle">New Cycle</h2></div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="cycleAction" value="create">
        <input type="hidden" name="id" id="cycleId" value="">
        <div class="form-group">
            <label>Cycle Name</label>
            <input type="text" name="cycle_name" id="cycleName" class="form-control" required placeholder="FY 2026-27">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group"><label>Goal Setting Opens</label><input type="date" name="goal_setting_opens" id="gs_opens" class="form-control" required></div>
            <div class="form-group"><label>Goal Setting Closes</label><input type="date" name="goal_setting_closes" id="gs_closes" class="form-control" required></div>
            <div class="form-group"><label>Q1 Opens</label><input type="date" name="q1_opens" id="q1_opens" class="form-control" required></div>
            <div class="form-group"><label>Q1 Closes</label><input type="date" name="q1_closes" id="q1_closes" class="form-control" required></div>
            <div class="form-group"><label>Q2 Opens</label><input type="date" name="q2_opens" id="q2_opens" class="form-control" required></div>
            <div class="form-group"><label>Q2 Closes</label><input type="date" name="q2_closes" id="q2_closes" class="form-control" required></div>
            <div class="form-group"><label>Q3 Opens</label><input type="date" name="q3_opens" id="q3_opens" class="form-control" required></div>
            <div class="form-group"><label>Q3 Closes</label><input type="date" name="q3_closes" id="q3_closes" class="form-control" required></div>
            <div class="form-group"><label>Q4 Opens</label><input type="date" name="q4_opens" id="q4_opens" class="form-control" required></div>
            <div class="form-group"><label>Q4 Closes</label><input type="date" name="q4_closes" id="q4_closes" class="form-control" required></div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" id="cycleActive" value="1"> Set as Active Cycle</label>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('newCycleForm').style.display='none'">Cancel</button>
        </div>
    </form>
</div>

<script>
function editCycle(c) {
    document.getElementById('newCycleForm').style.display = 'block';
    document.getElementById('cycleFormTitle').textContent = 'Edit Cycle';
    document.getElementById('cycleAction').value = 'update';
    document.getElementById('cycleId').value = c.id;
    document.getElementById('cycleName').value = c.cycle_name;
    document.getElementById('gs_opens').value = c.goal_setting_opens;
    document.getElementById('gs_closes').value = c.goal_setting_closes;
    document.getElementById('q1_opens').value = c.q1_opens;
    document.getElementById('q1_closes').value = c.q1_closes;
    document.getElementById('q2_opens').value = c.q2_opens;
    document.getElementById('q2_closes').value = c.q2_closes;
    document.getElementById('q3_opens').value = c.q3_opens;
    document.getElementById('q3_closes').value = c.q3_closes;
    document.getElementById('q4_opens').value = c.q4_opens;
    document.getElementById('q4_closes').value = c.q4_closes;
    document.getElementById('cycleActive').checked = c.is_active;
    window.scrollTo({top: document.getElementById('newCycleForm').offsetTop, behavior: 'smooth'});
}
</script>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
