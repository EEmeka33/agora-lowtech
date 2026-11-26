<?php
/*
 * Notifications of the signed-in person, in all their associations.
 * No live updates: the unread count is computed when a page loads.
 */

function notifications_list(): void
{
    $account = require_account();
    $items = rows('SELECT n.*, a.name AS association_name FROM notifications n LEFT JOIN associations a ON a.id = n.association_id
                   WHERE n.user_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT 100', [$account['id']]);
    render('notifications/list', ['items' => $items], t('notifications.title'), current_association() ? 'app' : 'auth');
}

/** Marks a notification as read, switches to its association and opens its link. */
function notifications_open(int $id): void
{
    $account = require_account();
    $n = row('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $account['id']]) ?? abort(404);
    if (!$n['read_at']) {
        update('notifications', ['read_at' => now_utc()], 'id = ?', [$id]);
    }
    if ($n['association_id'] && val("SELECT 1 FROM members WHERE association_id = ? AND user_id = ? AND status = 'ACTIVE'", [$n['association_id'], $account['id']])) {
        switch_association((int) $n['association_id']);
    }
    redirect(safe_local_path($n['link'], '/notifications'));
}

function notifications_read_all(): void
{
    $account = require_account();
    q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now_utc(), $account['id']]);
    redirect('/notifications', t('common.saved'));
}
