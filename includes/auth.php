<?php
/**
 * NexusSync - Authentication & Authorization
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

/**
 * Initialize auth: start session, set security headers
 */
function auth_init(): void {
    set_security_headers();
    init_session();
}

/**
 * Require that the current user has one of the given roles.
 * Redirects to login if unauthenticated, shows 403 if wrong role.
 */
function require_role(string ...$roles): void {
    auth_init();
    if (!is_logged_in()) {
        header('Location: /index.php?error=unauthenticated');
        exit;
    }
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        include __DIR__ . '/layout/header.php';
        echo '<div class="container"><div class="alert alert-danger">Access denied. You do not have permission to view this page.</div></div>';
        include __DIR__ . '/layout/footer.php';
        exit;
    }
}

/**
 * Require any authenticated user (any role)
 */
function require_auth(): void {
    auth_init();
    if (!is_logged_in()) {
        header('Location: /index.php?error=unauthenticated');
        exit;
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user name
 */
function current_user_name(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Login: verify credentials, populate session
 */
function login(string $email, string $password): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    // Regenerate CSRF token
    unset($_SESSION['csrf_token']);

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['manager_id'] = $user['manager_id'];
    $_SESSION['employee_id'] = $user['employee_id'];

    return ['success' => true, 'role' => $user['role']];
}

/**
 * Logout: destroy session
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Switch role for demo purposes (judges can quickly switch between roles)
 */
function switch_to_user(int $user_id): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) return false;

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['manager_id'] = $user['manager_id'];
    $_SESSION['employee_id'] = $user['employee_id'];

    return true;
}

/**
 * Get the dashboard URL for the current user's role
 */
function get_dashboard_url(?string $role = null): string {
    $role = $role ?? current_role();
    return match($role) {
        'admin'   => '/admin/dashboard.php',
        'manager' => '/manager/dashboard.php',
        default   => '/employee/dashboard.php',
    };
}
