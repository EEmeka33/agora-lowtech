<?php
/* Task fields. Expects $task (may be empty) and $form (form name). */
$v = fn ($k, $d = '') => old($k, $task[$k] ?? $d);
?>
<?= csrf_field($form) ?>
<?= input_field('title', t('common.title'), $v('title'), ['required' => true, 'class' => 'wide']) ?>
<?= textarea_field('description', t('common.description'), $v('description'), ['rows' => 3]) ?>
<?= select_field('status', t('common.status'), enum_options('taskStatus', TASK_STATUSES), $v('status', 'TODO')) ?>
<?= select_field('priority', t('common.priority'), enum_options('priority', PRIORITIES), $v('priority', 'MEDIUM')) ?>
<?= select_field('assignee_id', t('common.assignee'), member_options(), $v('assignee_id'), ['empty' => t('common.unassigned')]) ?>
<?= input_field('due_date', t('common.dueDate'), $v('due_date'), ['type' => 'date']) ?>
<?= select_field('project_id', t('common.project'), project_options(true), $v('project_id', get_str('project')), ['empty' => t('tasks.noProject')]) ?>
<?= select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id', get_str('pole')), ['empty' => t('common.none')]) ?>
