<?php
/*
 * Tasks: a board (one column per status) or a list, filters, comments.
 * Cards move between columns with ◀ ▶ buttons — small forms, no drag and drop.
 */

/** Tasks with their project, pole and assignee names. $where uses "t." for tasks. */
function load_tasks(string $where, array $params, string $order = 't.position, t.created_at DESC', int $limit = 500): array
{
    return rows(
        "SELECT t.*, p.title AS project_title, p.lead_id AS project_lead_id, po.name AS pole_name, u.name AS assignee_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN poles po ON po.id = t.pole_id
         LEFT JOIN users u ON u.id = t.assignee_id
         WHERE t.association_id = ? AND ($where) ORDER BY $order LIMIT $limit",
        [aid(), ...$params]
    );
}

function load_task(int $id): array
{
    return load_tasks('t.id = ?', [$id])[0] ?? abort(404);
}

function tasks_list(): void
{
    $user = require_member();
    $scope = in_array(get_str('scope'), ['mine', 'all', 'unassigned'], true) ? get_str('scope') : 'all';
    $view = get_str('view') === 'list' ? 'list' : 'board';
    $where = ['1 = 1'];
    $params = [];
    if ($scope === 'mine') {
        $where[] = 't.assignee_id = ?';
        $params[] = $user['id'];
    } elseif ($scope === 'unassigned') {
        $where[] = 't.assignee_id IS NULL';
    }
    foreach (['project' => 't.project_id', 'pole' => 't.pole_id', 'person' => 't.assignee_id'] as $key => $column) {
        if (ctype_digit(get_str($key))) {
            $where[] = "$column = ?";
            $params[] = (int) get_str($key);
        }
    }
    if (in_array(get_str('priority'), PRIORITIES, true)) {
        $where[] = 't.priority = ?';
        $params[] = get_str('priority');
    }
    // Done tasks: only the last 30 days on the board, to keep the page light.
    if ($view === 'board') {
        $where[] = "(t.status != 'DONE' OR t.completed_at > ?)";
        $params[] = utc_in(-30 * 86400);
    }
    $tasks = load_tasks(implode(' AND ', $where), $params, "t.due_date IS NULL, t.due_date, t.created_at DESC");
    render('tasks/list', [
        'user' => $user,
        'tasks' => $tasks,
        'scope' => $scope,
        'view' => $view,
        'filters' => ['project' => get_str('project'), 'pole' => get_str('pole'), 'person' => get_str('person'), 'priority' => get_str('priority')],
    ], t('tasks.title'));
}

function read_task(): array
{
    return [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'status' => post_enum('status', TASK_STATUSES, 'TODO'),
        'priority' => post_enum('priority', PRIORITIES, 'MEDIUM'),
        'due_date' => post_date('due_date'),
        'project_id' => post_id('project_id'),
        'pole_id' => post_id('pole_id'),
        'assignee_id' => post_id('assignee_id'),
    ];
}

function tasks_create(): void
{
    $user = require_member();
    $data = read_task();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], 'tasks_list');
    }
    assert_refs(['project_id' => $data['project_id'], 'pole_id' => $data['pole_id'], 'user_ids' => [$data['assignee_id']]]);
    if ($data['project_id'] && !$data['pole_id']) {
        $data['pole_id'] = val('SELECT pole_id FROM projects WHERE id = ?', [$data['project_id']]);
    }
    $id = insert('tasks', $data + [
        'association_id' => aid(),
        'creator_id' => $user['id'],
        'completed_at' => $data['status'] === 'DONE' ? now_utc() : null,
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    log_activity('created', 'task', $id, $data['title']);
    notify([$data['assignee_id']], 'task.assigned', ['title' => $data['title']], "/tasks/$id");
    back("/tasks/$id", t('common.created'));
}

function tasks_show(int $id): void
{
    $user = require_member();
    $task = load_task($id);
    $comments = rows('SELECT c.*, u.name AS author_name FROM task_comments c LEFT JOIN users u ON u.id = c.author_id
                      WHERE c.task_id = ? ORDER BY c.created_at, c.id', [$id]);
    $request = row('SELECT id, title FROM requests WHERE task_id = ?', [$id]);
    $creator = $task['creator_id'] ? val('SELECT name FROM users WHERE id = ?', [$task['creator_id']]) : null;
    $minutes = (int) val('SELECT COALESCE(SUM(minutes), 0) FROM time_entries WHERE task_id = ? AND association_id = ?', [$id, aid()]);
    render('tasks/show', compact('user', 'task', 'comments', 'request', 'creator', 'minutes'), $task['title']);
}

function editable_task(int $id): array
{
    $user = require_member();
    $task = load_task($id);
    ensure(can('editTask', $user, $task));
    return $task;
}

/** Keeps a request in sync with the task created from it. */
function sync_request(int $taskId, string $status): void
{
    $request = row('SELECT * FROM requests WHERE task_id = ?', [$taskId]);
    if (!$request) {
        return;
    }
    if ($status === 'DONE' && $request['status'] !== 'DONE') {
        update('requests', ['status' => 'DONE', 'closed_at' => now_utc(), 'updated_at' => now_utc()], 'id = ?', [$request['id']]);
        notify([$request['requester_id']], 'request.done', ['title' => $request['title']], "/requests/{$request['id']}");
    } elseif ($status !== 'DONE' && $request['status'] === 'DONE') {
        update('requests', ['status' => 'TAKEN', 'closed_at' => null], 'id = ?', [$request['id']]);
    }
}

function tasks_update(int $id): void
{
    $task = editable_task($id);
    $data = read_task();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], fn () => tasks_show($id));
    }
    assert_refs(['project_id' => $data['project_id'], 'pole_id' => $data['pole_id'], 'user_ids' => [$data['assignee_id']]]);
    $becameDone = $data['status'] === 'DONE' && $task['status'] !== 'DONE';
    update('tasks', $data + [
        'completed_at' => $data['status'] === 'DONE' ? ($task['completed_at'] ?? now_utc()) : null,
        'updated_at' => now_utc(),
    ], 'id = ?', [$id]);
    log_activity($becameDone ? 'completed' : 'updated', 'task', $id, $data['title']);
    if ($data['assignee_id'] && $data['assignee_id'] !== ($task['assignee_id'] ? (int) $task['assignee_id'] : null)) {
        notify([$data['assignee_id']], 'task.assigned', ['title' => $data['title']], "/tasks/$id");
    }
    if ($data['status'] !== $task['status']) {
        sync_request($id, $data['status']);
    }
    redirect("/tasks/$id", t('common.saved'));
}

function tasks_status(int $id): void
{
    $task = editable_task($id);
    $next = post_enum('status', TASK_STATUSES, 'TODO');
    if ($next !== $task['status']) {
        update('tasks', ['status' => $next, 'completed_at' => $next === 'DONE' ? now_utc() : null, 'updated_at' => now_utc()], 'id = ?', [$id]);
        if ($next === 'DONE' || $task['status'] === 'DONE') {
            log_activity($next === 'DONE' ? 'completed' : 'reopened', 'task', $id, $task['title']);
        }
        sync_request($id, $next);
    }
    back("/tasks/$id");
}

function tasks_delete(int $id): void
{
    $user = require_member();
    $task = load_task($id);
    ensure(can('deleteTask', $user, $task));
    // A request whose task is deleted goes back to its pole.
    q("UPDATE requests SET status = 'OPEN', taken_by_id = NULL, taken_at = NULL WHERE task_id = ? AND status = 'TAKEN'", [$id]);
    q('DELETE FROM tasks WHERE id = ?', [$id]);
    log_activity('deleted', 'task', null, $task['title']);
    redirect('/tasks', t('common.deleted'));
}

function tasks_comment(int $id): void
{
    $user = require_member();
    $task = load_task($id);
    $body = post_str('body');
    if ($body === '') {
        fail_form(['body' => t('validation.required')], fn () => tasks_show($id));
    }
    $previous = col('SELECT DISTINCT author_id FROM task_comments WHERE task_id = ?', [$id]);
    insert('task_comments', ['task_id' => $id, 'body' => $body, 'author_id' => $user['id'], 'created_at' => now_utc()]);
    log_activity('commented', 'task', $id, $task['title']);
    // The assignee, the creator and everyone already in the conversation.
    notify([$task['assignee_id'], $task['creator_id'], ...$previous], 'task.comment', ['title' => $task['title']], "/tasks/$id");
    redirect("/tasks/$id#comments");
}

function tasks_uncomment(int $id, int $commentId): void
{
    $user = require_member();
    load_task($id);
    $comment = row('SELECT * FROM task_comments WHERE id = ? AND task_id = ?', [$commentId, $id]) ?? abort(404);
    ensure((int) $comment['author_id'] === $user['id'] || is_admin($user));
    q('DELETE FROM task_comments WHERE id = ?', [$commentId]);
    redirect("/tasks/$id#comments", t('common.deleted'));
}
