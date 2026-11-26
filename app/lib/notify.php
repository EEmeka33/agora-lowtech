<?php
/*
 * Notifications. notify() stores one row per recipient (never the person who
 * acted). Texts are not stored: they are rebuilt from the dictionaries, so each
 * person reads them in their own language.
 *
 * Email: each person chooses INSTANT (one email per notification), DAILY (one
 * digest a day, the default: fewer emails, less distraction) or OFF.
 * No live updates: the count of unread notifications is shown when a page loads.
 */

function notify(array $userIds, string $type, array $data = [], ?string $link = null, ?array $actor = null, ?array $association = null): void
{
    $actor ??= current_user();
    $association ??= current_association();
    $recipients = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0 && $id !== (int) ($actor['id'] ?? 0))));
    if (!$recipients || !$association) {
        return;
    }
    $data = ['actor' => $actor['name'] ?? '', 'association' => $association['name']] + $data;
    foreach ($recipients as $userId) {
        insert('notifications', [
            'user_id' => $userId,
            'association_id' => $association['id'],
            'type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'link' => $link,
            'actor_id' => $actor['id'] ?? null,
            'created_at' => now_utc(),
        ]);
    }
    if (!mail_enabled()) {
        return;
    }
    $users = rows(
        "SELECT email, locale FROM users WHERE email_mode = 'INSTANT' AND password_hash IS NOT NULL AND id IN (" . placeholders($recipients) . ')',
        $recipients
    );
    foreach ($users as $u) {
        $loc = in_array($u['locale'], LOCALES, true) ? $u['locale'] : $association['locale'];
        $message = render_notification($type, $data, $loc);
        $body = $message . "\n\n" . absolute_url($link ?? '/') . "\n\n-- \n" . t('notifications.emailFooter', [], $loc) . "\n" . absolute_url('/settings/notifications');
        queue_mail($u['email'], "[{$association['name']}] $message", $body);
    }
}

function render_notification(string $type, array $data, ?string $locale = null): string
{
    return t("notifications.types.$type", $data, $locale);
}

function unread_notifications(): int
{
    static $count = null;
    $account = current_account();
    return $account ? $count ??= (int) val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$account['id']]) : 0;
}

/**
 * Sends one digest email to each person in DAILY mode who has unread
 * notifications since their last digest. Returns the number of emails.
 */
function send_digests(): int
{
    if (!mail_enabled()) {
        return 0;
    }
    $sent = 0;
    $since = utc_in(-23 * 3600);
    $users = rows(
        "SELECT * FROM users WHERE email_mode = 'DAILY' AND password_hash IS NOT NULL
         AND (last_digest_at IS NULL OR last_digest_at < ?)",
        [$since]
    );
    foreach ($users as $u) {
        $from = $u['last_digest_at'] ?? utc_in(-24 * 3600);
        $items = rows(
            'SELECT n.*, a.name AS association_name FROM notifications n LEFT JOIN associations a ON a.id = n.association_id
             WHERE n.user_id = ? AND n.read_at IS NULL AND n.created_at > ? ORDER BY n.created_at',
            [$u['id'], $from]
        );
        update('users', ['last_digest_at' => now_utc()], 'id = ?', [$u['id']]);
        if (!$items) {
            continue;
        }
        $loc = in_array($u['locale'], LOCALES, true) ? $u['locale'] : 'en';
        $lines = [t('notifications.digestIntro', ['name' => first_name($u['name'])], $loc), ''];
        foreach ($items as $n) {
            $lines[] = '• [' . $n['association_name'] . '] ' . render_notification($n['type'], json_decode($n['data'], true) ?: [], $loc);
            $lines[] = '  ' . absolute_url('/notifications/' . $n['id']);
        }
        $lines[] = '';
        $lines[] = '-- ';
        $lines[] = t('notifications.emailFooter', [], $loc);
        $lines[] = absolute_url('/settings/notifications');
        send_mail($u['email'], tn('notifications.digestSubject', count($items), [], $loc), implode("\n", $lines));
        $sent++;
    }
    return $sent;
}
