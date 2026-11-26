<?php
/*
 * Structure: organization chart, "who does what" (recurring duties), membership matrix.
 */

function structure_show(): void
{
    $user = require_member();
    $a = aid();
    $board = rows("SELECT m.board_position, u.id, u.name FROM members m JOIN users u ON u.id = m.user_id
                   WHERE m.association_id = ? AND m.status = 'ACTIVE' AND m.board_position IS NOT NULL", [$a]);
    $poles = rows("SELECT p.id, p.name,
                     (SELECT GROUP_CONCAT(u.name, ', ') FROM pole_members pm JOIN users u ON u.id = pm.user_id WHERE pm.pole_id = p.id AND pm.role = 'LEAD') AS leads
                   FROM poles p WHERE p.association_id = ? ORDER BY p.position, p.name", [$a]);
    $people = rows("SELECT u.id, u.name,
                      (SELECT COUNT(*) FROM tasks t WHERE t.association_id = m.association_id AND t.assignee_id = u.id AND t.status != 'DONE') AS open_tasks
                    FROM members m JOIN users u ON u.id = m.user_id WHERE m.association_id = ? AND m.status = 'ACTIVE' ORDER BY u.name", [$a]);
    $matrix = [];
    foreach (rows('SELECT pm.pole_id, pm.user_id, pm.role FROM pole_members pm JOIN poles p ON p.id = pm.pole_id WHERE p.association_id = ?', [$a]) as $r) {
        $matrix[$r['user_id']][$r['pole_id']] = $r['role'];
    }
    $duties = rows('SELECT r.*, u.name AS owner_name, p.name AS pole_name FROM responsibilities r
                    LEFT JOIN users u ON u.id = r.owner_id LEFT JOIN poles p ON p.id = r.pole_id
                    WHERE r.association_id = ? ORDER BY p.name IS NULL, p.name, r.title', [$a]);
    render('structure/show', compact('user', 'board', 'poles', 'people', 'matrix', 'duties'), t('structure.title'));
}

function responsibility_fields(array $d = []): string
{
    $v = fn ($k) => old($k, $d[$k] ?? '');
    return input_field('title', t('structure.responsibility'), $v('title'), ['required' => true])
        . select_field('frequency', t('structure.frequency'), enum_options('frequency', FREQUENCIES), $v('frequency') ?: 'ONGOING')
        . select_field('owner_id', t('common.owner'), member_options(), $v('owner_id'), ['empty' => t('common.unassigned')])
        . select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id'), ['empty' => t('common.none')])
        . textarea_field('description', t('common.description'), $v('description'), ['rows' => 2]);
}

function structure_edit(int $id): void
{
    ensure(can('manageStructure', require_member()));
    $duty = find_or_404('responsibilities', $id);
    render('edit', ['heading' => t('structure.editResponsibility'), 'back' => '/structure#duties', 'action' => "/responsibilities/$id",
        'fields' => responsibility_fields($duty), 'delete' => "/responsibilities/$id/delete"], $duty['title']);
}

function read_responsibility(): array
{
    return [
        'title' => post_str('title'),
        'description' => post_opt('description'),
        'frequency' => post_enum('frequency', FREQUENCIES, 'ONGOING'),
        'owner_id' => post_id('owner_id'),
        'pole_id' => post_id('pole_id'),
    ];
}

function structure_create(): void
{
    ensure(can('manageStructure', require_member()));
    $data = read_responsibility();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], 'structure_show');
    }
    assert_refs(['pole_id' => $data['pole_id'], 'user_ids' => [$data['owner_id']]]);
    $id = insert('responsibilities', $data + ['association_id' => aid(), 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    log_activity('created', 'responsibility', $id, $data['title']);
    redirect('/structure#duties', t('common.created'));
}

function structure_update(int $id): void
{
    ensure(can('manageStructure', require_member()));
    find_or_404('responsibilities', $id);
    $data = read_responsibility();
    if ($data['title'] === '') {
        fail_form(['title' => t('validation.required')], fn () => structure_edit($id));
    }
    assert_refs(['pole_id' => $data['pole_id'], 'user_ids' => [$data['owner_id']]]);
    update('responsibilities', $data + ['updated_at' => now_utc()], 'id = ?', [$id]);
    redirect('/structure#duties', t('common.saved'));
}

function structure_delete(int $id): void
{
    ensure(can('manageStructure', require_member()));
    find_or_404('responsibilities', $id);
    q('DELETE FROM responsibilities WHERE id = ?', [$id]);
    redirect('/structure#duties', t('common.deleted'));
}
