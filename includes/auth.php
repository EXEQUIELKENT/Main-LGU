<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

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
}
