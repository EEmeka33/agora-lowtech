<?php
$assoc = current_association();
$myTotal = array_sum(array_column($mine, 'minutes'));
$value = $assoc['volunteer_hour_cents'] ? (int) round($total / 60 * $assoc['volunteer_hour_cents']) : null;
$maxMember = $byMember ? max(array_column($byMember, 'minutes')) : 0;
$maxPole = $byPole ? max(array_column($byPole, 'minutes')) : 0;
?>
<?= page_head(t('volunteering.title'), t('volunteering.subtitle'),
    '<a href="' . h(url('/volunteering', ['year' => $year - 1])) . '">← ' . ($year - 1) . '</a> <strong>' . $year . '</strong> <a href="' . h(url('/volunteering', ['year' => $year + 1])) . '">' . ($year + 1) . ' →</a>') ?>

<details class="panel" id="new"<?= get_str('task') || get_str('event') || get_str('project') || form_failed('time') ? ' open' : '' ?>>
  <summary><?= h(t('volunteering.logTime')) ?></summary>
  <form method="post" action="<?= h(url('/volunteering')) ?>" class="form">
    <?= csrf_field('time') ?>
    <?= input_field('duration', t('volunteering.duration'), old('duration'), ['required' => true, 'placeholder' => '1h30', 'hint' => t('volunteering.durationHint')]) ?>
    <?= input_field('date', t('common.date'), old('date', today()), ['type' => 'date']) ?>
    <?= input_field('description', t('common.description'), old('description'), ['class' => 'wide', 'placeholder' => t('volunteering.descriptionPlaceholder')]) ?>
    <?= select_field('pole_id', t('common.pole'), pole_options(), old('pole_id'), ['empty' => t('common.none')]) ?>
    <?= select_field('project_id', t('common.project'), project_options(true), old('project_id', get_str('project')), ['empty' => t('common.none')]) ?>
    <?php if (get_str('task') && ($task = find('tasks', get_str('task')))): ?>
      <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>"><p class="wide meta"><?= h(t('common.tasks')) ?><?= colon() ?><?= h($task['title']) ?></p>
    <?php endif ?>
    <?php if (get_str('event') && ($event = find('events', get_str('event')))): ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><p class="wide meta"><?= h(t('nav.events')) ?><?= colon() ?><?= h($event['title']) ?></p>
    <?php endif ?>
    <p class="row"><button class="primary"><?= h(t('common.save')) ?></button></p>
  </form>
</details>

<div class="grid stats">
  <div class="box"><span class="stat"><?= h(t('volunteering.hours', ['h' => minutes_to_hours($myTotal)])) ?></span><?= h(t('volunteering.myYear', ['y' => $year])) ?></div>
  <div class="box"><span class="stat"><?= h(t('volunteering.hours', ['h' => minutes_to_hours($total)])) ?></span><?= h(t('volunteering.total', ['y' => $year])) ?></div>
  <div class="box"><span class="stat"><?= $value !== null ? h(fmt_money($value)) : '—' ?></span><?= h(t('volunteering.value')) ?>
    <?= $value === null ? '<small>' . h(t('volunteering.valueHint')) . '</small>' : '<small>' . h(t('volunteering.valueRate', ['rate' => fmt_money((int) $assoc['volunteer_hour_cents'])])) . '</small>' ?></div>
</div>

<div class="cols">
<div>
  <h2><?= h(t('volunteering.mine')) ?></h2>
  <?php if (!$mine): ?><?= empty_html(t('volunteering.emptyMine')) ?><?php else: ?>
  <div class="table"><table>
    <?php foreach ($mine as $e): ?>
    <tr><td><?= h(fmt_date($e['date'])) ?></td><td class="num"><?= h(t('volunteering.hours', ['h' => minutes_to_hours((int) $e['minutes'])])) ?></td>
      <td><?= h($e['description'] ?? '') ?><div class="meta"><?= h(implode(' · ', array_filter([$e['pole_name'], $e['project_title']]))) ?></div></td>
      <td class="no-print"><?= confirm_form("/volunteering/{$e['id']}/delete", t('common.delete')) ?></td></tr>
    <?php endforeach ?>
  </table></div>
  <?php endif ?>

  <?php if ($recent): ?>
  <h2><?= h(t('volunteering.recent')) ?></h2>
  <div class="table"><table>
    <?php foreach ($recent as $e): ?>
    <tr><td><?= h(fmt_date($e['date'])) ?></td><td><?= person((int) $e['user_id'], $e['user_name']) ?></td><td class="num"><?= h(t('volunteering.hours', ['h' => minutes_to_hours((int) $e['minutes'])])) ?></td>
      <td><?= h($e['description'] ?? '') ?></td><td class="no-print"><?= confirm_form("/volunteering/{$e['id']}/delete", t('common.delete')) ?></td></tr>
    <?php endforeach ?>
  </table></div>
  <?php endif ?>
</div>
<div>
  <h2><?= h(t('volunteering.byMember')) ?></h2>
  <table class="bars">
    <?php foreach ($byMember as $m): ?>
    <tr><td><?= person((int) $m['id'], $m['name']) ?></td><td class="num"><?= h(minutes_to_hours((int) $m['minutes'])) ?>&nbsp;h</td><td><?= bar((int) $m['minutes'], $maxMember) ?></td></tr>
    <?php endforeach ?>
  </table>
  <h2><?= h(t('volunteering.byPole')) ?></h2>
  <table class="bars">
    <?php foreach ($byPole as $p): ?>
    <tr><td><?= h($p['name']) ?></td><td class="num"><?= h(minutes_to_hours((int) $p['minutes'])) ?>&nbsp;h</td><td><?= bar((int) $p['minutes'], $maxPole) ?></td></tr>
    <?php endforeach ?>
  </table>
  <?php if (is_manager($user)): ?><p class="no-print"><a href="<?= h(url('/volunteering.csv', ['year' => $year])) ?>"><?= h(t('volunteering.export')) ?> (CSV)</a></p><?php endif ?>
</div>
</div>
