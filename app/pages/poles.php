<?php
/*
 * Poles: the working groups of the association, their leads and members.
 */
require_once APP . '/pages/tasks.php';

function poles_list(): void
{
    $user = require_member();
    $poles = rows(
        "SELECT p.*,
           (SELECT COUNT(*) FROM pole_members pm WHERE pm.pole_id = p.id) AS member_count,
           (SELECT GROUP_CONCAT(u.name, ', ') FROM pole_members pm JOIN users u ON u.id = pm.user_id WHERE pm.pole_id = p.id AND pm.role = 'LEAD') AS leads,
           (SELECT COUNT(*) FROM projects pr WHERE pr.pole_id = p.id AND pr.status NOT IN ('COMPLETED', 'CANCELLED')) AS project_count,
           (SELECT COUNT(*) FROM tasks t WHERE t.pole_id = p.id AND t.status != 'DONE') AS task_count,
           (SELECT COUNT(*) FROM requests r WHERE r.pole_id = p.id AND r.status = 'OPEN') AS request_count
         FROM poles p WHERE p.association_id = ? ORDER BY p.position, p.name",
        [aid()]
    );
    render('poles/list', ['user' => $user, 'poles' => $poles], t('poles.title'));
}

function read_pole(): array
{
    return ['name' => post_str('name'), 'description' => post_opt('description'), 'mission' => post_opt('mission')];
}

function poles_create(): void
{
    $user = require_member();
    ensure(can('managePoles', $user));
    $data = read_pole();
    if ($data['name'] === '') {
        fail_form(['name' => t('validation.required')], 'poles_list');
    }
    $leadId = post_id('lead_id');
    assert_refs(['user_ids' => [$leadId]]);
    $count = (int) val('SELECT COUNT(*) FROM poles WHERE association_id = ?', [aid()]);
    $id = insert('poles', $data + ['association_id' => aid(), 'position' => $count, 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    if ($leadId) {
        insert('pole_members', ['pole_id' => $id, 'user_id' => $leadId, 'role' => 'LEAD', 'joined_at' => now_utc()]);
    }
    log_activity('created', 'pole', $id, $data['name']);
    redirect("/poles/$id", t('common.created'));
}

function editable_pole(int $id): array
{
    $user = require_member();
    $pole = find_or_404('poles', $id);
    ensure(can('editPole', $user, ['pole_lead_ids' => pole_lead_ids($id)]));
    return $pole;
}

function poles_show(int $id): void
{
    $user = require_member();
    $pole = find_or_404('poles', $id);
    $members = rows('SELECT pm.*, u.name, m.title FROM pole_members pm JOIN users u ON u.id = pm.user_id
                     LEFT JOIN members m ON m.user_id = pm.user_id AND m.association_id = ?
                     WHERE pm.pole_id = ? ORDER BY pm.role = \'LEAD\' DESC, u.name', [aid(), $id]);
    render('poles/show', [
        'user' => $user,
        'pole' => $pole,
        'members' => $members,
        'canEdit' => can('editPole', $user, ['pole_lead_ids' => pole_lead_ids($id)]),
        'projects' => with_progress(rows("SELECT * FROM projects WHERE association_id = ? AND pole_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED') ORDER BY title", [aid(), $id])),
        'tasks' => load_tasks("t.pole_id = ? AND t.status != 'DONE'", [$id], 't.due_date IS NULL, t.due_date', 20),
        'requests' => rows("SELECT * FROM requests WHERE association_id = ? AND pole_id = ? AND status IN ('OPEN', 'TAKEN') ORDER BY created_at DESC", [aid(), $id]),
        'duties' => rows('SELECT r.*, u.name AS owner_name FROM responsibilities r LEFT JOIN users u ON u.id = r.owner_id WHERE r.association_id = ? AND r.pole_id = ? ORDER BY r.title', [aid(), $id]),
        'events' => rows('SELECT * FROM events WHERE association_id = ? AND pole_id = ? AND start_at >= ? ORDER BY start_at LIMIT 5', [aid(), $id, today()]),
    ], $pole['name']);
}

function poles_update(int $id): void
{
    editable_pole($id);
    $data = read_pole();
    if ($data['name'] === '') {
        fail_form(['name' => t('validation.required')], fn () => poles_show($id));
    }
    update('poles', $data + ['updated_at' => now_utc()], 'id = ?', [$id]);
    log_activity('updated', 'pole', $id, $data['name']);
    redirect("/poles/$id", t('common.saved'));
}

function poles_delete(int $id): void
{
    ensure(can('managePoles', require_member()));
    $pole = find_or_404('poles', $id);
    q('DELETE FROM poles WHERE id = ?', [$id]);
    log_activity('deleted', 'pole', null, $pole['name']);
    redirect('/poles', t('common.deleted'));
}

function poles_add_member(int $id): void
{
    $pole = editable_pole($id);
    $userId = post_id('user_id');
    if (!$userId) {
        fail_form(['user_id' => t('validation.required')], fn () => poles_show($id));
    }
    assert_refs(['user_ids' => [$userId]]);
    $role = post_enum('role', POLE_ROLES, 'MEMBER');
    q('INSERT INTO pole_members (pole_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)
       ON CONFLICT (pole_id, user_id) DO UPDATE SET role = excluded.role', [$id, $userId, $role, now_utc()]);
    log_activity('joined', 'pole', $id, $pole['name'], $userId);
    redirect("/poles/$id", t('common.saved'));
}

function poles_member_role(int $id, int $userId): void
{
    editable_pole($id);
    update('pole_members', ['role' => post_enum('role', POLE_ROLES, 'MEMBER')], 'pole_id = ? AND user_id = ?', [$id, $userId]);
    redirect("/poles/$id", t('common.saved'));
}

function poles_remove_member(int $id, int $userId): void
{
    editable_pole($id);
    q('DELETE FROM pole_members WHERE pole_id = ? AND user_id = ?', [$id, $userId]);
    redirect("/poles/$id", t('common.saved'));
}
