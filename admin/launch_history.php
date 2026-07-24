<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$filterSystem = trim($_GET['system'] ?? '');
$filterFrom = trim($_GET['from'] ?? '');
$filterTo = trim($_GET['to'] ?? '');

$where = [];
$params = [];
if ($filterSystem !== '') {
    $where[] = 'l.system_slug = ?';
    $params[] = $filterSystem;
}
if ($filterFrom !== '') {
    $where[] = 'l.launched_at >= ?';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $where[] = 'l.launched_at <= ?';
    $params[] = $filterTo . ' 23:59:59';
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = mainLguDb()->prepare("SELECT COUNT(*) FROM sso_launch_log l {$whereSql}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = mainLguDb()->prepare("
    SELECT l.*, a.full_name AS admin_name, a.email AS admin_email,
           s.name AS system_name, s.icon AS system_icon, s.theme_color AS system_theme
    FROM sso_launch_log l
    LEFT JOIN super_admins a ON a.id = l.super_admin_id
    LEFT JOIN connected_systems s ON s.slug = l.system_slug
    {$whereSql}
    ORDER BY l.launched_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$launches = $stmt->fetchAll();

$allSystems = mainLguDb()->query('SELECT slug, name FROM connected_systems ORDER BY name')->fetchAll();

function buildQuery(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Launch History — InfraGovServices</title>
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
        --dropdown-bg: #ffffff;
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
        --dropdown-bg: #10182f;
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
    :root { --sidebar-w: 208px; --sidebar-w-collapsed: 64px; }
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

    h1 { font-size: 1.15rem; color: var(--text-primary); margin: 0 0 20px; }

    .filters {
        display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px;
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px;
        backdrop-filter: blur(14px); position: relative; z-index: 20;
    }
    .filters .field { display: flex; flex-direction: column; gap: 6px; }
    .filters label { font-size: .72rem; color: var(--text-secondary); font-weight: 500; }
    .filters select, .filters input {
        padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); font-size: .82rem; font-family: inherit;
    }
    .filters button, .filters a.clear-link {
        padding: 9px 16px; border-radius: 8px; border: none; font-size: .82rem; font-weight: 600; cursor: pointer;
        background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; font-family: inherit; text-decoration: none;
        display: inline-flex; align-items: center;
    }
    .filters a.clear-link { background: rgba(120,140,220,.14); color: var(--text-primary); border: 1px solid var(--card-border); }

    /* ── Searchable dropdown (System filter) — CIMM-style combobox ── */
    .combobox { position: relative; width: 100%; min-width: 190px; }
    .combobox-display {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); cursor: pointer;
        font-size: .82rem; min-height: 40px; transition: border-color .2s, box-shadow .2s;
    }
    .combobox-display.open { border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.15); border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .combobox-arrow { color: var(--text-secondary); font-size: .68rem; transition: transform .2s; flex-shrink: 0; }
    .combobox-display.open .combobox-arrow { transform: rotate(180deg); }
    .combobox-dropdown {
        position: absolute; top: 100%; left: 0; width: 100%; z-index: 1000;
        background: var(--dropdown-bg); border: 1.5px solid #4f6ef7; border-top: none;
        border-radius: 0 0 8px 8px; box-shadow: 0 14px 30px rgba(0,0,0,.22);
        display: none;
    }
    .combobox-dropdown.open { display: block; }
    .combobox-search-wrap { position: relative; padding: 6px; border-bottom: 1px solid var(--card-border); }
    .combobox-search-wrap i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: .72rem; pointer-events: none; }
    .combobox-search { width: 100%; padding: 9px 10px 9px 40px; border: none; background: transparent; color: var(--text-primary); font-size: .8rem; outline: none; font-family: inherit; }
    .combobox-list { max-height: 200px; overflow-y: auto; }
    .combobox-option { padding: 9px 14px; font-size: .82rem; cursor: pointer; color: var(--text-primary); transition: background .12s; }
    .combobox-option:hover, .combobox-option.highlighted { background: rgba(79,110,247,.1); }
    .combobox-option.selected-opt { background: rgba(79,110,247,.16); font-weight: 600; color: #4f6ef7; }
    .combobox-no-results { padding: 12px 14px; color: var(--text-secondary); font-size: .78rem; text-align: center; }

    /* ── Custom date picker — CIMM-style popup calendar ── */
    .rdt-display {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--input-border);
        background: var(--input-bg); color: var(--text-primary); cursor: pointer;
        font-size: .82rem; min-height: 40px; user-select: none; transition: border-color .2s, box-shadow .2s; min-width: 150px;
    }
    .rdt-display:hover { border-color: #4f6ef7; }
    .rdt-icon { color: var(--text-secondary); font-size: .78rem; flex-shrink: 0; }
    .rdt-overlay {
        position: fixed; z-index: 99999; display: none; visibility: hidden; top: -9999px; left: -9999px;
        width: 264px; background: var(--card-bg); border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,.28); border: 1px solid var(--card-border);
        backdrop-filter: blur(22px); overflow: hidden; font-family: inherit;
    }
    .rdt-overlay.open { display: block; visibility: visible; top: var(--rdt-top, 0); left: var(--rdt-left, 0); animation: rdtPopIn .18s ease; }
    @keyframes rdtPopIn { from { opacity: 0; transform: scale(.95) translateY(-6px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .rdt-header {
        display: flex; align-items: center; justify-content: space-between; padding: 12px 14px;
        background: linear-gradient(135deg, #4f6ef7, #3f5adf); color: #fff;
    }
    .rdt-nav { background: rgba(255,255,255,.15); border: none; color: #fff; width: 26px; height: 26px; border-radius: 7px; cursor: pointer; font-size: .95rem; }
    .rdt-nav:hover { background: rgba(255,255,255,.3); }
    .rdt-month-label { font-size: .85rem; font-weight: 600; }
    .rdt-weekdays { display: grid; grid-template-columns: repeat(7,1fr); padding: 8px 8px 0; font-size: .64rem; color: var(--text-secondary); text-align: center; font-weight: 600; }
    .rdt-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; padding: 6px 8px 10px; }
    .rdt-day {
        aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; font-size: .74rem; cursor: pointer; color: var(--text-primary);
        background: none; border: none; font-family: inherit; transition: background .12s, color .12s;
    }
    .rdt-day:hover { background: rgba(79,110,247,.14); }
    .rdt-day.today { background: rgba(79,110,247,.12); font-weight: 700; color: #4f6ef7; }
    .rdt-day.selected { background: linear-gradient(135deg,#4f6ef7,#3f5adf) !important; color: #fff !important; font-weight: 700; }
    .rdt-day.empty { visibility: hidden; cursor: default; }
    .rdt-footer { display: flex; justify-content: space-between; padding: 10px 14px; border-top: 1px solid var(--card-border); }
    .rdt-footer button { background: none; border: none; font-size: .78rem; font-weight: 600; cursor: pointer; font-family: inherit; padding: 6px 10px; border-radius: 6px; }
    .rdt-clear { color: var(--text-secondary); }
    .rdt-clear:hover { background: rgba(120,140,220,.12); }
    .rdt-done { color: #4f6ef7; }
    .rdt-done:hover { background: rgba(79,110,247,.12); }

    .history-table-wrap {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        backdrop-filter: blur(14px); overflow: hidden; overflow-x: auto;
    }
    table.history-table { width: 100%; border-collapse: collapse; min-width: 640px; }
    table.history-table th {
        text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--text-secondary); padding: 14px 16px; border-bottom: 1px solid var(--card-border);
    }
    table.history-table td { padding: 13px 16px; border-bottom: 1px solid var(--card-border); font-size: .84rem; vertical-align: middle; }
    table.history-table tr:last-child td { border-bottom: none; }
    .sys-cell { display: flex; align-items: center; gap: 9px; }
    .sys-icon-chip {
        width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .74rem; flex-shrink: 0;
    }
    .sys-icon-chip.blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .sys-icon-chip.orange { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .sys-icon-chip.purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .sys-icon-chip.rose   { background: linear-gradient(135deg,#fb7185,#c8185a); }
    .sys-icon-chip.teal   { background: linear-gradient(135deg,#14b8a6,#0f766e); }
    .ip-cell { font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--text-secondary); }
    .time-cell { font-family: 'DM Mono', monospace; font-size: .78rem; white-space: nowrap; }

    .pagination { display: flex; align-items: center; justify-content: space-between; margin-top: 18px; flex-wrap: wrap; gap: 10px; }
    .pagination .info { color: var(--text-secondary); font-size: .8rem; }
    .pagination .pages { display: flex; gap: 6px; }
    .pagination .pages a, .pagination .pages span {
        display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px;
        border-radius: 8px; text-decoration: none; font-size: .8rem; padding: 0 10px;
        border: 1px solid var(--card-border); color: var(--text-primary); background: var(--card-bg);
    }
    .pagination .pages a:hover { background: rgba(79,110,247,.12); }
    .pagination .pages .current { background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; border-color: transparent; }
    .pagination .pages .disabled { opacity: .4; pointer-events: none; }

    .history-card-list { display: none; }
    .hist-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;
        padding: 16px; margin-bottom: 12px; backdrop-filter: blur(14px);
    }
    .hist-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .hist-card-header .sys-cell { font-weight: 600; color: var(--text-primary); font-size: .9rem; }
    .hist-card-row {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        font-size: .8rem; padding: 7px 0; border-top: 1px solid var(--card-border); color: var(--text-primary);
    }
    .hist-card-row span:first-child { color: var(--text-secondary); }

    @media (max-width: 768px) {
        main { padding: 18px 14px 40px; }
        .filters { flex-direction: column; align-items: stretch; }
        .history-table-wrap { display: none; }
        .history-card-list { display: block; }
    }
</style>
</head>
<body>
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
        <li><a href="launch_history.php" class="sidebar-link active"><i class="fas fa-clock-rotate-left"></i><span>Launch History</span></a></li>
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
    <h1>SSO launch history</h1>

    <?php
    $currentSystemName = 'All systems';
    foreach ($allSystems as $s) {
        if ($s['slug'] === $filterSystem) {
            $currentSystemName = $s['name'];
            break;
        }
    }
    ?>
    <form method="get" class="filters" id="filtersForm">
        <div class="field">
            <label for="systemComboDisplay">System</label>
            <input type="hidden" name="system" id="systemHidden" value="<?= htmlspecialchars($filterSystem) ?>">
            <div class="combobox" id="cbSystem">
                <div class="combobox-display" id="systemComboDisplay" tabindex="0">
                    <span id="systemComboLabel"><?= htmlspecialchars($currentSystemName) ?></span>
                    <span class="combobox-arrow">▾</span>
                </div>
                <div class="combobox-dropdown" id="systemComboDropdown">
                    <div class="combobox-search-wrap"><i class="fas fa-search"></i><input class="combobox-search" type="text" placeholder="Search systems…" autocomplete="off"></div>
                    <div class="combobox-list">
                        <div class="combobox-option <?= $filterSystem === '' ? 'selected-opt' : '' ?>" data-value="">All systems</div>
                        <?php foreach ($allSystems as $s): ?>
                            <div class="combobox-option <?= $filterSystem === $s['slug'] ? 'selected-opt' : '' ?>" data-value="<?= htmlspecialchars($s['slug']) ?>"><?= htmlspecialchars($s['name']) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="field">
            <label for="fromDisplay">From</label>
            <input type="hidden" name="from" id="fromHidden" value="<?= htmlspecialchars($filterFrom) ?>">
            <div class="rdt-display" id="fromDisplay" tabindex="0">
                <span class="rdt-text" id="fromText"><?= $filterFrom !== '' ? date('M j, Y', strtotime($filterFrom)) : 'Any date' ?></span>
                <i class="far fa-calendar-alt rdt-icon"></i>
            </div>
        </div>
        <div class="field">
            <label for="toDisplay">To</label>
            <input type="hidden" name="to" id="toHidden" value="<?= htmlspecialchars($filterTo) ?>">
            <div class="rdt-display" id="toDisplay" tabindex="0">
                <span class="rdt-text" id="toText"><?= $filterTo !== '' ? date('M j, Y', strtotime($filterTo)) : 'Any date' ?></span>
                <i class="far fa-calendar-alt rdt-icon"></i>
            </div>
        </div>
        <button type="submit"><i class="fas fa-filter"></i>&nbsp; Filter</button>
        <?php if ($filterSystem !== '' || $filterFrom !== '' || $filterTo !== ''): ?>
            <a href="launch_history.php" class="clear-link">Clear</a>
        <?php endif; ?>
    </form>

    <div class="history-table-wrap">
        <table class="history-table">
            <thead>
                <tr>
                    <th>System</th>
                    <th>Super admin</th>
                    <th>IP address</th>
                    <th>Launched at</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($launches as $launch): ?>
                <tr>
                    <td>
                        <div class="sys-cell">
                            <div class="sys-icon-chip <?= htmlspecialchars($launch['system_theme'] ?? 'blue') ?>"><i class="fas <?= htmlspecialchars($launch['system_icon'] ?? 'fa-server') ?>"></i></div>
                            <?= htmlspecialchars($launch['system_name'] ?? $launch['system_slug']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($launch['admin_name'] ?? 'Unknown') ?></td>
                    <td class="ip-cell"><?= htmlspecialchars($launch['ip_address'] ?? '—') ?></td>
                    <td class="time-cell"><?= date('M j, Y g:i:s A', strtotime($launch['launched_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($launches === []): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-secondary); padding:30px;">No launches recorded<?= $filterSystem || $filterFrom || $filterTo ? ' for this filter' : '' ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="history-card-list">
        <?php foreach ($launches as $launch): ?>
            <div class="hist-card">
                <div class="hist-card-header">
                    <div class="sys-icon-chip <?= htmlspecialchars($launch['system_theme'] ?? 'blue') ?>"><i class="fas <?= htmlspecialchars($launch['system_icon'] ?? 'fa-server') ?>"></i></div>
                    <span><?= htmlspecialchars($launch['system_name'] ?? $launch['system_slug']) ?></span>
                </div>
                <div class="hist-card-row"><span>Super admin</span><span><?= htmlspecialchars($launch['admin_name'] ?? 'Unknown') ?></span></div>
                <div class="hist-card-row"><span>IP address</span><span class="ip-cell" style="padding:0;"><?= htmlspecialchars($launch['ip_address'] ?? '—') ?></span></div>
                <div class="hist-card-row"><span>Launched at</span><span class="time-cell" style="padding:0;"><?= date('M j, Y g:i:s A', strtotime($launch['launched_at'])) ?></span></div>
            </div>
        <?php endforeach; ?>
        <?php if ($launches === []): ?>
            <div style="text-align:center; color:var(--text-secondary); padding:30px;">No launches recorded<?= $filterSystem || $filterFrom || $filterTo ? ' for this filter' : '' ?>.</div>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <span class="info">Showing <?= count($launches) ?> of <?= $total ?> launch<?= $total === 1 ? '' : 'es' ?></span>
        <div class="pages">
            <a href="<?= buildQuery(['page' => $page - 1]) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>"><i class="fas fa-chevron-left"></i></a>
            <span class="current"><?= $page ?> / <?= $totalPages ?></span>
            <a href="<?= buildQuery(['page' => $page + 1]) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</main>

<!-- Date picker popups (positioned via JS, appended to body-level stacking) -->
<div class="rdt-overlay" id="fromOverlay">
    <div class="rdt-header">
        <button type="button" class="rdt-nav" id="fromPrev">&#8249;</button>
        <span class="rdt-month-label" id="fromMonthLabel"></span>
        <button type="button" class="rdt-nav" id="fromNext">&#8250;</button>
    </div>
    <div class="rdt-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
    <div class="rdt-grid" id="fromGrid"></div>
    <div class="rdt-footer">
        <button type="button" class="rdt-clear" id="fromClear">Clear</button>
        <button type="button" class="rdt-done" id="fromDone">Done</button>
    </div>
</div>
<div class="rdt-overlay" id="toOverlay">
    <div class="rdt-header">
        <button type="button" class="rdt-nav" id="toPrev">&#8249;</button>
        <span class="rdt-month-label" id="toMonthLabel"></span>
        <button type="button" class="rdt-nav" id="toNext">&#8250;</button>
    </div>
    <div class="rdt-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
    <div class="rdt-grid" id="toGrid"></div>
    <div class="rdt-footer">
        <button type="button" class="rdt-clear" id="toClear">Clear</button>
        <button type="button" class="rdt-done" id="toDone">Done</button>
    </div>
</div>

<!-- Logout confirmation modal -->
<div class="modal-backdrop" id="logoutModal" style="position:fixed; inset:0; background:rgba(3,6,16,.55); backdrop-filter:blur(6px); display:none; align-items:center; justify-content:center; z-index:9999; padding:20px;">
    <div class="modal-card" style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px; padding:30px 26px 24px; max-width:340px; width:100%; box-shadow:0 25px 60px rgba(0,0,0,.4); backdrop-filter:blur(22px); text-align:center; animation: modalPop .25s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:60px; height:60px; border-radius:50%; margin:0 auto 16px; background:linear-gradient(135deg, rgba(239,68,68,.16), rgba(239,68,68,.08)); border:1.5px solid rgba(239,68,68,.28); display:flex; align-items:center; justify-content:center; color:#ef4444; font-size:1.3rem;"><i class="fas fa-arrow-right-from-bracket"></i></div>
        <h2 style="color:var(--text-primary); font-size:1.05rem; margin:0 0 8px;">Log out of your account?</h2>
        <p style="color:var(--text-secondary); font-size:.85rem; margin:0 0 22px; line-height:1.5;">Are you sure you want to log out? You'll need to sign in again to access any connected system.</p>
        <div style="display:flex; gap:10px;">
            <button type="button" id="cancelLogout" style="flex:1; padding:11px 0; border-radius:10px; border:1px solid var(--card-border); font-weight:600; font-size:.85rem; cursor:pointer; font-family:inherit; background:rgba(120,140,220,.14); color:var(--text-primary);">Cancel</button>
            <button type="button" id="confirmLogout" style="flex:1; padding:11px 0; border-radius:10px; border:none; font-weight:600; font-size:.85rem; cursor:pointer; font-family:inherit; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;">Log out</button>
        </div>
    </div>
</div>
<style>
.modal-backdrop.show { display: flex !important; }
@keyframes modalPop { from { transform: translateY(20px) scale(.94); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
</style>

<script>
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
// Client-side mirror of the 2-minute server-side session timeout.
// Disabled on localhost, where the server-side timeout is also disabled.
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

// Searchable dropdown (System filter) — CIMM-style combobox
(function () {
    var display = document.getElementById('systemComboDisplay');
    var label = document.getElementById('systemComboLabel');
    var hidden = document.getElementById('systemHidden');
    var dropdown = document.getElementById('systemComboDropdown');
    var search = dropdown.querySelector('.combobox-search');
    var list = dropdown.querySelector('.combobox-list');
    var options = Array.prototype.slice.call(list.querySelectorAll('.combobox-option'));

    function open() {
        display.classList.add('open');
        dropdown.classList.add('open');
        search.value = '';
        filter('');
        setTimeout(function () { search.focus(); }, 30);
    }
    function close() { display.classList.remove('open'); dropdown.classList.remove('open'); }
    function select(value, text) {
        hidden.value = value;
        label.textContent = text;
        options.forEach(function (o) { o.classList.toggle('selected-opt', o.dataset.value === value); });
        close();
    }
    function filter(q) {
        var ql = q.toLowerCase();
        var visible = 0;
        options.forEach(function (o) {
            var match = !ql || o.textContent.toLowerCase().indexOf(ql) !== -1;
            o.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        var noRes = list.querySelector('.combobox-no-results');
        if (!visible && !noRes) {
            var d = document.createElement('div');
            d.className = 'combobox-no-results';
            d.textContent = 'No results found';
            list.appendChild(d);
        } else if (visible && noRes) {
            noRes.remove();
        }
    }

    display.addEventListener('click', function (e) {
        e.stopPropagation();
        display.classList.contains('open') ? close() : open();
    });
    search.addEventListener('input', function () { filter(search.value); });
    list.addEventListener('mousedown', function (e) {
        var opt = e.target.closest('.combobox-option');
        if (!opt) return;
        e.preventDefault();
        select(opt.dataset.value, opt.textContent);
    });
    document.addEventListener('click', function (e) {
        if (!display.contains(e.target) && !dropdown.contains(e.target)) close();
    });
})();

// Custom date picker — CIMM-style popup calendar, two independent instances (From/To)
(function () {
    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function fmtISO(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    function isSameDay(a, b) { return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function makeDatePicker(cfg) {
        var display = document.getElementById(cfg.display);
        var textEl = document.getElementById(cfg.text);
        var hidden = document.getElementById(cfg.hidden);
        var overlay = document.getElementById(cfg.overlay);
        var monthLabel = document.getElementById(cfg.monthLabel);
        var grid = document.getElementById(cfg.grid);
        var prevBtn = document.getElementById(cfg.prev);
        var nextBtn = document.getElementById(cfg.next);
        var clearBtn = document.getElementById(cfg.clear);
        var doneBtn = document.getElementById(cfg.done);

        var today = new Date();
        var selectedDate = hidden.value ? new Date(hidden.value + 'T00:00:00') : null;
        var viewYear = selectedDate ? selectedDate.getFullYear() : today.getFullYear();
        var viewMonth = selectedDate ? selectedDate.getMonth() : today.getMonth();

        function render() {
            monthLabel.textContent = MONTHS[viewMonth] + ' ' + viewYear;
            grid.innerHTML = '';
            var firstDay = new Date(viewYear, viewMonth, 1).getDay();
            var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
            for (var i = 0; i < firstDay; i++) {
                var empty = document.createElement('span');
                empty.className = 'rdt-day empty';
                grid.appendChild(empty);
            }
            for (var d = 1; d <= daysInMonth; d++) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rdt-day';
                var thisDate = new Date(viewYear, viewMonth, d);
                if (isSameDay(thisDate, today)) btn.classList.add('today');
                if (isSameDay(thisDate, selectedDate)) btn.classList.add('selected');
                btn.textContent = d;
                (function (dayNum) {
                    btn.addEventListener('click', function () {
                        selectedDate = new Date(viewYear, viewMonth, dayNum);
                        hidden.value = fmtISO(selectedDate);
                        textEl.textContent = selectedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        render();
                    });
                })(d);
                grid.appendChild(btn);
            }
        }

        function position() {
            var rect = display.getBoundingClientRect();
            var ow = 264;
            var top = rect.bottom + 6;
            var left = rect.left;
            if (left + ow > window.innerWidth - 10) left = window.innerWidth - ow - 10;
            if (left < 10) left = 10;
            overlay.style.setProperty('--rdt-top', top + 'px');
            overlay.style.setProperty('--rdt-left', left + 'px');
        }

        function isOpen() { return overlay.classList.contains('open'); }
        function open() { render(); position(); overlay.classList.add('open'); }
        function close() { overlay.classList.remove('open'); }

        display.addEventListener('click', function (e) { e.stopPropagation(); isOpen() ? close() : open(); });
        prevBtn.addEventListener('click', function () { viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; } render(); });
        nextBtn.addEventListener('click', function () { viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; } render(); });
        clearBtn.addEventListener('click', function () {
            selectedDate = null;
            hidden.value = '';
            textEl.textContent = 'Any date';
            render();
            close();
        });
        doneBtn.addEventListener('click', close);

        document.addEventListener('click', function (e) {
            if (!display.contains(e.target) && !overlay.contains(e.target)) close();
        });
        window.addEventListener('scroll', function () { if (isOpen()) position(); }, true);
        window.addEventListener('resize', function () { if (isOpen()) close(); });
    }

    makeDatePicker({ display: 'fromDisplay', text: 'fromText', hidden: 'fromHidden', overlay: 'fromOverlay', monthLabel: 'fromMonthLabel', grid: 'fromGrid', prev: 'fromPrev', next: 'fromNext', clear: 'fromClear', done: 'fromDone' });
    makeDatePicker({ display: 'toDisplay', text: 'toText', hidden: 'toHidden', overlay: 'toOverlay', monthLabel: 'toMonthLabel', grid: 'toGrid', prev: 'toPrev', next: 'toNext', clear: 'toClear', done: 'toDone' });
})();
</script>
</body>
</html>