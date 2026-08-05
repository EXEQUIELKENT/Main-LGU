<?php
/**
 * Records who changed a connected system's configuration and how — the
 * launch log already tracks who used a system, but not who set it up.
 * system_slug/system_name are stored denormalized so a log entry still
 * reads sensibly after the system itself has been deleted.
 */
function logSystemAudit(string $action, string $slug, string $name, ?string $details = null): void
{
    // created_at written from PHP's own clock, not the column's DEFAULT
    // CURRENT_TIMESTAMP — MySQL's default runs in the DB server's own
    // timezone, which audit_log.php's/team.php's strtotime() display would
    // then misread on the live domain (same fix as sso_launch_log.launched_at).
    mainLguDb()->prepare(
        'INSERT INTO system_audit_log (super_admin_id, action, system_slug, system_name, details, ip_address, created_at) VALUES (?,?,?,?,?,?,?)'
    )->execute([
        $_SESSION['super_admin_id'] ?? null,
        $action,
        $slug,
        $name,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        date('Y-m-d H:i:s'),
    ]);
}

/**
 * Compares an edited system's old vs. new field values and returns a
 * human-readable summary like "base URL, icon changed" for the audit
 * log's details column — null if nothing actually changed.
 */
function summarizeSystemChanges(array $old, array $new): ?string
{
    $labels = [
        'name' => 'name',
        'base_url' => 'base URL',
        'admin_entry_path' => 'admin entry path',
        'sso_consume_path' => 'SSO consume path',
        'stats_path' => 'stats path',
        'icon' => 'icon',
        'theme_color' => 'card color',
        'short_tag' => 'badge text',
        'public_tagline' => 'public tagline',
        'is_active' => 'active status',
    ];

    $changed = [];
    foreach ($labels as $field => $label) {
        if ((string) ($old[$field] ?? '') !== (string) ($new[$field] ?? '')) {
            $changed[] = $label;
        }
    }

    return $changed === [] ? null : implode(', ', $changed) . ' changed';
}
