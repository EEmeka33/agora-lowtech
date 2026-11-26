<?php
/*
 * Accounts and sessions.
 *
 * The browser keeps a random 256-bit token in the cookie "agora_session"; the
 * database only stores its SHA-256. Sessions last 30 days and are extended
 * while used. Passwords are hashed with Argon2id when PHP supports it
 * (bcrypt otherwise): password_hash() is built into PHP.
 */

const SESSION_DAYS = 30;
const LOCK_AFTER = 5;      // failed sign-ins before the account is locked…
const LOCK_MINUTES = 15;   // …for this long

function hash_password(string $password): string
{
    return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

/** Spends the same time as a real check, so nobody can tell which emails exist. */
function fake_password_check(string $password): void
{
    static $dummy = null;
    $dummy ??= hash_password('agora-timing-equalizer');
    password_verify($password, $dummy);
}

/** The signed-in account, or null. Read once per request. */
function current_account(): ?array
{
    static $loaded = false;
    static $account = null;
    if ($loaded) {
        return $account;
    }
    $loaded = true;
    $token = $_COOKIE['agora_session'] ?? '';
    if ($token === '' || strlen($token) > 100) {
        return null;
    }
    $row = row(
        'SELECT s.id AS session_id, s.expires_at, s.last_seen_at, u.*
         FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token_hash = ?',
        [sha256($token)]
    );
    if (!$row || $row['expires_at'] < now_utc() || !$row['password_hash']) {
        return null;
    }
    // Sliding expiration, written at most once an hour.
    if (strtotime($row['last_seen_at'] . ' UTC') < time() - 3600) {
        update('sessions', ['last_seen_at' => now_utc(), 'expires_at' => utc_in(SESSION_DAYS * 86400)], 'id = ?', [$row['session_id']]);
    }
    unset($row['password_hash'], $row['calendar_token_hash']);
    $row['id'] = (int) $row['id'];
    $row['session_id'] = (int) $row['session_id'];
    return $account = $row;
}

/** For pages that only need an account (e.g. /welcome). */
function require_account(): array
{
    $account = current_account();
    if (!$account) {
        redirect(((int) val('SELECT COUNT(*) FROM users') === 0 ? '/signup' : '/login') . '?' . http_build_query(['next' => request_path()]));
    }
    return $account;
}

/** Creates a session and sets the cookie. */
function create_session(int $userId): void
{
    $token = new_token();
    insert('sessions', [
        'token_hash' => sha256($token),
        'user_id' => $userId,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        'ip' => client_ip(),
        'created_at' => now_utc(),
        'last_seen_at' => now_utc(),
        'expires_at' => utc_in(SESSION_DAYS * 86400),
    ]);
    set_cookie('agora_session', $token, SESSION_DAYS * 86400);
}

function destroy_session(): void
{
    $token = $_COOKIE['agora_session'] ?? '';
    if ($token !== '') {
        q('DELETE FROM sessions WHERE token_hash = ?', [sha256($token)]);
    }
    delete_cookie('agora_session');
}

/** Signs the account out everywhere, except (optionally) the current browser. */
function revoke_sessions(int $userId, ?int $keepSessionId = null): void
{
    q('DELETE FROM sessions WHERE user_id = ? AND id != ?', [$userId, $keepSessionId ?? 0]);
}

/**
 * Rate limiting stored in the database (PHP keeps nothing in memory between
 * requests). Returns false when $key was used $max times within $seconds.
 */
function rate_limit(string $key, int $max, int $seconds): bool
{
    $count = (int) val('SELECT COUNT(*) FROM attempts WHERE key = ? AND at > ?', [$key, time() - $seconds]);
    if ($count >= $max) {
        return false;
    }
    insert('attempts', ['key' => $key, 'at' => time()]);
    return true;
}

/** Message of a password problem, or null when the password is fine. */
function password_error(string $password, string $email = '', string $name = ''): ?string
{
    $issue = password_issue($password, $email, $name);
    return $issue ? t('validation.password.' . $issue, ['min' => MIN_PASSWORD_LENGTH]) : null;
}

/** Remembers the chosen language in a cookie (and on the account when signed in). */
function set_locale_cookie(?string $locale): void
{
    if (in_array($locale, LOCALES, true)) {
        set_cookie('agora_locale', $locale, 365 * 86400);
    }
}

/**
 * Creates (or replaces) a one-time link. INVITE links last 7 days, RESET links
 * 24 hours (1 hour when the person asked for it themselves).
 */
function new_invitation(int $userId, string $kind, ?int $associationId, ?int $byId, int $hours): string
{
    q('UPDATE invitations SET expires_at = ? WHERE user_id = ? AND used_at IS NULL', [now_utc(), $userId]);
    $token = new_token();
    insert('invitations', [
        'token_hash' => sha256($token),
        'kind' => $kind,
        'user_id' => $userId,
        'association_id' => $associationId,
        'invited_by_id' => $byId,
        'expires_at' => utc_in($hours * 3600),
        'created_at' => now_utc(),
    ]);
    return absolute_url('/invite/' . $token);
}
