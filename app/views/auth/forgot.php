<h1><?= h(t('account.forgot.title')) ?></h1>
<?php if (!mail_enabled()): ?>
<p><?= h(t('account.forgot.noMail')) ?></p>
<?php else: ?>
<p class="muted"><?= h(t('account.forgot.subtitle')) ?></p>
<form method="post" action="<?= h(url('/forgot')) ?>" class="form">
  <?= csrf_field() ?>
  <?= input_field('email', t('auth.email'), '', ['type' => 'email', 'required' => true, 'class' => 'wide']) ?>
  <p class="row"><button class="primary"><?= h(t('account.forgot.submit')) ?></button></p>
</form>
<?php endif ?>
<p><a href="<?= h(url('/login')) ?>"><?= h(t('auth.invite.goToLogin')) ?></a></p>
