<?php
/*
 * Volunteering: members log the time they give. Totals per member and per pole,
 * estimated value, CSV export (useful for grant applications).
 */

function volunteering_year(): int
{
    $y = (int) get_str('year');
    return $y >= 2000 && $y <= 2100 ? $y : (int) date('Y');
}

function volunteering_list(): void
{
    $user = require_member();
    $year = volunteering_year();
    $a = aid();
    $range = ["$year-01-01", ($year + 1) . '-01-01'];
    $mine = rows('SELECT te.*, p.name AS pole_name, pr.title AS project_title FROM time_entries te
                  LEFT JOIN poles p ON p.id = te.pole_id LEFT JOIN projects pr ON pr.id = te.project_id
                  WHERE te.association_id = ? AND te.user_id = ? AND te.date >= ? AND te.date < ? ORDER BY te.date DESC, te.id DESC',
        [$a, $user['id'], ...$range]);
    $byMember = rows('SELECT u.id, u.name, SUM(te.minutes) AS minutes FROM time_entries te JOIN users u ON u.id = te.user_id
                      WHERE te.association_id = ? AND te.date >= ? AND te.date < ? GROUP BY u.id ORDER BY minutes DESC', [$a, ...$range]);
    $byPole = rows('SELECT COALESCE(p.name, ?) AS name, SUM(te.minutes) AS minutes FROM time_entries te LEFT JOIN poles p ON p.id = te.pole_id
                    WHERE te.association_id = ? AND te.date >= ? AND te.date < ? GROUP BY p.id ORDER BY minutes DESC', [t('common.none'), $a, ...$range]);
    $recent = is_manager($user) ? rows('SELECT te.*, u.name AS user_name FROM time_entries te JOIN users u ON u.id = te.user_id
                                        WHERE te.association_id = ? AND te.date >= ? AND te.date < ? ORDER BY te.date DESC, te.id DESC LIMIT 50', [$a, ...$range]) : [];
    $total = array_sum(array_column($byMember, 'minutes'));
    render('volunteering/list', compact('user', 'year', 'mine', 'byMember', 'byPole', 'recent', 'total'), t('volunteering.title'));
}

function volunteering_create(): void
{
    $user = require_member();
    $minutes = parse_duration(post_str('duration'));
    if (!$minutes) {
        fail_form(['duration' => t('volunteering.invalidDuration')], 'volunteering_list');
    }
    $refs = ['pole_id' => post_id('pole_id'), 'project_id' => post_id('project_id'), 'task_id' => post_id('task_id'), 'event_id' => post_id('event_id')];
    assert_refs($refs);
    insert('time_entries', $refs + [
        'association_id' => aid(),
        'user_id' => $user['id'],
        'date' => post_date('date') ?? today(),
        'minutes' => $minutes,
        'description' => post_opt('description'),
        'created_at' => now_utc(),
    ]);
    back('/volunteering', t('volunteering.logged', ['h' => minutes_to_hours($minutes)]));
}

function volunteering_delete(int $id): void
{
    $user = require_member();
    $entry = find_or_404('time_entries', $id);
    ensure(can('deleteTimeEntry', $user, $entry));
    q('DELETE FROM time_entries WHERE id = ?', [$id]);
    back('/volunteering', t('common.deleted'));
}

function volunteering_csv(): void
{
    $user = require_member();
    ensure(is_manager($user));
    $year = volunteering_year();
    $rows = rows('SELECT te.date, u.name, te.minutes, te.description, p.name AS pole, pr.title AS project FROM time_entries te
                  JOIN users u ON u.id = te.user_id LEFT JOIN poles p ON p.id = te.pole_id LEFT JOIN projects pr ON pr.id = te.project_id
                  WHERE te.association_id = ? AND te.date >= ? AND te.date < ? ORDER BY te.date', [aid(), "$year-01-01", ($year + 1) . '-01-01']);
    $lines = [[t('common.date'), t('common.member'), t('volunteering.hoursLabel'), t('common.description'), t('common.pole'), t('common.project')]];
    foreach ($rows as $r) {
        $lines[] = [$r['date'], $r['name'], minutes_to_hours((int) $r['minutes']), $r['description'], $r['pole'], $r['project']];
    }
    send_download(csv($lines), "volunteering-$year.csv", 'text/csv; charset=utf-8');
}

/** CSV text (with a BOM so that spreadsheet programs read UTF-8 correctly). */
function csv(array $lines): string
{
    $fp = fopen('php://temp', 'r+');
    foreach ($lines as $line) {
        // A cell starting with = + - @ could run as a formula in a spreadsheet: neutralise it.
        $cells = array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) && !is_numeric($v) ? "'" . $v : (string) $v, $line);
        fputcsv($fp, $cells, ',', '"', '\\');
    }
    rewind($fp);
    return "\u{FEFF}" . stream_get_contents($fp);
}
