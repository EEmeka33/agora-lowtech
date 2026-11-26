<?php
/*
 * Gives back access when nobody can sign in any more: sets a new password for
 * an account (and makes it administrator of an association, optionally).
 *
 *   php bin/create-admin.php someone@example.org
 *   php bin/create-admin.php someone@example.org "Association name"
 */
require dirname(__DIR__) . '/app/boot.php';

$email = strtolower(trim($argv[1] ?? ''));
if (!valid_email($email)) {
    exit("Usage: php bin/create-admin.php email [\"association name\"]\n");
}
echo 'New password (at least ' . MIN_PASSWORD_LENGTH . ' characters): ';
$password = trim((string) fgets(STDIN));
if ($issue = password_issue($password, $email)) {
    exit("Password refused ($issue).\n");
}
$user = row('SELECT * FROM users WHERE email = ?', [$email]);
$id = $user ? (int) $user['id'] : insert('users', ['email' => $email, 'name' => explode('@', $email)[0], 'created_at' => now_utc(), 'updated_at' => now_utc()]);
update('users', ['password_hash' => hash_password($password), 'password_changed_at' => now_utc(), 'failed_logins' => 0, 'locked_until' => null], 'id = ?', [$id]);
if (isset($argv[2])) {
    $assoc = row('SELECT id FROM associations WHERE name = ?', [$argv[2]]) ?? exit("No association named \"{$argv[2]}\".\n");
    q("INSERT INTO members (association_id, user_id, role, status, joined_at, created_at) VALUES (?, ?, 'ADMIN', 'ACTIVE', ?, ?)
       ON CONFLICT (association_id, user_id) DO UPDATE SET role = 'ADMIN', status = 'ACTIVE'", [$assoc['id'], $id, today(), now_utc()]);
}
echo "✔ $email can sign in.\n";
