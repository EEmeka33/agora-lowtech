<?php
/*
 * Members, invitations and join codes.
 */
require_once APP . '/pages/volunteering.php';

function members_list(): void
{
    $user = require_member();
    $status = in_array(get_str('status'), ['INACTIVE', 'all'], true) ? get_str('status') : 'ACTIVE';
    $year = (int) date('Y');
    $members = rows(
        'SELECT m.*, u.name, u.email, u.phone, u.password_hash IS NOT NULL AS activated, u.last_login_at,
           (SELECT GROUP_CONCAT(p.name, \', \') FROM pole_members pm JOIN poles p ON p.id = pm.pole_id WHERE pm.user_id = u.id AND p.association_id = m.association_id) AS poles,
           (SELECT 1 FROM dues d WHERE d.user_id = u.id AND d.association_id = m.association_id AND d.year = ?) AS paid
         FROM members m JOIN users u ON u.id = m.user_id
         WHERE m.association_id = ?' . ($status === 'all' ? '' : ' AND m.status = ?') . ' ORDER BY u.name',
        $status === 'all' ? [$year, aid()] : [$year, aid(), $status]
    );
    render('members/list', compact('user', 'members', 'status', 'year'), t('members.title'));
}

function members_create(): void
{
    $user = require_member();
    ensure(can('manageMembers', $user));
    $name = post_str('name');
    $email = strtolower(post_str('email'));
    $errors = [];
    if ($name === '') {
        $errors['name'] = t('validation.required');
    }
    if (!valid_email($email)) {
        $errors['email'] = t('validation.email');
    }
    $account = $errors ? null : row('SELECT * FROM users WHERE email = ?', [$email]);
    if ($account && val('SELECT 1 FROM members WHERE association_id = ? AND user_id = ?', [aid(), $account['id']])) {
        $errors['email'] = t('members.emailTaken');
    }
    if ($errors) {
        fail_form($errors, 'members_list');
    }
    $poles = array_map('intval', post_all('poles'));
    foreach ($poles as $poleId) {
        assert_refs(['pole_id' => $poleId]);
    }
    $userId = $account ? (int) $account['id'] : insert('users', [
        'name' => $name,
        'email' => $email,
        'phone' => post_opt('phone'),
        'locale' => current_association()['locale'],
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    insert('members', [
        'association_id' => aid(),
        'user_id' => $userId,
        'role' => post_enum('role', ROLES, 'MEMBER'),
        'board_position' => post_enum('board_position', BOARD_POSITIONS, null),
        'title' => post_opt('title'),
        'joined_at' => post_date('joined_at') ?? today(),
        'created_at' => now_utc(),
    ]);
    foreach ($poles as $poleId) {
        q('INSERT OR IGNORE INTO pole_members (pole_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)', [$poleId, $userId, 'MEMBER', now_utc()]);
    }
    log_activity('invited', 'member', $userId, $account['name'] ?? $name);
    if ($account) {
        redirect("/members/$userId", t('members.addedExisting'));
    }
    if (post_bool('invite')) {
        $link = new_invitation($userId, 'INVITE', aid(), $user['id'], 7 * 24);
        redirect("/members/$userId", t('members.inviteCreated') . ' ' . $link);
    }
    redirect("/members/$userId", t('common.created'));
}

function members_show(int $id): void
{
    $user = require_member();
    $member = row('SELECT m.*, u.name, u.email, u.phone, u.bio, u.skills, u.password_hash IS NOT NULL AS activated, u.last_login_at,
                     (SELECT COUNT(*) FROM members x WHERE x.user_id = u.id) AS association_count
                   FROM members m JOIN users u ON u.id = m.user_id WHERE m.association_id = ? AND m.user_id = ?', [aid(), $id]) ?? abort(404);
    require_once APP . '/pages/tasks.php';
    render('members/show', [
        'user' => $user,
        'member' => $member,
        'poles' => rows('SELECT p.id, p.name, pm.role FROM pole_members pm JOIN poles p ON p.id = pm.pole_id WHERE pm.user_id = ? AND p.association_id = ? ORDER BY p.name', [$id, aid()]),
        'projects' => rows("SELECT id, title, status FROM projects WHERE association_id = ? AND lead_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED')", [aid(), $id]),
        'tasks' => load_tasks("t.assignee_id = ? AND t.status != 'DONE'", [$id], 't.due_date IS NULL, t.due_date', 10),
        'minutes' => (int) val('SELECT COALESCE(SUM(minutes), 0) FROM time_entries WHERE association_id = ? AND user_id = ? AND date >= ?', [aid(), $id, date('Y') . '-01-01']),
        'dues' => rows('SELECT * FROM dues WHERE association_id = ? AND user_id = ? ORDER BY year DESC', [aid(), $id]),
        'canEdit' => is_admin($user) || $user['id'] === $id,
        // Account details are shared by all associations: an admin may only change
        // them for people who haven't activated their account yet.
        'canEditAccount' => $user['id'] === $id || !$member['activated'],
    ], $member['name']);
}

function members_update(int $id): void
{
    $user = require_member();
    ensure(is_admin($user) || $user['id'] === $id);
    $member = row('SELECT m.*, u.password_hash, u.email FROM members m JOIN users u ON u.id = m.user_id WHERE m.association_id = ? AND m.user_id = ?', [aid(), $id]) ?? abort(404);
    $again = fn () => members_show($id);
    if ($user['id'] === $id || !$member['password_hash']) {
        $name = post_str('name');
        $email = strtolower(post_str('email'));
        $errors = [];
        if ($name === '') {
            $errors['name'] = t('validation.required');
        }
        if (!valid_email($email)) {
            $errors['email'] = t('validation.email');
        } elseif ($email !== $member['email'] && val('SELECT 1 FROM users WHERE email = ?', [$email])) {
            $errors['email'] = t('members.emailTaken');
        }
        if ($errors) {
            fail_form($errors, $again);
        }
        update('users', ['name' => $name, 'email' => $email, 'phone' => post_opt('phone'), 'bio' => post_opt('bio'),
            'skills' => post_opt('skills'), 'updated_at' => now_utc()], 'id = ?', [$id]);
    }
    if (is_admin($user) && isset($_POST['role'])) {
        $role = post_enum('role', ROLES, 'MEMBER');
        if ($user['id'] === $id && $role !== 'ADMIN') {
            fail_form(['role' => t('members.cannotDemoteSelf')], $again);
        }
        update('members', ['role' => $role, 'board_position' => post_enum('board_position', BOARD_POSITIONS, null),
            'title' => post_opt('title'), 'joined_at' => post_date('joined_at') ?? $member['joined_at']], 'id = ?', [$member['id']]);
    } elseif (isset($_POST['title'])) {
        update('members', ['title' => post_opt('title')], 'id = ?', [$member['id']]);
    }
    log_activity('updated', 'member', $id, post_str('name') ?: null);
    redirect("/members/$id", t('common.saved'));
}

/** Activation link (account never activated) or password reset link. */
function members_link(int $id): void
{
    $user = require_member();
    ensure(can('manageMembers', $user));
    $account = row('SELECT u.*, (SELECT COUNT(*) FROM members x WHERE x.user_id = u.id) AS association_count FROM users u
                    JOIN members m ON m.user_id = u.id AND m.association_id = ? WHERE u.id = ?', [aid(), $id]) ?? abort(404);
    if ($account['password_hash'] && (int) $account['association_count'] > 1) {
        // Resetting someone's password is only allowed when they belong to this association only.
        redirect("/members/$id", t('members.resetOnlySingle'), 'error');
    }
    $link = $account['password_hash']
        ? new_invitation($id, 'RESET', aid(), $user['id'], 24)
        : new_invitation($id, 'INVITE', aid(), $user['id'], 7 * 24);
    redirect("/members/$id", t('members.inviteCreated') . ' ' . $link);
}

function members_status(int $id): void
{
    $user = require_member();
    ensure(can('manageMembers', $user));
    $status = post_enum('status', MEMBER_STATUSES, 'ACTIVE');
    if ($id === $user['id'] && $status !== 'ACTIVE') {
        redirect("/members/$id", t('members.cannotDeleteSelf'), 'error');
    }
    update('members', ['status' => $status], 'association_id = ? AND user_id = ?', [aid(), $id]);
    redirect("/members/$id", t('common.saved'));
}

/** Removes someone from the association (and deletes accounts nobody activated). */
function members_delete(int $id): void
{
    $user = require_member();
    ensure(can('manageMembers', $user));
    if ($id === $user['id']) {
        redirect("/members/$id", t('members.cannotDeleteSelf'), 'error');
    }
    $account = row('SELECT u.*, (SELECT COUNT(*) FROM members x WHERE x.user_id = u.id) AS association_count FROM users u
                    JOIN members m ON m.user_id = u.id AND m.association_id = ? WHERE u.id = ?', [aid(), $id]) ?? abort(404);
    transaction(function () use ($id, $account) {
        q('DELETE FROM members WHERE association_id = ? AND user_id = ?', [aid(), $id]);
        q('DELETE FROM pole_members WHERE user_id = ? AND pole_id IN (SELECT id FROM poles WHERE association_id = ?)', [$id, aid()]);
        q('DELETE FROM project_members WHERE user_id = ? AND project_id IN (SELECT id FROM projects WHERE association_id = ?)', [$id, aid()]);
        if (!$account['password_hash'] && (int) $account['association_count'] <= 1) {
            q('DELETE FROM users WHERE id = ?', [$id]);
        }
    });
    log_activity('deleted', 'member', null, $account['name']);
    redirect('/members', t('common.deleted'));
}

function members_csv(): void
{
    ensure(can('exportMembers', require_member()));
    $year = (int) date('Y');
    $lines = [[t('common.name'), t('common.email'), t('common.phone'), t('members.role'), t('members.boardPosition'), t('members.memberTitle'), t('common.poles'), t('members.joinedAt'), t('common.status'), t('finance.dues') . " $year", t('members.skills')]];
    foreach (rows('SELECT m.*, u.name, u.email, u.phone, u.skills,
                     (SELECT GROUP_CONCAT(p.name, \', \') FROM pole_members pm JOIN poles p ON p.id = pm.pole_id WHERE pm.user_id = u.id AND p.association_id = m.association_id) AS poles,
                     (SELECT 1 FROM dues d WHERE d.user_id = u.id AND d.association_id = m.association_id AND d.year = ?) AS paid
                   FROM members m JOIN users u ON u.id = m.user_id WHERE m.association_id = ? ORDER BY u.name', [$year, aid()]) as $m) {
        $lines[] = [$m['name'], $m['email'], $m['phone'], enum_label('role', $m['role']), enum_label('boardPosition', $m['board_position']),
            $m['title'], $m['poles'], $m['joined_at'], enum_label('memberStatus', $m['status']), $m['paid'] ? t('common.yes') : t('common.no'), $m['skills']];
    }
    send_download(csv($lines), 'members.csv', 'text/csv; charset=utf-8');
}

// ─── Join codes ────────────────────────────────────────────────────────────

function members_codes(): void
{
    $user = require_member();
    ensure(can('manageCodes', $user));
    $codes = rows('SELECT j.*, p.name AS pole_name, u.name AS creator_name FROM join_codes j
                   LEFT JOIN poles p ON p.id = j.pole_id LEFT JOIN users u ON u.id = j.created_by_id
                   WHERE j.association_id = ? ORDER BY j.revoked_at IS NOT NULL, j.created_at DESC', [aid()]);
    render('members/codes', ['user' => $user, 'codes' => $codes], t('codes.title'));
}

function members_code_create(): void
{
    $user = require_member();
    ensure(can('manageCodes', $user));
    $role = post_enum('role', ROLES, 'MEMBER');
    if ($role !== 'MEMBER' && !is_admin($user)) {
        fail_form(['role' => t('codes.managerOnlyMembers')], 'members_codes');
    }
    $poleId = post_id('pole_id');
    assert_refs(['pole_id' => $poleId]);
    $days = post_str('expires');
    do {
        $code = new_join_code();
    } while (val('SELECT 1 FROM join_codes WHERE code = ?', [$code]));
    insert('join_codes', [
        'association_id' => aid(),
        'code' => $code,
        'label' => post_opt('label'),
        'role' => $role,
        'pole_id' => $poleId,
        'max_uses' => clamp(post_int('max_uses', 10), 1, 500),
        'expires_at' => in_array($days, ['7', '30', '90'], true) ? utc_in((int) $days * 86400) : null,
        'created_by_id' => $user['id'],
        'created_at' => now_utc(),
    ]);
    log_activity('created', 'member', null, $code);
    redirect('/members/codes', t('codes.created', ['code' => $code]));
}

function members_code_revoke(int $id): void
{
    ensure(can('manageCodes', require_member()));
    find_or_404('join_codes', $id);
    update('join_codes', ['revoked_at' => now_utc()], 'id = ?', [$id]);
    redirect('/members/codes', t('common.saved'));
}
