<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY id')->fetchAll();

$activeCount = 0;
foreach ($systems as $s) {
    if ($s['is_active']) {
        $activeCount++;
    }
}

$launchesToday = mainLguDb()->query(
    "SELECT COUNT(*) FROM sso_launch_log WHERE DATE(launched_at) = CURDATE()"
)->fetchColumn();

$lastLoginStmt = mainLguDb()->prepare('SELECT last_login FROM super_admins WHERE id = ?');
$lastLoginStmt->execute([$_SESSION['super_admin_id']]);
$lastLogin = $lastLoginStmt->fetchColumn();

// Mirrors the "Explore Departments" card styling/colors on the public
// citizendash.php page (.db-svc3-*) so the admin side feels like the same
// product instead of a bolted-on tool.
$cardMeta = [
    'ipms' => ['theme' => 'blue', 'icon' => 'fa-hard-hat', 'tag' => 'IPMS', 'num' => '01'],
    'roadmon' => ['theme' => 'orange', 'icon' => 'fa-road', 'tag' => 'RGMAP', 'num' => '02'],
    'cprf' => ['theme' => 'purple', 'icon' => 'fa-calendar-check', 'tag' => 'CPRF', 'num' => '03'],
    'cimm' => ['theme' => 'rose', 'icon' => 'fa-tools', 'tag' => 'CIMM', 'num' => '04'],
    'energy' => ['theme' => 'teal', 'icon' => 'fa-leaf', 'tag' => 'ECM', 'num' => '05'],
];
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
    header {
        position: sticky; top: 0; z-index: 20;
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
        display: flex; align-items: center; gap: 6px;
    }
    .header-clock i { font-size: .72rem; opacity: .8; }

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
        color: #ff8fa3; background: none; text-decoration: none; font-size: .82rem; font-weight: 500; font-family: inherit;
        border: 1px solid rgba(255,143,163,.35); padding: 8px 16px; border-radius: 50px; transition: background .15s;
        cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    }
    .who button.logout:hover { background: rgba(255,143,163,.1); }

    /* Theme toggle switch — same visual language as the public site */
    .theme-toggle { background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; }
    .theme-track {
        width: 46px; height: 25px; background: rgba(120,140,220,.16); border: 1px solid var(--card-border);
        border-radius: 50px; position: relative; transition: background .3s, border-color .3s;
    }
    .theme-track.is-dark { background: rgba(59,130,246,.25); border-color: rgba(59,130,246,.5); }
    .theme-thumb {
        position: absolute; top: 2px; left: 2px; width: 19px; height: 19px; background: #fff; border-radius: 50%;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1); display: flex; align-items: center; justify-content: center;
        font-size: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.3);
    }
    .theme-track.is-dark .theme-thumb { transform: translateX(21px); }

    main { max-width: 1300px; margin: 0 auto; padding: 34px 32px 60px; position: relative; z-index: 1; }

    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 36px; }
    .stat-tile {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px;
        padding: 18px 20px; backdrop-filter: blur(14px); transition: background .3s, border-color .3s, transform .2s;
        display: flex; align-items: center; gap: 14px;
    }
    .stat-tile:hover { transform: translateY(-2px); }
    .stat-icon {
        width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; color: #fff; flex-shrink: 0; box-shadow: 0 6px 16px rgba(0,0,0,.15);
    }
    .stat-icon.blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .stat-icon.green  { background: linear-gradient(135deg,#10b981,#047857); }
    .stat-icon.amber  { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .stat-icon.purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .stat-tile .label { color: var(--text-secondary); font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .stat-tile .value { font-family: 'DM Mono', monospace; font-size: 1.4rem; color: var(--text-primary); font-weight: 500; }

    h2.section-title { color: var(--text-primary); font-size: 1rem; font-weight: 600; margin: 0 0 18px; letter-spacing: .01em; }

    /* ── Department cards — lifted from public/styles.css .db-svc3-* ── */
    .svc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    @media (max-width: 1024px) { .svc-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .svc-grid { grid-template-columns: 1fr; } }

    .svc-card {
        position: relative; border-radius: 24px; padding: 28px 26px 26px; min-height: 280px;
        display: flex; flex-direction: column; overflow: hidden; border: 1px solid rgba(255,255,255,.07);
        transition: transform .4s cubic-bezier(.34,1.56,.64,1), box-shadow .4s ease, border-color .3s ease;
        text-decoration: none;
    }
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
    .modal-actions .btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(239,68,68,.45); }

    /* ── Mobile ─────────────────────────────────────────────── */
    @media (max-width: 768px) {
        header { padding: 12px 18px; }
        .brand span { display: none; }
        .header-clock { font-size: .68rem; padding: 6px 10px; }
        .who { gap: 8px; }
        .who .user-chip .name { display: none; }
        .who button.logout span { display: none; }
        .who button.logout { padding: 9px 12px; }
        main { padding: 22px 16px 40px; }
        .stats-row { grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 26px; }
        .stat-tile { padding: 14px 16px; }
        .stat-tile .value { font-size: 1.15rem; }
        .svc-grid { gap: 14px; }
    }
    @media (max-width: 420px) {
        .stats-row { grid-template-columns: 1fr 1fr; }
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
        <span class="header-clock" id="liveClock"><i class="fas fa-clock"></i> <span id="liveClockText"></span></span>
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="theme-track" id="themeTrack">
                <span class="theme-thumb"><i class="fas fa-sun" id="themeIcon" style="color:#101a3a;"></i></span>
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
    <div class="stats-row">
        <div class="stat-tile">
            <div class="stat-icon blue"><i class="fas fa-diagram-project"></i></div>
            <div>
                <div class="label">Connected systems</div>
                <div class="value"><?= count($systems) ?></div>
            </div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="label">Active</div>
                <div class="value"><?= $activeCount ?></div>
            </div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon amber"><i class="fas fa-rocket"></i></div>
            <div>
                <div class="label">Launches today</div>
                <div class="value"><?= (int) $launchesToday ?></div>
            </div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon purple"><i class="fas fa-clock-rotate-left"></i></div>
            <div>
                <div class="label">Last login</div>
                <div class="value" style="font-size:1rem;"><?= $lastLogin ? date('M j, g:i A', strtotime($lastLogin)) : '—' ?></div>
            </div>
        </div>
    </div>

    <h2 class="section-title">Connected systems</h2>
    <div class="svc-grid">
    <?php foreach ($systems as $system):
        $meta = $cardMeta[$system['slug']] ?? ['theme' => 'blue', 'icon' => 'fa-server', 'tag' => strtoupper($system['slug']), 'num' => '00'];
        $host = parse_url($system['base_url'], PHP_URL_HOST) ?: $system['base_url'];
    ?>
        <a class="svc-card svc-<?= $meta['theme'] ?>" href="launch.php?system=<?= urlencode($system['slug']) ?>">
            <div class="svc-bg-num"><?= $meta['num'] ?></div>
            <div class="svc-top">
                <div class="svc-chip"><i class="fas <?= $meta['icon'] ?>"></i></div>
                <span class="svc-tag"><?= htmlspecialchars($meta['tag']) ?></span>
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
// Theme toggle — shares the same localStorage keys as the public site
// (public/citizendash.php) so a preference set on either side carries over.
(function () {
    var html = document.documentElement;
    var track = document.getElementById('themeTrack');
    var icon = document.getElementById('themeIcon');
    var btn = document.getElementById('themeToggle');

    function apply(isDark) {
        if (isDark) { html.setAttribute('data-theme', 'dark'); } else { html.removeAttribute('data-theme'); }
        track.classList.toggle('is-dark', isDark);
        icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
        icon.style.color = isDark ? '#fff' : '#101a3a';
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

// Live clock
(function () {
    var el = document.getElementById('liveClockText');
    function tick() {
        var now = new Date();
        var datePart = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        var timePart = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
        el.textContent = datePart + ' · ' + timePart;
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
