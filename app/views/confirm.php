<?php /* Shown before an irreversible action when JavaScript is off. */ ?>
<div class="box">
  <h1><?= h(t('common.confirm')) ?></h1>
  <p><?= h($question) ?></p>
  <form method="post" action="<?= h(url($action)) ?>" class="actions">
    <?= csrf_field() ?>
    <input type="hidden" name="confirmed" value="1">
    <?php foreach ($fields as $name => $value): if (is_array($value)) continue; ?>
    <input type="hidden" name="<?= h($name) ?>" value="<?= h($value) ?>">
    <?php endforeach ?>
    <button class="danger"><?= h(t("common.yesConfirm")) ?></button>
    <a class="btn" href="<?= h(url($back)) ?>"><?= h(t('common.cancel')) ?></a>
  </form>
</div>
