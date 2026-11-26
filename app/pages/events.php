<?php
/*
 * Events and meetings: general assemblies, board meetings, workshops…
 * RSVP, agenda, minutes, a month calendar drawn as a table, and a private
 * iCalendar feed to follow events in any calendar app.
 */

function events_list(): void
{
    $user = require_member();
    $view = in_array(get_str('view'), ['calendar', 'past'], true) ? get_str('view') : 'upcoming';
    $vars = ['user' => $user, 'view' => $view];
    if ($view === 'calendar') {
        $month = preg_match('/^\d{4}-\d{2}$/', get_str('month')) ? get_str('month') : date('Y-m');
        $first = new DateTimeImmutable("$month-01");
        $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $end = $first->modify('last day of this month');
        $end = $end->modify('+' . (7 - (int) $end->format('N')) . ' days');
        $events = rows('SELECT id, title, start_at, type FROM events WHERE association_id = ? AND start_at >= ? AND start_at < ? ORDER BY start_at',
            [aid(), $start->format('Y-m-d'), $end->modify('+1 day')->format('Y-m-d')]);
        $byDay = [];
        foreach ($events as $e) {
            $byDay[substr($e['start_at'], 0, 10)][] = $e;
        }
        $vars += ['month' => $month, 'first' => $first, 'start' => $start, 'end' => $end, 'byDay' => $byDay];
    } else {
        $past = $view === 'past';
        $vars['events'] = rows(
            'SELECT e.*, p.name AS pole_name, ea.status AS my_rsvp,
               (SELECT COUNT(*) FROM event_attendees x WHERE x.event_id = e.id AND x.status = \'GOING\') AS going
             FROM events e LEFT JOIN poles p ON p.id = e.pole_id
             LEFT JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = ?
             WHERE e.association_id = ? AND e.start_at ' . ($past ? '<' : '>=') . ' ? ORDER BY e.start_at ' . ($past ? 'DESC LIMIT 100' : 'LIMIT 200'),
            [$user['id'], aid(), today()]
        );
    }
    render('events/list', $vars, t('events.title'));
}

function read_event(): array
{
    return [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'type' => post_enum('type', EVENT_TYPES, 'EVENT'),
        'start_at' => post_date('start_at', true),
        'end_at' => post_date('end_at', true),
        'location' => post_opt('location'),
        'agenda' => post_opt('agenda'),
        'pole_id' => post_id('pole_id'),
        'project_id' => post_id('project_id'),
    ];
}

function event_errors(array $data): array
{
    $errors = [];
    if ($data['title'] === '') {
        $errors['title'] = t('validation.required');
    }
    if (!$data['start_at']) {
        $errors['start_at'] = t('validation.invalidDate');
    }
    if ($data['start_at'] && $data['end_at'] && $data['end_at'] < $data['start_at']) {
        $errors['end_at'] = t('validation.dateOrder');
    }
    return $errors;
}

function events_create(): void
{
    $user = require_member();
    ensure(can('manageEvents', $user));
    $data = read_event();
    if ($errors = event_errors($data)) {
        fail_form($errors, 'events_list');
    }
    assert_refs(['pole_id' => $data['pole_id'], 'project_id' => $data['project_id']]);
    $id = insert('events', $data + ['association_id' => aid(), 'created_by_id' => $user['id'], 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    insert('event_attendees', ['event_id' => $id, 'user_id' => $user['id'], 'status' => 'GOING', 'updated_at' => now_utc()]);
    log_activity('created', 'event', $id, $data['title']);
    if (post_bool('notify')) {
        $recipients = $data['pole_id'] ? pole_member_ids($data['pole_id']) : association_member_ids(aid());
        notify($recipients, 'event.new', ['title' => $data['title'], 'when' => fmt_date($data['start_at'], 'datetime')], "/events/$id");
    }
    redirect("/events/$id", t('common.created'));
}

function events_show(int $id): void
{
    $user = require_member();
    $event = row('SELECT e.*, p.name AS pole_name, pr.title AS project_title, u.name AS creator_name FROM events e
                  LEFT JOIN poles p ON p.id = e.pole_id LEFT JOIN projects pr ON pr.id = e.project_id LEFT JOIN users u ON u.id = e.created_by_id
                  WHERE e.id = ? AND e.association_id = ?', [$id, aid()]) ?? abort(404);
    $attendees = rows('SELECT ea.status, u.id, u.name FROM event_attendees ea JOIN users u ON u.id = ea.user_id WHERE ea.event_id = ? ORDER BY u.name', [$id]);
    $mine = null;
    foreach ($attendees as $a) {
        if ((int) $a['id'] === $user['id']) {
            $mine = $a['status'];
        }
    }
    render('events/show', compact('user', 'event', 'attendees', 'mine'), $event['title']);
}

function events_update(int $id): void
{
    ensure(can('manageEvents', require_member()));
    find_or_404('events', $id);
    $data = read_event();
    if ($errors = event_errors($data)) {
        fail_form($errors, fn () => events_show($id));
    }
    assert_refs(['pole_id' => $data['pole_id'], 'project_id' => $data['project_id']]);
    update('events', $data + ['updated_at' => now_utc()], 'id = ?', [$id]);
    log_activity('updated', 'event', $id, $data['title']);
    redirect("/events/$id", t('common.saved'));
}

function events_delete(int $id): void
{
    ensure(can('manageEvents', require_member()));
    $event = find_or_404('events', $id);
    q('DELETE FROM events WHERE id = ?', [$id]);
    log_activity('deleted', 'event', null, $event['title']);
    redirect('/events', t('common.deleted'));
}

function events_rsvp(int $id): void
{
    $user = require_member();
    $event = find_or_404('events', $id);
    $status = post_enum('status', RSVP, 'GOING');
    q('INSERT INTO event_attendees (event_id, user_id, status, updated_at) VALUES (?, ?, ?, ?)
       ON CONFLICT (event_id, user_id) DO UPDATE SET status = excluded.status, updated_at = excluded.updated_at', [$id, $user['id'], $status, now_utc()]);
    log_activity('responded', 'event', $id, $event['title']);
    back("/events/$id", enum_label('rsvp', $status));
}

function events_minutes(int $id): void
{
    $user = require_member();
    $event = find_or_404('events', $id);
    ensure(can('writeMinutes', $user));
    update('events', ['minutes' => post_opt('minutes'), 'updated_at' => now_utc()], 'id = ?', [$id]);
    log_activity('updated', 'event', $id, $event['title']);
    redirect("/events/$id#minutes", t('common.saved'));
}

/** Private iCalendar feed (RFC 5545) with the events of all the person's associations. */
function events_feed(string $token): void
{
    $token = preg_replace('/\.ics$/', '', $token);
    $user = strlen($token) >= 40 ? row('SELECT id FROM users WHERE calendar_token_hash = ?', [sha256($token)]) : null;
    if (!$user) {
        abort(404);
    }
    $events = rows(
        "SELECT e.*, a.name AS association_name FROM events e JOIN associations a ON a.id = e.association_id
         WHERE e.start_at >= ? AND e.association_id IN (SELECT association_id FROM members WHERE user_id = ? AND status = 'ACTIVE')
         ORDER BY e.start_at LIMIT 1000",
        [date('Y-m-d', strtotime('-60 days')), $user['id']]
    );
    $esc = fn (?string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], (string) $s);
    // Dates are wall time: written as "floating" local times (no Z).
    $stamp = fn (string $wall) => str_replace(['-', ':', ' '], ['', '', 'T'], substr($wall . (strlen($wall) === 10 ? ' 00:00' : ''), 0, 16)) . '00';
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Agora low-tech//EN', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:Agora'];
    foreach ($events as $e) {
        $end = $e['end_at'] ?: date('Y-m-d H:i', strtotime($e['start_at'] . ' +1 hour'));
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = "UID:event-{$e['id']}@agora";
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z', (int) strtotime($e['updated_at'] . ' UTC'));
        $lines[] = 'DTSTART:' . $stamp($e['start_at']);
        $lines[] = 'DTEND:' . $stamp($end);
        $lines[] = 'SUMMARY:' . $esc("{$e['title']} · {$e['association_name']}");
        if ($e['location']) {
            $lines[] = 'LOCATION:' . $esc($e['location']);
        }
        $lines[] = 'DESCRIPTION:' . $esc(trim(($e['description'] ?? '') . "\n\n" . ($e['agenda'] ?? '')));
        $lines[] = 'URL:' . absolute_url("/events/{$e['id']}");
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    echo implode("\r\n", $lines) . "\r\n";
}
