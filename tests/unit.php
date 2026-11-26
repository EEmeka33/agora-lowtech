<?php
/*
 * Unit tests: small functions, dictionaries, permissions, progress.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
$dir = sys_get_temp_dir() . '/agora-unit-' . bin2hex(random_bytes(4));
mkdir($dir);
file_put_contents("$dir/config.php", '<?php return ' . var_export(['database' => "$dir/agora.db", 'upload_dir' => "$dir/up"], true) . ';');
putenv("AGORA_CONFIG=$dir/config.php");
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg($dir)));
require_once dirname(__DIR__) . '/app/boot.php';
locale('en');

test('dictionaries have the same keys and placeholders', function () {
    $en = require APP . '/lang/en.php';
    $fr = require APP . '/lang/fr.php';
    check_same([], array_values(array_diff(array_keys($en), array_keys($fr))), 'keys missing in fr.php');
    check_same([], array_values(array_diff(array_keys($fr), array_keys($en))), 'keys missing in en.php');
    $placeholders = function (string $s): array {
        preg_match_all('/\{(\w+)\}/', $s, $m);
        $list = array_unique($m[1]);
        sort($list);
        return $list;
    };
    foreach ($en as $key => $text) {
        if (isset($fr[$key]) && $placeholders($text) !== $placeholders($fr[$key])) {
            check(false, "placeholders differ for $key");
        }
    }
    check(true);
});

test('every translation key used in the code exists', function () {
    $en = require APP . '/lang/en.php';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP, FilesystemIterator::SKIP_DOTS));
    $missing = [];
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/lang/')) {
            continue;
        }
        preg_match_all("/\\bt[n]?\\(\\s*'([a-zA-Z0-9_.]+)'/", (string) file_get_contents($file->getPathname()), $m);
        foreach ($m[1] as $key) {
            if (!str_ends_with($key, '.') && !isset($en[$key])) {
                $missing[] = $key;
            }
        }
    }
    check_same([], array_values(array_unique($missing)), 'missing keys');
    foreach (['role' => ROLES, 'taskStatus' => TASK_STATUSES, 'projectStatus' => PROJECT_STATUSES, 'category' => transaction_categories(),
        'eventType' => EVENT_TYPES, 'documentCategory' => DOCUMENT_CATEGORIES, 'boardPosition' => BOARD_POSITIONS] as $enum => $values) {
        foreach ($values as $v) {
            check(isset($en["enums.$enum.$v"]), "enums.$enum.$v");
        }
    }
});

test('plural forms', function () {
    check_same('1 task', tn('common.countTasks', 1));
    check_same('3 tasks', tn('common.countTasks', 3));
    check_same('none overdue', tn('dashboard.stats.overdue', 0));
    check_same('0 tâche', tn('common.countTasks', 0, [], 'fr'));
    check_same('2 tâches', tn('common.countTasks', 2, [], 'fr'));
    check_same('Due tomorrow', tn('common.dueIn', 1));
});

test('money', function () {
    check_same(1250, parse_money('12,50'));
    check_same(1250, parse_money('12.5'));
    check_same(120000, parse_money('1 200'));
    check_same(null, parse_money('abc'));
    check_same(null, parse_money(''));
    check_same('€1,234.50', fmt_money(123450, 'EUR', 'en'));
    check_same("1\u{202F}234,50\u{00A0}€", fmt_money(123450, 'EUR', 'fr'));
    check_same('€12', fmt_money(1200, 'EUR', 'en'));
    check_same('12.50', money_input(1250));
    check_same('12', money_input(1200));
});

test('durations', function () {
    foreach (['1h30' => 90, '1h' => 60, '90min' => 90, '45m' => 45, '1:30' => 90, '2.5' => 150, '2,5' => 150] as $in => $out) {
        check_same($out, parse_duration($in), $in);
    }
    check_same(null, parse_duration('25h'));
    check_same(null, parse_duration('soon'));
    check_same('1.5', minutes_to_hours(90));
});

test('dates', function () {
    check_same('2026-03-04', parse_date_input('2026-03-04'));
    check_same('2026-03-04 18:30', parse_date_input('2026-03-04T18:30', true));
    check_same('2026-03-04 18:30', parse_date_input('04/03/2026 18:30', true));
    check_same(null, parse_date_input('2026-02-30'));
    check_same(null, parse_date_input('tomorrow'));
    check_same('4 Mar 2026', fmt_date('2026-03-04'));
    check_same('Wednesday 4 March', fmt_date('2026-03-04', 'long'));
    check_same('mercredi 4 mars', (function () { locale('fr'); $s = fmt_date('2026-03-04', 'long'); locale('en'); return $s; })());
    check_same(3, days_between('2026-03-01', '2026-03-04'));
    check(is_overdue('2000-01-01'));
    check(!is_overdue('2000-01-01', true));
});

test('join codes and tokens', function () {
    $code = new_join_code();
    check((bool) preg_match('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $code), $code);
    check_same($code, normalize_join_code(strtolower(str_replace('-', ' ', $code))));
    check_same('', normalize_join_code('O0O0O-I1I1I'));
    check_same(43, strlen(new_token()));
    check(new_token() !== new_token());
});

test('password policy', function () {
    check_same('length', password_issue('short'));
    check_same('common', password_issue('motdepasse1'));
    check_same('common', password_issue('aaaaaaaaaaaa'));
    check_same('personal', password_issue('camille.martin.42', 'camille.martin@example.org'));
    check_same(null, password_issue('repair-cafe-on-saturday'));
});

test('permissions', function () {
    $admin = ['id' => 1, 'role' => 'ADMIN'];
    $manager = ['id' => 2, 'role' => 'MANAGER'];
    $member = ['id' => 3, 'role' => 'MEMBER'];
    check(can('manageMembers', $admin) && !can('manageMembers', $manager));
    check(can('manageFinance', $manager) && !can('manageFinance', $member));
    check(can('editTask', $member, ['creator_id' => 3, 'assignee_id' => null]));
    check(can('editTask', $member, ['creator_id' => 9, 'assignee_id' => 3]));
    check(!can('editTask', $member, ['creator_id' => 9, 'assignee_id' => 8]));
    check(!can('deleteTask', $member, ['creator_id' => 9, 'assignee_id' => 3]));
    check(can('editProject', $member, ['lead_id' => 9, 'pole_lead_ids' => [3]]));
    check(can('assignRequest', $member, ['pole_lead_ids' => [3]]) && !can('assignRequest', $member, ['pole_lead_ids' => [4]]));
    check(can('writeMinutes', ['id' => 5, 'role' => 'MEMBER', 'board_position' => 'SECRETARY']));
    check(!can('writeMinutes', $member));
    check(can('editPoll', $member, ['created_by_id' => 3]) && !can('editPoll', $member, ['created_by_id' => 4]));
});

test('progress and health', function () {
    $p = ['status' => 'IN_PROGRESS', 'progress_mode' => 'AUTO', 'manual_progress' => 0, 'start_date' => null, 'due_date' => null];
    check_same(0, project_progress($p, [], []));
    check_same(50, project_progress($p, [['status' => 'DONE'], ['status' => 'TODO']], []));
    // A milestone weighs two tasks: (1 + 2) / (2 + 2) = 75 %
    check_same(75, project_progress($p, [['status' => 'DONE'], ['status' => 'TODO']], [['done' => 1]]));
    check_same(30, project_progress(['progress_mode' => 'MANUAL', 'manual_progress' => 30] + $p, [], []));
    check_same(100, project_progress(['status' => 'COMPLETED'] + $p, [], []));
    $dated = ['start_date' => '2026-01-01', 'due_date' => '2026-12-31'] + $p;
    check_same('OFF_TRACK', project_health($dated, 50, '2027-01-02 10:00'));
    check_same('AT_RISK', project_health($dated, 10, '2026-07-01 10:00'));
    check_same('ON_TRACK', project_health($dated, 45, '2026-07-01 10:00'));
    check_same(null, project_health(['status' => 'IDEA'] + $dated, 0));
    check_same(['done' => 1, 'total' => 2, 'percent' => 50], foundation_progress([['status' => 'DONE'], ['status' => 'TODO'], ['status' => 'SKIPPED']]));
});

test('html escaping and text bars', function () {
    check_same('&lt;script&gt;alert(1)&lt;/script&gt;', h('<script>alert(1)</script>'));
    check_same('&quot;&#039;', h('"\''));
    $html = text_html("see https://example.org/a?b=1&c=2 <b>");
    check(str_contains($html, '<a href="https://example.org/a?b=1&amp;c=2"'), $html);
    check(str_contains($html, '&lt;b&gt;'));
    check_same('██████████', text_bar(10, 10, 10));
    check_same('█████', text_bar(5, 10, 10));
    check_same('', text_bar(0, 10, 10));
    check_same('/tasks', safe_local_path('/tasks'));
    check_same('/', safe_local_path('//evil.example'));
    check_same('/', safe_local_path('https://evil.example'));
});

test('database schema and migrations', function () {
    check_same(1, (int) db()->query('PRAGMA user_version')->fetchColumn());
    check_same(1, (int) db()->query('PRAGMA foreign_keys')->fetchColumn());
    foreach (TENANT_TABLES as $table) {
        $cols = array_column(rows("PRAGMA table_info($table)"), 'name');
        check(in_array('association_id', $cols, true), "$table has association_id");
    }
});
