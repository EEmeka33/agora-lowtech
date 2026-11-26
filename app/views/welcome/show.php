<?php $mine = my_associations(); ?>
<h1><?= h(t('welcome.title', ['name' => first_name(current_account()['name'])])) ?></h1>
<p class="muted"><?= h(t('welcome.subtitle')) ?></p>

<?php if ($mine): ?>
<h2><?= h(t('welcome.yours')) ?></h2>
<ul class="plain">
  <?php foreach ($mine as $a): ?>
  <li>
    <form method="post" action="<?= h(url('/switch')) ?>" class="inline"><?= csrf_field() ?>
      <input type="hidden" name="association_id" value="<?= (int) $a['id'] ?>">
      <button class="link"><?= h($a['name']) ?></button>
    </form> <span class="meta"><?= h(enum_label('role', $a['role'])) ?></span>
  </li>
  <?php endforeach ?>
</ul>
<?php endif ?>

<details class="panel"<?= form_failed('join') || $code ? ' open' : '' ?>>
  <summary><?= h(t('welcome.joinTitle')) ?></summary>
  <?php if (!empty($codeInfo)): ?>
  <p><?= h(t('join.title', ['name' => $codeInfo['association_name']])) ?> — <?= h(t('join.subtitle', ['role' => enum_label('role', $codeInfo['role'])])) ?></p>
  <?php endif ?>
  <p class="muted"><?= h(t('welcome.joinText')) ?></p>
  <form method="post" action="<?= h(url('/welcome/join')) ?>" class="form">
    <?= csrf_field('join') ?>
    <?= input_field('code', t('join.codeLabel'), old('code', $code), ['required' => true, 'placeholder' => 'XXXXX-XXXXX']) ?>
    <p class="row"><button class="primary"><?= h(t('join.submit')) ?></button></p>
  </form>
</details>

<?php if (new_associations_allowed() || (int) val('SELECT COUNT(*) FROM associations') === 0): ?>
<details class="panel"<?= !$mine && !$code || form_failed('create') ? ' open' : '' ?>>
  <summary><?= h(t('welcome.createTitle')) ?></summary>
  <p class="muted"><?= h(t('setup.subtitle')) ?></p>
  <form method="post" action="<?= h(url('/welcome/new')) ?>" class="form">
    <?= csrf_field('create') ?>
    <?= input_field('name', t('setup.associationName'), old('name'), ['required' => true, 'placeholder' => t('setup.associationNamePlaceholder')]) ?>
    <?= input_field('short_name', t('setup.shortName'), old('short_name'), ['optional' => true]) ?>
    <?= textarea_field('purpose', t('setup.purpose'), old('purpose'), ['rows' => 3, 'placeholder' => t('setup.purposePlaceholder')]) ?>
    <?= input_field('legal_form', t('setup.legalForm'), old('legal_form', 'Loi 1901')) ?>
    <?= input_field('city', t('setup.city'), old('city'), ['optional' => true]) ?>
    <?= input_field('country', t('setup.country'), old('country'), ['optional' => true]) ?>
    <?= select_field('currency', t('setup.currency'), array_combine(CURRENCIES, CURRENCIES), old('currency', 'EUR')) ?>
    <?= select_field('locale', t('setup.language'), enum_options('locale', LOCALES), old('locale', locale())) ?>
    <?= select_field('board_position', t('setup.yourPosition'), enum_options('boardPosition', BOARD_POSITIONS), old('board_position', 'PRESIDENT'), ['empty' => t('common.none')]) ?>
    <div class="wide">
      <?= checkbox_field('checklist', t('setup.starterChecklist'), form_errors() ? post_bool('checklist') : true, t('setup.starterChecklistHint')) ?>
    </div>
    <?= checkboxes_field('poles', t('setup.starterPoles'), array_combine(SUGGESTED_POLES, array_map(fn ($k) => t("setup.suggestedPoles.$k"), SUGGESTED_POLES)), old('poles', ['workshops', 'repair', 'communication', 'treasury'])) ?>
    <p class="row"><button class="primary"><?= h(t('setup.create')) ?></button></p>
  </form>
</details>
<?php endif ?>

<form method="post" action="<?= h(url("/logout")) ?>"><?= csrf_field() ?><button class="link"><?= h(t("nav.logout")) ?></button></form>
