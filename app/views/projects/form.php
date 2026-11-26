<?php $v = fn ($k, $d = '') => old($k, $project[$k] ?? $d); ?>
<?= csrf_field('project') ?>
<?= input_field('title', t('common.title'), $v('title'), ['required' => true, 'class' => 'wide']) ?>
<?= textarea_field('description', t('common.description'), $v('description'), ['rows' => 3]) ?>
<?= textarea_field('objectives', t('projects.objectives'), $v('objectives'), ['rows' => 2, 'placeholder' => t('projects.objectivesPlaceholder')]) ?>
<?= select_field('status', t('common.status'), enum_options('projectStatus', PROJECT_STATUSES), $v('status', 'PLANNED')) ?>
<?= select_field('priority', t('common.priority'), enum_options('priority', PRIORITIES), $v('priority', 'MEDIUM')) ?>
<?= select_field('lead_id', t('common.lead'), member_options(), $v('lead_id'), ['empty' => t('common.nobody')]) ?>
<?= select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id', get_str('pole')), ['empty' => t('projects.noPole')]) ?>
<?= input_field('start_date', t('common.startDate'), $v('start_date'), ['type' => 'date']) ?>
<?= input_field('due_date', t('common.dueDate'), $v('due_date'), ['type' => 'date']) ?>
<?= input_field('budget', t('common.budget'), old('budget', money_input(isset($project['budget_cents']) ? (int) $project['budget_cents'] : null)), ['attrs' => ['inputmode' => 'decimal'], 'placeholder' => '0']) ?>
<?= select_field('progress_mode', t('projects.progressMode'), ['AUTO' => t('projects.progressAuto'), 'MANUAL' => t('projects.progressManual')], $v('progress_mode', 'AUTO')) ?>
<?= input_field('manual_progress', t('projects.manualProgress'), $v('manual_progress', 0), ['type' => 'number', 'attrs' => ['min' => 0, 'max' => 100]]) ?>
