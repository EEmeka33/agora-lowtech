<?php /* New request form. Optional $poleId to preselect the pole being asked. */ ?>
<form method="post" action="<?= h(url('/requests')) ?>" class="form">
  <?= csrf_field('request') ?>
  <?= select_field('pole_id', t('requests.toPole'), pole_options(), old('pole_id', $poleId ?? get_str('pole')), ['empty' => t('common.selectPlaceholder'), 'required' => true]) ?>
  <?= input_field('title', t('requests.whatDoYouNeed'), old('title'), ['required' => true, 'class' => 'wide', 'placeholder' => t('requests.titlePlaceholder')]) ?>
  <?= textarea_field('description', t('common.description'), old('description'), ['rows' => 3]) ?>
  <?= select_field('priority', t('common.priority'), enum_options('priority', PRIORITIES), old('priority', 'MEDIUM')) ?>
  <?= input_field('due_date', t('common.dueDate'), old('due_date'), ['type' => 'date']) ?>
  <?= select_field('from_pole_id', t('requests.fromPole'), pole_options(), old('from_pole_id'), ['empty' => t('common.none')]) ?>
  <?= select_field('project_id', t('common.project'), project_options(), old('project_id'), ['empty' => t('common.none')]) ?>
  <p class="row"><button class="primary"><?= h(t('requests.send')) ?></button> <small><?= h(t('requests.notifyHint')) ?></small></p>
</form>
