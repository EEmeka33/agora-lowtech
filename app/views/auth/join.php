<?php if (!$jc): ?>
<h1><?= h(t('join.invalid')) ?></h1>
<p><?= h(t('join.invalidText')) ?></p>
<?php else: ?>
<h1><?= h(t('join.title', ['name' => $jc['association_name']])) ?></h1>
<p><?= h(t('join.subtitle', ['role' => enum_label('role', $jc['role'])])) ?></p>
<p class="row">
  <a href="<?= h(url('/signup', ['code' => $code])) ?>"><strong><?= h(t('join.createAccount')) ?></strong></a> ·
  <a href="<?= h(url('/login', ['next' => '/join?code=' . $code])) ?>"><?= h(t('join.haveAccount')) ?></a>
</p>
<?php endif ?>
