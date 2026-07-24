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
    header {
        position: sticky; top: 0; z-index: 200;
        background: var(--header-bg); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid var(--card-border);
        padding: 12px 32px; display: flex; align-items: center; justify-content: space-between;
        transition: background .3s, border-color .3s;
        flex-wrap: wrap; gap: 12px;
    }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand img { width: 36px; height: 36px; border-radius: 9px; }
    .brand strong { color: var(--text-primary); font-size: 1rem; display: block; }
    .brand span { color: var(--text-secondary); font-size: .74rem; }
    .header-clock {
        font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--text-secondary);
        background: rgba(120,140,220,.1); border: 1px solid var(--card-border); padding: 7px 12px; border-radius: 50px;
        display: flex; align-items: center; gap: 6px; white-space: nowrap;
    }
    .header-clock i { font-size: .72rem; opacity: .8; }
    .clock-date::after { content: ' · '; }
    .who { display: flex; align-items: center; gap: 14px; }
    .who .user-chip {
        display: flex; align-items: center; gap: 9px;
        background: rgba(120,140,220,.1); border: 1px solid var(--card-border); padding: 5px 14px 5px 6px; border-radius: 50px;
    }
    .who .user-avatar {
        width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg,#4f6ef7,#3f5adf);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700;
        flex-shrink: 0;
    }
    .who .name { color: var(--text-secondary); font-size: .8rem; }
    .who .name strong { color: var(--text-primary); }
    .who button.logout {
        color: #ff8fa3; background: none; font-size: .82rem; font-weight: 500; font-family: inherit;
        border: 1px solid rgba(255,143,163,.35); padding: 8px 16px; border-radius: 50px; transition: background .15s;
        cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    }
    .who button.logout:hover { background: rgba(255,143,163,.1); }
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
    @media (max-width: 768px) {
        header {
            position: sticky; top: 0; height: 54px; padding: 0 14px; gap: 8px;
            background: rgba(5,10,25,.94); border-bottom: 1px solid rgba(59,130,246,.2);
            box-shadow: 0 2px 16px rgba(0,0,0,.5); justify-content: flex-end;
        }
        .brand { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 0; }
        .brand img { width: 26px; height: 26px; }
        .brand strong, .brand span { display: none; }
        .header-clock { background: none; border: none; padding: 0; backdrop-filter: none; font-size: .72rem; font-weight: 700; color: rgba(255,255,255,.65); }
        .header-clock i { display: none; }
        .clock-date { display: none; }
        .who { gap: 6px; }
        .who .user-chip { display: none; }
        .who button.logout {
            color: #fca5b1; background: rgba(255,255,255,.06); border-color: rgba(255,143,163,.3);
            width: 32px; height: 32px; border-radius: 8px; padding: 0; justify-content: center;
        }
        .who button.logout:hover { background: rgba(255,80,100,.18); }
        .who button.logout span { display: none; }
        .theme-toggle {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
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

    main { max-width: 1100px; margin: 0 auto; padding: 34px 32px 60px; position: relative; z-index: 1; }

    .admin-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
    .admin-tab {
        display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; border-radius: 10px;
        background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-secondary);
        text-decoration: none; font-size: .84rem; font-weight: 500; transition: background .2s, color .2s;
        backdrop-filter: blur(14px);
    }
    .admin-tab:hover { background: rgba(79,110,247,.12); color: var(--text-primary); }
    .admin-tab.active { background: linear-gradient(135deg,#4f6ef7,#3f5adf); color: #fff; border-color: transparent; }

    h1 { font-size: 1.15rem; color: var(--text-primary); margin: 0 0 20px; }

    .filters {
        display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px;
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px;
        backdrop-filter: blur(14px);
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

    @media (max-width: 768px) {
        main { padding: 18px 14px 40px; }
        .filters { flex-direction: column; align-items: stretch; }
    }
</style>
</head>
<body>
<header>
    <div class="brand">
        <img src="../public/logocityhall.png" alt="InfraGovServices">
        <div>
            <strong>InfraGovServices</strong>
            <span>Super Admin · SSO Hub</span>
        </div>
    </div>
    <div class="who">
        <span class="header-clock"><i class="fas fa-clock"></i><span class="clock-date" id="clockDate"></span><span class="clock-time" id="clockTime"></span></span>
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="theme-track">
                <span class="theme-thumb"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></span>
            </span>
        </button>
        <div class="user-chip">
            <span class="user-avatar"><?= strtoupper(substr($_SESSION['super_admin_name'], 0, 1)) ?></span>
            <span class="name">Signed in as <strong><?= htmlspecialchars($_SESSION['super_admin_name']) ?></strong></span>
        </div>
        <button type="button" class="logout" id="openLogoutModal"><i class="fas fa-arrow-right-from-bracket"></i> <span>Log out</span></button>
    </div>
</header>
<main>
    <nav class="admin-tabs">
        <a href="dashboard.php" class="admin-tab"><i class="fas fa-gauge"></i> Dashboard</a>
        <a href="systems.php" class="admin-tab"><i class="fas fa-server"></i> Connected Systems</a>
        <a href="launch_history.php" class="admin-tab active"><i class="fas fa-clock-rotate-left"></i> Launch History</a>
    </nav>

    <h1>SSO launch history</h1>

    <form method="get" class="filters">
        <div class="field">
            <label for="filterSystem">System</label>
            <select name="system" id="filterSystem">
                <option value="">All systems</option>
                <?php foreach ($allSystems as $s): ?>
                    <option value="<?= htmlspecialchars($s['slug']) ?>" <?= $filterSystem === $s['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="filterFrom">From</label>
            <input type="date" name="from" id="filterFrom" value="<?= htmlspecialchars($filterFrom) ?>">
        </div>
        <div class="field">
            <label for="filterTo">To</label>
            <input type="date" name="to" id="filterTo" value="<?= htmlspecialchars($filterTo) ?>">
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

    <div class="pagination">
        <span class="info">Showing <?= count($launches) ?> of <?= $total ?> launch<?= $total === 1 ? '' : 'es' ?></span>
        <div class="pages">
            <a href="<?= buildQuery(['page' => $page - 1]) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>"><i class="fas fa-chevron-left"></i></a>
            <span class="current"><?= $page ?> / <?= $totalPages ?></span>
            <a href="<?= buildQuery(['page' => $page + 1]) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</main>

<!-- Logout confirmation modal -->
<div class="modal-backdrop" id="logoutModal" style="position:fixed; inset:0; background:rgba(3,6,16,.55); backdrop-filter:blur(6px); display:none; align-items:center; justify-content:center; z-index:9999; padding:20px;">
    <div class="modal-card" style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px; padding:30px 26px 24px; max-width:340px; width:100%; box-shadow:0 25px 60px rgba(0,0,0,.4); backdrop-filter:blur(22px); text-align:center;">
        <div style="width:60px; height:60px; border-radius:50%; margin:0 auto 16px; background:linear-gradient(135deg, rgba(239,68,68,.16), rgba(239,68,68,.08)); border:1.5px solid rgba(239,68,68,.28); display:flex; align-items:center; justify-content:center; color:#ef4444; font-size:1.3rem;"><i class="fas fa-arrow-right-from-bracket"></i></div>
        <h2 style="color:var(--text-primary); font-size:1.05rem; margin:0 0 8px;">Log out of your account?</h2>
        <p style="color:var(--text-secondary); font-size:.85rem; margin:0 0 22px; line-height:1.5;">Are you sure you want to log out? You'll need to sign in again to access any connected system.</p>
        <div style="display:flex; gap:10px;">
            <button type="button" id="cancelLogout" style="flex:1; padding:11px 0; border-radius:10px; border:1px solid var(--card-border); font-weight:600; font-size:.85rem; cursor:pointer; font-family:inherit; background:rgba(120,140,220,.14); color:var(--text-primary);">Cancel</button>
            <button type="button" id="confirmLogout" style="flex:1; padding:11px 0; border-radius:10px; border:none; font-weight:600; font-size:.85rem; cursor:pointer; font-family:inherit; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;">Log out</button>
        </div>
    </div>
</div>
<style>.modal-backdrop.show { display: flex !important; }</style>

<script>
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
