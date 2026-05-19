<?php
/**
 * NexusSync - Login Page & Auth Actions
 */

require_once __DIR__ . '/../includes/auth.php';

auth_init();

$action = $_GET['action'] ?? '';

// Handle logout
if ($action === 'logout') {
    logout();
    header('Location: /index.php?msg=logged_out');
    exit;
}

// Handle demo role switch
if ($action === 'switch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id && switch_to_user($user_id)) {
        header('Location: ' . get_dashboard_url());
        exit;
    }
    header('Location: /index.php?error=switch_failed');
    exit;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'switch') {
    validate_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = login($email, $password);
    if ($result['success']) {
        header('Location: ' . get_dashboard_url($result['role']));
        exit;
    }
    $error = $result['error'];
}

// Redirect if already logged in
if (is_logged_in() && empty($action)) {
    header('Location: ' . get_dashboard_url());
    exit;
}

$error = $error ?? ($_GET['error'] ?? '');
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexusSync</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <h1>⚛ NexusSync</h1>
        <p class="subtitle">Goal Setting & Tracking Portal</p>

        <?php if ($error === 'unauthenticated'): ?>
            <div class="alert alert-warning">Please log in to continue.</div>
        <?php elseif ($error === 'switch_failed'): ?>
            <div class="alert alert-danger">Failed to switch user.</div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($msg === 'logged_out'): ?>
            <div class="alert alert-success">You have been logged out.</div>
        <?php endif; ?>

        <form method="POST" action="/index.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="admin@atom.local" required autofocus
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">Sign In</button>
        </form>

        <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
            <p class="text-muted" style="font-size:0.8rem; text-align:center;">Demo Credentials</p>
            <table style="width:100%; font-size:0.8rem; color:var(--text-muted);">
                <tr><td>Admin</td><td>admin@atom.local</td><td>admin123</td></tr>
                <tr><td>Manager</td><td>mgr@atom.local</td><td>manager123</td></tr>
                <tr><td>Employee</td><td>emp@atom.local</td><td>employee123</td></tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
