<?= template('settings/tabs', ['tab' => $tab]) ?>
<p class="muted"><?= h(t('settings.profileHint')) ?></p>
<form method="post" action="<?= h(url('/settings')) ?>" class="form">
  <?= csrf_field() ?>
  <?= input_field('name', t('common.name'), old('name', $me['name']), ['required' => true]) ?>
  <?= input_field('email', t('common.email'), old('email', $me['email']), ['type' => 'email', 'required' => true]) ?>
  <?= input_field('phone', t('common.phone'), old('phone', $me['phone']), ['type' => 'tel']) ?>
  <?= select_field('locale', t('common.language'), enum_options('locale', LOCALES), old('locale', $me['locale'] ?: locale())) ?>
  <?= input_field('skills', t('members.skills'), old('skills', $me['skills']), ['class' => 'wide', 'placeholder' => t('members.skillsPlaceholder')]) ?>
  <?= textarea_field('bio', t('members.bio'), old('bio', $me['bio']), ['rows' => 3]) ?>
  <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
</form>
