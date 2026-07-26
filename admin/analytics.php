<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

$requestedRange = (int) ($_GET['range'] ?? 30);
$days = in_array($requestedRange, [7, 30, 90], true) ? $requestedRange : 30;

$dailyStmt = mainLguDb()->prepare('SELECT DATE(launched_at) d, COUNT(*) c FROM sso_launch_log WHERE launched_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(launched_at)');
$dailyStmt->execute([$days - 1]);
$dailyRaw = array_column($dailyStmt->fetchAll(), 'c', 'd');

$daily = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $daily[] = ['date' => $date, 'count' => (int) ($dailyRaw[$date] ?? 0)];
}

$totalLaunches = array_sum(array_column($daily, 'count'));
$maxDailyCount = max(array_column($daily, 'count'));

$busiest = null;
foreach ($daily as $d) {
    if ($d['count'] > 0 && ($busiest === null || $d['count'] > $busiest['count'])) {
        $busiest = $d;
    }
}

$bySystemStmt = mainLguDb()->prepare("
    SELECT l.system_slug, COUNT(*) c, s.name, s.icon, s.theme_color
    FROM sso_launch_log l
    LEFT JOIN connected_systems s ON s.slug = l.system_slug
    WHERE l.launched_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY l.system_slug
    ORDER BY c DESC
");
$bySystemStmt->execute([$days - 1]);
$bySystem = $bySystemStmt->fetchAll();
$maxSystemCount = $bySystem !== [] ? $bySystem[0]['c'] : 0;
$mostActive = $bySystem[0] ?? null;

$rangeLabel = "last {$days} days";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — InfraGovServices</title>
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
        --tooltip-bg: #12193a;
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
        --tooltip-bg: #0a0f24;
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
    .range-tabs { display: flex; gap: 6px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 10px; padding: 4px; }
    .range-tabs a {
        padding: 7px 14px; border-radius: 7px; font-size: .8rem; font-weight: 600; text-decoration: none;
        color: var(--text-secondary); transition: background .15s, color .15s;
    }
    .range-tabs a:hover { background: rgba(79,110,247,.1); }
    .range-tabs a.active { background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; }

    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-tile {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;
        padding: 18px 20px; backdrop-filter: blur(14px); display: flex; align-items: center; gap: 14px;
    }
    .stat-tile .stat-icon {
        width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; color: #fff; flex-shrink: 0; box-shadow: 0 6px 16px rgba(0,0,0,.15);
        background: linear-gradient(135deg,#4f6ef7,#3f5adf);
    }
    .stat-tile .label { color: var(--text-secondary); font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .stat-tile .value { font-family: 'DM Mono', monospace; font-size: 1.4rem; color: var(--text-primary); font-weight: 500; }
    .stat-tile .value .unit { font-size: .8rem; color: var(--text-secondary); font-family: 'Poppins', sans-serif; margin-left: 4px; }

    .chart-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        padding: 22px 24px; backdrop-filter: blur(14px); margin-bottom: 20px;
    }
    .chart-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
    .chart-card-head h2 { font-size: .92rem; color: var(--text-primary); margin: 0; }
    .chart-toggle-btn {
        border: 1px solid var(--card-border); background: none; color: var(--text-secondary); font-size: .74rem;
        font-weight: 600; padding: 6px 12px; border-radius: 7px; cursor: pointer; font-family: inherit;
    }
    .chart-toggle-btn:hover { background: rgba(120,140,220,.1); color: var(--text-primary); }

    /* Daily trend bar chart */
    .trend-chart { display: flex; align-items: flex-end; gap: 3px; height: 160px; position: relative; }
    .trend-bar-col {
        flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end;
        align-items: center; position: relative; cursor: pointer;
    }
    .trend-bar {
        width: 100%; max-width: 22px; background: linear-gradient(180deg,#4f6ef7,#3f5adf);
        border-radius: 4px 4px 0 0; transition: opacity .12s; min-height: 2px;
    }
    .trend-bar-col:hover .trend-bar, .trend-bar-col:focus .trend-bar { opacity: .72; }
    .trend-bar-label {
        position: absolute; bottom: -20px; font-size: .64rem; color: var(--text-secondary);
        white-space: nowrap; left: 50%; transform: translateX(-50%);
    }
    .trend-tooltip {
        position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(-8px);
        background: var(--tooltip-bg); color: #fff; padding: 6px 10px; border-radius: 7px; font-size: .72rem;
        white-space: nowrap; pointer-events: none; opacity: 0; transition: opacity .12s; z-index: 10;
        box-shadow: 0 6px 18px rgba(0,0,0,.3);
    }
    .trend-bar-col:hover .trend-tooltip, .trend-bar-col:focus .trend-tooltip { opacity: 1; }
    .trend-tooltip strong { font-family: 'DM Mono', monospace; }

    .trend-table { display: none; width: 100%; border-collapse: collapse; font-size: .82rem; }
    .trend-table.show { display: table; }
    .trend-chart.hide { display: none; }
    .trend-table th { text-align: left; color: var(--text-secondary); font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; padding: 8px 10px; border-bottom: 1px solid var(--card-border); }
    .trend-table td { padding: 7px 10px; border-bottom: 1px solid var(--card-border); color: var(--text-primary); font-family: 'DM Mono', monospace; }
    .trend-table tbody tr:last-child td { border-bottom: none; }

    /* Ranked per-system bar list */
    .rank-list { display: flex; flex-direction: column; gap: 12px; }
    .rank-row { display: flex; align-items: center; gap: 12px; }
    .rank-label { display: flex; align-items: center; gap: 8px; width: 190px; flex-shrink: 0; overflow: hidden; }
    .rank-label span:last-child { font-size: .82rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sys-icon-chip {
        width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .7rem; flex-shrink: 0;
    }
    .sys-icon-chip.blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .sys-icon-chip.orange { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .sys-icon-chip.purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .sys-icon-chip.rose   { background: linear-gradient(135deg,#fb7185,#c8185a); }
    .sys-icon-chip.teal   { background: linear-gradient(135deg,#14b8a6,#0f766e); }
    .sys-icon-chip.amber  { background: linear-gradient(135deg,#d4920a,#a05a00); }
    .rank-track { flex: 1; height: 20px; background: rgba(120,140,220,.1); border-radius: 6px; overflow: hidden; }
    .rank-fill { height: 100%; border-radius: 6px; min-width: 6px; }
    .rank-fill.blue   { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
    .rank-fill.orange { background: linear-gradient(90deg,#f59e0b,#d97706); }
    .rank-fill.purple { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
    .rank-fill.rose   { background: linear-gradient(90deg,#fb7185,#c8185a); }
    .rank-fill.teal   { background: linear-gradient(90deg,#14b8a6,#0f766e); }
    .rank-fill.amber  { background: linear-gradient(90deg,#d4920a,#a05a00); }
    .rank-count { font-family: 'DM Mono', monospace; font-size: .82rem; color: var(--text-primary); width: 34px; text-align: right; flex-shrink: 0; }

    .empty-note { text-align: center; color: var(--text-secondary); padding: 30px; font-size: .85rem; }

    @media (max-width: 768px) {
        main { padding: 18px 14px 40px; }
        .rank-label { width: 130px; }
        .trend-chart { height: 120px; }
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
        <li><a href="launch_history.php" class="sidebar-link"><i class="fas fa-clock-rotate-left"></i><span>Launch History</span></a></li>
        <li><a href="analytics.php" class="sidebar-link active"><i class="fas fa-chart-line"></i><span>Analytics</span></a></li>
        <li><a href="audit_log.php" class="sidebar-link"><i class="fas fa-list-check"></i><span>Audit Log</span></a></li>
        <li><a href="team.php" class="sidebar-link"><i class="fas fa-users"></i><span>Team</span></a></li>
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
        <h1>Analytics</h1>
        <div class="range-tabs">
            <a href="?range=7" class="<?= $days === 7 ? 'active' : '' ?>">7d</a>
            <a href="?range=30" class="<?= $days === 30 ? 'active' : '' ?>">30d</a>
            <a href="?range=90" class="<?= $days === 90 ? 'active' : '' ?>">90d</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-tile">
            <div class="stat-icon"><i class="fas fa-arrow-right-to-bracket"></i></div>
            <div>
                <div class="label">Total launches</div>
                <div class="value"><?= number_format($totalLaunches) ?> <span class="unit"><?= htmlspecialchars($rangeLabel) ?></span></div>
            </div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div>
                <div class="label">Most active system</div>
                <div class="value" style="font-size:1.1rem;"><?= $mostActive ? htmlspecialchars($mostActive['name'] ?? $mostActive['system_slug']) : '—' ?> <?= $mostActive ? '<span class="unit">' . $mostActive['c'] . ' launches</span>' : '' ?></div>
            </div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div>
                <div class="label">Busiest day</div>
                <div class="value" style="font-size:1.1rem;"><?= $busiest ? date('M j', strtotime($busiest['date'])) : '—' ?> <?= $busiest ? '<span class="unit">' . $busiest['count'] . ' launches</span>' : '' ?></div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-card-head">
            <h2>Launches per day — <?= htmlspecialchars($rangeLabel) ?></h2>
            <button type="button" class="chart-toggle-btn" id="trendToggleBtn"><i class="fas fa-table"></i> Table view</button>
        </div>
        <?php if ($totalLaunches === 0): ?>
            <div class="empty-note">No launches recorded in this range.</div>
        <?php else: ?>
            <div class="trend-chart" id="trendChart" style="margin-bottom: 26px;">
                <?php $labelEvery = max(1, (int) ceil($days / 6)); ?>
                <?php foreach ($daily as $i => $d): $pct = $maxDailyCount > 0 ? round($d['count'] / $maxDailyCount * 100) : 0; ?>
                    <div class="trend-bar-col" tabindex="0">
                        <div class="trend-tooltip"><strong><?= $d['count'] ?></strong> on <?= date('M j, Y', strtotime($d['date'])) ?></div>
                        <div class="trend-bar" style="height: <?= max($pct, 2) ?>%"></div>
                        <?php if ($i === 0 || $i === count($daily) - 1 || $i % $labelEvery === 0): ?>
                            <span class="trend-bar-label"><?= date('M j', strtotime($d['date'])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <table class="trend-table" id="trendTable">
                <thead><tr><th>Date</th><th>Launches</th></tr></thead>
                <tbody>
                    <?php foreach (array_reverse($daily) as $d): ?>
                        <tr><td><?= date('M j, Y', strtotime($d['date'])) ?></td><td><?= $d['count'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <div class="chart-card-head">
            <h2>Launches by system — <?= htmlspecialchars($rangeLabel) ?></h2>
        </div>
        <?php if ($bySystem === []): ?>
            <div class="empty-note">No launches recorded in this range.</div>
        <?php else: ?>
            <div class="rank-list">
                <?php foreach ($bySystem as $row): $pct = $maxSystemCount > 0 ? round($row['c'] / $maxSystemCount * 100) : 0; ?>
                    <div class="rank-row">
                        <div class="rank-label">
                            <span class="sys-icon-chip <?= htmlspecialchars($row['theme_color'] ?? 'blue') ?>"><i class="fas <?= htmlspecialchars($row['icon'] ?? 'fa-server') ?>"></i></span>
                            <span><?= htmlspecialchars($row['name'] ?? $row['system_slug']) ?></span>
                        </div>
                        <div class="rank-track"><div class="rank-fill <?= htmlspecialchars($row['theme_color'] ?? 'blue') ?>" style="width: <?= max($pct, 3) ?>%"></div></div>
                        <span class="rank-count"><?= $row['c'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

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

// Trend chart <-> table toggle
(function () {
    var btn = document.getElementById('trendToggleBtn');
    var chart = document.getElementById('trendChart');
    var table = document.getElementById('trendTable');
    if (!btn || !chart || !table) return;
    var showingTable = false;
    btn.addEventListener('click', function () {
        showingTable = !showingTable;
        chart.classList.toggle('hide', showingTable);
        table.classList.toggle('show', showingTable);
        btn.innerHTML = showingTable
            ? '<i class="fas fa-chart-column"></i> Chart view'
            : '<i class="fas fa-table"></i> Table view';
    });
})();
</script>
</body>
</html>
