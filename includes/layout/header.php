<?php
/**
 * AtomQuest — Layout Header
 * Role-aware navigation bar with notification bell and demo role switcher
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';

$_page_title = $page_title ?? 'AtomQuest';
$_role = current_role();
$_user_name = current_user_name();
$_unread = is_logged_in() ? get_unread_count(current_user_id()) : 0;

// Load all users for demo switcher
$_all_users = is_logged_in() ? get_all_users() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AtomQuest — In-House Goal Setting & Tracking Portal">
    <title><?= h($_page_title) ?> — AtomQuest</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (!empty($use_bootstrap)): ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
    <?php endif; ?>
</head>
<body>
<?php if (is_logged_in()): ?>
<nav class="navbar">
    <div class="nav-brand">
        <a href="<?= get_dashboard_url() ?>">⚛ AtomQuest</a>
    </div>
    <div class="nav-links">
        <?php if ($_role === 'employee' || $_role === 'manager'): ?>
            <a href="/employee/dashboard.php" <?= str_contains($_SERVER['REQUEST_URI'], '/employee/') ? 'class="active"' : '' ?>>My Goals</a>
        <?php endif; ?>
        <?php if ($_role === 'manager'): ?>
            <a href="/manager/dashboard.php" <?= str_contains($_SERVER['REQUEST_URI'], '/manager/') ? 'class="active"' : '' ?>>Team</a>
        <?php endif; ?>
        <?php if ($_role === 'admin'): ?>
            <a href="/admin/dashboard.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/dashboard') ? 'class="active"' : '' ?>>Dashboard</a>
            <a href="/admin/cycles.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/cycles') ? 'class="active"' : '' ?>>Cycles</a>
            <a href="/admin/users.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/users') ? 'class="active"' : '' ?>>Users</a>
            <a href="/admin/shared_goals.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/shared_goals') ? 'class="active"' : '' ?>>Shared Goals</a>
            <a href="/admin/reports.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/reports') ? 'class="active"' : '' ?>>Reports</a>
            <a href="/admin/audit_log.php" <?= str_contains($_SERVER['REQUEST_URI'], '/admin/audit_log') ? 'class="active"' : '' ?>>Audit</a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <!-- Notifications Bell -->
        <div class="notif-wrapper">
            <button class="notif-bell" onclick="toggleNotifications()" id="notifBell">
                🔔<?php if ($_unread > 0): ?><span class="notif-count"><?= $_unread ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown" style="display:none;">
                <div class="notif-header">Notifications</div>
                <div class="notif-list" id="notifList">Loading...</div>
            </div>
        </div>

        <!-- Demo Role Switcher -->
        <div class="role-switcher">
            <select id="roleSwitcher" onchange="switchRole(this.value)">
                <option value="">Switch User</option>
                <?php foreach ($_all_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == current_user_id() ? 'selected' : '' ?>>
                        <?= h($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <span class="nav-user"><?= h($_user_name) ?> <span class="badge badge-role"><?= ucfirst($_role) ?></span></span>
        <a href="/index.php?action=logout" class="nav-logout">Logout</a>
    </div>
</nav>
<?php endif; ?>

<main class="container">
    <?php
    // Flash messages
    $success = flash('success');
    $error = flash('error');
    if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif;
    if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif;
    ?>
