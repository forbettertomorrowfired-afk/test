<?php
/**
 * AtomQuest — Shared Goals Management
 */

$page_title = 'Shared Goals';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin', 'manager');

$pdo = get_db();
$cycle = get_active_cycle();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_template') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $thrust_area_id = (int)($_POST['thrust_area_id'] ?? 0);
        $uom_type = $_POST['uom_type'] ?? '';
        $target_value = validate_numeric($_POST['target_value'] ?? null);
        $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
        $department = sanitize($_POST['department'] ?? '') ?: null;
        $primary_owner_id = !empty($_POST['primary_owner_id']) ? (int)$_POST['primary_owner_id'] : null;

        if (empty($title) || !$cycle) {
            flash('error', 'Title and active cycle required.');
            redirect('/admin/shared_goals.php');
        }

        $stmt = $pdo->prepare("INSERT INTO shared_goal_templates (title, description, thrust_area_id, uom_type, target_value, target_date, department, created_by, primary_owner_id, cycle_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$title, $description, $thrust_area_id, $uom_type, $target_value, $target_date, $department, current_user_id(), $primary_owner_id, $cycle['id']]);
        $template_id = $pdo->lastInsertId();

        flash('success', 'Shared goal template created. Now push it to employees.');
        redirect('/admin/shared_goals.php');
    }

    if ($action === 'push') {
        $template_id = (int)($_POST['template_id'] ?? 0);
        $user_ids = $_POST['user_ids'] ?? [];

        $stmt = $pdo->prepare("SELECT * FROM shared_goal_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();

        if (!$template || empty($user_ids)) {
            flash('error', 'Invalid template or no users selected.');
            redirect('/admin/shared_goals.php');
        }

        $pushed = 0;
        foreach ($user_ids as $uid) {
            $uid = (int)$uid;
            // Ensure user has a goal sheet
            $sheet = get_goal_sheet($uid, $template['cycle_id']);
            if (!$sheet) {
                $stmt = $pdo->prepare("INSERT INTO goal_sheets (user_id, cycle_id, status) VALUES (?, ?, 'draft') RETURNING id");
                $stmt->execute([$uid, $template['cycle_id']]);
                $sheet_id = (int)$stmt->fetchColumn();
            } else {
                $sheet_id = $sheet['id'];
            }

            // Check if already has this shared goal
            $check = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE goal_sheet_id = ? AND is_shared = TRUE AND title = ? AND is_deleted = FALSE");
            $check->execute([$sheet_id, $template['title']]);
            if ((int)$check->fetchColumn() > 0) continue;

            // Find primary owner's goal (if exists)
            $source_id = null;
            if ($template['primary_owner_id']) {
                $primary_sheet = get_goal_sheet($template['primary_owner_id'], $template['cycle_id']);
                if ($primary_sheet) {
                    $src = $pdo->prepare("SELECT id FROM goals WHERE goal_sheet_id = ? AND title = ? AND is_deleted = FALSE LIMIT 1");
                    $src->execute([$primary_sheet['id'], $template['title']]);
                    $source_id = $src->fetchColumn() ?: null;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO goals (goal_sheet_id, thrust_area_id, title, description, uom_type, target_value, target_date, weightage, is_shared, shared_source_id) VALUES (?,?,?,?,?,?,?,10,TRUE,?)");
            $stmt->execute([$sheet_id, $template['thrust_area_id'], $template['title'], $template['description'], $template['uom_type'], $template['target_value'], $template['target_date'], $source_id]);

            create_notification($uid, 'shared_goal', 'A shared departmental goal has been added to your sheet: ' . $template['title'], '/employee/goal_create.php');
            $pushed++;
        }

        flash('success', "Shared goal pushed to $pushed employee(s).");
        redirect('/admin/shared_goals.php');
    }
}

$templates = $cycle ? $pdo->prepare("SELECT sgt.*, u.name AS creator_name, po.name AS owner_name
    FROM shared_goal_templates sgt
    JOIN users u ON u.id = sgt.created_by
    LEFT JOIN users po ON po.id = sgt.primary_owner_id
    WHERE sgt.cycle_id = ? ORDER BY sgt.created_at DESC") : null;
if ($templates) { $templates->execute([$cycle['id']]); $templates = $templates->fetchAll(); } else { $templates = []; }

$thrust_areas = get_thrust_areas();
$employees = $pdo->query("SELECT id, name, department FROM users WHERE role IN ('employee','manager') AND is_active = TRUE ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Shared Goals</h1>

<?php if (!$cycle): ?>
    <div class="alert alert-info">No active cycle. Create one first.</div>
<?php else: ?>

<!-- Templates -->
<div class="card">
    <div class="card-header">
        <h2>Shared Goal Templates</h2>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('createForm').style.display='block'">+ New Template</button>
    </div>
    <?php if (empty($templates)): ?>
        <p class="text-muted">No shared goal templates yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>UoM</th><th>Target</th><th>Primary Owner</th><th>Created By</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?= h($t['title']) ?></td>
                    <td><?= h(uom_label($t['uom_type'])) ?></td>
                    <td><?= $t['uom_type'] === 'timeline' ? h($t['target_date']) : h($t['target_value']) ?></td>
                    <td><?= h($t['owner_name'] ?? '—') ?></td>
                    <td><?= h($t['creator_name']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="showPush(<?= $t['id'] ?>)">Push to Employees</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create Template Form -->
<div class="card" id="createForm" style="display:none;">
    <div class="card-header"><h2>Create Shared Goal Template</h2></div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_template">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group">
                <label>Thrust Area</label>
                <select name="thrust_area_id" class="form-control" required>
                    <?php foreach ($thrust_areas as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>UoM Type</label>
                <select name="uom_type" class="form-control" required>
                    <?php foreach (UOM_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Target Value</label><input type="number" name="target_value" class="form-control" step="0.01"></div>
            <div class="form-group"><label>Target Date</label><input type="date" name="target_date" class="form-control"></div>
            <div class="form-group">
                <label>Primary Owner</label>
                <select name="primary_owner_id" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        <div class="btn-group"><button type="submit" class="btn btn-primary">Create</button><button type="button" class="btn btn-secondary" onclick="document.getElementById('createForm').style.display='none'">Cancel</button></div>
    </form>
</div>

<!-- Push Dialog -->
<div class="card" id="pushForm" style="display:none;">
    <div class="card-header"><h2>Push to Employees</h2></div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="push">
        <input type="hidden" name="template_id" id="pushTemplateId" value="">
        <div class="form-group">
            <label>Select Employees</label>
            <div style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius); padding:8px;">
                <?php foreach ($employees as $e): ?>
                <label style="display:block; padding:2px 0;"><input type="checkbox" name="user_ids[]" value="<?= $e['id'] ?>"> <?= h($e['name']) ?> (<?= h($e['department']) ?>)</label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="btn-group"><button type="submit" class="btn btn-success">Push</button><button type="button" class="btn btn-secondary" onclick="document.getElementById('pushForm').style.display='none'">Cancel</button></div>
    </form>
</div>

<script>
function showPush(tid) {
    document.getElementById('pushTemplateId').value = tid;
    document.getElementById('pushForm').style.display = 'block';
    window.scrollTo({top: document.getElementById('pushForm').offsetTop, behavior: 'smooth'});
}
</script>

<?php endif; ?>
<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
