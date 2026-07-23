<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY name')->fetchAll();

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

$iconMap = [
    'roadmon' => ['icon' => 'fa-road', 'grad' => 'linear-gradient(135deg,#f59e0b,#d97706)'],
    'ipms' => ['icon' => 'fa-hard-hat', 'grad' => 'linear-gradient(135deg,#3b82f6,#1d4ed8)'],
    'energy' => ['icon' => 'fa-bolt', 'grad' => 'linear-gradient(135deg,#10b981,#047857)'],
    'cprf' => ['icon' => 'fa-landmark', 'grad' => 'linear-gradient(135deg,#8b5cf6,#6d28d9)'],
    'cimm' => ['icon' => 'fa-tools', 'grad' => 'linear-gradient(135deg,#14b8a6,#0f766e)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard — InfraGovServices</title>
<link rel="icon" href="../public/logocityhall.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; font-family: 'Poppins', system-ui, sans-serif;
        background:
            radial-gradient(ellipse 900px 600px at 10% 0%, rgba(79,110,247,.16), transparent 55%),
            radial-gradient(ellipse 700px 500px at 95% 100%, rgba(59,130,246,.12), transparent 55%),
            linear-gradient(160deg, #050a19 0%, #0a1628 55%, #0d1f3c 100%);
        color: #e8ecfb;
    }
    header {
        position: sticky; top: 0; z-index: 20;
        background: rgba(8, 13, 32, .78); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(120,140,220,.14);
        padding: 16px 32px; display: flex; align-items: center; justify-content: space-between;
    }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand img { width: 36px; height: 36px; border-radius: 9px; }
    .brand strong { color: #fff; font-size: 1rem; display: block; }
    .brand span { color: #7c88b8; font-size: .74rem; }
    .who { display: flex; align-items: center; gap: 16px; }
    .who .name { color: #b6bedc; font-size: .85rem; }
    .who a.logout {
        color: #ff8fa3; text-decoration: none; font-size: .82rem; font-weight: 500;
        border: 1px solid rgba(255,143,163,.3); padding: 7px 14px; border-radius: 8px; transition: background .15s;
    }
    .who a.logout:hover { background: rgba(255,143,163,.1); }

    main { max-width: 1180px; margin: 0 auto; padding: 34px 32px 60px; }

    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 34px; }
    .stat-tile {
        background: rgba(15, 22, 48, .55); border: 1px solid rgba(120,140,220,.14); border-radius: 14px;
        padding: 18px 20px; backdrop-filter: blur(14px);
    }
    .stat-tile .label { color: #8b95c0; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .stat-tile .value { font-family: 'DM Mono', monospace; font-size: 1.6rem; color: #fff; font-weight: 500; }

    h2.section-title { color: #dfe4f7; font-size: 1rem; font-weight: 600; margin: 0 0 18px; letter-spacing: .01em; }

    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
    .card {
        background: rgba(15, 22, 48, .55); border: 1px solid rgba(120,140,220,.14); border-radius: 16px;
        padding: 24px; backdrop-filter: blur(14px); transition: transform .18s, box-shadow .18s, border-color .18s;
        position: relative; overflow: hidden;
    }
    .card:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgba(0,0,0,.35); border-color: rgba(120,140,220,.3); }
    .card .icon-tile {
        width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.15rem; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(0,0,0,.3);
    }
    .card h3 { margin: 0 0 6px; color: #fff; font-size: 1.05rem; font-weight: 600; }
    .card p.url { margin: 0 0 18px; color: #7c88b8; font-size: .78rem; word-break: break-all; }
    .card a.launch {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
        background: linear-gradient(135deg, #4f6ef7, #3f5adf); color: #fff;
        text-decoration: none; border-radius: 9px; font-size: .85rem; font-weight: 600;
        box-shadow: 0 8px 20px rgba(63,90,223,.3); transition: transform .15s;
    }
    .card a.launch:hover { transform: translateY(-1px); }
    .badge {
        position: absolute; top: 20px; right: 20px;
        font-size: .68rem; padding: 3px 10px; border-radius: 999px; font-weight: 600; letter-spacing: .02em;
    }
    .badge.active { background: rgba(79,201,122,.14); color: #4fc97a; border: 1px solid rgba(79,201,122,.3); }
    .badge.inactive { background: rgba(215,63,82,.14); color: #d73f52; border: 1px solid rgba(215,63,82,.3); }
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
        <span class="name">Signed in as <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['super_admin_name']) ?></strong></span>
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
    <div class="grid">
    <?php foreach ($systems as $system):
        $meta = $iconMap[$system['slug']] ?? ['icon' => 'fa-server', 'grad' => 'linear-gradient(135deg,#4f6ef7,#3f5adf)'];
        $host = parse_url($system['base_url'], PHP_URL_HOST) ?: $system['base_url'];
    ?>
        <div class="card">
            <span class="badge <?= $system['is_active'] ? 'active' : 'inactive' ?>"><?= $system['is_active'] ? 'Active' : 'Inactive' ?></span>
            <div class="icon-tile" style="background: <?= $meta['grad'] ?>;"><i class="fas <?= $meta['icon'] ?>"></i></div>
            <h3><?= htmlspecialchars($system['name']) ?></h3>
            <p class="url"><?= htmlspecialchars($host) ?></p>
            <a class="launch" href="launch.php?system=<?= urlencode($system['slug']) ?>">Open Admin <i class="fas fa-arrow-right"></i></a>
        </div>
    <?php endforeach; ?>
    </div>
</main>
</body>
</html>
