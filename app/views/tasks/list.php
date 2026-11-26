<?php
$query = array_filter(['scope' => $scope, 'view' => $view] + $filters);
$tabUrl = fn (array $change) => url('/tasks', array_merge($query, $change));
$columns = array_fill_keys(TASK_STATUSES, []);
foreach ($tasks as $task) {
    $columns[$task['status']][] = $task;
}
$statusOptions = enum_options('taskStatus', TASK_STATUSES);
?>
<?= page_head(t('tasks.title'), t('tasks.subtitle'), $view === 'board'
    ? '<a class="btn small" href="' . h($tabUrl(['view' => 'list'])) . '">' . h(t('common.list')) . '</a>'
    : '<a class="btn small" href="' . h($tabUrl(['view' => 'board'])) . '">' . h(t('common.board')) . '</a>') ?>

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

<form class="filters auto no-print" action="<?= h(url('/tasks')) ?>">
  <input type="hidden" name="scope" value="<?= h($scope) ?>">
  <input type="hidden" name="view" value="<?= h($view) ?>">
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
<div class="board">
  <?php foreach ($columns as $status => $list): ?>
  <section>
    <h2><?= h(enum_label('taskStatus', $status)) ?> <span class="meta"><?= count($list) ?></span></h2>
    <?php foreach ($list as $task): ?>
    <div class="card">
      <a href="<?= h(url('/tasks/' . $task['id'])) ?>"><?= h($task['title']) ?></a>
      <?= in_array($task['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $task['priority']) : '' ?>
      <div class="meta"><?= h($task['assignee_name'] ?? t('common.unassigned')) ?><?= $task['due_date'] ? ' · ' . due_html($task['due_date'], $status === 'DONE') : '' ?></div>
      <?php if ($task['project_title']): ?><div class="meta"><?= h($task['project_title']) ?></div><?php endif ?>
      <?php if (can('editTask', $user, $task)): ?>
      <form method="post" action="<?= h(url("/tasks/{$task['id']}/status")) ?>" class="move no-print">
        <?= csrf_field() ?><?= back_field() ?>
        <select name="status" aria-label="<?= h(t('tasks.moveTo')) ?>"><?= options_html($statusOptions, $status) ?></select>
        <button class="small"><?= h(t('tasks.move')) ?></button>
      </form>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </section>
  <?php endforeach ?>
</div>
<?php endif ?>
