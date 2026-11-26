<?php $v = fn ($k, $d = '') => old($k, $event[$k] ?? $d); ?>
<?= input_field('title', t('common.title'), $v('title'), ['required' => true, 'class' => 'wide']) ?>
<?= select_field('type', t('common.type'), enum_options('eventType', EVENT_TYPES), $v('type', 'EVENT')) ?>
<?= input_field('location', t('common.location'), $v('location')) ?>
<?= input_field('start_at', t('events.start'), form_errors() ? old('start_at') : datetime_input($event['start_at'] ?? null), ['type' => 'datetime-local', 'required' => true, 'hint' => t('events.dateHint')]) ?>
<?= input_field('end_at', t('events.end'), form_errors() ? old('end_at') : datetime_input($event['end_at'] ?? null), ['type' => 'datetime-local']) ?>
<?= select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id'), ['empty' => t('common.none')]) ?>
<?= select_field('project_id', t('common.project'), project_options(true), $v('project_id'), ['empty' => t('common.none')]) ?>
<?= textarea_field('description', t('common.description'), $v('description'), ['rows' => 3]) ?>
<?= textarea_field('agenda', t('events.agenda'), $v('agenda'), ['rows' => 4, 'placeholder' => t('events.agendaPlaceholder')]) ?>
