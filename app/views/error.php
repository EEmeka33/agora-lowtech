<h1><?= h($code) ?> · <?= h($heading) ?></h1>
<p><?= h($message) ?></p>
<?php if ($detail !== ''): ?>
<pre><?= h($detail) ?></pre>
<?php endif ?>
<p><a href="<?= h(url('/')) ?>"><?= h(t('common.goHome')) ?></a></p>
