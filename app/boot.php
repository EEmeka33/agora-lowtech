<?php
/*
 * Loads the configuration and every library file. Both the website
 * (public/index.php) and the command-line scripts (bin/*.php) start here.
 *
 * There is no framework and no autoloader: each file in app/lib/ holds plain
 * functions, grouped by topic. Read them in the order below to understand the app.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('STARTED_AT', microtime(true));

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit("Agora needs PHP 8.1 or newer (this server runs PHP " . PHP_VERSION . ").\n");
}
if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    exit("Agora needs the PHP extension pdo_sqlite.\n");
}

/** Reads a setting from config.php (or config.example.php for defaults). */
function config(string $key): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require ROOT . '/config.example.php';
        $file = getenv('AGORA_CONFIG') ?: ROOT . '/config.php';
        if (is_file($file)) {
            $config = array_replace_recursive($config, require $file);
        }
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null;
    }
    return $value;
}

date_default_timezone_set((string) config('timezone') ?: 'UTC');
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
// Every warning becomes an exception: problems are visible instead of silently ignored.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require APP . '/lib/constants.php';
require APP . '/lib/util.php';
require APP . '/lib/db.php';
require APP . '/lib/i18n.php';
require APP . '/lib/http.php';
require APP . '/lib/auth.php';
require APP . '/lib/context.php';
require APP . '/lib/permissions.php';
require APP . '/lib/view.php';
require APP . '/lib/mail.php';
require APP . '/lib/notify.php';
require APP . '/lib/progress.php';
require APP . '/lib/maintenance.php';
