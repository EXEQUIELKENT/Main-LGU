<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard — InfraGovServices</title>
<style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f6fb; }
    header {
        background: #050a19; color: #fff; padding: 18px 32px; display: flex;
        align-items: center; justify-content: space-between;
    }
    header h1 { font-size: 1.1rem; margin: 0; }
    header .who { color: #8b95b8; font-size: 0.85rem; }
    header a.logout { color: #ff8fa3; text-decoration: none; font-size: 0.85rem; margin-left: 20px; }
    main { max-width: 1080px; margin: 0 auto; padding: 32px; }
    h2 { color: #1c2748; font-size: 1rem; margin-bottom: 18px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
    .card {
        background: #fff; border: 1px solid #e3e7f3; border-radius: 12px; padding: 22px;
        box-shadow: 0 4px 14px rgba(20,30,70,0.05);
    }
    .card h3 { margin: 0 0 6px; color: #1c2748; font-size: 1.02rem; }
    .card p { margin: 0 0 16px; color: #7a819e; font-size: 0.82rem; word-break: break-all; }
    .card a {
        display: inline-block; padding: 9px 16px; background: #4f6ef7; color: #fff;
        text-decoration: none; border-radius: 7px; font-size: 0.85rem; font-weight: 600;
    }
    .card a:hover { background: #3f5adf; }
    .badge {
        display: inline-block; font-size: 0.7rem; padding: 2px 8px; border-radius: 999px;
        background: #e7f8ee; color: #1b8a4c; margin-bottom: 10px;
    }
</style>
</head>
<body>
<header>
    <h1>InfraGovServices — Super Admin</h1>
    <div>
        <span class="who">Signed in as <?= htmlspecialchars($_SESSION['super_admin_name']) ?></span>
        <a class="logout" href="logout.php">Log out</a>
    </div>
</header>
<main>
    <h2>Connected systems</h2>
    <div class="grid">
    <?php foreach ($systems as $system): ?>
        <div class="card">
            <span class="badge"><?= $system['is_active'] ? 'Active' : 'Inactive' ?></span>
            <h3><?= htmlspecialchars($system['name']) ?></h3>
            <p><?= htmlspecialchars($system['base_url']) ?></p>
            <a href="launch.php?system=<?= urlencode($system['slug']) ?>">Open Admin →</a>
        </div>
    <?php endforeach; ?>
    </div>
</main>
</body>
</html>
