<?php
/*
 * Runs every test. No framework: plain PHP.
 *
 *   php tests/run.php          unit tests and end-to-end story
 *   php tests/run.php unit     only the unit tests (fast)
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$only = $argv[1] ?? 'all';
$started = microtime(true);
if ($only === 'all' || $only === 'unit') {
    echo "Unit tests ";
    require __DIR__ . '/unit.php';
    echo "\n";
}
if ($only === 'all' || $only === 'e2e') {
    echo "End-to-end ";
    require __DIR__ . '/e2e.php';
}
echo "\n(" . round(microtime(true) - $started, 1) . " s)";
exit(summary());
