<?php
/**
 * Fetches each connected system's headline stat (e.g. report/request count)
 * from its own /stats.php-style endpoint. Any failure (timeout, non-200,
 * malformed JSON) for a given system just comes back as null — callers
 * should render a "—" fallback rather than erroring.
 *
 * Deliberately sequential rather than curl_multi: curl_multi_select() is
 * unreliable on Windows (spurious -1 returns cause requests in the same
 * batch to silently drop), which is a real concern since this runs on
 * Windows/XAMPP locally. Timeouts are kept tight (1.5s connect / 2.5s
 * total per system) so a handful of unreachable systems still doesn't
 * make the dashboard noticeably slow to load.
 *
 * @param array $systems rows from connected_systems (needs slug, base_url,
 *                        stats_path, shared_secret)
 * @return array<string,?array{count:int,label:string}> keyed by slug
 */
function fetchAllSystemStats(array $systems): array
{
    $results = [];

    foreach ($systems as $system) {
        if (empty($system['stats_path']) || !$system['is_active']) {
            continue;
        }

        $results[$system['slug']] = null;

        $url = rtrim($system['base_url'], '/') . $system['stats_path'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $system['shared_secret']],
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS => 2500,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $body) {
            $data = json_decode($body, true);
            if (is_array($data) && isset($data['count'], $data['label'])) {
                $results[$system['slug']] = ['count' => (int) $data['count'], 'label' => (string) $data['label']];
            }
        }
    }

    return $results;
}
