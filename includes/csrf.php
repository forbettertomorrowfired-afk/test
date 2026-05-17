<?php
/**
 * AtomQuest — CSRF Protection
 */

/**
 * Get or generate CSRF token for the current session
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for forms
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST body or X-CSRF-Token header.
 * Exits with 403 on failure.
 */
function validate_csrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF validation failed. Please refresh the page.']);
        } else {
            echo 'CSRF validation failed. Please refresh the page and try again.';
        }
        exit;
    }
}
