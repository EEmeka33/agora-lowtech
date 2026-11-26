<?= template('settings/tabs', ['tab' => $tab]) ?>
<?php
$v = fn ($k) => old($k, $assoc[$k] ?? '');
$months = explode(',', t('date.months'));
?>
<p class="muted"><?= h(t('settings.associationHint')) ?></p>
<form method="post" action="<?= h(url('/settings/association')) ?>" class="form">
  <?= csrf_field() ?>
  <?= input_field('name', t('setup.associationName'), $v('name'), ['required' => true]) ?>
  <?= input_field('short_name', t('setup.shortName'), $v('short_name')) ?>
  <?= input_field('tagline', t('settings.tagline'), $v('tagline'), ['class' => 'wide']) ?>
  <?= textarea_field('purpose', t('setup.purpose'), $v('purpose'), ['rows' => 3]) ?>
  <h2 class="wide"><?= h(t('settings.legalSection')) ?></h2>
  <?= input_field('legal_form', t('setup.legalForm'), $v('legal_form')) ?>
  <?= input_field('registration_number', t('settings.registrationNumber'), $v('registration_number')) ?>
  <?= input_field('siret', t('settings.siret'), $v('siret')) ?>
  <?= input_field('founded_at', t('settings.foundedAt'), $v('founded_at'), ['type' => 'date']) ?>
  <h2 class="wide"><?= h(t('settings.contactSection')) ?></h2>
  <?= input_field('email', t('common.email'), $v('email'), ['type' => 'email']) ?>
  <?= input_field('phone', t('common.phone'), $v('phone'), ['type' => 'tel']) ?>
  <?= input_field('website', t('settings.website'), $v('website'), ['type' => 'url']) ?>
  <?= input_field('address', t('settings.address'), $v('address')) ?>
  <?= input_field('postal_code', t('settings.postalCode'), $v('postal_code')) ?>
  <?= input_field('city', t('setup.city'), $v('city')) ?>
  <?= input_field('country', t('setup.country'), $v('country')) ?>
  <h2 class="wide"><?= h(t('settings.financeSection')) ?></h2>
  <?= select_field('currency', t('setup.currency'), array_combine(CURRENCIES, CURRENCIES), $v('currency')) ?>
  <?= input_field('membership_fee', t('settings.membershipFee'), old('membership_fee', money_input($assoc['membership_fee_cents'] !== null ? (int) $assoc['membership_fee_cents'] : null)), ['attrs' => ['inputmode' => 'decimal']]) ?>
  <?= input_field('volunteer_hour', t('settings.volunteerHourValue'), old('volunteer_hour', money_input($assoc['volunteer_hour_cents'] !== null ? (int) $assoc['volunteer_hour_cents'] : null)), ['attrs' => ['inputmode' => 'decimal']]) ?>
  <?= select_field('fiscal_year_start_month', t('settings.fiscalYearStart'), array_combine(range(1, 12), $months), $v('fiscal_year_start_month')) ?>
  <?= select_field('locale', t('setup.language'), enum_options('locale', LOCALES), $v('locale')) ?>
  <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
</form>
