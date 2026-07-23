<?php
/**
 * Gates visibility of the "Log in" nav link on the public citizen dashboard.
 * Mirrors the pattern already used by the CIMM system
 * (LGU/lgu-portal/includes/config/auth_config.php) so staff/admins reach the
 * super admin login without it being a visible link for every visitor.
 *
 * Usage: require_once __DIR__ . '/../includes/auth_gate.php'; before any
 * output, then check $show_login in the page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Office/static IPs that always see the login link (add real office IPs here).
$ALLOWED_IPS = [
    '127.0.0.1',
    '::1',
];

// Field/remote access: visit citizendash.php?admin=SECRET_ACCESS_KEY once to
// reveal the login link for the rest of the browser session.
define('SECRET_ACCESS_KEY', 'infragov_admin_2026_baf450638e4e3097');

$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$show_login = false;

if (in_array($visitor_ip, $ALLOWED_IPS, true)) {
    $show_login = true;
    $_SESSION['authorized_access'] = true;
}

if (isset($_GET['admin']) && hash_equals(SECRET_ACCESS_KEY, (string) $_GET['admin'])) {
    $_SESSION['authorized_access'] = true;
    $show_login = true;

    // Strip the secret key from the URL/history immediately after granting access.
    $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $cleanUrl);
    exit;
}

if (!empty($_SESSION['authorized_access'])) {
    $show_login = true;
}
