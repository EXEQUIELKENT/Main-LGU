<?php
if (session_status() === PHP_SESSION_NONE) {
    // Distinct cookie name so this session can never collide with (or be
    // wiped by) a sibling system's own PHPSESSID cookie — e.g. on local
    // XAMPP, every app shares the same "localhost" host and PHP's default
    // session.cookie_path=/, so without this, logging out of CIMM's
    // employee session (which also expires its PHPSESSID cookie) silently
    // destroyed this admin session too, since both were sharing one cookie.
    session_name('MAINLGU_ADMIN_SESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

define('SUPER_ADMIN_SESSION_TIMEOUT', 120); // 2 minutes

function is_super_admin_logged_in(): bool
{
    return !empty($_SESSION['super_admin_id']);
}

function require_super_admin(): void
{
    if (!is_super_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }

    if (isset($_SESSION['super_admin_last_activity']) && (time() - $_SESSION['super_admin_last_activity']) > SUPER_ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['super_admin_last_activity'] = time();
}
