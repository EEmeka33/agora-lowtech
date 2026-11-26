<?php
/*
 * Projects: lead, team, dates, budget, milestones, progress updates, automatic
 * progress and health. Views: list, or a timeline drawn as a table (one cell per month).
 */
require_once APP . '/pages/tasks.php';

function projects_list(): void
{
    $user = require_member();
    $filter = in_array(get_str('filter'), ['active', 'all', 'done'], true) ? get_str('filter') : 'active';
    $view = get_str('view') === 'timeline' ? 'timeline' : 'list';
    $where = match ($filter) {
        'active' => "AND p.status NOT IN ('COMPLETED', 'CANCELLED')",
        'done' => "AND p.status IN ('COMPLETED', 'CANCELLED')",
        default => '',
    };
    $projects = with_progress(rows(
        "SELECT p.*, u.name AS lead_name, po.name AS pole_name FROM projects p
         LEFT JOIN users u ON u.id = p.lead_id LEFT JOIN poles po ON po.id = p.pole_id
         WHERE p.association_id = ? $where ORDER BY p.status = 'IN_PROGRESS' DESC, p.due_date IS NULL, p.due_date, p.title",
        [aid()]
    ));
    $leadPoles = array_map('intval', col("SELECT pm.pole_id FROM pole_members pm JOIN poles p ON p.id = pm.pole_id WHERE pm.user_id = ? AND pm.role = 'LEAD' AND p.association_id = ?", [$user['id'], aid()]));
    render('projects/list', compact('user', 'projects', 'filter', 'view', 'leadPoles'), t('projects.title'));
}

function read_project(): array
{
    return [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'objectives' => post_opt('objectives'),
        'status' => post_enum('status', PROJECT_STATUSES, 'PLANNED'),
        'priority' => post_enum('priority', PRIORITIES, 'MEDIUM'),
        'progress_mode' => post_enum('progress_mode', ['AUTO', 'MANUAL'], 'AUTO'),
        'manual_progress' => clamp(post_int('manual_progress'), 0, 100),
        'start_date' => post_date('start_date'),
        'due_date' => post_date('due_date'),
        'budget_cents' => post_money('budget'),
        'pole_id' => post_id('pole_id'),
        'lead_id' => post_id('lead_id'),
    ];
}

function project_errors(array $data): array
{
    $errors = [];
    if ($data['title'] === '') {
        $errors['title'] = t('validation.required');
    }
    if ($data['start_date'] && $data['due_date'] && $data['due_date'] < $data['start_date']) {
        $errors['due_date'] = t('validation.dateOrder');
    }
    return $errors;
}

function projects_create(): void
{
    $user = require_member();
    $data = read_project();
    assert_refs(['pole_id' => $data['pole_id'], 'user_ids' => [$data['lead_id']]]);
    // Pole leads may create projects in their pole.
    ensure(can('createProject', $user) || in_array($user['id'], pole_lead_ids($data['pole_id']), true));
    if ($errors = project_errors($data)) {
        fail_form($errors, 'projects_list');
    }
    $id = insert('projects', $data + ['association_id' => aid(), 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    if ($data['lead_id']) {
        insert('project_members', ['project_id' => $id, 'user_id' => $data['lead_id'], 'joined_at' => now_utc()]);
    }
    log_activity('created', 'project', $id, $data['title']);
    redirect("/projects/$id", t('common.created'));
}

/** A project of the association that the person may edit. */
function editable_project(int $id): array
{
    $user = require_member();
    $project = find_or_404('projects', $id);
    ensure(can('editProject', $user, $project + ['pole_lead_ids' => pole_lead_ids($project['pole_id'] ? (int) $project['pole_id'] : null)]));
    return $project;
}

function projects_show(int $id): void
{
    $user = require_member();
    $project = row(
        'SELECT p.*, u.name AS lead_name, po.name AS pole_name FROM projects p
         LEFT JOIN users u ON u.id = p.lead_id LEFT JOIN poles po ON po.id = p.pole_id
         WHERE p.id = ? AND p.association_id = ?',
        [$id, aid()]
    ) ?? abort(404);
    $project = with_progress([$project])[0];
    $tab = in_array(get_str('tab'), ['overview', 'tasks', 'updates', 'team', 'budget'], true) ? get_str('tab') : 'overview';
    $team = rows('SELECT pm.*, u.name FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY u.name', [$id]);
    $vars = [
        'user' => $user,
        'project' => $project,
        'tab' => $tab,
        'team' => $team,
        'canEdit' => can('editProject', $user, $project + ['pole_lead_ids' => pole_lead_ids($project['pole_id'] ? (int) $project['pole_id'] : null)]),
        'isTeam' => in_array($user['id'], array_map('intval', array_column($team, 'user_id')), true),
        'spent' => (int) val("SELECT COALESCE(SUM(amount_cents), 0) FROM transactions WHERE association_id = ? AND project_id = ? AND type = 'EXPENSE'", [aid(), $id]),
        'milestones' => rows('SELECT * FROM milestones WHERE project_id = ? ORDER BY position, id', [$id]),
        'latest' => row('SELECT pu.*, u.name AS author_name FROM project_updates pu LEFT JOIN users u ON u.id = pu.author_id WHERE pu.project_id = ? ORDER BY pu.created_at DESC LIMIT 1', [$id]),
    ];
    if ($tab === 'tasks') {
        $vars['tasks'] = load_tasks('t.project_id = ?', [$id], "t.status = 'DONE', t.due_date IS NULL, t.due_date");
    } elseif ($tab === 'updates') {
        $vars['updates'] = rows('SELECT pu.*, u.name AS author_name FROM project_updates pu LEFT JOIN users u ON u.id = pu.author_id WHERE pu.project_id = ? ORDER BY pu.created_at DESC', [$id]);
    } elseif ($tab === 'budget') {
        $vars['transactions'] = rows('SELECT * FROM transactions WHERE association_id = ? AND project_id = ? ORDER BY date DESC', [aid(), $id]);
    } elseif ($tab === 'overview') {
        $vars['events'] = rows('SELECT id, title, start_at FROM events WHERE association_id = ? AND project_id = ? ORDER BY start_at DESC LIMIT 5', [aid(), $id]);
        $vars['documents'] = rows('SELECT id, title, url, stored_name FROM documents WHERE association_id = ? AND project_id = ? ORDER BY created_at DESC LIMIT 5', [aid(), $id]);
    }
    render('projects/show', $vars, $project['title']);
}

function projects_update(int $id): void
{
    $project = editable_project($id);
    $data = read_project();
    if ($errors = project_errors($data)) {
        fail_form($errors, fn () => projects_show($id));
    }
    assert_refs(['pole_id' => $data['pole_id'], 'user_ids' => [$data['lead_id']]]);
    update('projects', $data + ['updated_at' => now_utc()], 'id = ?', [$id]);
    if ($data['lead_id']) {
        q('INSERT OR IGNORE INTO project_members (project_id, user_id, joined_at) VALUES (?, ?, ?)', [$id, $data['lead_id'], now_utc()]);
    }
    log_activity($data['status'] === 'COMPLETED' && $project['status'] !== 'COMPLETED' ? 'completed' : 'updated', 'project', $id, $data['title']);
    redirect("/projects/$id", t('common.saved'));
}

function projects_delete(int $id): void
{
    $project = editable_project($id);
    q('DELETE FROM projects WHERE id = ?', [$id]);
    log_activity('deleted', 'project', null, $project['title']);
    redirect('/projects', t('common.deleted'));
}

// ─── Milestones ────────────────────────────────────────────────────────────

function projects_add_milestone(int $id): void
{
    editable_project($id);
    $title = post_str('title');
    if ($title === '') {
        fail_form(['title' => t('validation.required')], fn () => projects_show($id));
    }
    $count = (int) val('SELECT COUNT(*) FROM milestones WHERE project_id = ?', [$id]);
    insert('milestones', ['project_id' => $id, 'title' => $title, 'due_date' => post_date('due_date'), 'position' => $count, 'created_at' => now_utc()]);
    redirect("/projects/$id", t('common.created'));
}

function projects_toggle_milestone(int $id, int $milestoneId): void
{
    editable_project($id);
    $m = row('SELECT * FROM milestones WHERE id = ? AND project_id = ?', [$milestoneId, $id]) ?? abort(404);
    update('milestones', ['done' => $m['done'] ? 0 : 1, 'done_at' => $m['done'] ? null : now_utc()], 'id = ?', [$milestoneId]);
    if (!$m['done']) {
        log_activity('completed', 'milestone', $id, $m['title']);
    }
    redirect("/projects/$id");
}

function projects_delete_milestone(int $id, int $milestoneId): void
{
    editable_project($id);
    q('DELETE FROM milestones WHERE id = ? AND project_id = ?', [$milestoneId, $id]);
    redirect("/projects/$id", t('common.deleted'));
}

// ─── Updates and team ──────────────────────────────────────────────────────

function projects_post_update(int $id): void
{
    $user = require_member();
    $project = find_or_404('projects', $id);
    $isTeam = (bool) val('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$id, $user['id']]);
    ensure($isTeam || can('editProject', $user, $project + ['pole_lead_ids' => pole_lead_ids($project['pole_id'] ? (int) $project['pole_id'] : null)]));
    $body = post_str('body');
    if ($body === '') {
        fail_form(['body' => t('validation.required')], fn () => projects_show($id));
    }
    insert('project_updates', [
        'project_id' => $id,
        'author_id' => $user['id'],
        'body' => $body,
        'health' => post_enum('health', HEALTH, 'ON_TRACK'),
        'progress' => post_str('progress') === '' ? null : clamp(post_int('progress'), 0, 100),
        'created_at' => now_utc(),
    ]);
    log_activity('posted', 'update', $id, $project['title']);
    redirect("/projects/$id?tab=updates", t('common.created'));
}

function projects_add_member(int $id): void
{
    editable_project($id);
    $userId = post_id('user_id') ?? abort(422);
    assert_refs(['user_ids' => [$userId]]);
    q('INSERT INTO project_members (project_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)
       ON CONFLICT (project_id, user_id) DO UPDATE SET role = excluded.role', [$id, $userId, post_opt('role'), now_utc()]);
    redirect("/projects/$id?tab=team", t('common.saved'));
}

function projects_remove_member(int $id, int $userId): void
{
    editable_project($id);
    q('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$id, $userId]);
    redirect("/projects/$id?tab=team", t('common.saved'));
}
