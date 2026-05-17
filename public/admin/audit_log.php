<?php
/**
 * AtomQuest — Audit Log Viewer
 */

$page_title = 'Audit Trail';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();

// Filters
$filter_table = $_GET['table'] ?? '';
$filter_user = (int)($_GET['user'] ?? 0);
$filter_action = $_GET['action_type'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if ($filter_table) { $where[] = 'a.table_name = ?'; $params[] = $filter_table; }
if ($filter_user) { $where[] = 'a.changed_by = ?'; $params[] = $filter_user; }
if ($filter_action) { $where[] = 'a.action = ?'; $params[] = $filter_action; }
if ($filter_from) { $where[] = 'a.changed_at >= ?'; $params[] = $filter_from; }
if ($filter_to) { $where[] = 'a.changed_at <= ?'; $params[] = $filter_to . ' 23:59:59'; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $where_sql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$stmt = $pdo->prepare("SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.changed_by $where_sql ORDER BY a.changed_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
$tables = $pdo->query("SELECT DISTINCT table_name FROM audit_log ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Audit Trail</h1>

<!-- Filters -->
<div class="card">
    <form method="GET" class="form-inline">
        <div class="form-group">
            <label>Table</label>
            <select name="table" class="form-control">
                <option value="">All</option>
                <?php foreach ($tables as $t): ?>
                <option value="<?= h($t) ?>" <?= $filter_table === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>User</label>
            <select name="user" class="form-control">
                <option value="">All</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filter_user === $u['id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Action</label>
            <select name="action_type" class="form-control">
                <option value="">All</option>
                <?php foreach (['INSERT','UPDATE','DELETE','UNLOCK','SOFT_DELETE','SYNC'] as $a): ?>
                <option value="<?= $a ?>" <?= $filter_action === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="from" class="form-control" value="<?= h($filter_from) ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="to" class="form-control" value="<?= h($filter_to) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/admin/audit_log.php" class="btn btn-secondary">Reset</a>
    </form>
</div>

<!-- Results -->
<div class="card">
    <div class="card-header">
        <h2>Showing <?= count($logs) ?> of <?= $total ?> entries</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>When</th><th>User</th><th>Table</th><th>Record</th><th>Action</th><th>Field</th><th>Old</th><th>New</th><th>Reason</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td style="white-space:nowrap"><?= date('d M Y H:i:s', strtotime($l['changed_at'])) ?></td>
                    <td><?= h($l['user_name'] ?? 'System') ?></td>
                    <td><code><?= h($l['table_name']) ?></code></td>
                    <td><?= $l['record_id'] ?></td>
                    <td><span class="badge badge-<?= match($l['action']) { 'INSERT' => 'success', 'DELETE','SOFT_DELETE' => 'danger', 'UNLOCK' => 'warning', default => 'secondary' } ?>"><?= h($l['action']) ?></span></td>
                    <td><?= h($l['field_name'] ?? '—') ?></td>
                    <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis"><?= h($l['old_value'] ?? '—') ?></td>
                    <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis"><?= h($l['new_value'] ?? '—') ?></td>
                    <td><?= h($l['reason'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-2 text-center">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <?php $qs = http_build_query(array_merge($_GET, ['page' => $p])); ?>
            <a href="?<?= $qs ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
