<?php
/*
 * Sign in, sign up, forgotten password, activation links, join links, language.
 */

function auth_login(): void
{
    if (current_account()) {
        redirect('/');
    }
    if ((int) val('SELECT COUNT(*) FROM users') === 0) {
        redirect('/signup');
    }
    render('auth/login', ['next' => safe_local_path(get_str('next'))], t('auth.signIn'), 'auth');
}

function auth_login_submit(): void
{
    $email = strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');
    $again = fn () => render('auth/login', ['next' => safe_local_path(post_str('next'))], t('auth.signIn'), 'auth');

    if (!rate_limit('login:ip:' . client_ip(), 30, 600) || !rate_limit("login:email:$email", 10, 600)) {
        fail_form(['email' => t('account.tooMany')], $again);
    }
    $user = $email !== '' ? row('SELECT * FROM users WHERE email = ?', [$email]) : null;
    if (!$user || !$user['password_hash']) {
        fake_password_check($password);
        fail_form(['email' => t('auth.invalidCredentials')], $again);
    }
    if ($user['locked_until'] && $user['locked_until'] > now_utc()) {
        fail_form(['email' => t('account.locked')], $again);
    }
    if (!password_verify($password, $user['password_hash'])) {
        $failures = (int) $user['failed_logins'] + 1;
        $locked = $failures >= LOCK_AFTER;
        update('users', [
            'failed_logins' => $locked ? 0 : $failures,
            'locked_until' => $locked ? utc_in(LOCK_MINUTES * 60) : null,
        ], 'id = ?', [$user['id']]);
        fail_form(['email' => $locked ? t('account.locked') : t('auth.invalidCredentials')], $again);
    }
    $data = ['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now_utc()];
    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        $data['password_hash'] = hash_password($password);
    }
    update('users', $data, 'id = ?', [$user['id']]);
    create_session((int) $user['id']);
    set_locale_cookie($user['locale']);
    redirect(safe_local_path(post_str('next')));
}

function auth_logout(): void
{
    destroy_session();
    redirect('/login');
}

// ─── Sign up ───────────────────────────────────────────────────────────────

function auth_signup(): void
{
    if (current_account()) {
        redirect('/welcome');
    }
    $isFirst = (int) val('SELECT COUNT(*) FROM users') === 0;
    render('auth/signup', ['isFirst' => $isFirst, 'code' => normalize_join_code(get_str('code')) ?: get_str('code')], t('account.signup.title'), 'auth');
}

function auth_signup_submit(): void
{
    $name = post_str('name');
    $email = strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');
    $code = normalize_join_code(post_str('code'));
    $isFirst = (int) val('SELECT COUNT(*) FROM users') === 0;
    $mode = signup_mode();
    $again = fn () => render('auth/signup', ['isFirst' => $isFirst, 'code' => post_str('code')], t('account.signup.title'), 'auth');

    if ($mode === 'closed' && !$isFirst) {
        fail_form(['email' => t('account.signup.closed')], $again);
    }
    if ($mode === 'invite' && !$isFirst && !$code) {
        fail_form(['code' => t('account.signup.codeRequired')], $again);
    }
    if (!rate_limit('signup:' . client_ip(), 10, 3600)) {
        fail_form(['email' => t('account.tooMany')], $again);
    }
    $errors = [];
    if ($name === '') {
        $errors['name'] = t('validation.required');
    }
    if (!valid_email($email)) {
        $errors['email'] = t('validation.email');
    }
    if ($e = password_error($password, $email, $name)) {
        $errors['password'] = $e;
    }
    if ($password !== ($_POST['confirm'] ?? '')) {
        $errors['confirm'] = t('setup.passwordMismatch');
    }
    if (post_str('code') !== '' && !$code) {
        $errors['code'] = t('join.invalid');
    }
    if (!$errors && ($existing = row('SELECT password_hash FROM users WHERE email = ?', [$email]))) {
        $errors['email'] = $existing['password_hash'] ? t('account.signup.emailExists') : t('account.signup.emailInvited');
    }
    // In "invite" mode, the code must be valid before the account is created.
    if (!$errors && $code && $mode === 'invite' && !$isFirst && !usable_join_code($code)) {
        $errors['code'] = t('join.invalid');
    }
    if ($errors) {
        fail_form($errors, $again);
    }
    $userId = insert('users', [
        'name' => $name,
        'email' => $email,
        'password_hash' => hash_password($password),
        'password_changed_at' => now_utc(),
        'locale' => locale(),
        'last_login_at' => now_utc(),
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    create_session($userId);
    if ($code) {
        $result = redeem_join_code($userId, $code);
        if ($result['ok']) {
            switch_association($result['association_id']);
            redirect('/', t('join.joined', ['name' => $result['name']]));
        }
    }
    redirect('/welcome');
}

// ─── Join codes ────────────────────────────────────────────────────────────

/** A code that can still be used (not revoked, not expired, uses left), or null. */
function usable_join_code(string $code): ?array
{
    $jc = row('SELECT j.*, a.name AS association_name, a.locale AS association_locale
               FROM join_codes j JOIN associations a ON a.id = j.association_id WHERE j.code = ?', [$code]);
    if (!$jc || $jc['revoked_at'] || ($jc['expires_at'] && $jc['expires_at'] < now_utc()) || $jc['uses'] >= $jc['max_uses']) {
        return null;
    }
    return $jc;
}

/**
 * Adds an account to the association of a join code, using one use of the code.
 * Returns ['ok' => bool, 'reason' => invalid|already|inactive, 'association_id', 'name'].
 */
function redeem_join_code(int $userId, string $code): array
{
    $jc = usable_join_code($code);
    if (!$jc) {
        return ['ok' => false, 'reason' => 'invalid'];
    }
    $existing = row('SELECT status FROM members WHERE association_id = ? AND user_id = ?', [$jc['association_id'], $userId]);
    if ($existing) {
        return ['ok' => false, 'reason' => $existing['status'] === 'ACTIVE' ? 'already' : 'inactive', 'association_id' => (int) $jc['association_id']];
    }
    // Use one slot only if some are left: safe even if two people use the last one at the same time.
    if (q('UPDATE join_codes SET uses = uses + 1 WHERE id = ? AND uses < max_uses AND revoked_at IS NULL', [$jc['id']])->rowCount() === 0) {
        return ['ok' => false, 'reason' => 'invalid'];
    }
    insert('members', [
        'association_id' => $jc['association_id'],
        'user_id' => $userId,
        'role' => $jc['role'],
        'joined_at' => today(),
        'created_at' => now_utc(),
    ]);
    if ($jc['pole_id']) {
        q('INSERT OR IGNORE INTO pole_members (pole_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)', [$jc['pole_id'], $userId, 'MEMBER', now_utc()]);
    }
    $user = row('SELECT id, name FROM users WHERE id = ?', [$userId]);
    $assoc = row('SELECT * FROM associations WHERE id = ?', [$jc['association_id']]);
    log_activity('joined', 'workspace', null, $assoc['name'], $userId, (int) $assoc['id']);
    notify(association_member_ids((int) $assoc['id'], ['ADMIN', 'MANAGER']), 'member.joined', [], "/members/$userId", $user, $assoc);
    return ['ok' => true, 'association_id' => (int) $jc['association_id'], 'name' => $jc['association_name']];
}

/** The link shared with a join code: /join?code=XXXXX-XXXXX */
function auth_join(): void
{
    $code = normalize_join_code(get_str('code'));
    $jc = $code ? usable_join_code($code) : null;
    if (current_account()) {
        render('welcome/show', ['code' => $code, 'codeInfo' => $jc], t('welcome.title', ['name' => first_name(current_account()['name'])]), 'auth');
        return;
    }
    render('auth/join', ['code' => $code, 'jc' => $jc], t('join.title', ['name' => $jc['association_name'] ?? 'Agora']), 'auth');
}

// ─── Password reset and activation links ───────────────────────────────────

function auth_forgot(): void
{
    render('auth/forgot', [], t('account.forgot.title'), 'auth');
}

/** Emails a one-hour link. Never tells whether the email exists. */
function auth_forgot_submit(): void
{
    if (!mail_enabled()) {
        redirect('/forgot', t('account.forgot.noMail'), 'error');
    }
    $email = strtolower(post_str('email'));
    if (rate_limit('forgot:' . client_ip(), 5, 900) && rate_limit("forgot:$email", 3, 3600) && valid_email($email)) {
        $user = row('SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL', [$email]);
        if ($user) {
            $loc = in_array($user['locale'], LOCALES, true) ? $user['locale'] : locale();
            $link = new_invitation((int) $user['id'], 'RESET', null, null, 1);
            queue_mail($user['email'], t('account.forgot.emailSubject', [], $loc), t('account.forgot.emailBody', [], $loc) . "\n\n$link\n");
        }
    }
    redirect('/login', t('account.forgot.sent'));
}

function invitation_by_token(string $token): ?array
{
    $inv = row('SELECT i.*, u.email, u.name, u.locale FROM invitations i JOIN users u ON u.id = i.user_id WHERE i.token_hash = ?', [sha256($token)]);
    return $inv && !$inv['used_at'] && $inv['expires_at'] > now_utc() ? $inv : null;
}

function auth_invite(string $token): void
{
    $inv = invitation_by_token($token);
    $assoc = $inv && $inv['association_id'] ? row('SELECT name FROM associations WHERE id = ?', [$inv['association_id']]) : null;
    render('auth/invite', ['inv' => $inv, 'token' => $token, 'assocName' => $assoc['name'] ?? 'Agora'], t('auth.invite.resetTitle'), 'auth');
}

/** Activates an account created by an admin, or sets a new password from a reset link. */
function auth_invite_submit(string $token): void
{
    $inv = invitation_by_token($token) ?? abort(404);
    $password = (string) ($_POST['password'] ?? '');
    $name = post_str('name');
    $errors = [];
    if ($e = password_error($password, $inv['email'], $name ?: $inv['name'])) {
        $errors['password'] = $e;
    }
    if ($password !== ($_POST['confirm'] ?? '')) {
        $errors['confirm'] = t('setup.passwordMismatch');
    }
    if ($inv['kind'] === 'INVITE' && $name === '') {
        $errors['name'] = t('validation.required');
    }
    if ($errors) {
        fail_form($errors, fn () => auth_invite($token));
    }
    transaction(function () use ($inv, $password, $name) {
        $data = ['password_hash' => hash_password($password), 'password_changed_at' => now_utc(), 'failed_logins' => 0,
            'locked_until' => null, 'last_login_at' => now_utc(), 'updated_at' => now_utc()];
        if ($inv['kind'] === 'INVITE') {
            $data['name'] = $name;
        }
        update('users', $data, 'id = ?', [$inv['user_id']]);
        update('invitations', ['used_at' => now_utc()], 'id = ?', [$inv['id']]);
    });
    // A new password signs out every other device.
    revoke_sessions((int) $inv['user_id']);
    if ($inv['association_id']) {
        log_activity('joined', 'workspace', null, null, (int) $inv['user_id'], (int) $inv['association_id']);
        switch_association((int) $inv['association_id']);
    }
    create_session((int) $inv['user_id']);
    set_locale_cookie($inv['locale']);
    redirect('/', t('common.saved'));
}

// ─── Language ──────────────────────────────────────────────────────────────

function auth_locale(): void
{
    $locale = post_enum('locale', LOCALES, null);
    if ($locale) {
        set_locale_cookie($locale);
        if ($account = current_account()) {
            update('users', ['locale' => $locale], 'id = ?', [$account['id']]);
        }
    }
    back('/');
}
