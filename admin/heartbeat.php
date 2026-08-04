<?php
/**
 * Idle-timeout heartbeat — pinged by the visibility-aware timer in each
 * admin page's own <script> block (see admin/dashboard.php etc.) the moment
 * this tab regains focus, plus periodically while it stays visible.
 *
 * Deliberately does NOT go through require_super_admin()'s idle-timeout
 * check — that check is exactly what this endpoint exists to keep from
 * firing after time spent away on another tab (e.g. CIMM's admin panel).
 * It only needs to confirm the underlying login is still valid and then
 * unconditionally refresh super_admin_last_activity to "now".
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_super_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$_SESSION['super_admin_last_activity'] = time();
echo json_encode(['success' => true]);
