<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/totp.php';
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

function currentAdmin(): array
{
    $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE id = ?');
    $stmt->execute([$_SESSION['super_admin_id']]);
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $admin = currentAdmin();

    if ($action === 'totp_setup_start') {
        $_SESSION['pending_totp_secret'] = totp_generate_secret();
        $_SESSION['show_totp_setup'] = true;
    }

    if ($action === 'totp_setup_cancel') {
        unset($_SESSION['pending_totp_secret'], $_SESSION['show_totp_setup']);
    }

    if ($action === 'totp_setup_confirm') {
        $secret = $_SESSION['pending_totp_secret'] ?? '';
        $code = (string) ($_POST['code'] ?? '');

        if ($secret === '' || !totp_verify($secret, $code)) {
            setNotification('error', 'Incorrect code. Check your authenticator app and try again.');
        } else {
            mainLguDb()->prepare('UPDATE super_admins SET totp_secret = ?, totp_enabled = 1, totp_confirmed_at = NOW() WHERE id = ?')
                ->execute([$secret, $admin['id']]);

            mainLguDb()->prepare('DELETE FROM super_admin_recovery_codes WHERE super_admin_id = ?')->execute([$admin['id']]);
            $codes = totp_generate_recovery_codes();
            $insert = mainLguDb()->prepare('INSERT INTO super_admin_recovery_codes (super_admin_id, code_hash) VALUES (?, ?)');
            foreach ($codes as $code) {
                $insert->execute([$admin['id'], password_hash($code, PASSWORD_DEFAULT)]);
            }

            unset($_SESSION['pending_totp_secret'], $_SESSION['show_totp_setup']);
            $_SESSION['show_recovery_codes'] = $codes;
            setNotification('success', 'Two-factor authentication is now enabled.');
        }
    }

    if ($action === 'totp_disable') {
        $password = (string) ($_POST['password'] ?? '');
        if (!password_verify($password, $admin['password_hash'])) {
            setNotification('error', 'Incorrect password. 2FA was not disabled.');
        } else {
            mainLguDb()->prepare('UPDATE super_admins SET totp_secret = NULL, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = ?')->execute([$admin['id']]);
            mainLguDb()->prepare('DELETE FROM super_admin_recovery_codes WHERE super_admin_id = ?')->execute([$admin['id']]);
            sendSecurityAlert(
                'Two-factor authentication disabled',
                'Two-factor authentication was just turned off on your Super Admin account, leaving it protected by your password alone.',
                $admin['email']
            );
            setNotification('success', 'Two-factor authentication has been disabled.');
        }
    }

    if ($action === 'totp_regenerate_codes') {
        if (!$admin['totp_enabled']) {
            setNotification('error', 'Enable 2FA first.');
        } else {
            mainLguDb()->prepare('DELETE FROM super_admin_recovery_codes WHERE super_admin_id = ?')->execute([$admin['id']]);
            $codes = totp_generate_recovery_codes();
            $insert = mainLguDb()->prepare('INSERT INTO super_admin_recovery_codes (super_admin_id, code_hash) VALUES (?, ?)');
            foreach ($codes as $code) {
                $insert->execute([$admin['id'], password_hash($code, PASSWORD_DEFAULT)]);
            }
            $_SESSION['show_recovery_codes'] = $codes;
            setNotification('success', 'New recovery codes generated. Your old codes no longer work.');
        }
    }

    header('Location: security.php');
    exit;
}

$admin = currentAdmin();
$showTotpSetup = !empty($_SESSION['show_totp_setup']) && !empty($_SESSION['pending_totp_secret']);
$pendingSecret = $_SESSION['pending_totp_secret'] ?? '';
$provisioningUri = $showTotpSetup ? totp_provisioning_uri($pendingSecret, $admin['email']) : '';

$recoveryCodes = $_SESSION['show_recovery_codes'] ?? null;
unset($_SESSION['show_recovery_codes']);

$recoveryCodesRemaining = 0;
if ($admin['totp_enabled']) {
    $stmt = mainLguDb()->prepare('SELECT COUNT(*) FROM super_admin_recovery_codes WHERE super_admin_id = ? AND used_at IS NULL');
    $stmt->execute([$admin['id']]);
    $recoveryCodesRemaining = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security — InfraGovServices</title>
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

    main { max-width: 1600px; margin: 0 auto; padding: 34px 40px 60px; position: relative; z-index: 1; }

    .page-head { margin-bottom: 20px; }
    .page-head h1 { font-size: 1.15rem; color: var(--text-primary); margin: 0 0 4px; }
    .page-head p { font-size: .84rem; color: var(--text-secondary); margin: 0; }

    .sec-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        backdrop-filter: blur(14px); padding: 24px; margin-bottom: 20px; max-width: 720px;
    }
    .sec-card-head { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 4px; }
    .sec-icon-wrap {
        width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; font-size: 1.1rem; flex-shrink: 0;
    }
    .sec-icon-wrap.on { background: linear-gradient(135deg,#22c55e,#16a34a); }
    .sec-card-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .sec-card-title h2 { font-size: 1rem; color: var(--text-primary); margin: 0; }
    .sec-card p.sec-desc { font-size: .84rem; color: var(--text-secondary); margin: 6px 0 18px; line-height: 1.6; }
    .sec-badge { font-size: .65rem; padding: 3px 10px; border-radius: 999px; font-weight: 700; letter-spacing: .02em; }
    .sec-badge.on { background: rgba(79,201,122,.18); color: #1b8a4c; border: 1px solid rgba(79,201,122,.4); }
    [data-theme="dark"] .sec-badge.on { color: #d1fae0; }
    .sec-badge.off { background: rgba(120,140,220,.14); color: var(--text-secondary); border: 1px solid var(--card-border); }
    .sec-meta { font-size: .78rem; color: var(--text-secondary); margin-bottom: 18px; }
    .sec-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn-add {
        display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; border: none;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; font-weight: 600; font-size: .85rem;
        cursor: pointer; font-family: inherit; box-shadow: 0 8px 20px rgba(63,90,223,.3);
    }
    .btn-outline {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px;
        border: 1px solid var(--card-border); background: none; color: var(--text-primary); font-weight: 600; font-size: .85rem;
        cursor: pointer; font-family: inherit;
    }
    .btn-outline:hover { background: rgba(120,140,220,.1); }
    .btn-outline.danger { color: #dc2626; border-color: rgba(220,38,38,.35); }
    .btn-outline.danger:hover { background: rgba(220,38,38,.08); }

    /* Setup panel */
    .totp-setup { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--card-border); }
    .totp-setup ol { margin: 0 0 18px; padding-left: 20px; font-size: .84rem; color: var(--text-secondary); line-height: 1.9; }
    .manual-key {
        background: rgba(79,110,247,.08); border: 1px solid rgba(79,110,247,.25); border-radius: 10px;
        padding: 14px; margin-bottom: 18px; text-align: center;
    }
    .manual-key .key-value {
        font-family: 'DM Mono', monospace; font-size: 1.05rem; letter-spacing: .12em; color: var(--text-primary);
        word-break: break-all; font-weight: 600;
    }
    .manual-key .key-label { font-size: .72rem; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: .05em; }
    .code-input-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    .code-input-row .form-field { flex: 1; min-width: 160px; }
    .form-field label { display: block; color: var(--text-secondary); font-size: .78rem; font-weight: 500; margin-bottom: 6px; }
    .form-field input {
        width: 100%; padding: 10px 12px; border-radius: 9px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); font-size: .88rem; font-family: 'DM Mono', monospace;
        letter-spacing: .2em; text-align: center;
    }
    .form-field input:focus { outline: none; border-color: #4f6ef7; }

    /* Recovery codes modal grid */
    .recovery-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px;
    }
    .recovery-code {
        background: rgba(79,110,247,.08); border: 1px solid rgba(79,110,247,.25); border-radius: 8px;
        padding: 10px; text-align: center; font-family: 'DM Mono', monospace; font-size: .84rem; color: var(--text-primary);
    }

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
    .modal-card.modal-card--wide { max-width: 460px; }
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
    .modal-icon-wrap--success {
        background: linear-gradient(135deg, rgba(34,197,94,.18), rgba(34,197,94,.08));
        border-color: rgba(34,197,94,.3); color: #22c55e;
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

    .copy-btn {
        display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 6px 12px; border-radius: 7px;
        border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-primary); font-size: .76rem;
        cursor: pointer; font-family: inherit;
    }

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
        .recovery-grid { grid-template-columns: 1fr; }
        .code-input-row { flex-direction: column; align-items: stretch; }
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
        <li><a href="team.php" class="sidebar-link"><i class="fas fa-users"></i><span>Team</span></a></li>
        <li><a href="security.php" class="sidebar-link active"><i class="fas fa-shield-halved"></i><span>Security</span></a></li>
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
        <h1>Security</h1>
        <p>Manage how your Super Admin account is protected.</p>
    </div>

    <div class="sec-card">
        <div class="sec-card-head">
            <div class="sec-icon-wrap<?= $admin['totp_enabled'] ? ' on' : '' ?>"><i class="fas fa-mobile-screen-button"></i></div>
            <div style="flex:1; min-width:0;">
                <div class="sec-card-title">
                    <h2>Two-factor authentication</h2>
                    <span class="sec-badge <?= $admin['totp_enabled'] ? 'on' : 'off' ?>"><?= $admin['totp_enabled'] ? 'Enabled' : 'Disabled' ?></span>
                </div>
            </div>
        </div>
        <p class="sec-desc">Require a 6-digit code from an authenticator app (Google Authenticator, Authy, 1Password, etc.) in addition to your password when signing in. This replaces the emailed OTP step in production once turned on.</p>

        <?php if ($admin['totp_enabled']): ?>
            <div class="sec-meta">
                Enabled since <?= date('M j, Y', strtotime($admin['totp_confirmed_at'])) ?> ·
                <?= $recoveryCodesRemaining ?> of 8 recovery codes remaining
            </div>
            <div class="sec-actions">
                <button type="button" class="btn-outline" id="openRegenModal"><i class="fas fa-rotate"></i> Regenerate recovery codes</button>
                <button type="button" class="btn-outline danger" id="openDisableModal"><i class="fas fa-shield-slash"></i> Disable 2FA</button>
            </div>
        <?php elseif ($showTotpSetup): ?>
            <div class="totp-setup">
                <ol>
                    <li>Open your authenticator app and choose "Add account" / "Scan a QR code".</li>
                    <li>Since this page doesn't render a QR code, choose "Enter a setup key manually" instead and type the code below.</li>
                    <li>Enter the 6-digit code the app generates to confirm.</li>
                </ol>
                <div class="manual-key">
                    <div class="key-label">Manual entry key</div>
                    <div class="key-value" id="manualKeyValue"><?= htmlspecialchars(chunk_split($pendingSecret, 4, ' ')) ?></div>
                    <button type="button" class="copy-btn" id="copyKeyBtn"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <form method="post" id="totpConfirmForm">
                    <input type="hidden" name="action" value="totp_setup_confirm">
                    <div class="code-input-row">
                        <div class="form-field">
                            <label for="totpCode">6-digit code</label>
                            <input type="text" name="code" id="totpCode" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="000000" required autofocus>
                        </div>
                        <button type="submit" class="btn-add"><i class="fas fa-check"></i> Confirm &amp; enable</button>
                    </div>
                </form>
                <form method="post" id="totpCancelForm" style="margin-top:12px;">
                    <input type="hidden" name="action" value="totp_setup_cancel">
                    <button type="submit" class="btn-outline" style="width:100%; justify-content:center;">Cancel setup</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" id="totpStartForm">
                <input type="hidden" name="action" value="totp_setup_start">
                <button type="submit" class="btn-add"><i class="fas fa-shield-halved"></i> Set up 2FA</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<!-- Recovery codes reveal (shown once right after enable/regenerate) -->
<div class="modal-backdrop<?= $recoveryCodes ? ' show' : '' ?>" id="recoveryCodesModal">
    <div class="modal-card modal-card--wide">
        <div class="modal-icon-wrap modal-icon-wrap--success"><i class="fas fa-key"></i></div>
        <h2>Save your recovery codes</h2>
        <p class="modal-sub">Each code works once, if you ever lose access to your authenticator app. Store them somewhere safe — they won't be shown again.</p>
        <div class="recovery-grid" id="recoveryGrid">
            <?php foreach (($recoveryCodes ?? []) as $code): ?>
                <div class="recovery-code"><?= htmlspecialchars($code) ?></div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="copy-btn" id="copyCodesBtn" style="width:100%; justify-content:center; margin-bottom:16px;"><i class="fas fa-copy"></i> Copy all codes</button>
        <div class="modal-actions">
            <button type="button" class="btn-confirm btn-confirm--info" id="closeRecoveryModal" style="width:100%;">I've saved these</button>
        </div>
    </div>
</div>

<!-- Disable 2FA confirmation -->
<div class="modal-backdrop" id="disableModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><i class="fas fa-shield-slash"></i></div>
        <h2>Disable two-factor authentication?</h2>
        <p class="modal-sub">Your account will only be protected by your password. Enter it below to confirm.</p>
        <form method="post" id="disableForm">
            <input type="hidden" name="action" value="totp_disable">
            <div class="form-field" style="text-align:left; margin-bottom:18px;">
                <label for="disablePassword">Password</label>
                <input type="password" name="password" id="disablePassword" required style="letter-spacing:normal; text-align:left; font-family:inherit;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelDisable">Cancel</button>
                <button type="submit" class="btn-confirm">Disable 2FA</button>
            </div>
        </form>
    </div>
</div>

<!-- Regenerate recovery codes confirmation -->
<div class="modal-backdrop" id="regenModal">
    <div class="modal-card">
        <div class="modal-icon-wrap modal-icon-wrap--info"><i class="fas fa-rotate"></i></div>
        <h2>Regenerate recovery codes?</h2>
        <p class="modal-sub">Your existing 8 codes will stop working immediately, replaced by a new set.</p>
        <form method="post" id="regenForm">
            <input type="hidden" name="action" value="totp_regenerate_codes">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelRegen">Cancel</button>
                <button type="submit" class="btn-confirm btn-confirm--info">Regenerate</button>
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

// Theme toggle
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

// Live clock
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

// Logout confirmation modal
(function () {
    var modal = document.getElementById('logoutModal');
    document.getElementById('openLogoutModal').addEventListener('click', function () { modal.classList.add('show'); });
    document.getElementById('cancelLogout').addEventListener('click', function () { modal.classList.remove('show'); });
    document.getElementById('confirmLogout').addEventListener('click', function () { window.location.href = 'logout.php'; });
})();

// Disable 2FA modal
(function () {
    var modal = document.getElementById('disableModal');
    var openBtn = document.getElementById('openDisableModal');
    if (openBtn) openBtn.addEventListener('click', function () { modal.classList.add('show'); });
    document.getElementById('cancelDisable').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Regenerate codes modal
(function () {
    var modal = document.getElementById('regenModal');
    var openBtn = document.getElementById('openRegenModal');
    if (openBtn) openBtn.addEventListener('click', function () { modal.classList.add('show'); });
    document.getElementById('cancelRegen').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Recovery codes reveal modal
(function () {
    var modal = document.getElementById('recoveryCodesModal');
    var closeBtn = document.getElementById('closeRecoveryModal');
    var copyBtn = document.getElementById('copyCodesBtn');
    if (closeBtn) closeBtn.addEventListener('click', function () { modal.classList.remove('show'); });
    if (copyBtn) copyBtn.addEventListener('click', function () {
        var codes = Array.from(document.querySelectorAll('#recoveryGrid .recovery-code')).map(function (el) { return el.textContent; });
        navigator.clipboard.writeText(codes.join('\n')).then(function () {
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(function () { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy all codes'; }, 2000);
        });
    });
})();

// Manual entry key copy button
(function () {
    var copyBtn = document.getElementById('copyKeyBtn');
    if (!copyBtn) return;
    copyBtn.addEventListener('click', function () {
        var text = document.getElementById('manualKeyValue').textContent.replace(/\s+/g, '');
        navigator.clipboard.writeText(text).then(function () {
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(function () { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 2000);
        });
    });
})();

// Client-side mirror of the 2-minute server-side session timeout
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
</script>
</body>
</html>
