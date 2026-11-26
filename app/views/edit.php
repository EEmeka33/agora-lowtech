<?php /* Generic edit page: $heading, $back (path), $action, $fields (HTML), $delete (path or null), $multipart. */ ?>
<p class="meta"><a href="<?= h(url($back)) ?>">← <?= h(t('common.back')) ?></a></p>
<?= page_head($heading) ?>
<form method="post" action="<?= h(url($action)) ?>" class="form"<?= !empty($multipart) ? ' enctype="multipart/form-data"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="_back" value="<?= h($back) ?>">
  <?= $fields ?>
  <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button> <a href="<?= h(url($back)) ?>"><?= h(t('common.cancel')) ?></a></p>
</form>
<?php if (!empty($delete)): ?>
<p><?= confirm_form($delete, t('common.delete'), $deleteWarning ?? '', ['_back' => strtok($back, '#')]) ?></p>
<?php endif ?>
