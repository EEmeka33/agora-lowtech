<h1><?= h(t('auth.welcomeBack')) ?></h1>
<p class="muted"><?= h(t('auth.signInSubtitle')) ?></p>
<form method="post" action="<?= h(url('/login')) ?>" class="form">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= h($next) ?>">
  <?= input_field('email', t('auth.email'), old('email'), ['type' => 'email', 'required' => true, 'class' => 'wide', 'attrs' => ['autocomplete' => 'username', 'autofocus' => true]]) ?>
  <?= input_field('password', t('auth.password'), '', ['type' => 'password', 'required' => true, 'class' => 'wide', 'attrs' => ['autocomplete' => 'current-password']]) ?>
  <p class="row"><button class="primary"><?= h(t('auth.signIn')) ?></button> <a href="<?= h(url('/forgot')) ?>"><?= h(t('account.forgot.link')) ?></a></p>
</form>
<?php if (signup_mode() !== 'closed'): ?>
<p><?= h(t('account.signup.noAccount')) ?> <a href="<?= h(url('/signup')) ?>"><?= h(t('account.signup.title')) ?></a></p>
<?php else: ?>
<p class="muted"><?= h(t('auth.noAccount')) ?></p>
<?php endif ?>
