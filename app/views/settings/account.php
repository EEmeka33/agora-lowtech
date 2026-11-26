<?= template('settings/tabs', ['tab' => $tab]) ?>
<h2><?= h(t('settings.securityTitle')) ?></h2>
<form method="post" action="<?= h(url('/settings/password')) ?>" class="form">
  <?= csrf_field() ?>
  <?= input_field('current', t('settings.currentPassword'), '', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
  <?= input_field('password', t('settings.newPassword'), '', ['type' => 'password', 'required' => true, 'hint' => t('account.passwordHint', ['min' => MIN_PASSWORD_LENGTH]), 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <?= input_field('confirm', t('settings.confirmPassword'), '', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <p class="row"><button class="primary"><?= h(t('settings.securityTitle')) ?></button></p>
</form>

<h2><?= h(t('settings.sessionsTitle')) ?></h2>
<p class="muted"><?= h(t('settings.sessionsHint')) ?></p>
<ul class="plain">
  <?php foreach ($sessions as $s): ?>
  <li><?= h(str_cut($s['user_agent'] ?: t('settings.unknownDevice'), 90)) ?>
    <div class="meta"><?= h($s['ip'] ?? '') ?> · <?= h(t('settings.lastActive', ['when' => fmt_relative($s['last_seen_at'])])) ?>
      <?= (int) $s['id'] === $account['session_id'] ? ' · ' . badge(t('settings.thisDevice'), 'ok') : ' · ' . button_form("/settings/sessions/{$s['id']}/revoke", t('settings.signOutDevice'), [], 'link') ?></div></li>
  <?php endforeach ?>
</ul>
<?php if (count($sessions) > 1): ?><p><?= button_form('/settings/sessions/revoke', t('settings.signOutOthers')) ?></p><?php endif ?>

<?php if (my_associations()): ?>
<h2><?= h(t('settings.associationsTitle')) ?></h2>
<ul class="plain">
  <?php foreach (my_associations() as $a): ?>
  <li><?= h($a['name']) ?> <span class="meta"><?= h(enum_label('role', $a['role'])) ?></span>
    <?= confirm_form('/settings/leave', t('settings.leave'), t('settings.leaveText'), ['association_id' => $a['id']]) ?></li>
  <?php endforeach ?>
</ul>
<?php endif ?>

<h2><?= h(t('settings.deleteTitle')) ?></h2>
<p class="muted"><?= h(t('settings.deleteText')) ?></p>
<details class="panel"<?= isset(form_errors()['password']) && !isset($_POST['current']) ? ' open' : '' ?>><summary><?= h(t('settings.deleteButton')) ?>…</summary>
  <form method="post" action="<?= h(url('/settings/delete')) ?>" class="form">
    <?= csrf_field() ?>
    <?= input_field('password', t('settings.deleteConfirm'), '', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
    <p class="row"><button class="danger"><?= h(t('settings.deleteButton')) ?></button></p>
  </form>
</details>
