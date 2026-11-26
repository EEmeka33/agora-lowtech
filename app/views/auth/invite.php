<?php if (!$inv): ?>
<h1><?= h(t('auth.invite.invalid')) ?></h1>
<p><?= h(t('auth.invite.invalidText')) ?></p>
<p><a href="<?= h(url('/login')) ?>"><?= h(t('auth.invite.goToLogin')) ?></a></p>
<?php else: $invite = $inv['kind'] === 'INVITE'; ?>
<h1><?= h($invite ? t('auth.invite.title', ['assoName' => $assocName]) : t('auth.invite.resetTitle')) ?></h1>
<p class="muted"><?= h($invite ? t('auth.invite.subtitle') : t('auth.invite.resetSubtitle')) ?> (<?= h($inv['email']) ?>)</p>
<form method="post" action="<?= h(url('/invite/' . $token)) ?>" class="form">
  <?= csrf_field() ?>
  <?php if ($invite): ?>
  <?= input_field('name', t('setup.yourName'), old('name', $inv['name']), ['required' => true, 'class' => 'wide']) ?>
  <?php endif ?>
  <?= input_field('password', t('setup.password'), '', ['type' => 'password', 'required' => true, 'hint' => t('account.passwordHint', ['min' => MIN_PASSWORD_LENGTH]), 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <?= input_field('confirm', t('setup.confirmPassword'), '', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <p class="row"><button class="primary"><?= h($invite ? t('auth.invite.submit') : t('auth.invite.resetSubmit')) ?></button></p>
</form>
<?php endif ?>
