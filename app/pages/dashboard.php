<?php
/*
 * Dashboard: what concerns me, what's happening, announcements.
 */

function dashboard_show(): void
{
    $user = require_member();
    $a = aid();
    $today = today();
    $poleIds = my_pole_ids($user['id']);

    $myTasks = rows(
        "SELECT t.*, p.title AS project_title FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.association_id = ? AND t.assignee_id = ? AND t.status != 'DONE'
         ORDER BY t.due_date IS NULL, t.due_date, t.priority = 'URGENT' DESC LIMIT 8",
        [$a, $user['id']]
    );
    $requests = $poleIds ? rows(
        "SELECT r.*, p.name AS pole_name, u.name AS requester_name FROM requests r
         JOIN poles p ON p.id = r.pole_id LEFT JOIN users u ON u.id = r.requester_id
         WHERE r.association_id = ? AND r.status = 'OPEN' AND r.pole_id IN (" . placeholders($poleIds) . ")
         AND (r.requester_id IS NULL OR r.requester_id != ?) ORDER BY r.created_at DESC LIMIT 5",
        [$a, ...$poleIds, $user['id']]
    ) : [];
    $projects = with_progress(rows(
        "SELECT p.*, u.name AS lead_name FROM projects p LEFT JOIN users u ON u.id = p.lead_id
         WHERE p.association_id = ? AND p.status = 'IN_PROGRESS' ORDER BY p.due_date IS NULL, p.due_date LIMIT 6",
        [$a]
    ));
    $events = rows(
        'SELECT e.*, ea.status AS my_rsvp FROM events e
         LEFT JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = ?
         WHERE e.association_id = ? AND e.start_at >= ? ORDER BY e.start_at LIMIT 4',
        [$user['id'], $a, $today]
    );
    $polls = rows(
        "SELECT p.id, p.question, p.closes_at,
           EXISTS (SELECT 1 FROM poll_votes v WHERE v.poll_id = p.id AND v.user_id = ?) AS voted
         FROM polls p WHERE p.association_id = ? AND p.closed_at IS NULL AND (p.closes_at IS NULL OR p.closes_at >= ?)
         ORDER BY p.created_at DESC LIMIT 4",
        [$user['id'], $a, now_wall()]
    );
    $announcements = rows(
        'SELECT an.*, u.name AS author_name FROM announcements an LEFT JOIN users u ON u.id = an.author_id
         WHERE an.association_id = ? ORDER BY an.pinned DESC, an.created_at DESC LIMIT 5',
        [$a]
    );
    $steps = rows('SELECT id, title, status FROM foundation_steps WHERE association_id = ? ORDER BY position, id', [$a]);
    $stats = row(
        "SELECT
           (SELECT COUNT(*) FROM members WHERE association_id = ? AND status = 'ACTIVE') AS members,
           (SELECT COUNT(*) FROM tasks WHERE association_id = ? AND status != 'DONE') AS open_tasks,
           (SELECT COUNT(*) FROM tasks WHERE association_id = ? AND status != 'DONE' AND due_date < ?) AS overdue,
           (SELECT COUNT(*) FROM projects WHERE association_id = ? AND status = 'IN_PROGRESS') AS projects,
           (SELECT COALESCE(SUM(CASE type WHEN 'INCOME' THEN amount_cents ELSE -amount_cents END), 0)
              FROM transactions WHERE association_id = ?) AS balance",
        [$a, $a, $a, $today, $a, $a]
    );
    render('dashboard/show', [
        'user' => $user,
        'myTasks' => $myTasks,
        'requests' => $requests,
        'projects' => $projects,
        'events' => $events,
        'polls' => $polls,
        'announcements' => $announcements,
        'founding' => foundation_progress($steps),
        'nextStep' => current(array_filter($steps, fn ($s) => $s['status'] === 'IN_PROGRESS')) ?: current(array_filter($steps, fn ($s) => $s['status'] === 'TODO')),
        'stats' => $stats,
        'activity' => recent_activity(8),
    ], t('nav.dashboard'));
}

/** Latest entries of the activity feed (with the actor's name). */
function recent_activity(int $limit, int $offset = 0): array
{
    return rows(
        'SELECT a.*, u.name AS actor_name FROM activities a LEFT JOIN users u ON u.id = a.actor_id
         WHERE a.association_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?',
        [aid(), $limit, $offset]
    );
}

function dashboard_announce(): void
{
    $user = require_member();
    ensure(can('manageAnnouncements', $user));
    $errors = [];
    foreach (['title', 'body'] as $f) {
        if (post_str($f) === '') {
            $errors[$f] = t('validation.required');
        }
    }
    if ($errors) {
        fail_form($errors, 'dashboard_show');
    }
    $id = insert('announcements', [
        'association_id' => aid(),
        'title' => post_str('title'),
        'body' => post_str('body'),
        'pinned' => post_bool('pinned') ? 1 : 0,
        'author_id' => $user['id'],
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    log_activity('posted', 'announcement', $id, post_str('title'));
    notify(association_member_ids(aid()), 'announcement.new', ['title' => post_str('title')], '/');
    redirect('/', t('common.created'));
}

function dashboard_pin(int $id): void
{
    ensure(can('manageAnnouncements', require_member()));
    $a = find_or_404('announcements', $id);
    update('announcements', ['pinned' => $a['pinned'] ? 0 : 1], 'id = ?', [$id]);
    redirect('/');
}

function dashboard_unannounce(int $id): void
{
    ensure(can('manageAnnouncements', require_member()));
    find_or_404('announcements', $id);
    q('DELETE FROM announcements WHERE id = ?', [$id]);
    redirect('/', t('common.deleted'));
}
