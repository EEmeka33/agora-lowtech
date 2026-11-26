<?php
/** Link of an activity entry. */
$href = function (array $a): string {
    $id = $a['entity_id'];
    return url(match ($a['entity_type']) {
        'task' => $id ? "/tasks/$id" : '/tasks',
        'project', 'update', 'milestone' => $id ? "/projects/$id" : '/projects',
        'pole' => $id ? "/poles/$id" : '/poles',
        'member', 'membership' => $id ? "/members/$id" : '/members',
        'event' => $id ? "/events/$id" : '/events',
        'request' => $id ? "/requests/$id" : '/requests',
        'poll' => '/polls',
        'step' => '/foundation',
        'document' => '/documents',
        'transaction' => '/finance',
        'responsibility' => '/structure',
        'association' => '/settings/association',
        default => '/',
    });
};
?>
<?php if (!$items): ?>
<?= empty_html(t('dashboard.activityEmpty')) ?>
<?php else: ?>
<ul class="plain">
<?php foreach ($items as $a): ?>
  <li><?= person($a['actor_id'] ? (int) $a['actor_id'] : null, $a['actor_name'] ?? t('activity.someone')) ?>
    <?= h(t('activity.verbs.' . $a['action'])) ?> <?= h(t('activity.entities.' . $a['entity_type'])) ?>
    <?php if ($a['entity_label']): ?><a href="<?= h($href($a)) ?>"><?= h($a['entity_label']) ?></a><?php endif ?>
    <div class="meta"><?= h(fmt_relative($a['created_at'])) ?></div></li>
<?php endforeach ?>
</ul>
<?php endif ?>
