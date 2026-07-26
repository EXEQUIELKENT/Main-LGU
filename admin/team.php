<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/security_alerts.php';
require_super_admin();

function setNotification(string $type, string $message): void
{
    $_SESSION['notification'] = ['type' => $type, 'message' => $message];
}

function renderNotification(): void
{
    if (empty($_SESSION['notification'])) {
        return;
    }
    $type = $_SESSION['notification']['type'];
    $message = htmlspecialchars($_SESSION['notification']['message']);
    $icon = ['success' => '✔️', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'][$type] ?? 'ℹ️';
    echo "<div class='notif-popup notif-{$type}' id='notifPopup'>
            <span class='notif-icon'>{$icon}</span>
            <span class='notif-message'>{$message}</span>
            <button type='button' class='notif-close' onclick=\"closeNotif()\">&times;</button>
          </div>";
    unset($_SESSION['notification']);
}

function adminStatusInfo(array $admin): array
{
    if ($admin['invite_token'] !== null) {
        return strtotime($admin['invite_token_expires']) > time()
            ? ['label' => 'Invite pending', 'class' => 'pending']
            : ['label' => 'Invite expired', 'class' => 'expired'];
    }
    return $admin['is_active']
        ? ['label' => 'Active', 'class' => 'active']
        : ['label' => 'Deactivated', 'class' => 'deactivated'];
}

function sendInvite(int $adminId, string $email, string $fullName, string $inviterName): void
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 7 * 86400);
    mainLguDb()->prepare('UPDATE super_admins SET invite_token = ?, invite_token_expires = ? WHERE id = ?')
        ->execute([$token, $expires, $adminId]);

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = str_replace('team.php', '', $_SERVER['SCRIPT_NAME'] ?? '/admin/team.php');
    $inviteUrl = "{$scheme}://{$host}{$basePath}login.php?invite_token={$token}";

    try {
        $mail = mainLguMailer();
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'InfraGovServices — You\'ve been invited as a Super Admin';
        $mail->Body = mainLguInviteEmailHtml($inviteUrl, $inviterName);
        $mail->AltBody = "{$inviterName} invited you to InfraGovServices Super Admin.\n\nSet up your account: {$inviteUrl}\n\nValid for 7 days.";
        $mail->send();
    } catch (\Throwable $e) {
        error_log('Main LGU invite mail failed: ' . $e->getMessage());
        setNotification('error', 'Invite created, but the email could not be sent. Use "Resend invite" to try again.');
    }
}

$currentAdminId = (int) $_SESSION['super_admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setNotification('error', 'Enter a valid name and email address.');
        } else {
            $existsStmt = mainLguDb()->prepare('SELECT id FROM super_admins WHERE email = ?');
            $existsStmt->execute([$email]);
            if ($existsStmt->fetch()) {
                setNotification('error', 'That email is already registered.');
            } else {
                $username = 'invited_' . substr(md5($email . microtime()), 0, 10);
                $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                mainLguDb()->prepare('INSERT INTO super_admins (username, email, password_hash, full_name, is_active, invited_by) VALUES (?,?,?,?,0,?)')
                    ->execute([$username, $email, $placeholderHash, $fullName, $currentAdminId]);
                $newId = (int) mainLguDb()->lastInsertId();

                sendInvite($newId, $email, $fullName, $_SESSION['super_admin_name']);
                sendSecurityAlert('New Super Admin invited', "{$fullName} ({$email}) was invited to join by " . $_SESSION['super_admin_name'] . '.');
                setNotification('success', "Invite sent to {$email}.");
            }
        }
    }

    if ($action === 'resend_invite') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE id = ? AND invite_token IS NOT NULL');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target) {
            sendInvite($id, $target['email'], $target['full_name'], $_SESSION['super_admin_name']);
            setNotification('success', "Invite resent to {$target['email']}.");
        }
    }

    if ($action === 'revoke_invite') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mainLguDb()->prepare('DELETE FROM super_admins WHERE id = ? AND invite_token IS NOT NULL');
        $stmt->execute([$id]);
        setNotification('success', 'Invite revoked.');
    }

    if ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $currentAdminId) {
            setNotification('error', "You can't deactivate your own account.");
        } else {
            $activeCountStmt = mainLguDb()->query('SELECT COUNT(*) FROM super_admins WHERE is_active = 1 AND invite_token IS NULL');
            $activeCount = (int) $activeCountStmt->fetchColumn();
            $stmt = mainLguDb()->prepare('SELECT full_name FROM super_admins WHERE id = ? AND is_active = 1 AND invite_token IS NULL');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                setNotification('error', 'That account is not active.');
            } elseif ($activeCount <= 1) {
                setNotification('error', "Can't deactivate the only active Super Admin.");
            } else {
                mainLguDb()->prepare('UPDATE super_admins SET is_active = 0 WHERE id = ?')->execute([$id]);
                sendSecurityAlert('Super Admin account deactivated', "{$target['full_name']}'s account was deactivated by " . $_SESSION['super_admin_name'] . '.');
                setNotification('success', "{$target['full_name']} deactivated.");
            }
        }
    }

    if ($action === 'reactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        mainLguDb()->prepare('UPDATE super_admins SET is_active = 1 WHERE id = ? AND invite_token IS NULL')->execute([$id]);
        setNotification('success', 'Account reactivated.');
    }

    header('Location: team.php');
    exit;
}

$admins = mainLguDb()->query('SELECT * FROM super_admins ORDER BY (invite_token IS NOT NULL) DESC, is_active DESC, full_name')->fetchAll();
$adminNames = array_column($admins, 'full_name', 'id');

$activityMeta = [
    'create' => ['verb' => 'added', 'icon' => 'fa-plus', 'color' => 'success'],
    'update' => ['verb' => 'updated', 'icon' => 'fa-pen', 'color' => 'info'],
    'toggle_active' => ['verb' => 'changed the status of', 'icon' => 'fa-toggle-on', 'color' => 'info'],
    'rotate_secret' => ['verb' => 'rotated the secret for', 'icon' => 'fa-key', 'color' => 'warning'],
    'delete' => ['verb' => 'deleted', 'icon' => 'fa-trash', 'color' => 'danger'],
    'launch' => ['verb' => 'launched into', 'icon' => 'fa-arrow-right-to-bracket', 'color' => 'info'],
];
$activity = mainLguDb()->query("
    (SELECT 'system' AS kind, super_admin_id, action, system_name, created_at AS ts FROM system_audit_log)
    UNION ALL
    (SELECT 'launch' AS kind, l.super_admin_id, 'launch' AS action, COALESCE(s.name, l.system_slug) AS system_name, l.launched_at AS ts
        FROM sso_launch_log l LEFT JOIN connected_systems s ON s.slug = l.system_slug)
    ORDER BY ts DESC LIMIT 15
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team — InfraGovServices</title>
<link rel="icon" href="../public/logocityhall.png" type="image/png">
<script>
(function () {
    try {
        var t = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light';
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) {}
    try {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.documentElement.setAttribute('data-sidebar-collapsed', 'true');
        }
    } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root {
        --bg-scrim: linear-gradient(160deg, rgba(238,241,251,.68) 0%, rgba(245,247,253,.60) 55%, rgba(255,255,255,.55) 100%);
        --card-bg: rgba(255,255,255,.84);
        --card-border: rgba(80,100,180,.16);
        --text-primary: #101a3a;
        --text-secondary: #5b6690;
        --header-bg: rgba(255,255,255,.84);
        --input-bg: rgba(255,255,255,.7);
        --input-border: rgba(80,100,180,.22);
        --scrollbar-track: #eef1fb;
        --scrollbar-thumb: #1a56db;
    }
    [data-theme="dark"] {
        --bg-scrim: linear-gradient(160deg, rgba(5,10,25,.78) 0%, rgba(10,22,40,.74) 55%, rgba(13,31,60,.70) 100%);
        --card-bg: rgba(15,22,48,.80);
        --card-border: rgba(120,140,220,.16);
        --text-primary: #fff;
        --text-secondary: #8b95c0;
        --header-bg: rgba(8,13,32,.84);
        --input-bg: rgba(6,12,30,.55);
        --input-border: rgba(120,140,220,.22);
        --scrollbar-track: #0a1628;
        --scrollbar-thumb: #1a56db;
    }
    * { box-sizing: border-box; }
    html { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track); }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 4px; }
    body {
        margin: 0; min-height: 100vh; font-family: 'Poppins', system-ui, sans-serif;
        color: var(--text-primary); transition: color .3s;
        background-image: var(--bg-scrim), url('../public/assets/img/memcir.jpg');
        background-size: cover, cover;
        background-position: center, center;
        background-attachment: fixed, fixed;
    }
    /* ── Sidebar (desktop: fixed left column, collapsible; mobile: slide-in drawer) ── */
    :root { --sidebar-w: 240px; --sidebar-w-collapsed: 72px; }
    .sidebar {
        position: fixed; top: 0; left: 0; width: var(--sidebar-w); height: 100vh; z-index: 500;
        background: var(--card-bg); border-right: 1px solid var(--card-border);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        display: flex; flex-direction: column; transition: width .3s ease, left .3s ease, background .3s;
    }
    .sidebar.collapsed, html[data-sidebar-collapsed="true"] .sidebar { width: var(--sidebar-w-collapsed); }
    .sidebar-header { display: flex; align-items: center; justify-content: flex-end; padding: 14px; }
    .sidebar.collapsed .sidebar-header, html[data-sidebar-collapsed="true"] .sidebar-header { justify-content: center; }
    .sidebar-toggle {
        width: 30px; height: 30px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: .78rem;
        box-shadow: 0 3px 10px rgba(63,90,223,.35); transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    }
    .sidebar.collapsed .sidebar-toggle, html[data-sidebar-collapsed="true"] .sidebar-toggle { transform: rotate(180deg); }
    .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 0 18px 18px; }
    .sidebar-logo img { width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0; }
    .sidebar-logo strong { display: block; color: var(--text-primary); font-size: .9rem; white-space: nowrap; }
    .sidebar-logo span { display: block; color: var(--text-secondary); font-size: .68rem; white-space: nowrap; }
    .sidebar.collapsed .sidebar-logo-text, html[data-sidebar-collapsed="true"] .sidebar-logo-text { display: none; }
    .sidebar-nav-list { list-style: none; margin: 0; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; overflow-y: auto; overflow-x: hidden; }
    .sidebar-link {
        display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px;
        color: var(--text-secondary); text-decoration: none; font-size: .85rem; font-weight: 500;
        white-space: nowrap; transition: background .2s, color .2s, transform .2s;
    }
    .sidebar-link i { width: 18px; text-align: center; flex-shrink: 0; }
    .sidebar-link:hover { background: rgba(79,110,247,.1); color: var(--text-primary); }
    .sidebar-link.active { background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; box-shadow: 0 3px 10px rgba(63,90,223,.3); }
    .sidebar.collapsed .sidebar-link, html[data-sidebar-collapsed="true"] .sidebar-link { justify-content: center; padding: 11px; }
    .sidebar.collapsed .sidebar-link span, html[data-sidebar-collapsed="true"] .sidebar-link span { display: none; }
    .sidebar-bottom { border-top: 1px solid var(--card-border); padding: 14px; display: flex; flex-direction: column; gap: 10px; }
    .sidebar-user { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .sidebar-user .user-avatar {
        width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#4f6ef7,#3f5adf);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 700; flex-shrink: 0;
    }
    .sidebar-user span.uname { color: var(--text-primary); font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar.collapsed .sidebar-user span.uname, html[data-sidebar-collapsed="true"] .sidebar-user span.uname { display: none; }
    .sidebar-logout {
        display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
        padding: 10px 12px; border-radius: 9px; border: none; background: #dc2626; color: #fff;
        font-family: inherit; font-size: .82rem; font-weight: 600; letter-spacing: .02em;
        white-space: nowrap; cursor: pointer; position: relative; overflow: hidden;
        box-shadow: 0 2px 8px rgba(220,38,38,.25);
        transition: background .22s ease, transform .18s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease;
    }
    .sidebar-logout i { font-size: .8rem; flex-shrink: 0; transition: transform .22s cubic-bezier(.34,1.56,.64,1); }
    .sidebar-logout::after {
        content: ''; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        background: linear-gradient(105deg, transparent 35%, rgba(255,255,255,.18) 50%, transparent 65%);
        transform: translateX(-100%); transition: transform .45s ease;
    }
    .sidebar-logout:hover { background: #b91c1c; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(220,38,38,.4), 0 2px 6px rgba(220,38,38,.25); }
    .sidebar-logout:hover::after { transform: translateX(100%); }
    .sidebar-logout:hover i { transform: translateX(3px); }
    .sidebar-logout:active { transform: translateY(0) scale(.97); box-shadow: 0 2px 8px rgba(220,38,38,.25); }
    .sidebar.collapsed .sidebar-logout, html[data-sidebar-collapsed="true"] .sidebar-logout { width: 44px; gap: 0; padding: 10px 0; margin: 0 auto; }
    .sidebar.collapsed .sidebar-logout span, html[data-sidebar-collapsed="true"] .sidebar-logout span { display: none; }
    .sidebar.collapsed .sidebar-logout:hover i, html[data-sidebar-collapsed="true"] .sidebar-logout:hover i { transform: scale(1.18) translateX(0); }
    .sidebar-overlay {
        display: none; position: fixed; inset: 0; background: rgba(3,6,16,.5); z-index: 490;
        opacity: 0; transition: opacity .3s;
    }
    .sidebar-overlay.show { display: block; opacity: 1; }
    .mobile-toggle {
        display: none; position: fixed; top: 11px; left: 12px; z-index: 600;
        width: 36px; height: 36px; border-radius: 9px; background: rgba(5,10,25,.9); color: #fff;
        border: 1px solid rgba(255,255,255,.14); align-items: center; justify-content: center; cursor: pointer; font-size: .95rem;
    }

    .topbar {
        position: sticky; top: 0; z-index: 200; margin-left: var(--sidebar-w);
        background: var(--header-bg); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid var(--card-border);
        padding: 12px 32px; display: flex; align-items: center; justify-content: flex-end; gap: 12px;
        transition: margin-left .3s ease, background .3s;
    }
    .sidebar.collapsed ~ .topbar, html[data-sidebar-collapsed="true"] .topbar { margin-left: var(--sidebar-w-collapsed); }

    .header-clock {
        font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--text-secondary);
        background: rgba(120,140,220,.1); border: 1px solid var(--card-border); padding: 7px 12px; border-radius: 50px;
        display: flex; align-items: center; gap: 6px; white-space: nowrap;
    }
    .header-clock i { font-size: .72rem; opacity: .8; }
    .clock-date::after { content: ' · '; }

    .theme-toggle { background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; }
    .theme-track {
        width: 46px; height: 25px; background: rgba(120,140,220,.16); border: 1px solid var(--card-border);
        border-radius: 50px; position: relative; transition: background .3s, border-color .3s; display: block;
    }
    [data-theme="dark"] .theme-track { background: rgba(59,130,246,.25); border-color: rgba(59,130,246,.5); }
    .theme-thumb {
        position: absolute; top: 2px; left: 2px; width: 19px; height: 19px; background: #fff; border-radius: 50%;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1); display: flex; align-items: center; justify-content: center;
        font-size: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.3);
    }
    [data-theme="dark"] .theme-thumb { transform: translateX(21px); }
    .theme-thumb .fa-sun { color: #101a3a; }
    .theme-thumb .fa-moon { display: none; color: #fff; }
    [data-theme="dark"] .theme-thumb .fa-sun { display: none; }
    [data-theme="dark"] .theme-thumb .fa-moon { display: inline; }

    .main-content { margin-left: var(--sidebar-w); transition: margin-left .3s ease; }
    .sidebar.collapsed ~ .main-content, html[data-sidebar-collapsed="true"] .main-content { margin-left: var(--sidebar-w-collapsed); }

    @media (max-width: 900px) {
        .sidebar { left: -100%; width: 260px; box-shadow: none; }
        .sidebar.mobile-active { left: 0; box-shadow: 0 0 50px rgba(0,0,0,.45); }
        .sidebar.collapsed { width: 260px; }
        .sidebar.collapsed .sidebar-logo-text,
        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .sidebar-user span.uname,
        .sidebar.collapsed .sidebar-logout span { display: block; }
        .sidebar-header { display: none; }
        .sidebar-logo { padding-top: 64px; }
        .mobile-toggle { display: flex; }
        .topbar, .main-content, .sidebar.collapsed ~ .topbar, .sidebar.collapsed ~ .main-content { margin-left: 0 !important; }
        .topbar {
            height: 54px; padding: 0 14px 0 58px; gap: 8px;
            background: rgba(5,10,25,.94); border-bottom: 1px solid rgba(59,130,246,.2);
            box-shadow: 0 2px 16px rgba(0,0,0,.5);
        }
        .header-clock {
            background: none; border: none; padding: 0; backdrop-filter: none;
            font-size: .72rem; font-weight: 700; color: rgba(255,255,255,.65); margin-right: auto;
        }
        .header-clock i { display: none; }
        .clock-date { display: none; }
        .theme-toggle {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
            width: 32px; height: 32px; border-radius: 8px; padding: 0; justify-content: center;
        }
        .theme-toggle:hover { background: rgba(59,130,246,.2); }
        .theme-track { position: static; width: auto; height: auto; background: none !important; border: none !important; display: flex; }
        .theme-thumb { position: static; width: auto; height: auto; background: none !important; box-shadow: none; transform: none !important; }
        .theme-thumb i { font-size: 14px; }
        .theme-thumb .fa-sun { color: #fbbf24; }
        .theme-thumb .fa-moon { color: #93c5fd; }
    }

    main { max-width: 1100px; margin: 0 auto; padding: 34px 40px 60px; position: relative; z-index: 1; }

    .page-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    .page-head h1 { font-size: 1.15rem; color: var(--text-primary); margin: 0; }
    .btn-add {
        display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; border: none;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; font-weight: 600; font-size: .85rem;
        cursor: pointer; font-family: inherit; box-shadow: 0 8px 20px rgba(63,90,223,.3);
    }

    .team-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; }
    .team-row {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;
        padding: 16px 18px; backdrop-filter: blur(14px); display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    }
    .team-row .user-avatar {
        width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg,#4f6ef7,#3f5adf);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; flex-shrink: 0;
    }
    .team-row-info { flex: 1; min-width: 180px; }
    .team-row-name { font-size: .9rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
    .team-row-name .you-tag { font-size: .65rem; font-weight: 700; color: #4f6ef7; background: rgba(79,110,247,.14); padding: 2px 8px; border-radius: 999px; }
    .team-row-email { font-size: .78rem; color: var(--text-secondary); }
    .team-row-meta { font-size: .72rem; color: var(--text-secondary); margin-top: 3px; }
    .status-badge { font-size: .65rem; padding: 3px 10px; border-radius: 999px; font-weight: 700; letter-spacing: .02em; white-space: nowrap; }
    .status-badge.active { background: rgba(79,201,122,.18); color: #1b8a4c; border: 1px solid rgba(79,201,122,.4); }
    [data-theme="dark"] .status-badge.active { color: #d1fae0; }
    .status-badge.deactivated { background: rgba(215,63,82,.18); color: #b3283f; border: 1px solid rgba(215,63,82,.4); }
    [data-theme="dark"] .status-badge.deactivated { color: #ffd9de; }
    .status-badge.pending { background: rgba(79,110,247,.16); color: #3f5adf; border: 1px solid rgba(79,110,247,.35); }
    [data-theme="dark"] .status-badge.pending { color: #c7d3ff; }
    .status-badge.expired { background: rgba(217,119,6,.16); color: #a05a00; border: 1px solid rgba(217,119,6,.35); }
    [data-theme="dark"] .status-badge.expired { color: #ffd9a0; }
    .team-row-actions { display: flex; gap: 8px; }
    .row-btn {
        padding: 7px 13px; border-radius: 8px; border: 1px solid var(--card-border); background: rgba(120,140,220,.08);
        color: var(--text-secondary); cursor: pointer; font-size: .78rem; font-weight: 600; font-family: inherit;
        transition: background .15s, color .15s; white-space: nowrap;
    }
    .row-btn:hover { background: rgba(79,110,247,.15); color: var(--text-primary); }
    .row-btn.danger:hover { background: rgba(239,68,68,.15); color: #ef4444; }

    .activity-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        padding: 22px 24px; backdrop-filter: blur(14px);
    }
    .activity-card h2 { font-size: .92rem; color: var(--text-primary); margin: 0 0 16px; }
    .activity-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-top: 1px solid var(--card-border); }
    .activity-item:first-child { border-top: none; padding-top: 0; }
    .activity-icon {
        width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
        font-size: .72rem; color: #fff; flex-shrink: 0; margin-top: 1px;
    }
    .activity-icon.success { background: linear-gradient(135deg,#4fc97a,#1b8a4c); }
    .activity-icon.danger { background: linear-gradient(135deg,#ef4444,#b3283f); }
    .activity-icon.info { background: linear-gradient(135deg,#4f6ef7,#3f5adf); }
    .activity-icon.warning { background: linear-gradient(135deg,#f0a93a,#a05a00); }
    .activity-text { font-size: .84rem; color: var(--text-primary); line-height: 1.5; }
    .activity-text strong { font-weight: 600; }
    .activity-time { font-size: .72rem; color: var(--text-secondary); margin-top: 2px; }
    .empty-note { text-align: center; color: var(--text-secondary); padding: 24px; font-size: .85rem; }

    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(3,6,16,.55); backdrop-filter: blur(6px);
        display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; overflow-y: auto;
    }
    .modal-backdrop.show { display: flex; }
    .modal-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px;
        padding: 30px 26px 24px; max-width: 380px; width: 100%; box-shadow: 0 25px 60px rgba(0,0,0,.4);
        backdrop-filter: blur(22px); text-align: center; animation: modalPop .25s cubic-bezier(.34,1.56,.64,1);
    }
    .modal-card.modal-card--form { max-width: 420px; text-align: left; }
    @keyframes modalPop { from { transform: translateY(20px) scale(.94); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
    .modal-icon-wrap {
        width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 16px;
        background: linear-gradient(135deg, rgba(239,68,68,.16), rgba(239,68,68,.08));
        border: 1.5px solid rgba(239,68,68,.28); display: flex; align-items: center; justify-content: center;
        color: #ef4444; font-size: 1.3rem;
    }
    .modal-icon-wrap--info {
        background: linear-gradient(135deg, rgba(79,110,247,.18), rgba(79,110,247,.08));
        border-color: rgba(79,110,247,.3); color: #4f6ef7;
    }
    .modal-card h2 { color: var(--text-primary); font-size: 1.05rem; margin: 0 0 8px; }
    .modal-card p.modal-sub { color: var(--text-secondary); font-size: .85rem; margin: 0 0 22px; line-height: 1.5; }
    .modal-actions { display: flex; gap: 10px; }
    .modal-actions button {
        flex: 1; padding: 11px 0; border-radius: 10px; border: none; font-weight: 600; font-size: .85rem;
        cursor: pointer; font-family: inherit; transition: all .18s;
    }
    .modal-actions .btn-cancel { background: rgba(120,140,220,.14); color: var(--text-primary); border: 1px solid var(--card-border); }
    .modal-actions .btn-cancel:hover { background: rgba(120,140,220,.22); }
    .modal-actions .btn-confirm { background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; box-shadow: 0 4px 14px rgba(239,68,68,.35); }
    .modal-actions .btn-confirm--info { background: linear-gradient(135deg,#4f6ef7,#3f5adf); box-shadow: 0 4px 14px rgba(63,90,223,.35); }
    .modal-actions .btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(239,68,68,.45); }
    .modal-actions .btn-confirm--info:hover { box-shadow: 0 6px 18px rgba(63,90,223,.45); }

    .form-field { margin-bottom: 16px; }
    .form-field label { display: block; color: var(--text-secondary); font-size: .78rem; font-weight: 500; margin-bottom: 6px; }
    .form-field input {
        width: 100%; padding: 10px 12px; border-radius: 9px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); font-size: .88rem; font-family: inherit;
    }
    .form-field input:focus { outline: none; border-color: #4f6ef7; }

    .notif-popup {
        position: fixed; top: 26px; left: 50%; transform: translateX(-50%);
        min-width: 280px; max-width: 92vw; padding: 15px 26px;
        background: #12193a; color: #eef1ff; border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0,0,0,.4);
        z-index: 10000; display: flex; align-items: center; gap: 12px;
        font-family: 'Poppins', sans-serif; font-size: .92rem; font-weight: 500;
        opacity: 1; transition: opacity .35s; border: 1px solid rgba(120,140,220,.2);
    }
    .notif-popup.notif-success { border-left: 4px solid #4fc97a; }
    .notif-popup.notif-error { border-left: 4px solid #d73f52; }
    .notif-popup.notif-warning { border-left: 4px solid #dda203; }
    .notif-popup.notif-info { border-left: 4px solid #527cdf; }
    .notif-close { background: none; border: none; font-size: 18px; margin-left: auto; color: #7c88b8; cursor: pointer; }

    @media (max-width: 768px) {
        main { padding: 18px 14px 40px; }
        .team-row { flex-direction: column; align-items: stretch; }
        .team-row-actions { justify-content: flex-end; }
    }
</style>
</head>
<body>
<?php renderNotification(); ?>
<button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar"><i class="fas fa-chevron-left"></i></button>
    </div>
    <div class="sidebar-logo">
        <img src="../public/logocityhall.png" alt="InfraGovServices">
        <div class="sidebar-logo-text">
            <strong>InfraGovServices</strong>
            <span>Super Admin · SSO Hub</span>
        </div>
    </div>
    <ul class="sidebar-nav-list">
        <li><a href="dashboard.php" class="sidebar-link"><i class="fas fa-gauge"></i><span>Dashboard</span></a></li>
        <li><a href="systems.php" class="sidebar-link"><i class="fas fa-server"></i><span>Connected Systems</span></a></li>
        <li><a href="launch_history.php" class="sidebar-link"><i class="fas fa-clock-rotate-left"></i><span>Launch History</span></a></li>
        <li><a href="analytics.php" class="sidebar-link"><i class="fas fa-chart-line"></i><span>Analytics</span></a></li>
        <li><a href="audit_log.php" class="sidebar-link"><i class="fas fa-list-check"></i><span>Audit Log</span></a></li>
        <li><a href="team.php" class="sidebar-link active"><i class="fas fa-users"></i><span>Team</span></a></li>
        <li><a href="security.php" class="sidebar-link"><i class="fas fa-shield-halved"></i><span>Security</span></a></li>
    </ul>
    <div class="sidebar-bottom">
        <div class="sidebar-user">
            <span class="user-avatar"><?= strtoupper(substr($_SESSION['super_admin_name'], 0, 1)) ?></span>
            <span class="uname"><?= htmlspecialchars($_SESSION['super_admin_name']) ?></span>
        </div>
        <button type="button" class="sidebar-logout" id="openLogoutModal"><i class="fas fa-arrow-right-from-bracket"></i> <span>Log out</span></button>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="topbar">
    <span class="header-clock"><i class="fas fa-clock"></i><span class="clock-date" id="clockDate"></span><span class="clock-time" id="clockTime"></span></span>
    <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
        <span class="theme-track">
            <span class="theme-thumb"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></span>
        </span>
    </button>
</div>

<main class="main-content">
    <div class="page-head">
        <h1>Team</h1>
        <button type="button" class="btn-add" id="openInviteModal"><i class="fas fa-user-plus"></i> Invite admin</button>
    </div>

    <div class="team-list">
        <?php foreach ($admins as $admin): $status = adminStatusInfo($admin); $isSelf = (int) $admin['id'] === $currentAdminId; ?>
            <div class="team-row">
                <span class="user-avatar"><?= strtoupper(substr($admin['full_name'], 0, 1)) ?></span>
                <div class="team-row-info">
                    <div class="team-row-name">
                        <?= htmlspecialchars($admin['full_name']) ?>
                        <?php if ($isSelf): ?><span class="you-tag">You</span><?php endif; ?>
                    </div>
                    <div class="team-row-email"><?= htmlspecialchars($admin['email']) ?></div>
                    <div class="team-row-meta">
                        <?= $admin['last_login'] ? 'Last login ' . date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never signed in' ?>
                        <?= $admin['totp_enabled'] ? ' · 2FA on' : '' ?>
                    </div>
                </div>
                <span class="status-badge <?= $status['class'] ?>"><?= htmlspecialchars($status['label']) ?></span>
                <div class="team-row-actions">
                    <?php if ($status['class'] === 'pending' || $status['class'] === 'expired'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="resend_invite">
                            <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                            <button type="submit" class="row-btn">Resend invite</button>
                        </form>
                        <button type="button" class="row-btn danger revoke-btn" data-id="<?= (int) $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['full_name']) ?>">Revoke</button>
                    <?php elseif ($status['class'] === 'active' && !$isSelf): ?>
                        <button type="button" class="row-btn danger deactivate-btn" data-id="<?= (int) $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['full_name']) ?>">Deactivate</button>
                    <?php elseif ($status['class'] === 'deactivated'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="reactivate">
                            <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                            <button type="submit" class="row-btn">Reactivate</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="activity-card">
        <h2>Recent activity</h2>
        <?php if ($activity === []): ?>
            <div class="empty-note">No activity recorded yet.</div>
        <?php else: ?>
            <?php foreach ($activity as $item): $meta = $activityMeta[$item['action']] ?? ['verb' => $item['action'], 'icon' => 'fa-circle-info', 'color' => 'info']; ?>
                <div class="activity-item">
                    <span class="activity-icon <?= $meta['color'] ?>"><i class="fas <?= $meta['icon'] ?>"></i></span>
                    <div>
                        <div class="activity-text">
                            <strong><?= htmlspecialchars($adminNames[$item['super_admin_id']] ?? 'Unknown') ?></strong>
                            <?= htmlspecialchars($meta['verb']) ?>
                            <strong><?= htmlspecialchars($item['system_name']) ?></strong>
                        </div>
                        <div class="activity-time"><?= date('M j, Y g:i A', strtotime($item['ts'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Invite admin modal -->
<div class="modal-backdrop" id="inviteAdminModal">
    <div class="modal-card modal-card--form">
        <h2 style="text-align:center;">Invite a Super Admin</h2>
        <p class="modal-sub" style="text-align:center;">They'll get an email with a link to set their own password.</p>
        <form method="post" id="inviteAdminForm">
            <input type="hidden" name="action" value="invite">
            <div class="form-field">
                <label for="inviteFullName">Full name</label>
                <input type="text" name="full_name" id="inviteFullName" required>
            </div>
            <div class="form-field">
                <label for="inviteEmail">Email</label>
                <input type="email" name="email" id="inviteEmail" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelInviteAdmin">Cancel</button>
                <button type="submit" class="btn-confirm btn-confirm--info">Send invite</button>
            </div>
        </form>
    </div>
</div>

<!-- Revoke invite confirmation -->
<div class="modal-backdrop" id="revokeModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><i class="fas fa-user-xmark"></i></div>
        <h2>Revoke invite for <span id="revokeName"></span>?</h2>
        <p class="modal-sub">They won't be able to use that invite link anymore.</p>
        <form method="post" id="revokeForm">
            <input type="hidden" name="action" value="revoke_invite">
            <input type="hidden" name="id" id="revokeId">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelRevoke">Cancel</button>
                <button type="submit" class="btn-confirm">Revoke</button>
            </div>
        </form>
    </div>
</div>

<!-- Deactivate confirmation -->
<div class="modal-backdrop" id="deactivateModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><i class="fas fa-user-slash"></i></div>
        <h2>Deactivate <span id="deactivateName"></span>?</h2>
        <p class="modal-sub">They'll immediately lose access to the Super Admin dashboard until reactivated.</p>
        <form method="post" id="deactivateForm">
            <input type="hidden" name="action" value="deactivate">
            <input type="hidden" name="id" id="deactivateId">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelDeactivate">Cancel</button>
                <button type="submit" class="btn-confirm">Deactivate</button>
            </div>
        </form>
    </div>
</div>

<!-- Logout confirmation modal -->
<div class="modal-backdrop" id="logoutModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><i class="fas fa-arrow-right-from-bracket"></i></div>
        <h2>Log out of your account?</h2>
        <p class="modal-sub">Are you sure you want to log out? You'll need to sign in again to access any connected system.</p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" id="cancelLogout">Cancel</button>
            <button type="button" class="btn-confirm" id="confirmLogout">Log out</button>
        </div>
    </div>
</div>

<script>
function closeNotif() {
    var n = document.getElementById('notifPopup');
    if (n) { n.style.opacity = '0'; setTimeout(() => n.remove(), 350); }
}
setTimeout(closeNotif, 4500);

// Sidebar: desktop collapse (persisted) + mobile slide-in drawer
(function () {
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var mobileToggle = document.getElementById('mobileToggle');
    var overlay = document.getElementById('sidebarOverlay');

    try {
        if (localStorage.getItem('sidebarCollapsed') === 'true') sidebar.classList.add('collapsed');
    } catch (e) {}

    sidebarToggle.addEventListener('click', function () {
        var isCollapsed = sidebar.classList.toggle('collapsed');
        try { localStorage.setItem('sidebarCollapsed', isCollapsed); } catch (e) {}
        if (isCollapsed) {
            document.documentElement.setAttribute('data-sidebar-collapsed', 'true');
        } else {
            document.documentElement.removeAttribute('data-sidebar-collapsed');
        }
    });

    function openMobile() { sidebar.classList.add('mobile-active'); overlay.classList.add('show'); }
    function closeMobile() { sidebar.classList.remove('mobile-active'); overlay.classList.remove('show'); }

    mobileToggle.addEventListener('click', function () {
        sidebar.classList.contains('mobile-active') ? closeMobile() : openMobile();
    });
    overlay.addEventListener('click', closeMobile);
})();

(function () {
    var html = document.documentElement;
    var btn = document.getElementById('themeToggle');
    function apply(isDark) {
        if (isDark) { html.setAttribute('data-theme', 'dark'); } else { html.removeAttribute('data-theme'); }
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.setItem('theme_backup', isDark ? 'dark' : 'light');
        } catch (e) {}
    }
    var saved = 'light';
    try { saved = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light'; } catch (e) {}
    apply(saved === 'dark');
    btn.addEventListener('click', function () { apply(!html.hasAttribute('data-theme')); });
})();

(function () {
    var dateEl = document.getElementById('clockDate');
    var timeEl = document.getElementById('clockTime');
    function tick() {
        var now = new Date();
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
    }
    tick();
    setInterval(tick, 1000);
})();

(function () {
    var modal = document.getElementById('logoutModal');
    document.getElementById('openLogoutModal').addEventListener('click', function () { modal.classList.add('show'); });
    document.getElementById('cancelLogout').addEventListener('click', function () { modal.classList.remove('show'); });
    document.getElementById('confirmLogout').addEventListener('click', function () { window.location.href = 'logout.php'; });
})();

<?php if (!SUPER_ADMIN_IS_LOCALHOST): ?>
(function () {
    var TIMEOUT_MS = 120 * 1000;
    var timer = null;
    function resetTimer() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { window.location.href = 'login.php?timeout=1'; }, TIMEOUT_MS);
    }
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, resetTimer, { passive: true });
    });
    resetTimer();
})();
<?php endif; ?>

// Invite admin modal
(function () {
    var modal = document.getElementById('inviteAdminModal');
    document.getElementById('openInviteModal').addEventListener('click', function () { modal.classList.add('show'); });
    document.getElementById('cancelInviteAdmin').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Revoke invite modal
(function () {
    var modal = document.getElementById('revokeModal');
    document.querySelectorAll('.revoke-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('revokeId').value = btn.dataset.id;
            document.getElementById('revokeName').textContent = btn.dataset.name;
            modal.classList.add('show');
        });
    });
    document.getElementById('cancelRevoke').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Deactivate modal
(function () {
    var modal = document.getElementById('deactivateModal');
    document.querySelectorAll('.deactivate-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deactivateId').value = btn.dataset.id;
            document.getElementById('deactivateName').textContent = btn.dataset.name;
            modal.classList.add('show');
        });
    });
    document.getElementById('cancelDeactivate').addEventListener('click', function () { modal.classList.remove('show'); });
})();
</script>
</body>
</html>
