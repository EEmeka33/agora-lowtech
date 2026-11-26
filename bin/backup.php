<?php
/*
 * Backs up the database and the uploaded files, safely, even while Agora runs.
 *
 *   php bin/backup.php                 → data/backups/2026-11-26-0300/
 *   php bin/backup.php /mnt/usb-key    → /mnt/usb-key/2026-11-26-0300/
 *
 * Keeps the 14 most recent backups in the destination folder. To restore: stop
 * the web server, copy agora.db and uploads/ back to data/, start it again.
 * Put it in a cron job for a daily backup (see docs/self-hosting.md).
 */
require dirname(__DIR__) . '/app/boot.php';

$root = rtrim($argv[1] ?? dirname((string) config('database')) . '/backups', '/');
$dir = $root . '/' . date('Y-m-d-Hi');
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Cannot create $dir\n");
    exit(1);
}

// VACUUM INTO writes a clean, consistent copy of the database (SQLite 3.27+).
db()->exec('VACUUM INTO ' . db()->quote("$dir/agora.db"));

$uploads = (string) config('upload_dir');
$count = 0;
if (is_dir($uploads)) {
    mkdir("$dir/uploads", 0700);
    foreach (glob("$uploads/*") ?: [] as $file) {
        if (is_file($file) && copy($file, "$dir/uploads/" . basename($file))) {
            $count++;
        }
    }
}

// Keep the 14 most recent backups.
$all = glob("$root/[0-9][0-9][0-9][0-9]-*", GLOB_ONLYDIR) ?: [];
sort($all);
foreach (array_slice($all, 0, max(0, count($all) - 14)) as $old) {
    array_map('unlink', glob("$old/uploads/*") ?: []);
    @rmdir("$old/uploads");
    @unlink("$old/agora.db");
    @rmdir($old);
}

printf("✔ Backup written to %s (database %s, %d files)\n", $dir, fmt_bytes((int) filesize("$dir/agora.db")), $count);
