<h1><?= h(t('account.signup.title')) ?></h1>
<p class="muted"><?= h($isFirst ? t('account.signup.firstSubtitle') : t('account.signup.subtitle')) ?></p>
<?php if (signup_mode() === 'closed' && !$isFirst): ?>
<p class="flash error"><?= h(t('account.signup.closed')) ?></p>
<?php else: ?>
<form method="post" action="<?= h(url('/signup')) ?>" class="form">
  <?= csrf_field() ?>
  <?= input_field('name', t('setup.yourName'), old('name'), ['required' => true, 'class' => 'wide', 'attrs' => ['autocomplete' => 'name']]) ?>
  <?= input_field('email', t('setup.yourEmail'), old('email'), ['type' => 'email', 'required' => true, 'class' => 'wide', 'attrs' => ['autocomplete' => 'email']]) ?>
  <?= input_field('password', t('setup.password'), '', ['type' => 'password', 'required' => true, 'hint' => t('account.passwordHint', ['min' => MIN_PASSWORD_LENGTH]), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => MIN_PASSWORD_LENGTH]]) ?>
  <?= input_field('confirm', t('setup.confirmPassword'), '', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <?php if (!$isFirst): ?>
  <?= input_field('code', t('account.signup.code'), old('code', $code), ['required' => signup_mode() === 'invite', 'class' => 'wide', 'placeholder' => 'XXXXX-XXXXX', 'hint' => t('account.signup.codeHint')]) ?>
  <?php endif ?>
  <p class="row"><button class="primary"><?= h(t('account.signup.submit')) ?></button></p>
</form>
<?php endif ?>
<p><?= h(t('account.signup.haveAccount')) ?> <a href="<?= h(url('/login')) ?>"><?= h(t('auth.signIn')) ?></a></p>
