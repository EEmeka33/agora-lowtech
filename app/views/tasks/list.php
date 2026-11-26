<?php
$query = array_filter(['scope' => $scope, 'view' => $view] + $filters);
$tabUrl = fn (array $change) => url('/tasks', array_merge($query, $change));
$columns = [];
foreach (TASK_STATUSES as $s) {
    $columns[$s] = [];
}
foreach ($tasks as $task) {
    $columns[$task['status']][] = $task;
}
?>
<?= page_head(t('tasks.title'), t('tasks.subtitle')) ?>

<details class="panel" id="new"<?= get_str('new') || form_failed('task') ? ' open' : '' ?>>
  <summary><?= h(t('tasks.newTask')) ?></summary>
  <form method="post" action="<?= h(url('/tasks')) ?>" class="form">
    <?= template('tasks/form', ['task' => [], 'form' => 'task']) ?>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
  </form>
</details>

<?= tabs_html([
    'all' => [t('tasks.scope.all'), $tabUrl(['scope' => 'all'])],
    'mine' => [t('tasks.scope.mine'), $tabUrl(['scope' => 'mine'])],
    'unassigned' => [t('tasks.scope.unassigned'), $tabUrl(['scope' => 'unassigned'])],
], $scope) ?>

<form class="filters no-print" action="<?= h(url('/tasks')) ?>">
  <input type="hidden" name="scope" value="<?= h($scope) ?>">
  <?= select_field('view', t('tasks.view'), ['board' => t('common.board'), 'list' => t('common.list')], $view) ?>
  <?= select_field('project', t('common.project'), project_options(true), $filters['project'], ['empty' => t('common.all')]) ?>
  <?= select_field('pole', t('common.pole'), pole_options(), $filters['pole'], ['empty' => t('common.all')]) ?>
  <?= select_field('person', t('common.assignee'), member_options(), $filters['person'], ['empty' => t('common.all')]) ?>
  <?= select_field('priority', t('common.priority'), enum_options('priority', PRIORITIES), $filters['priority'], ['empty' => t('common.all')]) ?>
  <button><?= h(t('common.filter')) ?></button>
  <?php if (array_filter($filters)): ?><a href="<?= h(url('/tasks', ['scope' => $scope, 'view' => $view])) ?>"><?= h(t('common.clearFilters')) ?></a><?php endif ?>
</form>

<?php if (!$tasks): ?>
  <?= empty_html(t('tasks.emptyText')) ?>
<?php elseif ($view === 'list'): ?>
  <?= template('tasks/table', ['tasks' => $tasks]) ?>
<?php else: ?>
<p class="meta"><?= h(t('tasks.moveHint')) ?></p>
<div class="board">
  <?php foreach ($columns as $status => $list): $i = array_search($status, TASK_STATUSES, true); ?>
  <section>
    <h2><?= h(enum_label('taskStatus', $status)) ?> <small>(<?= count($list) ?>)</small></h2>
    <?php foreach ($list as $task): ?>
    <div class="card">
      <a href="<?= h(url('/tasks/' . $task['id'])) ?>"><?= h($task['title']) ?></a>
      <?= in_array($task['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $task['priority']) : '' ?>
      <div class="meta"><?= h($task['assignee_name'] ?? t('common.unassigned')) ?><?= $task['due_date'] ? ' · ' . due_html($task['due_date'], $status === 'DONE') : '' ?>
        <?= $task['project_title'] ? '<br>' . h($task['project_title']) : '' ?></div>
      <?php if (can('editTask', $user, $task)): ?>
      <div class="move no-print">
        <?php if ($i > 0): ?><?= button_form("/tasks/{$task['id']}/status", '◀', ['status' => TASK_STATUSES[$i - 1]], '', t('tasks.moveTo') . ' ' . enum_label('taskStatus', TASK_STATUSES[$i - 1])) ?><?php endif ?>
        <?php if ($i < count(TASK_STATUSES) - 1): ?><?= button_form("/tasks/{$task['id']}/status", '▶', ['status' => TASK_STATUSES[$i + 1]], '', t('tasks.moveTo') . ' ' . enum_label('taskStatus', TASK_STATUSES[$i + 1])) ?><?php endif ?>
      </div>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </section>
  <?php endforeach ?>
</div>
<?php endif ?>
