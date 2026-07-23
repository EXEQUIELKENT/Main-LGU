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
        --bg-scrim: linear-gradient(160deg, rgba(238,241,251,.93) 0%, rgba(245,247,253,.90) 55%, rgba(255,255,255,.88) 100%);
        --card-bg: rgba(255,255,255,.80);
        --card-border: rgba(80,100,180,.16);
        --text-primary: #101a3a;
        --text-secondary: #5b6690;
        --header-bg: rgba(255,255,255,.80);
        --input-border: rgba(80,100,180,.22);
    }
    [data-theme="dark"] {
        --bg-scrim: linear-gradient(160deg, rgba(5,10,25,.90) 0%, rgba(10,22,40,.87) 55%, rgba(13,31,60,.85) 100%);
        --card-bg: rgba(15,22,48,.72);
        --card-border: rgba(120,140,220,.16);
        --text-primary: #fff;
        --text-secondary: #8b95c0;
        --header-bg: rgba(8,13,32,.78);
        --input-border: rgba(120,140,220,.22);
    }
    * { box-sizing: border-box; }
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
        padding: 14px 32px; display: flex; align-items: center; justify-content: space-between;
        transition: background .3s, border-color .3s;
    }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand img { width: 36px; height: 36px; border-radius: 9px; }
    .brand strong { color: var(--text-primary); font-size: 1rem; display: block; }
    .brand span { color: var(--text-secondary); font-size: .74rem; }

    .header-clock { font-family: 'DM Mono', monospace; font-size: .82rem; color: var(--text-secondary); }

    .who { display: flex; align-items: center; gap: 18px; }
    .who .name { color: var(--text-secondary); font-size: .85rem; }
    .who a.logout {
        color: #ff8fa3; text-decoration: none; font-size: .82rem; font-weight: 500;
        border: 1px solid rgba(255,143,163,.35); padding: 7px 14px; border-radius: 8px; transition: background .15s;
    }
    .who a.logout:hover { background: rgba(255,143,163,.1); }

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

    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 36px; }
    .stat-tile {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;
        padding: 18px 20px; backdrop-filter: blur(14px); transition: background .3s, border-color .3s;
    }
    .stat-tile .label { color: var(--text-secondary); font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .stat-tile .value { font-family: 'DM Mono', monospace; font-size: 1.6rem; color: var(--text-primary); font-weight: 500; }

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
        <span class="header-clock" id="liveClock"></span>
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="theme-track" id="themeTrack">
                <span class="theme-thumb"><i class="fas fa-sun" id="themeIcon" style="color:#101a3a;"></i></span>
            </span>
        </button>
        <span class="name">Signed in as <strong style="color:var(--text-primary);"><?= htmlspecialchars($_SESSION['super_admin_name']) ?></strong></span>
        <a class="logout" href="logout.php"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
    </div>
</header>
<main>
    <div class="stats-row">
        <div class="stat-tile">
            <div class="label">Connected systems</div>
            <div class="value"><?= count($systems) ?></div>
        </div>
        <div class="stat-tile">
            <div class="label">Active</div>
            <div class="value"><?= $activeCount ?></div>
        </div>
        <div class="stat-tile">
            <div class="label">Launches today</div>
            <div class="value"><?= (int) $launchesToday ?></div>
        </div>
        <div class="stat-tile">
            <div class="label">Last login</div>
            <div class="value" style="font-size:1rem;"><?= $lastLogin ? date('M j, g:i A', strtotime($lastLogin)) : '—' ?></div>
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
    var el = document.getElementById('liveClock');
    function tick() {
        var now = new Date();
        var datePart = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        var timePart = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
        el.textContent = datePart + ' · ' + timePart;
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>
