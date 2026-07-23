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

$log = mainLguDb()->prepare('INSERT INTO sso_launch_log (super_admin_id, system_slug, ip_address) VALUES (?, ?, ?)');
$log->execute([$_SESSION['super_admin_id'], $system['slug'], $_SERVER['REMOTE_ADDR'] ?? null]);

$token = issue_sso_token(
    $system['shared_secret'],
    $system['slug'],
    $_SESSION['super_admin_email'],
    $_SESSION['super_admin_name'],
    'super_admin'
);

$redirectUrl = rtrim($system['base_url'], '/') . $system['sso_consume_path'] . '?sso_token=' . urlencode($token);
header('Location: ' . $redirectUrl);
exit;
