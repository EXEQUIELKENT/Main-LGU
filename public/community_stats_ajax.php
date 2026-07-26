<?php
/**
 * Public, unauthenticated JSON endpoint for the citizen dashboard's stats
 * bar. Fetched client-side (see citizendash.php) so a slow/unreachable
 * connected system never blocks the public page itself from loading.
 *
 * Cached to a file for a few minutes — this page gets real citizen
 * traffic, and re-curling all 7 connected systems' stats endpoints on
 * every single visit would hammer them for no benefit; a 5-minute-old
 * "live" number is still live in every way that matters here.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_stats.php';

header('Content-Type: application/json');

$cacheFile = sys_get_temp_dir() . '/mainlgu_community_stats.json';
$cacheTtl = 300;

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    readfile($cacheFile);
    exit;
}

$systems = mainLguDb()->query('SELECT * FROM connected_systems WHERE is_active = 1')->fetchAll();
$stats = fetchAllSystemStats($systems);

$total = 0;
foreach ($stats as $stat) {
    if ($stat !== null) {
        $total += $stat['count'];
    }
}

$result = [
    'total' => $total,
    'reports' => $stats['cimm'] ?? null,
];

$json = json_encode($result);
file_put_contents($cacheFile, $json);
echo $json;
