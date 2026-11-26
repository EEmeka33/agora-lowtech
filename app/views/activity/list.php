<?= page_head(t('activity.title'), t('activity.subtitle')) ?>
<?= template('activity/items', ['items' => $items]) ?>
<p>
  <?php if ($page > 1): ?><a href="<?= h(url('/activity', ['page' => $page - 1])) ?>">← <?= h(t('common.previous')) ?></a><?php endif ?>
  <?php if ($more): ?> <a href="<?= h(url('/activity', ['page' => $page + 1])) ?>"><?= h(t('common.next')) ?> →</a><?php endif ?>
</p>
