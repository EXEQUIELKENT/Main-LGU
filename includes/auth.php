<?php
// PHP's ini-configured timezone doesn't necessarily match MySQL's own
// (SYSTEM) timezone — this box's PHP defaults to Europe/Berlin while MySQL's
// NOW() runs in Asia/Manila, the LGU's actual timezone (same mismatch
// already flagged and fixed this way elsewhere in this codebase suite, e.g.
// ipms_lgu/includes/config.php). Without this, comparing PHP's time()
// against a MySQL-generated DATETIME (e.g. super_admins.locked_until) is
// off by the timezone gap — confirmed for real as a login-lockout countdown
// showing "375 minutes" instead of 15.
date_default_timezone_set('Asia/Manila');

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

// Same localhost detection used by admin/login.php to skip the OTP step
// during local development — the idle session timeout is disabled there
// too, so devs aren't kicked back to the login screen every 2 minutes
// while working locally.
define('SUPER_ADMIN_IS_LOCALHOST', in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true));

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

    if (!SUPER_ADMIN_IS_LOCALHOST && isset($_SESSION['super_admin_last_activity']) && (time() - $_SESSION['super_admin_last_activity']) > SUPER_ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['super_admin_last_activity'] = time();
}