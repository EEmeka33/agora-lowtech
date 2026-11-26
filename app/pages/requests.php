<?php
/*
 * Requests: a pole asks another one for help. Every member of the asked pole is
 * notified; one of them takes it and it becomes their task. When the task is
 * done, the request is closed and the requester is notified.
 */
require_once APP . '/pages/tasks.php';

function load_requests(string $where, array $params, int $limit = 200): array
{
    return rows(
        "SELECT r.*, p.name AS pole_name, fp.name AS from_pole_name, pr.title AS project_title,
                u.name AS requester_name, tb.name AS taken_by_name
         FROM requests r
         JOIN poles p ON p.id = r.pole_id
         LEFT JOIN poles fp ON fp.id = r.from_pole_id
         LEFT JOIN projects pr ON pr.id = r.project_id
         LEFT JOIN users u ON u.id = r.requester_id
         LEFT JOIN users tb ON tb.id = r.taken_by_id
         WHERE r.association_id = ? AND ($where)
         ORDER BY CASE r.status WHEN 'OPEN' THEN 0 WHEN 'TAKEN' THEN 1 ELSE 2 END, r.created_at DESC LIMIT $limit",
        [aid(), ...$params]
    );
}

function requests_list(): void
{
    $user = require_member();
    $poleIds = my_pole_ids($user['id']);
    $tab = in_array(get_str('tab'), ['mine', 'asked', 'all'], true) ? get_str('tab') : ($poleIds ? 'mine' : 'all');
    $status = in_array(get_str('status'), [...REQUEST_STATUSES, 'any'], true) ? get_str('status') : 'active';
    $where = ['1 = 1'];
    $params = [];
    if ($tab === 'mine') {
        $where[] = $poleIds ? 'r.pole_id IN (' . placeholders($poleIds) . ')' : '0';
        array_push($params, ...$poleIds);
    } elseif ($tab === 'asked') {
        $where[] = 'r.requester_id = ?';
        $params[] = $user['id'];
    }
    if ($status === 'active') {
        $where[] = "r.status IN ('OPEN', 'TAKEN')";
    } elseif ($status !== 'any') {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
    $openForMe = $poleIds ? (int) val("SELECT COUNT(*) FROM requests WHERE association_id = ? AND status = 'OPEN' AND pole_id IN (" . placeholders($poleIds) . ')', [aid(), ...$poleIds]) : 0;
    render('requests/list', [
        'requests' => load_requests(implode(' AND ', $where), $params),
        'tab' => $tab,
        'status' => $status,
        'openForMe' => $openForMe,
    ], t('requests.title'));
}

function requests_create(): void
{
    $user = require_member();
    $data = [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'priority' => post_enum('priority', PRIORITIES, 'MEDIUM'),
        'due_date' => post_date('due_date'),
        'pole_id' => post_id('pole_id'),
        'from_pole_id' => post_id('from_pole_id'),
        'project_id' => post_id('project_id'),
    ];
    $errors = [];
    if ($data['title'] === '') {
        $errors['title'] = t('validation.required');
    }
    if (!$data['pole_id']) {
        $errors['pole_id'] = t('validation.required');
    }
    if ($errors) {
        fail_form($errors, 'requests_list');
    }
    assert_refs(['pole_id' => $data['pole_id'], 'project_id' => $data['project_id']]);
    assert_refs(['pole_id' => $data['from_pole_id']]);
    $id = insert('requests', $data + ['association_id' => aid(), 'requester_id' => $user['id'], 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    log_activity('created', 'request', $id, $data['title']);
    $pole = find('poles', $data['pole_id']);
    notify(pole_member_ids($data['pole_id']), 'request.new', ['title' => $data['title'], 'pole' => $pole['name']], "/requests/$id");
    back("/requests/$id", t('requests.sent'));
}

function requests_show(int $id): void
{
    $user = require_member();
    $request = load_requests('r.id = ?', [$id])[0] ?? abort(404);
    $members = [];
    foreach (rows("SELECT u.id, u.name FROM pole_members pm JOIN users u ON u.id = pm.user_id
                   JOIN members m ON m.user_id = u.id AND m.association_id = ? AND m.status = 'ACTIVE'
                   WHERE pm.pole_id = ? ORDER BY u.name", [aid(), $request['pole_id']]) as $m) {
        $members[$m['id']] = $m['name'];
    }
    $task = $request['task_id'] ? row('SELECT id, title, status FROM tasks WHERE id = ?', [$request['task_id']]) : null;
    render('requests/show', [
        'user' => $user,
        'request' => $request,
        'task' => $task,
        'poleMembers' => $members,
        'inPole' => isset($members[$user['id']]),
        'canAssign' => can('assignRequest', $user, ['pole_lead_ids' => pole_lead_ids((int) $request['pole_id'])]),
    ], $request['title']);
}

/** Turns an open request into a task assigned to $assigneeId. Only one person can take it. */
function give_request(array $request, int $assigneeId): int
{
    $taskId = transaction(function () use ($request, $assigneeId) {
        $taken = q("UPDATE requests SET status = 'TAKEN', taken_by_id = ?, taken_at = ?, updated_at = ? WHERE id = ? AND status = 'OPEN'",
            [$assigneeId, now_utc(), now_utc(), $request['id']])->rowCount();
        if ($taken === 0) {
            return null;
        }
        $taskId = insert('tasks', [
            'association_id' => aid(),
            'title' => $request['title'],
            'description' => $request['description'],
            'priority' => $request['priority'],
            'due_date' => $request['due_date'],
            'pole_id' => $request['pole_id'],
            'project_id' => $request['project_id'],
            'assignee_id' => $assigneeId,
            'creator_id' => $request['requester_id'],
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ]);
        update('requests', ['task_id' => $taskId], 'id = ?', [$request['id']]);
        return $taskId;
    });
    return $taskId ?? redirect("/requests/{$request['id']}", t('requests.alreadyTaken'), 'error');
}

function requests_take(int $id): void
{
    $user = require_member();
    $request = find_or_404('requests', $id);
    if (!is_manager($user) && !in_array((int) $request['pole_id'], my_pole_ids($user['id']), true)) {
        redirect("/requests/$id", t('requests.notInPole'), 'error');
    }
    $taskId = give_request($request, $user['id']);
    log_activity('took', 'request', $id, $request['title']);
    notify([$request['requester_id']], 'request.taken', ['title' => $request['title']], "/requests/$id");
    redirect("/tasks/$taskId", t('requests.takenToast'));
}

function requests_assign(int $id): void
{
    $user = require_member();
    $request = find_or_404('requests', $id);
    ensure(can('assignRequest', $user, ['pole_lead_ids' => pole_lead_ids((int) $request['pole_id'])]));
    $assigneeId = post_id('user_id');
    if (!$assigneeId || !in_array($assigneeId, pole_member_ids((int) $request['pole_id']), true)) {
        redirect("/requests/$id", t('requests.notInPole'), 'error');
    }
    $taskId = give_request($request, $assigneeId);
    $assignee = (string) val('SELECT name FROM users WHERE id = ?', [$assigneeId]);
    log_activity('took', 'request', $id, $request['title'], $assigneeId);
    notify([$assigneeId], 'request.assigned', ['title' => $request['title']], "/tasks/$taskId");
    notify([$request['requester_id']], 'request.taken', ['title' => $request['title'], 'actor' => $assignee], "/requests/$id");
    redirect("/requests/$id", t('common.saved'));
}

/** The person who took it (or a manager) gives the request back to the pole. */
function requests_release(int $id): void
{
    $user = require_member();
    $request = find_or_404('requests', $id);
    ensure((int) $request['taken_by_id'] === $user['id'] || is_manager($user));
    if ($request['status'] !== 'TAKEN') {
        abort(403);
    }
    update('requests', ['status' => 'OPEN', 'taken_by_id' => null, 'taken_at' => null, 'task_id' => null, 'updated_at' => now_utc()], 'id = ?', [$id]);
    if ($request['task_id']) {
        q("DELETE FROM tasks WHERE id = ? AND association_id = ? AND status != 'DONE'", [$request['task_id'], aid()]);
    }
    redirect("/requests/$id", t('common.saved'));
}

function requests_cancel(int $id): void
{
    $user = require_member();
    $request = find_or_404('requests', $id);
    ensure(can('cancelRequest', $user, $request));
    if ($request['status'] !== 'OPEN') {
        redirect("/requests/$id", t('requests.alreadyTaken'), 'error');
    }
    update('requests', ['status' => 'CANCELLED', 'closed_at' => now_utc(), 'updated_at' => now_utc()], 'id = ?', [$id]);
    redirect("/requests/$id", t('common.saved'));
}
