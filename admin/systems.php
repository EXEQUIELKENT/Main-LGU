<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_display.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $adminEntryPath = trim($_POST['admin_entry_path'] ?? '');
        $ssoConsumePath = trim($_POST['sso_consume_path'] ?? '');
        $statsPath = trim($_POST['stats_path'] ?? '');
        $icon = array_key_exists($_POST['icon'] ?? '', SYSTEM_ICON_CHOICES) ? $_POST['icon'] : 'fa-server';
        $themeColor = in_array($_POST['theme_color'] ?? '', SYSTEM_THEME_COLORS, true) ? $_POST['theme_color'] : 'blue';
        $shortTag = trim($_POST['short_tag'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $baseUrl === '' || $ssoConsumePath === '') {
            setNotification('error', 'Name, base URL, and SSO consume path are required.');
        } elseif ($action === 'create') {
            $slug = strtolower(trim($_POST['slug'] ?? ''));
            if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
                setNotification('error', 'Slug must be lowercase letters, numbers, underscores, or hyphens only.');
            } else {
                $secret = bin2hex(random_bytes(32));
                try {
                    mainLguDb()->prepare(
                        'INSERT INTO connected_systems (slug, name, base_url, admin_entry_path, sso_consume_path, stats_path, shared_secret, is_active, icon, theme_color, short_tag) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$slug, $name, $baseUrl, $adminEntryPath, $ssoConsumePath, $statsPath ?: null, $secret, $isActive, $icon, $themeColor, $shortTag]);
                    $_SESSION['reveal_secret'] = ['name' => $name, 'secret' => $secret];
                    setNotification('success', "\"{$name}\" added. Copy its shared secret below before leaving this page — it won't be shown again.");
                } catch (\PDOException $e) {
                    setNotification('error', str_contains($e->getMessage(), 'Duplicate') ? 'That slug is already in use.' : 'Could not add the system.');
                }
            }
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            mainLguDb()->prepare(
                'UPDATE connected_systems SET name=?, base_url=?, admin_entry_path=?, sso_consume_path=?, stats_path=?, icon=?, theme_color=?, short_tag=?, is_active=? WHERE id=?'
            )->execute([$name, $baseUrl, $adminEntryPath, $ssoConsumePath, $statsPath ?: null, $icon, $themeColor, $shortTag, $isActive, $id]);
            setNotification('success', 'System updated.');
        }
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        mainLguDb()->prepare('UPDATE connected_systems SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        setNotification('success', 'Status updated.');
    }

    if ($action === 'rotate_secret') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mainLguDb()->prepare('SELECT name FROM connected_systems WHERE id = ?');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        if ($name !== false) {
            $secret = bin2hex(random_bytes(32));
            mainLguDb()->prepare('UPDATE connected_systems SET shared_secret = ? WHERE id = ?')->execute([$secret, $id]);
            $_SESSION['reveal_secret'] = ['name' => $name, 'secret' => $secret];
            setNotification('success', "Secret rotated for \"{$name}\" — update it on that system's own config too, it will stop authenticating until you do.");
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        mainLguDb()->prepare('DELETE FROM connected_systems WHERE id = ?')->execute([$id]);
        setNotification('success', 'System removed.');
    }

    header('Location: systems.php');
    exit;
}

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY id')->fetchAll();
$revealSecret = $_SESSION['reveal_secret'] ?? null;
unset($_SESSION['reveal_secret']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connected Systems — InfraGovServices</title>
<link rel="icon" href="../public/logocityhall.png" type="image/png">
<script>
(function () {
    try {
        var t = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light';
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
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
    .sidebar.collapsed { width: var(--sidebar-w-collapsed); }
    .sidebar-header { display: flex; align-items: center; justify-content: flex-end; padding: 14px; }
    .sidebar.collapsed .sidebar-header { justify-content: center; }
    .sidebar-toggle {
        width: 30px; height: 30px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: .78rem;
        box-shadow: 0 3px 10px rgba(63,90,223,.35); transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    }
    .sidebar.collapsed .sidebar-toggle { transform: rotate(180deg); }
    .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 0 18px 18px; }
    .sidebar-logo img { width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0; }
    .sidebar-logo strong { display: block; color: var(--text-primary); font-size: .9rem; white-space: nowrap; }
    .sidebar-logo span { display: block; color: var(--text-secondary); font-size: .68rem; white-space: nowrap; }
    .sidebar.collapsed .sidebar-logo-text { display: none; }
    .sidebar-nav-list { list-style: none; margin: 0; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; overflow-y: auto; overflow-x: hidden; }
    .sidebar-link {
        display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px;
        color: var(--text-secondary); text-decoration: none; font-size: .85rem; font-weight: 500;
        white-space: nowrap; transition: background .2s, color .2s, transform .2s;
    }
    .sidebar-link i { width: 18px; text-align: center; flex-shrink: 0; }
    .sidebar-link:hover { background: rgba(79,110,247,.1); color: var(--text-primary); }
    .sidebar-link.active { background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; box-shadow: 0 3px 10px rgba(63,90,223,.3); }
    .sidebar.collapsed .sidebar-link { justify-content: center; padding: 11px; }
    .sidebar.collapsed .sidebar-link span { display: none; }
    .sidebar-bottom { border-top: 1px solid var(--card-border); padding: 14px; display: flex; flex-direction: column; gap: 10px; }
    .sidebar-user { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .sidebar-user .user-avatar {
        width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#4f6ef7,#3f5adf);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 700; flex-shrink: 0;
    }
    .sidebar-user span.uname { color: var(--text-primary); font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar.collapsed .sidebar-user span.uname { display: none; }
    .sidebar-logout {
        display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 9px;
        border: 1px solid rgba(255,143,163,.3); background: none; color: #ff8fa3; cursor: pointer;
        font-family: inherit; font-size: .82rem; font-weight: 500; transition: background .15s;
    }
    .sidebar-logout:hover { background: rgba(255,143,163,.1); }
    .sidebar.collapsed .sidebar-logout { justify-content: center; padding: 10px; }
    .sidebar.collapsed .sidebar-logout span { display: none; }
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
    .sidebar.collapsed ~ .topbar { margin-left: var(--sidebar-w-collapsed); }

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
    .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-w-collapsed); }

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

    .page-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    .page-head h1 { font-size: 1.15rem; color: var(--text-primary); margin: 0; }
    .btn-add {
        display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; border: none;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; font-weight: 600; font-size: .85rem;
        cursor: pointer; font-family: inherit; box-shadow: 0 8px 20px rgba(63,90,223,.3);
    }

    .systems-table-wrap {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        backdrop-filter: blur(14px); overflow: hidden; overflow-x: auto;
    }
    table.systems-table { width: 100%; border-collapse: collapse; min-width: 640px; }
    table.systems-table th {
        text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--text-secondary); padding: 14px 16px; border-bottom: 1px solid var(--card-border);
    }
    table.systems-table td { padding: 14px 16px; border-bottom: 1px solid var(--card-border); font-size: .85rem; vertical-align: middle; }
    table.systems-table tr:last-child td { border-bottom: none; }
    .sys-name-cell { display: flex; align-items: center; gap: 10px; }
    .sys-icon-chip {
        width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .85rem; flex-shrink: 0;
    }
    .sys-icon-chip.blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .sys-icon-chip.orange { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .sys-icon-chip.purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .sys-icon-chip.rose   { background: linear-gradient(135deg,#fb7185,#c8185a); }
    .sys-icon-chip.teal   { background: linear-gradient(135deg,#14b8a6,#0f766e); }
    .sys-slug { color: var(--text-secondary); font-size: .74rem; font-family: 'DM Mono', monospace; }
    .sys-url { color: var(--text-secondary); font-size: .78rem; word-break: break-all; }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .row-btn {
        width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--card-border); background: rgba(120,140,220,.08);
        color: var(--text-secondary); display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: .8rem; transition: background .15s, color .15s;
    }
    .row-btn:hover { background: rgba(79,110,247,.15); color: var(--text-primary); }
    .row-btn.danger:hover { background: rgba(239,68,68,.15); color: #ef4444; }
    .status-toggle-btn { border: none; cursor: pointer; font-family: inherit; }
    .badge { font-size: .65rem; padding: 3px 10px; border-radius: 999px; font-weight: 700; letter-spacing: .02em; }
    .badge.active { background: rgba(79,201,122,.18); color: #1b8a4c; border: 1px solid rgba(79,201,122,.4); }
    [data-theme="dark"] .badge.active { color: #d1fae0; }
    .badge.inactive { background: rgba(215,63,82,.18); color: #b3283f; border: 1px solid rgba(215,63,82,.4); }
    [data-theme="dark"] .badge.inactive { color: #ffd9de; }

    /* ── Mobile card view — same dual-markup approach CIMM uses (render both
       table and cards, media query swaps which is visible) rather than a
       CSS-only table transform ── */
    .systems-card-list { display: none; }
    .sys-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;
        padding: 16px; margin-bottom: 12px; backdrop-filter: blur(14px);
    }
    .sys-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .sys-card-header .sys-card-name { color: var(--text-primary); font-weight: 600; font-size: .9rem; }
    .sys-card-header .sys-slug { display: block; }
    .sys-card-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: .8rem; padding: 6px 0; border-top: 1px solid var(--card-border); }
    .sys-card-row strong { color: var(--text-secondary); font-weight: 500; }
    .sys-card-row .sys-url { text-align: right; }
    .sys-card-actions { display: flex; gap: 8px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--card-border); }
    .sys-card-actions .row-btn { flex: 1; width: auto; gap: 6px; padding: 0 10px; font-size: .78rem; font-weight: 600; }
    .sys-card-actions .row-btn.rotate-btn, .sys-card-actions .row-btn.delete-btn { flex: 0 0 auto; padding: 0; width: 38px; }

    /* ── Modals ── */
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(3,6,16,.55); backdrop-filter: blur(6px);
        display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; overflow-y: auto;
    }
    .modal-backdrop.show { display: flex; }
    .modal-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px;
        padding: 30px 26px 24px; max-width: 340px; width: 100%; box-shadow: 0 25px 60px rgba(0,0,0,.4);
        backdrop-filter: blur(22px); text-align: center; animation: modalPop .25s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes modalPop { from { transform: translateY(20px) scale(.94); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }

    /* Add/Edit system modal — header-bar form modal (CIMM-inspired) */
    .sysform-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px;
        max-width: 520px; width: 100%; max-height: 90vh; box-shadow: 0 25px 60px rgba(0,0,0,.4);
        backdrop-filter: blur(22px); text-align: left; animation: modalPop .25s cubic-bezier(.34,1.56,.64,1);
        overflow: hidden; display: flex; flex-direction: column; margin: auto;
    }
    .sysform-card form { display: flex; flex-direction: column; min-height: 0; flex: 1; }
    .sysform-head {
        display: flex; align-items: center; gap: 13px; padding: 18px 22px; flex-shrink: 0;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff;
    }
    .sysform-head-icon {
        width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,.18);
        display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
    }
    .sysform-head-text { flex: 1; min-width: 0; }
    .sysform-head-text h2 { margin: 0; font-size: 1rem; color: #fff; }
    .sysform-head-text span { font-size: .74rem; opacity: .85; display: block; margin-top: 2px; }
    .sysform-close {
        width: 30px; height: 30px; border-radius: 50%; border: none; background: rgba(255,255,255,.15);
        color: #fff; font-size: .8rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background .18s; flex-shrink: 0;
    }
    .sysform-close:hover { background: rgba(255,255,255,.3); }
    .sysform-body { padding: 22px 22px 4px; overflow-y: auto; flex: 1; min-height: 0; }
    .sysform-footer { flex-shrink: 0; padding: 16px 22px; border-top: 1px solid var(--card-border); }
    @media (max-width: 520px) {
        .sysform-card { max-width: 96vw; }
    }
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

    /* Form fields (add/edit modal) */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-field label { display: block; color: var(--text-secondary); font-size: .78rem; font-weight: 500; margin-bottom: 6px; }
    .form-field input, .form-field select {
        width: 100%; padding: 10px 12px; border-radius: 9px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); font-size: .88rem; font-family: inherit;
    }
    .form-field input:focus, .form-field select:focus { outline: none; border-color: #4f6ef7; }
    .form-checkbox { display: flex; align-items: center; gap: 8px; font-size: .85rem; color: var(--text-primary); margin-bottom: 18px; }
    .form-checkbox input { width: auto; }

    .secret-box {
        background: rgba(79,110,247,.08); border: 1px solid rgba(79,110,247,.25); border-radius: 10px;
        padding: 14px; margin-bottom: 18px; word-break: break-all; font-family: 'DM Mono', monospace; font-size: .78rem;
        color: var(--text-primary); position: relative;
    }
    .copy-btn {
        display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; padding: 6px 12px; border-radius: 7px;
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
        .form-grid { grid-template-columns: 1fr; }
        .systems-table-wrap { display: none; }
        .systems-card-list { display: block; }
    }
</style>
</head>
<body>
<div id="loadingOverlay"></div>
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
        <li><a href="systems.php" class="sidebar-link active"><i class="fas fa-server"></i><span>Connected Systems</span></a></li>
        <li><a href="launch_history.php" class="sidebar-link"><i class="fas fa-clock-rotate-left"></i><span>Launch History</span></a></li>
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
        <h1>Connected systems</h1>
        <button type="button" class="btn-add" id="openAddModal"><i class="fas fa-plus"></i> Add system</button>
    </div>

    <div class="systems-table-wrap">
        <table class="systems-table">
            <thead>
                <tr>
                    <th>System</th>
                    <th>Base URL</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($systems as $system): ?>
                <tr>
                    <td>
                        <div class="sys-name-cell">
                            <div class="sys-icon-chip <?= htmlspecialchars($system['theme_color']) ?>"><i class="fas <?= htmlspecialchars($system['icon']) ?>"></i></div>
                            <div>
                                <div><?= htmlspecialchars($system['name']) ?></div>
                                <div class="sys-slug"><?= htmlspecialchars($system['slug']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="sys-url"><?= htmlspecialchars($system['base_url']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= (int) $system['id'] ?>">
                            <button type="submit" class="badge status-toggle-btn <?= $system['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $system['is_active'] ? '● Active' : '● Inactive' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="row-btn edit-btn" title="Edit"
                                data-id="<?= (int) $system['id'] ?>"
                                data-slug="<?= htmlspecialchars($system['slug']) ?>"
                                data-name="<?= htmlspecialchars($system['name']) ?>"
                                data-base-url="<?= htmlspecialchars($system['base_url']) ?>"
                                data-admin-entry-path="<?= htmlspecialchars($system['admin_entry_path']) ?>"
                                data-sso-consume-path="<?= htmlspecialchars($system['sso_consume_path']) ?>"
                                data-stats-path="<?= htmlspecialchars((string) $system['stats_path']) ?>"
                                data-icon="<?= htmlspecialchars($system['icon']) ?>"
                                data-theme-color="<?= htmlspecialchars($system['theme_color']) ?>"
                                data-short-tag="<?= htmlspecialchars($system['short_tag']) ?>"
                                data-is-active="<?= (int) $system['is_active'] ?>"
                            ><i class="fas fa-pen"></i></button>

                            <button type="button" class="row-btn rotate-btn" title="Rotate secret" data-id="<?= (int) $system['id'] ?>" data-name="<?= htmlspecialchars($system['name']) ?>"><i class="fas fa-key"></i></button>

                            <button type="button" class="row-btn danger delete-btn" title="Delete" data-id="<?= (int) $system['id'] ?>" data-name="<?= htmlspecialchars($system['name']) ?>"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($systems === []): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-secondary); padding:30px;">No connected systems yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="systems-card-list">
        <?php foreach ($systems as $system): ?>
            <div class="sys-card">
                <div class="sys-card-header">
                    <div class="sys-icon-chip <?= htmlspecialchars($system['theme_color']) ?>"><i class="fas <?= htmlspecialchars($system['icon']) ?>"></i></div>
                    <div>
                        <div class="sys-card-name"><?= htmlspecialchars($system['name']) ?></div>
                        <div class="sys-slug"><?= htmlspecialchars($system['slug']) ?></div>
                    </div>
                </div>
                <div class="sys-card-row">
                    <span>Base URL</span>
                    <span class="sys-url" style="text-align:right;"><?= htmlspecialchars($system['base_url']) ?></span>
                </div>
                <div class="sys-card-row">
                    <span>Status</span>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= (int) $system['id'] ?>">
                        <button type="submit" class="badge status-toggle-btn <?= $system['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $system['is_active'] ? '● Active' : '● Inactive' ?>
                        </button>
                    </form>
                </div>
                <div class="sys-card-actions">
                    <button type="button" class="row-btn edit-btn" title="Edit"
                        data-id="<?= (int) $system['id'] ?>"
                        data-slug="<?= htmlspecialchars($system['slug']) ?>"
                        data-name="<?= htmlspecialchars($system['name']) ?>"
                        data-base-url="<?= htmlspecialchars($system['base_url']) ?>"
                        data-admin-entry-path="<?= htmlspecialchars($system['admin_entry_path']) ?>"
                        data-sso-consume-path="<?= htmlspecialchars($system['sso_consume_path']) ?>"
                        data-stats-path="<?= htmlspecialchars((string) $system['stats_path']) ?>"
                        data-icon="<?= htmlspecialchars($system['icon']) ?>"
                        data-theme-color="<?= htmlspecialchars($system['theme_color']) ?>"
                        data-short-tag="<?= htmlspecialchars($system['short_tag']) ?>"
                        data-is-active="<?= (int) $system['is_active'] ?>"
                    ><i class="fas fa-pen"></i> Edit</button>

                    <button type="button" class="row-btn rotate-btn" title="Rotate secret" data-id="<?= (int) $system['id'] ?>" data-name="<?= htmlspecialchars($system['name']) ?>"><i class="fas fa-key"></i></button>

                    <button type="button" class="row-btn danger delete-btn" title="Delete" data-id="<?= (int) $system['id'] ?>" data-name="<?= htmlspecialchars($system['name']) ?>"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($systems === []): ?>
            <div style="text-align:center; color:var(--text-secondary); padding:30px;">No connected systems yet.</div>
        <?php endif; ?>
    </div>
</main>

<!-- Reveal secret modal (shown once right after create/rotate) -->
<div class="modal-backdrop<?= $revealSecret ? ' show' : '' ?>" id="revealSecretModal">
    <div class="modal-card">
        <div class="modal-icon-wrap modal-icon-wrap--info"><i class="fas fa-key"></i></div>
        <h2>Shared secret for <?= $revealSecret ? htmlspecialchars($revealSecret['name']) : '' ?></h2>
        <p class="modal-sub">Copy this now and set it as the SSO shared secret on that system's own config. It will not be shown again.</p>
        <div class="secret-box" id="secretBoxValue"><?= $revealSecret ? htmlspecialchars($revealSecret['secret']) : '' ?></div>
        <button type="button" class="copy-btn" id="copySecretBtn" style="width:100%; justify-content:center;"><i class="fas fa-copy"></i> Copy to clipboard</button>
        <div class="modal-actions" style="margin-top:16px;">
            <button type="button" class="btn-confirm btn-confirm--info" id="closeRevealSecret" style="width:100%;">Done</button>
        </div>
    </div>
</div>

<!-- Add/Edit modal -->
<div class="modal-backdrop" id="formModal">
    <div class="sysform-card">
        <div class="sysform-head">
            <div class="sysform-head-icon"><i class="fas fa-server" id="formModalIcon"></i></div>
            <div class="sysform-head-text">
                <h2 id="formModalTitle">Add system</h2>
                <span id="formModalSub">Register a new connected system</span>
            </div>
            <button type="button" class="sysform-close" id="closeFormModalX" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post" id="systemForm">
            <div class="sysform-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                <div class="form-grid">
                    <div class="form-field" id="slugField">
                        <label for="formSlug">Slug (fixed identifier)</label>
                        <input type="text" name="slug" id="formSlug" placeholder="e.g. roadmon" pattern="[a-z0-9_-]+" required>
                    </div>
                    <div class="form-field">
                        <label for="formName">Display name</label>
                        <input type="text" name="name" id="formName" required>
                    </div>
                    <div class="form-field full">
                        <label for="formBaseUrl">Base URL</label>
                        <input type="text" name="base_url" id="formBaseUrl" placeholder="https://example.infragovservices.com" required>
                    </div>
                    <div class="form-field">
                        <label for="formSsoPath">SSO consume path</label>
                        <input type="text" name="sso_consume_path" id="formSsoPath" placeholder="/sso/consume" required>
                    </div>
                    <div class="form-field">
                        <label for="formStatsPath">Stats path (optional)</label>
                        <input type="text" name="stats_path" id="formStatsPath" placeholder="/api/stats">
                    </div>
                    <div class="form-field full">
                        <label for="formAdminPath">Admin entry path (reference only)</label>
                        <input type="text" name="admin_entry_path" id="formAdminPath" placeholder="/dashboard">
                    </div>
                    <div class="form-field">
                        <label for="formIcon">Icon</label>
                        <select name="icon" id="formIcon">
                            <?php foreach (SYSTEM_ICON_CHOICES as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="formThemeColor">Card color</label>
                        <select name="theme_color" id="formThemeColor">
                            <?php foreach (SYSTEM_THEME_COLORS as $color): ?>
                                <option value="<?= $color ?>"><?= ucfirst($color) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field full">
                        <label for="formShortTag">Badge text</label>
                        <input type="text" name="short_tag" id="formShortTag" placeholder="e.g. RGMAP" maxlength="20">
                    </div>
                </div>
                <label class="form-checkbox"><input type="checkbox" name="is_active" id="formIsActive" checked> Active (visible on the dashboard)</label>
            </div>
            <div class="sysform-footer">
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="cancelForm">Cancel</button>
                    <button type="submit" class="btn-confirm btn-confirm--info" id="submitFormBtn">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Save changes confirmation (edit only) -->
<div class="modal-backdrop" id="saveConfirmModal">
    <div class="modal-card">
        <div class="modal-icon-wrap modal-icon-wrap--info"><i class="fas fa-floppy-disk"></i></div>
        <h2>Save changes to <span id="saveConfirmName"></span>?</h2>
        <p class="modal-sub">This updates the system's connection settings immediately.</p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" id="cancelSaveConfirm">Cancel</button>
            <button type="button" class="btn-confirm btn-confirm--info" id="confirmSaveBtn">Save changes</button>
        </div>
    </div>
</div>

<!-- Rotate secret confirm modal -->
<div class="modal-backdrop" id="rotateModal">
    <div class="modal-card">
        <div class="modal-icon-wrap modal-icon-wrap--info"><i class="fas fa-key"></i></div>
        <h2>Rotate secret for <span id="rotateSystemName"></span>?</h2>
        <p class="modal-sub">The old secret stops working immediately. You'll need to update it on that system's own config right after.</p>
        <form method="post" id="rotateForm">
            <input type="hidden" name="action" value="rotate_secret">
            <input type="hidden" name="id" id="rotateId">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelRotate">Cancel</button>
                <button type="submit" class="btn-confirm btn-confirm--info">Rotate</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><i class="fas fa-trash"></i></div>
        <h2>Remove <span id="deleteSystemName"></span>?</h2>
        <p class="modal-sub">This removes it from the dashboard entirely. Past launch history is kept for the record.</p>
        <form method="post" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelDelete">Cancel</button>
                <button type="submit" class="btn-confirm">Remove</button>
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

// Add/Edit modal
(function () {
    var modal = document.getElementById('formModal');
    var title = document.getElementById('formModalTitle');
    var sub = document.getElementById('formModalSub');
    var icon = document.getElementById('formModalIcon');
    var slugField = document.getElementById('slugField');
    var slugInput = document.getElementById('formSlug');

    function closeModal() { modal.classList.remove('show'); }

    function openForCreate() {
        title.textContent = 'Add system';
        sub.textContent = 'Register a new connected system';
        icon.className = 'fas fa-plus';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formId').value = '';
        document.getElementById('systemForm').reset();
        slugField.style.display = '';
        slugInput.readOnly = false;
        modal.classList.add('show');
    }

    function openForEdit(btn) {
        var d = btn.dataset;
        title.textContent = 'Edit ' + d.name;
        sub.textContent = 'Update this system’s connection settings';
        icon.className = 'fas fa-pen';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formId').value = d.id;
        document.getElementById('formSlug').value = d.slug;
        document.getElementById('formName').value = d.name;
        document.getElementById('formBaseUrl').value = d.baseUrl;
        document.getElementById('formAdminPath').value = d.adminEntryPath;
        document.getElementById('formSsoPath').value = d.ssoConsumePath;
        document.getElementById('formStatsPath').value = d.statsPath;
        document.getElementById('formIcon').value = d.icon;
        document.getElementById('formThemeColor').value = d.themeColor;
        document.getElementById('formShortTag').value = d.shortTag;
        document.getElementById('formIsActive').checked = d.isActive === '1';
        slugField.style.display = 'none';
        modal.classList.add('show');
    }

    document.getElementById('openAddModal').addEventListener('click', openForCreate);
    document.querySelectorAll('.edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openForEdit(btn); });
    });
    document.getElementById('cancelForm').addEventListener('click', closeModal);
    document.getElementById('closeFormModalX').addEventListener('click', closeModal);
})();

// Confirm before saving edits (create is submitted directly)
(function () {
    var form = document.getElementById('systemForm');
    var confirmModal = document.getElementById('saveConfirmModal');
    var confirmName = document.getElementById('saveConfirmName');
    var confirmYes = document.getElementById('confirmSaveBtn');
    var confirmNo = document.getElementById('cancelSaveConfirm');

    form.addEventListener('submit', function (e) {
        if (document.getElementById('formAction').value === 'update') {
            e.preventDefault();
            confirmName.textContent = document.getElementById('formName').value || 'this system';
            confirmModal.classList.add('show');
        }
    });
    confirmYes.addEventListener('click', function () {
        confirmModal.classList.remove('show');
        form.submit();
    });
    confirmNo.addEventListener('click', function () { confirmModal.classList.remove('show'); });
})();

// Rotate secret modal
(function () {
    var modal = document.getElementById('rotateModal');
    document.querySelectorAll('.rotate-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('rotateId').value = btn.dataset.id;
            document.getElementById('rotateSystemName').textContent = btn.dataset.name;
            modal.classList.add('show');
        });
    });
    document.getElementById('cancelRotate').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Delete modal
(function () {
    var modal = document.getElementById('deleteModal');
    document.querySelectorAll('.delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteId').value = btn.dataset.id;
            document.getElementById('deleteSystemName').textContent = btn.dataset.name;
            modal.classList.add('show');
        });
    });
    document.getElementById('cancelDelete').addEventListener('click', function () { modal.classList.remove('show'); });
})();

// Reveal-secret modal (auto-shown server-side if a secret was just issued)
(function () {
    var modal = document.getElementById('revealSecretModal');
    var closeBtn = document.getElementById('closeRevealSecret');
    var copyBtn = document.getElementById('copySecretBtn');
    if (closeBtn) closeBtn.addEventListener('click', function () { modal.classList.remove('show'); });
    if (copyBtn) copyBtn.addEventListener('click', function () {
        var text = document.getElementById('secretBoxValue').textContent;
        navigator.clipboard.writeText(text).then(function () {
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(function () { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy to clipboard'; }, 2000);
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