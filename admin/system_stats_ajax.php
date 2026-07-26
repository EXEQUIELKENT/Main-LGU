<?php
/**
 * JSON endpoint the dashboard polls after its own page load, so the slow
 * part (curling each connected system's stats_path, up to 2.5s per system)
 * never blocks the page itself from rendering.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_stats.php';
require_super_admin();

header('Content-Type: application/json');

$systems = mainLguDb()->query('SELECT * FROM connected_systems ORDER BY id')->fetchAll();
echo json_encode(fetchAllSystemStats($systems));
