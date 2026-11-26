<?php
/*
 * The current association and the signed-in person's role in it.
 *
 * A person may belong to several associations. The one being viewed is kept in
 * the cookie "agora_asso"; it is only a preference: we always check that the
 * person is an active member of it, and fall back to their first association.
 */

/** All active memberships of the signed-in person (for the switcher). */
function my_associations(): array
{
    static $list = null;
    $account = current_account();
    if (!$account) {
        return [];
    }
    return $list ??= rows(
        "SELECT a.*, m.role, m.board_position, m.title, m.id AS member_id
         FROM members m JOIN associations a ON a.id = m.association_id
         WHERE m.user_id = ? AND m.status = 'ACTIVE' ORDER BY m.created_at, m.id",
        [$account['id']]
    );
}

/** The association being viewed, or null. */
function current_association(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $list = my_associations();
    if (!$list) {
        $cache = [];
        return null;
    }
    $wanted = (int) ($_COOKIE['agora_asso'] ?? 0);
    foreach ($list as $a) {
        if ((int) $a['id'] === $wanted) {
            return $cache = $a;
        }
    }
    return $cache = $list[0];
}

/** The signed-in person inside the current association: account + role, board position… */
function current_user(): ?array
{
    $account = current_account();
    $assoc = current_association();
    if (!$account || !$assoc) {
        return $account;
    }
    return $account + [
        'role' => $assoc['role'],
        'board_position' => $assoc['board_position'],
        'title' => $assoc['title'],
    ];
}

/** For pages of the workspace: signs in first, or creates/joins an association. */
function require_member(): array
{
    require_account();
    if (!current_association()) {
        redirect('/welcome');
    }
    return current_user();
}

/** Makes an association the current one (the person must be an active member). */
function switch_association(int $associationId): void
{
    set_cookie('agora_asso', (string) $associationId, 365 * 86400);
}
