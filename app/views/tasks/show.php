<?php $canEdit = can('editTask', $user, $task); ?>
<p class="meta"><a href="<?= h(url('/tasks')) ?>">← <?= h(t('tasks.title')) ?></a></p>
<?= page_head($task['title'], '', status_badge('taskStatus', $task['status']) . ' ' . status_badge('priority', $task['priority'])) ?>

<div class="cols">
<div>
  <?php if ($task['description']): ?><div class="text"><?= text_html($task['description']) ?></div><?php endif ?>
  <?php if ($request): ?><p><?= h(t('tasks.fromRequest')) ?><?= colon() ?><a href="<?= h(url('/requests/' . $request['id'])) ?>"><?= h($request['title']) ?></a></p><?php endif ?>

  <?php if ($canEdit): ?>
  <p class="actions no-print">
    <?php foreach (TASK_STATUSES as $s): if ($s !== $task['status']): ?>
      <?= button_form("/tasks/{$task['id']}/status", enum_label('taskStatus', $s), ['status' => $s], $s === 'DONE' ? 'primary' : '') ?>
    <?php endif; endforeach ?>
  </p>
  <details class="panel edit"<?= form_failed('task') ? ' open' : '' ?>>
    <summary><?= h(t('tasks.editTask')) ?></summary>
    <form method="post" action="<?= h(url('/tasks/' . $task['id'])) ?>" class="form">
      <?= template('tasks/form', ['task' => $task, 'form' => 'task']) ?>
      <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
    </form>
  </details>
  <?php endif ?>

  <h2 id="comments"><?= h(t('tasks.comments')) ?> <small>(<?= count($comments) ?>)</small></h2>
  <?php if (!$comments): ?><?= empty_html(t('tasks.noComments')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($comments as $c): ?>
    <li><div class="meta"><?= person($c['author_id'] ? (int) $c['author_id'] : null, $c['author_name']) ?> · <?= h(fmt_relative($c['created_at'])) ?>
      <?php if ((int) $c['author_id'] === $user['id'] || is_admin($user)): ?> · <?= confirm_form("/tasks/{$task['id']}/comments/{$c['id']}/delete", t('common.delete')) ?><?php endif ?></div>
      <div class="text"><?= text_html($c['body']) ?></div></li>
    <?php endforeach ?>
  </ul>
  <form method="post" action="<?= h(url('/tasks/' . $task['id'] . '/comments')) ?>" class="form no-print">
    <?= csrf_field('comment') ?>
    <?= textarea_field('body', t('tasks.postComment'), form_failed('comment') ? old('body') : '', ['rows' => 3, 'required' => true, 'placeholder' => t('tasks.commentPlaceholder')]) ?>
    <p class="row"><button><?= h(t('tasks.postComment')) ?></button></p>
  </form>
</div>

<aside>
  <table>
    <tr><th><?= h(t('common.assignee')) ?></th><td><?= person($task['assignee_id'] ? (int) $task['assignee_id'] : null, $task['assignee_name']) ?></td></tr>
    <tr><th><?= h(t('common.dueDate')) ?></th><td><?= due_html($task['due_date'], $task['status'] === 'DONE') ?: '—' ?></td></tr>
    <tr><th><?= h(t('common.project')) ?></th><td><?= $task['project_id'] ? '<a href="' . h(url('/projects/' . $task['project_id'])) . '">' . h($task['project_title']) . '</a>' : '—' ?></td></tr>
    <tr><th><?= h(t('common.pole')) ?></th><td><?= $task['pole_id'] ? '<a href="' . h(url('/poles/' . $task['pole_id'])) . '">' . h($task['pole_name']) . '</a>' : '—' ?></td></tr>
    <tr><th><?= h(t('tasks.createdByLabel')) ?></th><td><?= h($creator ?? '—') ?>, <?= h(fmt_date(utc_to_local($task['created_at']))) ?></td></tr>
    <?php if ($task['completed_at']): ?><tr><th><?= h(t('enums.taskStatus.DONE')) ?></th><td><?= h(fmt_instant($task['completed_at'])) ?></td></tr><?php endif ?>
    <tr><th><?= h(t('volunteering.title')) ?></th><td><?= h(t('volunteering.hours', ['h' => minutes_to_hours($minutes)])) ?> · <a href="<?= h(url('/volunteering', ['task' => $task['id']])) ?>#new"><?= h(t('tasks.logTime')) ?></a></td></tr>
  </table>
  <?php if (can('deleteTask', $user, $task)): ?>
  <p class="no-print"><?= confirm_form("/tasks/{$task['id']}/delete", t('tasks.deleteTask')) ?></p>
  <?php endif ?>
</aside>
</div>
