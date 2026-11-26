<?php
$links = ['projects' => '/projects/', 'tasks' => '/tasks/', 'requests' => '/requests/', 'members' => '/members/', 'poles' => '/poles/', 'events' => '/events/'];
?>
<?= page_head(t('common.search')) ?>
<form action="<?= h(url('/search')) ?>" class="filters">
  <?= input_field('q', t('search.placeholder'), $q, ['type' => 'search', 'attrs' => ['autofocus' => true]]) ?>
  <button class="primary"><?= h(t('common.search')) ?></button>
</form>
<?php if ($q !== '' && !$groups): ?><?= empty_html(t('common.noResults')) ?><?php endif ?>
<?php foreach ($groups as $group => $items): ?>
<h2><?= h(t("search.groups.$group")) ?> <small>(<?= count($items) ?>)</small></h2>
<ul>
  <?php foreach ($items as $it): ?>
  <li><a href="<?= h($group === 'documents' ? url('/documents', ['q' => $it['title']]) : url($links[$group] . $it['id'])) ?>"><?= h($it['title']) ?></a>
    <?php if ($it['sub']): ?><span class="meta"> · <?= h($group === 'events' ? fmt_date($it['sub']) : $it['sub']) ?></span><?php endif ?></li>
  <?php endforeach ?>
</ul>
<?php endforeach ?>
