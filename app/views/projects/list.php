<?= page_head(t('projects.title'), t('projects.subtitle')) ?>
<?php if (can('createProject', $user) || $leadPoles): ?>
<details class="panel" id="new"<?= get_str('new') || form_failed('project') ? ' open' : '' ?>>
  <summary><?= h(t('projects.newProject')) ?></summary>
  <form method="post" action="<?= h(url('/projects')) ?>" class="form">
    <?= template('projects/form', ['project' => []]) ?>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
  </form>
</details>
<?php endif ?>

<?= tabs_html([
    'active' => [t('projects.filters.active'), url('/projects', ['filter' => 'active', 'view' => $view])],
    'all' => [t('projects.filters.all'), url('/projects', ['filter' => 'all', 'view' => $view])],
    'done' => [t('projects.filters.done'), url('/projects', ['filter' => 'done', 'view' => $view])],
], $filter) ?>
<p class="no-print"><?= $view === 'list'
    ? '<a href="' . h(url('/projects', ['filter' => $filter, 'view' => 'timeline'])) . '">' . h(t('common.timeline')) . '</a>'
    : '<a href="' . h(url('/projects', ['filter' => $filter])) . '">' . h(t('common.list')) . '</a>' ?></p>

<?php if (!$projects): ?>
  <?= empty_html(t('projects.emptyText')) ?>
<?php elseif ($view === 'list'): ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('common.project')) ?></th><th><?= h(t('common.status')) ?></th><th><?= h(t('common.progress')) ?></th><th><?= h(t('common.lead')) ?></th><th><?= h(t('common.dueDate')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($projects as $p): ?>
  <tr>
    <td><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h($p['title']) ?></a><?= $p['pole_name'] ? '<div class="meta">' . h($p['pole_name']) . '</div>' : '' ?></td>
    <td><?= status_badge('projectStatus', $p['status']) ?> <?= status_badge('health', $p['health']) ?></td>
    <td><?= progress_html($p['progress']) ?><div class="meta"><?= h(t('projects.tasksDone', ['done' => $p['tasks_done'], 'total' => $p['tasks_total']])) ?></div></td>
    <td><?= person($p['lead_id'] ? (int) $p['lead_id'] : null, $p['lead_name']) ?></td>
    <td><?= due_html($p['due_date'], in_array($p['status'], ['COMPLETED', 'CANCELLED'], true)) ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php else:
    // Timeline: 12 months, from 2 months ago.
    $first = new DateTimeImmutable(date('Y-m-01') . ' -2 months');
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $months[] = $first->modify("+$i months")->format('Y-m');
    }
    $current = date('Y-m');
    $scheduled = array_filter($projects, fn ($p) => $p['start_date'] || $p['due_date']);
    $short = explode(',', t('date.monthsShort'));
?>
  <?php if (!$scheduled): ?><?= empty_html(t('projects.timelineEmpty')) ?><?php else: ?>
  <div class="table"><table class="gantt">
    <thead><tr><th><?= h(t('common.project')) ?></th>
      <?php foreach ($months as $m): ?><th class="month"><?= h($short[(int) substr($m, 5) - 1]) ?><br><?= substr($m, 2, 2) ?></th><?php endforeach ?></tr></thead>
    <tbody>
    <?php foreach ($scheduled as $p):
        $from = substr($p['start_date'] ?: $p['due_date'], 0, 7);
        $to = substr($p['due_date'] ?: $p['start_date'], 0, 7);
        $late = $p['health'] === 'OFF_TRACK'; ?>
    <tr><td><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h(str_cut($p['title'], 40)) ?></a> <small><?= $p['progress'] ?>&nbsp;%</small></td>
      <?php foreach ($months as $m): $on = $m >= $from && $m <= $to; ?>
      <td class="month<?= $on ? ' on' . ($late ? ' late' : '') : '' ?><?= $m === $current ? ' now' : '' ?>" title="<?= $on ? h(fmt_date($p['start_date']) . ' → ' . fmt_date($p['due_date'])) : '' ?>"><?= $on ? '<span class="skip">■</span>' : '' ?></td>
      <?php endforeach ?></tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <p class="meta"><?= h(t('projects.timelineHint')) ?></p>
  <?php endif ?>
<?php endif ?>
