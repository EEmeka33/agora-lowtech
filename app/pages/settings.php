<?php
/*
 * Settings: profile, account and security, notifications and calendar,
 * association details (administrators), backup (administrators).
 */

function settings_render(string $tab, array $vars = []): void
{
    $vars['tab'] = $tab;
    render("settings/$tab", $vars, t('settings.title'), current_association() ? 'app' : 'auth');
}

function settings_profile(): void
{
    $account = require_account();
    settings_render('profile', ['me' => row('SELECT * FROM users WHERE id = ?', [$account['id']])]);
}

function settings_profile_submit(): void
{
    $account = require_account();
    $name = post_str('name');
    $email = strtolower(post_str('email'));
    $errors = [];
    if ($name === '') {
        $errors['name'] = t('validation.required');
    }
    if (!valid_email($email)) {
        $errors['email'] = t('validation.email');
    } elseif ($email !== $account['email'] && val('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $errors['email'] = t('members.emailTaken');
    }
    if ($errors) {
        fail_form($errors, 'settings_profile');
    }
    $locale = post_enum('locale', LOCALES, locale());
    update('users', ['name' => $name, 'email' => $email, 'phone' => post_opt('phone'), 'bio' => post_opt('bio'),
        'skills' => post_opt('skills'), 'locale' => $locale, 'updated_at' => now_utc()], 'id = ?', [$account['id']]);
    set_locale_cookie($locale);
    redirect('/settings', t('common.saved', [], $locale));
}

// ─── Account and security ──────────────────────────────────────────────────

function settings_account(): void
{
    $account = require_account();
    settings_render('account', [
        'account' => $account,
        'sessions' => rows('SELECT * FROM sessions WHERE user_id = ? AND expires_at > ? ORDER BY last_seen_at DESC', [$account['id'], now_utc()]),
    ]);
}

function settings_password(): void
{
    $account = require_account();
    $hash = (string) val('SELECT password_hash FROM users WHERE id = ?', [$account['id']]);
    $password = (string) ($_POST['password'] ?? '');
    if (!password_verify((string) ($_POST['current'] ?? ''), $hash)) {
        fail_form(['current' => t('settings.wrongPassword')], 'settings_account');
    }
    if ($e = password_error($password, $account['email'], $account['name'])) {
        fail_form(['password' => $e], 'settings_account');
    }
    if ($password !== ($_POST['confirm'] ?? '')) {
        fail_form(['confirm' => t('setup.passwordMismatch')], 'settings_account');
    }
    update('users', ['password_hash' => hash_password($password), 'password_changed_at' => now_utc()], 'id = ?', [$account['id']]);
    revoke_sessions($account['id'], $account['session_id']);
    redirect('/settings/account', t('settings.passwordUpdatedOthers'));
}

function settings_revoke(int $id): void
{
    $account = require_account();
    q('DELETE FROM sessions WHERE id = ? AND user_id = ? AND id != ?', [$id, $account['id'], $account['session_id']]);
    redirect('/settings/account', t('common.saved'));
}

function settings_revoke_others(): void
{
    $account = require_account();
    revoke_sessions($account['id'], $account['session_id']);
    redirect('/settings/account', t('common.saved'));
}

function is_last_admin(int $associationId, int $userId): bool
{
    $admins = array_map('intval', col("SELECT user_id FROM members WHERE association_id = ? AND role = 'ADMIN' AND status = 'ACTIVE'", [$associationId]));
    return $admins === [$userId];
}

function settings_leave(): void
{
    $account = require_account();
    $associationId = post_id('association_id') ?? abort(404);
    if (is_last_admin($associationId, $account['id'])) {
        redirect('/settings/account', t('settings.lastAdmin'), 'error');
    }
    q('DELETE FROM members WHERE association_id = ? AND user_id = ?', [$associationId, $account['id']]);
    q('DELETE FROM pole_members WHERE user_id = ? AND pole_id IN (SELECT id FROM poles WHERE association_id = ?)', [$account['id'], $associationId]);
    delete_cookie('agora_asso');
    redirect('/', t('settings.left'));
}

function settings_delete(): void
{
    $account = require_account();
    $hash = (string) val('SELECT password_hash FROM users WHERE id = ?', [$account['id']]);
    if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        fail_form(['password' => t('settings.wrongPassword')], 'settings_account');
    }
    foreach (my_associations() as $a) {
        if ($a['role'] === 'ADMIN' && is_last_admin((int) $a['id'], $account['id'])) {
            redirect('/settings/account', t('settings.deleteLastAdmin', ['name' => $a['name']]), 'error');
        }
    }
    // Everything the person created stays, without their name (ON DELETE SET NULL).
    q('DELETE FROM users WHERE id = ?', [$account['id']]);
    delete_cookie('agora_session');
    delete_cookie('agora_asso');
    redirect('/login', t('settings.deleted'));
}

// ─── Notifications and calendar ────────────────────────────────────────────

function settings_notifications(): void
{
    $account = require_account();
    settings_render('notifications', ['me' => row('SELECT * FROM users WHERE id = ?', [$account['id']]), 'calendarLink' => null]);
}

function settings_notifications_submit(): void
{
    $account = require_account();
    update('users', ['email_mode' => post_enum('email_mode', EMAIL_MODES, 'DAILY')], 'id = ?', [$account['id']]);
    redirect('/settings/notifications', t('common.saved'));
}

/** Creates (or replaces) the private calendar link. Only its hash is stored, so it is shown once. */
function settings_calendar(): void
{
    $account = require_account();
    $token = new_token();
    update('users', ['calendar_token_hash' => sha256($token)], 'id = ?', [$account['id']]);
    flash('ok', t('common.saved'));
    settings_render('notifications', ['me' => row('SELECT * FROM users WHERE id = ?', [$account['id']]), 'calendarLink' => absolute_url("/calendar/$token.ics")]);
}

// ─── Association (administrators) ──────────────────────────────────────────

function settings_association(): void
{
    $user = require_member();
    ensure(can('manageAssociation', $user));
    settings_render('association', ['assoc' => current_association()]);
}

function settings_association_submit(): void
{
    $user = require_member();
    ensure(can('manageAssociation', $user));
    $name = post_str('name');
    if ($name === '') {
        fail_form(['name' => t('validation.required')], 'settings_association');
    }
    $fields = ['short_name', 'tagline', 'purpose', 'registration_number', 'siret', 'email', 'phone', 'website', 'address', 'postal_code', 'city', 'country'];
    $data = ['name' => $name];
    foreach ($fields as $f) {
        $data[$f] = post_opt($f);
    }
    update('associations', $data + [
        'legal_form' => post_str('legal_form') ?: 'Loi 1901',
        'founded_at' => post_date('founded_at'),
        'locale' => post_enum('locale', LOCALES, 'en'),
        'currency' => post_enum('currency', CURRENCIES, 'EUR'),
        'membership_fee_cents' => post_money('membership_fee'),
        'volunteer_hour_cents' => post_money('volunteer_hour'),
        'fiscal_year_start_month' => clamp(post_int('fiscal_year_start_month', 1), 1, 12),
        'updated_at' => now_utc(),
    ], 'id = ?', [aid()]);
    log_activity('updated', 'association', null, $name);
    redirect('/settings/association', t('common.saved'));
}

/**
 * JSON backup of the current association (administrators). Never contains
 * password hashes, sessions or tokens.
 */
function settings_backup(): void
{
    $user = require_member();
    ensure(can('backup', $user));
    $a = aid();
    if (get_str('download') !== '1') {
        settings_render('backup', ['size' => (int) filesize((string) config('database'))]);
        return;
    }
    $data = [];
    foreach (TENANT_TABLES as $table) {
        $data[$table] = rows("SELECT * FROM $table WHERE association_id = ?", [$a]);
    }
    $data['users'] = rows('SELECT u.id, u.name, u.email, u.phone, u.bio, u.skills, u.locale, u.created_at FROM users u JOIN members m ON m.user_id = u.id WHERE m.association_id = ?', [$a]);
    $children = [
        'pole_members' => 'pole_id IN (SELECT id FROM poles WHERE association_id = ?)',
        'project_members' => 'project_id IN (SELECT id FROM projects WHERE association_id = ?)',
        'milestones' => 'project_id IN (SELECT id FROM projects WHERE association_id = ?)',
        'project_updates' => 'project_id IN (SELECT id FROM projects WHERE association_id = ?)',
        'task_comments' => 'task_id IN (SELECT id FROM tasks WHERE association_id = ?)',
        'event_attendees' => 'event_id IN (SELECT id FROM events WHERE association_id = ?)',
        'poll_options' => 'poll_id IN (SELECT id FROM polls WHERE association_id = ?)',
        'poll_votes' => 'poll_id IN (SELECT id FROM polls WHERE association_id = ?)',
    ];
    foreach ($children as $table => $where) {
        $data[$table] = rows("SELECT * FROM $table WHERE $where", [$a]);
    }
    $json = json_encode(['exported_at' => now_utc(), 'format' => 'agora-lowtech-1', 'association' => row('SELECT * FROM associations WHERE id = ?', [$a]), 'data' => $data],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    send_download($json, 'agora-backup-' . date('Y-m-d') . '.json', 'application/json');
}
