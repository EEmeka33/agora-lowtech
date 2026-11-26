<?php
/*
 * Housekeeping: deletes expired sessions and old data, sends the daily email
 * digests. Agora runs it by itself at most once an hour, after a page has been
 * sent; you can also run it from cron at a fixed time, e.g. every morning:
 *
 *   0 7 * * *  php /path/to/agora/bin/maintenance.php
 */
require dirname(__DIR__) . '/app/boot.php';

$done = run_maintenance();
q("INSERT OR REPLACE INTO meta (key, value) VALUES ('maintenance_at', ?)", [time()]);
flush_mail_queue();
foreach ($done as $what => $n) {
    echo str_pad($what, 15), $n, "\n";
}
