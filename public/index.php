<?php
/*
 * The only PHP file the web server runs. Everything else lives outside public/,
 * where no visitor can download it.
 *
 * Development server:  php -S localhost:8080 -t public public/index.php
 */

// PHP's built-in server: serve the stylesheet (and other real files) directly,
// with long-term caching and compression.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        if (str_ends_with($file, '.css')) {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=31536000, immutable');
            $css = file_get_contents($file);
            if (str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
                header('Content-Encoding: gzip');
                $css = gzencode($css, 9);
            }
            header('Content-Length: ' . strlen($css));
            echo $css;
            return true;
        }
        return false;
    }
}

require dirname(__DIR__) . '/app/boot.php';
run_app();
