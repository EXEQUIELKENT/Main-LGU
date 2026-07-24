<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_stats.php';
require_once __DIR__ . '/../includes/system_display.php';
require_super_admin();

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY id')->fetchAll();
$systemStats = fetchAllSystemStats($systems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard — InfraGovServices</title>
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

    /* ── Top utility bar (clock + theme toggle), sits beside the sidebar ── */
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

    /* Theme toggle switch — same visual language as the public site */
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

    /* ── Mobile: sidebar becomes an off-canvas drawer, topbar/content full-width ── */
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

    main { max-width: 1300px; margin: 0 auto; padding: 34px 32px 60px; position: relative; z-index: 1; }

    @keyframes dashCardIn {
        from { opacity: 0; transform: translateY(18px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 36px; }
    .stat-tile {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        padding: 18px 20px; backdrop-filter: blur(14px); transition: background .3s, border-color .3s, transform .2s;
        display: flex; align-items: center; gap: 14px;
        animation: dashCardIn .5s cubic-bezier(.34,1.56,.64,1) backwards;
    }
    .stat-tile:nth-child(1) { animation-delay: .04s; }
    .stat-tile:nth-child(2) { animation-delay: .09s; }
    .stat-tile:nth-child(3) { animation-delay: .14s; }
    .stat-tile:nth-child(4) { animation-delay: .19s; }
    .stat-tile:hover { transform: translateY(-2px); }
    .stat-icon {
        width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; color: #fff; flex-shrink: 0; box-shadow: 0 6px 16px rgba(0,0,0,.15);
    }
    .stat-icon.blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .stat-icon.green  { background: linear-gradient(135deg,#10b981,#047857); }
    .stat-icon.amber  { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .stat-icon.orange { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .stat-icon.purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .stat-icon.rose   { background: linear-gradient(135deg,#fb7185,#c8185a); }
    .stat-icon.teal   { background: linear-gradient(135deg,#14b8a6,#0f766e); }
    .stat-tile .stat-fallback { color: var(--text-secondary); font-size: .95rem; font-weight: 500; }
    .stat-tile .label { color: var(--text-secondary); font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .stat-tile .value { font-family: 'DM Mono', monospace; font-size: 1.4rem; color: var(--text-primary); font-weight: 500; }

    /* ── Department cards — lifted from public/styles.css .db-svc3-* ── */
    .svc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    @media (max-width: 1024px) { .svc-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .svc-grid { grid-template-columns: 1fr; } }

    .svc-card {
        position: relative; border-radius: 24px; padding: 28px 26px 26px; min-height: 280px;
        display: flex; flex-direction: column; overflow: hidden; border: 1px solid rgba(255,255,255,.07);
        transition: transform .4s cubic-bezier(.34,1.56,.64,1), box-shadow .4s ease, border-color .3s ease;
        text-decoration: none;
        animation: dashCardIn .55s cubic-bezier(.34,1.56,.64,1) backwards;
    }
    .svc-card:nth-child(1) { animation-delay: .1s; }
    .svc-card:nth-child(2) { animation-delay: .16s; }
    .svc-card:nth-child(3) { animation-delay: .22s; }
    .svc-card:nth-child(4) { animation-delay: .28s; }
    .svc-card:nth-child(5) { animation-delay: .34s; }
    .svc-blue   { background: linear-gradient(145deg, #0d1f52 0%, #1a3a8a 55%, #1e56c8 100%); }
    .svc-orange { background: linear-gradient(145deg, #3d1400 0%, #8b3000 55%, #c84b10 100%); }
    .svc-purple { background: linear-gradient(145deg, #1e0b4a 0%, #4c1f8f 55%, #7c3fd4 100%); }
    .svc-rose   { background: linear-gradient(145deg, #3a0020 0%, #8b0045 55%, #c8185a 100%); }
    .svc-teal   { background: linear-gradient(145deg, #003030 0%, #0a5f5f 55%, #0d9e9e 100%); }

    .svc-blue:hover   { transform: translateY(-8px) scale(1.015); box-shadow: 0 24px 60px rgba(30,86,200,.5); border-color: rgba(59,130,246,.5); }
    .svc-orange:hover { transform: translateY(-8px) scale(1.015); box-shadow: 0 24px 60px rgba(200,75,16,.5); border-color: rgba(251,146,60,.5); }
    .svc-purple:hover { transform: translateY(-8px) scale(1.015); box-shadow: 0 24px 60px rgba(124,63,212,.5); border-color: rgba(167,139,250,.5); }
    .svc-rose:hover   { transform: translateY(-8px) scale(1.015); box-shadow: 0 24px 60px rgba(200,24,90,.5); border-color: rgba(251,113,133,.5); }
    .svc-teal:hover   { transform: translateY(-8px) scale(1.015); box-shadow: 0 24px 60px rgba(13,158,158,.5); border-color: rgba(45,212,191,.5); }

    .svc-bg-num {
        position: absolute; bottom: -12px; right: 14px; font-size: 6.5rem; font-weight: 900;
        color: rgba(255,255,255,.06); line-height: 1; letter-spacing: -3px; pointer-events: none;
        transition: transform .4s ease, color .3s;
    }
    .svc-card:hover .svc-bg-num { transform: translateY(-8px); color: rgba(255,255,255,.1); }

    .svc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .svc-chip {
        width: 44px; height: 44px; border-radius: 13px; background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.22); display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; color: #fff; backdrop-filter: blur(8px); transition: transform .35s cubic-bezier(.34,1.56,.64,1);
        flex-shrink: 0;
    }
    .svc-card:hover .svc-chip { transform: scale(1.12) rotate(-6deg); }
    .svc-tag {
        display: inline-flex; padding: 4px 12px; border-radius: 50px; font-size: .65rem; font-weight: 900;
        text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.85); backdrop-filter: blur(6px);
    }
    .svc-status {
        position: absolute; top: 20px; left: 50%; transform: translateX(-8px);
    }
    .svc-body { flex: 1; }
    .svc-title { font-size: 1.02rem; font-weight: 800; color: #fff; margin: 0 0 10px; line-height: 1.3; text-shadow: 0 1px 6px rgba(0,0,0,.3); }
    .svc-desc { font-size: .8rem; color: rgba(255,255,255,.62); line-height: 1.6; margin: 0 0 18px; }
    .svc-url { font-size: .68rem; color: rgba(255,255,255,.4); margin: -10px 0 14px; word-break: break-all; }
    .svc-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 50px;
        background: rgba(255,255,255,.15); border: 1.5px solid rgba(255,255,255,.35); color: #fff;
        font-size: .78rem; font-weight: 700; text-decoration: none; text-transform: uppercase; letter-spacing: .8px;
        width: fit-content; margin-top: auto; transition: background .2s, box-shadow .25s;
    }
    .svc-btn:hover { background: rgba(255,255,255,.28); box-shadow: 0 0 20px rgba(255,255,255,.2); }
    .svc-btn i { font-size: .72rem; transition: transform .2s; }
    .svc-btn:hover i { transform: translateX(4px); }

    .badge {
        font-size: .65rem; padding: 3px 10px; border-radius: 999px; font-weight: 700; letter-spacing: .02em;
    }
    .badge.active { background: rgba(79,201,122,.18); color: #d1fae0; border: 1px solid rgba(79,201,122,.4); }
    .badge.inactive { background: rgba(215,63,82,.18); color: #ffd9de; border: 1px solid rgba(215,63,82,.4); }

    /* ── Logout confirmation modal ─────────────────────────── */
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(3,6,16,.55); backdrop-filter: blur(6px);
        display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px;
    }
    .modal-backdrop.show { display: flex; }
    .modal-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px;
        padding: 30px 26px 24px; max-width: 340px; width: 100%; box-shadow: 0 25px 60px rgba(0,0,0,.4);
        backdrop-filter: blur(22px); text-align: center; animation: modalPop .25s cubic-bezier(.34,1.56,.64,1);
    }
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

    /* ── Mobile: content sizing ────────────────────────────── */
    @media (max-width: 768px) {
        main { padding: 18px 14px 40px; }
        .stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 22px; }
        .stat-tile { padding: 12px 14px; gap: 10px; }
        .stat-icon { width: 34px; height: 34px; font-size: .9rem; }
        .stat-tile .value { font-size: 1.05rem; }
        .svc-grid { gap: 14px; }
    }
    @media (max-width: 420px) {
        .stats-row { grid-template-columns: 1fr 1fr; }
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
        <li><a href="dashboard.php" class="sidebar-link active"><i class="fas fa-gauge"></i><span>Dashboard</span></a></li>
        <li><a href="systems.php" class="sidebar-link"><i class="fas fa-server"></i><span>Connected Systems</span></a></li>
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
    <div class="stats-row">
    <?php foreach ($systems as $system):
        $stat = $systemStats[$system['slug']] ?? null;
    ?>
        <div class="stat-tile">
            <div class="stat-icon <?= htmlspecialchars($system['theme_color']) ?>"><i class="fas <?= htmlspecialchars($system['icon']) ?>"></i></div>
            <div>
                <div class="label"><?= htmlspecialchars($stat['label'] ?? $system['name']) ?></div>
                <?php if ($stat !== null): ?>
                    <div class="value"><?= $stat['count'] ?></div>
                <?php else: ?>
                    <div class="stat-fallback">—</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="svc-grid">
    <?php foreach ($systems as $i => $system):
        $host = parse_url($system['base_url'], PHP_URL_HOST) ?: $system['base_url'];
        $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $tag = $system['short_tag'] !== '' ? $system['short_tag'] : strtoupper($system['slug']);
    ?>
        <a class="svc-card svc-<?= htmlspecialchars($system['theme_color']) ?> launch-trigger" href="launch.php?system=<?= urlencode($system['slug']) ?>" data-system-name="<?= htmlspecialchars($system['name']) ?>">
            <div class="svc-bg-num"><?= $num ?></div>
            <div class="svc-top">
                <div class="svc-chip"><i class="fas <?= htmlspecialchars($system['icon']) ?>"></i></div>
                <span class="svc-tag"><?= htmlspecialchars($tag) ?></span>
            </div>
            <div class="svc-body">
                <h3 class="svc-title"><?= htmlspecialchars($system['name']) ?></h3>
                <p class="svc-desc">
                    <span class="badge <?= $system['is_active'] ? 'active' : 'inactive' ?>"><?= $system['is_active'] ? '● Active' : '● Inactive' ?></span>
                </p>
                <p class="svc-url"><?= htmlspecialchars($host) ?></p>
            </div>
            <span class="svc-btn">Open Admin <i class="fas fa-arrow-right"></i></span>
        </a>
    <?php endforeach; ?>
    </div>
</main>

<!-- Launch confirmation modal -->
<div class="modal-backdrop" id="launchModal">
    <div class="modal-card">
        <div class="modal-icon-wrap modal-icon-wrap--info"><i class="fas fa-arrow-up-right-from-square"></i></div>
        <h2>Open <span id="launchSystemName">this system</span>'s admin?</h2>
        <p class="modal-sub">You'll be signed into its admin panel using your Super Admin session. Continue?</p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" id="cancelLaunch">Cancel</button>
            <button type="button" class="btn-confirm btn-confirm--info" id="confirmLaunch">Open Admin</button>
        </div>
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

// Theme toggle — shares the same localStorage keys as the public site
// (public/citizendash.php) so a preference set on either side carries over.
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

    btn.addEventListener('click', function () {
        apply(!html.hasAttribute('data-theme'));
    });
})();

// Live clock — date hidden on mobile via CSS, only the time shows there
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

// Launch confirmation modal — every "Open Admin" card asks before handing
// off into that system's admin panel via SSO.
(function () {
    var modal = document.getElementById('launchModal');
    var nameEl = document.getElementById('launchSystemName');
    var confirmBtn = document.getElementById('confirmLaunch');
    var pendingUrl = null;

    document.querySelectorAll('.launch-trigger').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            pendingUrl = card.getAttribute('href');
            nameEl.textContent = card.dataset.systemName || 'this system';
            modal.classList.add('show');
        });
    });

    document.getElementById('cancelLaunch').addEventListener('click', function () {
        modal.classList.remove('show');
        pendingUrl = null;
    });
    confirmBtn.addEventListener('click', function () {
        if (pendingUrl) window.location.href = pendingUrl;
    });
})();

// Client-side mirror of the 2-minute server-side session timeout
// (includes/auth.php). Purely a UX nicety — the server enforces the real
// limit on the next request regardless of whether this fires.
(function () {
    var TIMEOUT_MS = 120 * 1000;
    var timer = null;

    function resetTimer() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            window.location.href = 'login.php?timeout=1';
        }, TIMEOUT_MS);
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, resetTimer, { passive: true });
    });
    resetTimer();
})();
</script>
</body>
</html>