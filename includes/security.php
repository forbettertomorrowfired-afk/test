<?php
/**
 * AtomQuest — Security Headers & Cookie Configuration
 */

function set_security_headers(): void {
    // Session cookie hardening
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200'); // 2 hours

    // Only set secure flag if HTTPS (Railway serves HTTPS, localhost doesn't)
    if (!empty($_SERVER['HTTPS']) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', '1');
    }

    // Security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-XSS-Protection: 1; mode=block");

    // CSP — allow self + inline styles for Bootstrap compatibility
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");
}
