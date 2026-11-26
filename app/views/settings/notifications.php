<?= template('settings/tabs', ['tab' => $tab]) ?>
<h2><?= h(t('settings.notificationsTitle')) ?></h2>
<p class="muted"><?= h(t('settings.notificationsHint')) ?></p>
<?php if (!mail_enabled()): ?><p class="flash error"><?= h(t('settings.emailNotConfigured')) ?></p><?php endif ?>
<form method="post" action="<?= h(url('/settings/notifications')) ?>">
  <?= csrf_field() ?>
  <fieldset class="field"><legend><?= h(t('settings.emailMode')) ?></legend>
    <?php foreach (EMAIL_MODES as $mode): ?>
    <label class="check"><input type="radio" name="email_mode" value="<?= h($mode) ?>"<?= $me['email_mode'] === $mode ? ' checked' : '' ?>> <?= h(t("settings.emailModes.$mode")) ?></label>
    <?php endforeach ?>
  </fieldset>
  <p><button class="primary"><?= h(t('common.save')) ?></button></p>
</form>

<h2 id="calendar"><?= h(t('settings.calendar.title')) ?></h2>
<p class="muted"><?= h(t('settings.calendarHint')) ?></p>
<?php if ($calendarLink): ?>
<p class="banner"><?= h(t('settings.calendarCopy')) ?><br><code><?= h($calendarLink) ?></code></p>
<?php endif ?>
<?= button_form('/settings/calendar', $me['calendar_token_hash'] ? t('settings.calendarRenew') : t('settings.calendarCreate')) ?>
