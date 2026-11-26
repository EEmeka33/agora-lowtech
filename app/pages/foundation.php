<?php
/*
 * The founding checklist: from the purpose of the association to its bank account.
 */
require_once APP . '/pages/welcome.php';

function foundation_list(): void
{
    $user = require_member();
    $steps = rows('SELECT s.*, u.name AS assignee_name FROM foundation_steps s LEFT JOIN users u ON u.id = s.assignee_id
                   WHERE s.association_id = ? ORDER BY s.position, s.id', [aid()]);
    render('foundation/list', ['user' => $user, 'steps' => $steps, 'progress' => foundation_progress($steps)], t('foundation.title'));
}

function step_fields(array $s = []): string
{
    $v = fn ($k, $d = '') => old($k, $s[$k] ?? $d);
    return input_field('title', t('common.title'), $v('title'), ['required' => true, 'class' => 'wide'])
        . textarea_field('description', t('common.description'), $v('description'), ['rows' => 3])
        . select_field('category', t('common.category'), enum_options('stepCategory', STEP_CATEGORIES), $v('category', 'PROJECT'))
        . select_field('status', t('common.status'), enum_options('stepStatus', STEP_STATUSES), $v('status', 'TODO'))
        . select_field('assignee_id', t('common.assignee'), member_options(), $v('assignee_id'), ['empty' => t('common.unassigned')])
        . input_field('due_date', t('common.dueDate'), $v('due_date'), ['type' => 'date'])
        . input_field('resource_url', t('foundation.resourceUrl'), $v('resource_url'), ['type' => 'url', 'placeholder' => 'https://'])
        . textarea_field('notes', t('common.notes'), $v('notes'), ['rows' => 3]);
}

/** A step: its explanation for everyone, the form for those who may change it. */
function foundation_edit(int $id): void
{
    $user = require_member();
    find_or_404('foundation_steps', $id);
    $step = row('SELECT s.*, u.name AS assignee_name FROM foundation_steps s LEFT JOIN users u ON u.id = s.assignee_id WHERE s.id = ?', [$id]);
    render('foundation/step', ['user' => $user, 'step' => $step, 'canEdit' => can('editStep', $user, $step)], $step['title']);
}

function read_step(): array
{
    return [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'category' => post_enum('category', STEP_CATEGORIES, 'PROJECT'),
        'status' => post_enum('status', STEP_STATUSES, 'TODO'),
        'assignee_id' => post_id('assignee_id'),
        'due_date' => post_date('due_date'),
        'resource_url' => preg_match('#^https?://#i', post_str('resource_url')) ? post_str('resource_url') : null,
        'notes' => post_opt('notes'),
    ];
}

function foundation_create(): void
{
    ensure(can('manageFoundation', require_member()));
    $data = read_step();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], 'foundation_list');
    }
    assert_refs(['user_ids' => [$data['assignee_id']]]);
    $count = (int) val('SELECT COUNT(*) FROM foundation_steps WHERE association_id = ?', [aid()]);
    $id = insert('foundation_steps', $data + [
        'association_id' => aid(),
        'position' => $count,
        'completed_at' => $data['status'] === 'DONE' ? now_utc() : null,
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    log_activity('created', 'step', $id, $data['title']);
    redirect('/foundation', t('common.created'));
}

function foundation_update(int $id): void
{
    $user = require_member();
    $step = find_or_404('foundation_steps', $id);
    ensure(can('editStep', $user, $step));
    $data = read_step();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], fn () => foundation_edit($id));
    }
    assert_refs(['user_ids' => [$data['assignee_id']]]);
    update('foundation_steps', $data + [
        'completed_at' => $data['status'] === 'DONE' ? ($step['completed_at'] ?? now_utc()) : null,
        'updated_at' => now_utc(),
    ], 'id = ?', [$id]);
    log_activity($data['status'] === 'DONE' && $step['status'] !== 'DONE' ? 'completed' : 'updated', 'step', $id, $data['title']);
    redirect("/foundation#step-$id", t('common.saved'));
}

function foundation_status(int $id): void
{
    $user = require_member();
    $step = find_or_404('foundation_steps', $id);
    ensure(can('editStep', $user, $step));
    $next = post_enum('status', STEP_STATUSES, 'TODO');
    update('foundation_steps', ['status' => $next, 'completed_at' => $next === 'DONE' ? now_utc() : null, 'updated_at' => now_utc()], 'id = ?', [$id]);
    if ($next === 'DONE') {
        log_activity('completed', 'step', $id, $step['title']);
    }
    back("/foundation#step-$id");
}

function foundation_delete(int $id): void
{
    ensure(can('manageFoundation', require_member()));
    find_or_404('foundation_steps', $id);
    q('DELETE FROM foundation_steps WHERE id = ?', [$id]);
    redirect('/foundation', t('common.deleted'));
}

function foundation_template(): void
{
    ensure(can('manageFoundation', require_member()));
    add_foundation_template(aid(), locale());
    redirect('/foundation', t('common.created'));
}
