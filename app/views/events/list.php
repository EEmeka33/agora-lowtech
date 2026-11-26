<?= page_head(t('events.title'), t('events.subtitle')) ?>
<?php if (can('manageEvents', $user)): ?>
<details class="panel" id="new"<?= get_str('new') || form_failed('event') ? ' open' : '' ?>>
  <summary><?= h(t('events.newEvent')) ?></summary>
  <form method="post" action="<?= h(url('/events')) ?>" class="form">
    <?= csrf_field('event') ?>
    <?= template('events/form', ['event' => []]) ?>
    <div class="row"><?= checkbox_field('notify', t('events.notifyMembers'), true, t('events.notifyHint')) ?></div>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
  </form>
</details>
<?php endif ?>
<?= tabs_html([
    'upcoming' => [t('events.upcoming'), url('/events')],
    'calendar' => [t('common.calendar'), url('/events', ['view' => 'calendar'])],
    'past' => [t('events.past'), url('/events', ['view' => 'past'])],
], $view) ?>

<?php if ($view === 'calendar'):
    $prev = $first->modify('-1 month')->format('Y-m');
    $next = $first->modify('+1 month')->format('Y-m');
    $letters = explode(',', t('date.daysShort'));
?>
<p class="actions"><a href="<?= h(url('/events', ['view' => 'calendar', 'month' => $prev])) ?>">← <?= h(t('common.previous')) ?></a>
  <strong><?= h(ucfirst(fmt_date($first->format('Y-m-d'), 'month'))) ?></strong>
  <a href="<?= h(url('/events', ['view' => 'calendar', 'month' => $next])) ?>"><?= h(t('common.next')) ?> →</a></p>
<div class="table"><table class="cal">
  <thead><tr><?php foreach ($letters as $l): ?><th><?= h($l) ?></th><?php endforeach ?></tr></thead>
  <tbody>
  <?php for ($day = $start; $day <= $end; $day = $day->modify('+1 day')):
      $d = $day->format('Y-m-d');
      if ($day->format('N') === '1'): ?><tr><?php endif ?>
    <td class="<?= $day->format('Y-m') !== $month ? 'out' : '' ?><?= $d === today() ? ' today' : '' ?>">
      <span class="day"><?= $day->format('j') ?></span>
      <?php foreach (array_slice($byDay[$d] ?? [], 0, 3) as $e): ?>
        <a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h(substr($e['start_at'], 11, 5)) ?> <?= h(str_cut($e['title'], 28)) ?></a><br>
      <?php endforeach ?>
      <?php if (count($byDay[$d] ?? []) > 3): ?><small><?= h(tn('events.moreEvents', count($byDay[$d]) - 3)) ?></small><?php endif ?>
    </td>
    <?php if ($day->format('N') === '7'): ?></tr><?php endif ?>
  <?php endfor ?>
  </tbody>
</table></div>
<?php elseif (!$events): ?>
  <?= empty_html($view === 'past' ? t('events.emptyPast') : t('events.emptyText')) ?>
<?php else: ?>
<ul class="plain">
  <?php foreach ($events as $e): ?>
  <li><strong><?= h(fmt_date($e['start_at'], 'datetime')) ?></strong> · <a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h($e['title']) ?></a>
    <?= badge(enum_label('eventType', $e['type'])) ?> <?= $e['my_rsvp'] ? status_badge('rsvp', $e['my_rsvp']) : '' ?>
    <div class="meta"><?= h(implode(' · ', array_filter([$e['location'], $e['pole_name'], tn('common.countAttendees', (int) $e['going'])]))) ?></div></li>
  <?php endforeach ?>
</ul>
<?php endif ?>
<p class="meta"><?= h(t('events.feedHint')) ?> <a href="<?= h(url('/settings/notifications')) ?>#calendar"><?= h(t('settings.calendar.title')) ?></a></p>
