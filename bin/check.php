<?php
/*
 * Checks that this server can run Agora: PHP version, extensions, folders.
 *   php bin/check.php
 */
$ok = true;
$line = function (bool $good, string $what, string $hint = '') use (&$ok) {
    $ok = $ok && $good;
    echo ($good ? '✔ ' : '✘ ') . $what . ($good || $hint === '' ? '' : "  → $hint") . "\n";
};
$line(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION . ' (8.1 or newer)', 'install a newer PHP');
$line(extension_loaded('pdo_sqlite'), 'extension pdo_sqlite', 'apt install php-sqlite3');
$line(function_exists('gzencode'), 'extension zlib (compressed pages)', 'optional');
$line(function_exists('imagecreatefromstring'), 'extension gd (reduces big photos)', 'optional: apt install php-gd');
$line(function_exists('finfo_open'), 'extension fileinfo (detects file types)', 'optional');
$line(defined('PASSWORD_ARGON2ID'), 'Argon2id passwords', 'optional: bcrypt is used instead');
require dirname(__DIR__) . '/app/boot.php';
$data = dirname((string) config('database'));
if (!is_dir($data)) {
    @mkdir($data, 0700, true);
}
$line(is_writable($data), "data folder writable ($data)", 'chmod/chown it for the web server user');
$line(!str_starts_with(realpath($data) ?: $data, realpath(ROOT . '/public') ?: '/nonexistent'), 'data folder outside public/', 'move it: config.php → database, upload_dir');
$version = (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn();
$line(version_compare($version, '3.27', '>='), "SQLite $version (3.27 or newer)", 'needed for backups (VACUUM INTO)');
$line(in_array(config('mail.transport'), ['off', 'log', 'mail', 'smtp'], true), 'mail transport: ' . config('mail.transport'));
echo $ok ? "\nAll good.\n" : "\nSome checks failed.\n";
exit($ok ? 0 : 1);
