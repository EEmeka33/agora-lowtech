<?php /* A list of tasks as a table. Expects $tasks (with project_title, assignee_name) and $showAssignee. */ ?>
<div class="table"><table>
  <thead><tr>
    <th><?= h(t('common.title')) ?></th><th><?= h(t('common.status')) ?></th>
    <?php if ($showAssignee ?? true): ?><th><?= h(t('common.assignee')) ?></th><?php endif ?>
    <th><?= h(t('common.dueDate')) ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($tasks as $task): ?>
    <tr>
      <td><a href="<?= h(url('/tasks/' . $task['id'])) ?>"><?= h($task['title']) ?></a>
        <?= in_array($task['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $task['priority']) : '' ?>
        <?php if (!empty($task['project_title'])): ?><div class="meta"><?= h($task['project_title']) ?></div><?php endif ?></td>
      <td><?= status_badge('taskStatus', $task['status']) ?></td>
      <?php if ($showAssignee ?? true): ?><td><?= person($task['assignee_id'] ? (int) $task['assignee_id'] : null, $task['assignee_name'] ?? null) ?></td><?php endif ?>
      <td><?= due_html($task['due_date'], $task['status'] === 'DONE') ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
