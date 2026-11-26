<?= page_head(t('notifications.title'), t('notifications.subtitle'), unread_notifications() ? button_form('/notifications/read', t('notifications.markAllRead')) : '') ?>
<?php if (!$items): ?><?= empty_html(t('notifications.empty')) ?><?php else: ?>
<ul class="plain">
  <?php foreach ($items as $n): $text = render_notification($n['type'], json_decode($n['data'], true) ?: []); ?>
  <li><a href="<?= h(url('/notifications/' . $n['id'])) ?>"><?= $n['read_at'] ? h($text) : '<strong>' . h($text) . '</strong>' ?></a>
    <div class="meta"><?= h($n['association_name'] ?? '') ?> · <?= h(fmt_relative($n['created_at'])) ?><?= $n['read_at'] ? '' : ' · ' . badge(t('notifications.unread'), 'info') ?></div></li>
  <?php endforeach ?>
</ul>
<?php endif ?>
<p class="meta"><?= h(t('notifications.emailHint')) ?> <a href="<?= h(url('/settings/notifications')) ?>"><?= h(t('settings.tabs.notifications')) ?></a></p>
