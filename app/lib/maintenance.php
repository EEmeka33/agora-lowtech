<?php
/*
 * Housekeeping, at most once an hour, after a page has been sent (or from
 * `php bin/maintenance.php` in a cron job): sends daily digests and deletes
 * what is no longer needed. Keeping less data is part of being low-tech:
 * smaller backups, less to protect, less to store.
 */

function maybe_run_maintenance(): void
{
    $last = (int) val("SELECT value FROM meta WHERE key = 'maintenance_at'");
    if ($last > time() - 3600) {
        return;
    }
    // Only one request does the work, even if several arrive at the same time.
    q("INSERT OR IGNORE INTO meta (key, value) VALUES ('maintenance_at', '0')");
    if (q("UPDATE meta SET value = ? WHERE key = 'maintenance_at' AND value = ?", [time(), (string) $last])->rowCount() === 1) {
        run_maintenance();
    }
}

/** Returns what was done, for the command-line script. */
function run_maintenance(): array
{
    $done = [];
    $done['sessions'] = q('DELETE FROM sessions WHERE expires_at < ?', [now_utc()])->rowCount();
    $done['attempts'] = q('DELETE FROM attempts WHERE at < ?', [time() - 86400])->rowCount();
    $done['links'] = q('DELETE FROM invitations WHERE expires_at < ?', [utc_in(-30 * 86400)])->rowCount();
    $days = (int) config('retention_days');
    if ($days > 0) {
        $before = utc_in(-$days * 86400);
        $done['notifications'] = q('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?', [$before])->rowCount();
        $done['activity'] = q('DELETE FROM activities WHERE created_at < ?', [$before])->rowCount();
    }
    $done['digests'] = send_digests();
    return $done;
}
