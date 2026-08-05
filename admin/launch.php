<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sso_token.php';
require_super_admin();

$slug = $_GET['system'] ?? '';

$stmt = mainLguDb()->prepare('SELECT * FROM connected_systems WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$system = $stmt->fetch();

if (!$system) {
    http_response_code(404);
    exit('Unknown or inactive system.');
}

// launched_at written from PHP's own clock, not the column's DEFAULT
// CURRENT_TIMESTAMP — same fix as last_login above and system_audit_log's
// logSystemAudit(): MySQL's default runs in the DB server's own timezone,
// which team.php's/launch_history.php's strtotime() display would then
// misread on the live domain.
$log = mainLguDb()->prepare('INSERT INTO sso_launch_log (super_admin_id, system_slug, ip_address, launched_at) VALUES (?, ?, ?, ?)');
$log->execute([$_SESSION['super_admin_id'], $system['slug'], $_SERVER['REMOTE_ADDR'] ?? null, date('Y-m-d H:i:s')]);

$token = issue_sso_token(
    $system['shared_secret'],
    $system['slug'],
    $_SESSION['super_admin_email'],
    $_SESSION['super_admin_name'],
    'super_admin'
);

// This is a same-tab handoff — the browser is about to navigate fully away
// from Main LGU into the target system's admin panel, which unloads this
// page (and any JS timer on it) completely. There's no way for a heartbeat
// to keep super_admin_last_activity fresh while the admin is working over
// there, so require_super_admin()'s normal 2-minute idle check would read
// that time away as plain inactivity and force-logout the moment they came
// back — landing them on the login screen instead of the dashboard. Flag
// this session so require_super_admin() forgives exactly one return visit
// regardless of how long the admin was away (no fixed cutoff — they may be
// deep in a task over there for a while), then resumes normal 2-minute
// idle enforcement from that point on.
$_SESSION['super_admin_return_grace'] = true;

$redirectUrl = rtrim($system['base_url'], '/') . $system['sso_consume_path'] . '?sso_token=' . urlencode($token);
header('Location: ' . $redirectUrl);
exit;
