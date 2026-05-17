<?php
/**
 * AtomQuest — User Management
 */

$page_title = 'Manage Users';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $data = [
            'employee_id' => sanitize($_POST['employee_id'] ?? ''),
            'name'        => sanitize($_POST['name'] ?? ''),
            'email'       => sanitize($_POST['email'] ?? ''),
            'role'        => $_POST['role'] ?? 'employee',
            'department'  => sanitize($_POST['department'] ?? ''),
            'manager_id'  => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
            'is_active'   => !empty($_POST['is_active']),
        ];

        if (empty($data['name']) || empty($data['email']) || empty($data['employee_id'])) {
            flash('error', 'Name, email, and employee ID are required.');
            redirect('/admin/users.php');
        }

        if ($action === 'create') {
            $password = $_POST['password'] ?? 'password123';
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (employee_id, name, email, password_hash, role, department, manager_id, is_active)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['employee_id'], $data['name'], $data['email'], $hash,
                           $data['role'], $data['department'], $data['manager_id'], $data['is_active'] ? 't' : 'f']);
            audit_log('users', (int)$pdo->lastInsertId(), 'INSERT');
            flash('success', 'User created. Default password: ' . $password);
        } else {
            // EC-10: Manager change mid-cycle logged
            $old = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $old->execute([$id]);
            $old_data = $old->fetch();

            $stmt = $pdo->prepare("UPDATE users SET employee_id=?, name=?, email=?, role=?, department=?, manager_id=?, is_active=? WHERE id=?");
            $stmt->execute([$data['employee_id'], $data['name'], $data['email'], $data['role'],
                           $data['department'], $data['manager_id'], $data['is_active'] ? 't' : 'f', $id]);

            if ($old_data && (int)($old_data['manager_id'] ?? 0) !== ($data['manager_id'] ?? 0)) {
                audit_log('users', $id, 'UPDATE', null, 'manager_id',
                          (string)($old_data['manager_id'] ?? 'NULL'), (string)($data['manager_id'] ?? 'NULL'));
            }
            flash('success', 'User updated.');
        }

        // Update password if provided
        if (!empty($_POST['new_password']) && $action === 'update') {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
        }

        redirect('/admin/users.php');
    }

    if ($action === 'toggle_active') {
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        audit_log('users', $id, 'UPDATE', null, 'is_active', null, null);
        flash('success', 'User status toggled.');
        redirect('/admin/users.php');
    }
}

$users = $pdo->query("SELECT u.*, m.name AS manager_name FROM users u LEFT JOIN users m ON m.id = u.manager_id ORDER BY u.name")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role IN ('manager','admin') AND is_active = TRUE ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Manage Users</h1>

<div class="card">
    <div class="card-header">
        <h2>All Users (<?= count($users) ?>)</h2>
        <button class="btn btn-primary btn-sm" onclick="showUserForm()">+ New User</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Manager</th><th>Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= h($u['employee_id']) ?></td>
                    <td><?= h($u['name']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td><span class="badge badge-role"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= h($u['department']) ?></td>
                    <td><?= h($u['manager_name'] ?? '—') ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick='editUser(<?= json_encode($u) ?>)'>Edit</button>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Form -->
<div class="card" id="userForm" style="display:none;">
    <div class="card-header"><h2 id="userFormTitle">New User</h2></div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="userAction" value="create">
        <input type="hidden" name="id" id="userId" value="">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" id="uEmpId" class="form-control" required></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" id="uName" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="uEmail" class="form-control" required></div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="uRole" class="form-control">
                    <option value="employee">Employee</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group"><label>Department</label><input type="text" name="department" id="uDept" class="form-control"></div>
            <div class="form-group">
                <label>Reports To</label>
                <select name="manager_id" id="uManager" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($managers as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="pwGroup"><label>Password</label><input type="text" name="password" id="uPw" class="form-control" placeholder="password123"></div>
            <div class="form-group" id="newPwGroup" style="display:none"><label>New Password (leave blank to keep)</label><input type="text" name="new_password" class="form-control"></div>
        </div>
        <div class="form-group"><label><input type="checkbox" name="is_active" id="uActive" value="1" checked> Active</label></div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('userForm').style.display='none'">Cancel</button>
        </div>
    </form>
</div>

<script>
function showUserForm() {
    document.getElementById('userForm').style.display = 'block';
    document.getElementById('userFormTitle').textContent = 'New User';
    document.getElementById('userAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('uEmpId').value = '';
    document.getElementById('uName').value = '';
    document.getElementById('uEmail').value = '';
    document.getElementById('uRole').value = 'employee';
    document.getElementById('uDept').value = '';
    document.getElementById('uManager').value = '';
    document.getElementById('uActive').checked = true;
    document.getElementById('pwGroup').style.display = '';
    document.getElementById('newPwGroup').style.display = 'none';
}
function editUser(u) {
    document.getElementById('userForm').style.display = 'block';
    document.getElementById('userFormTitle').textContent = 'Edit: ' + u.name;
    document.getElementById('userAction').value = 'update';
    document.getElementById('userId').value = u.id;
    document.getElementById('uEmpId').value = u.employee_id;
    document.getElementById('uName').value = u.name;
    document.getElementById('uEmail').value = u.email;
    document.getElementById('uRole').value = u.role;
    document.getElementById('uDept').value = u.department;
    document.getElementById('uManager').value = u.manager_id || '';
    document.getElementById('uActive').checked = u.is_active;
    document.getElementById('pwGroup').style.display = 'none';
    document.getElementById('newPwGroup').style.display = '';
    window.scrollTo({top: document.getElementById('userForm').offsetTop, behavior: 'smooth'});
}
</script>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
