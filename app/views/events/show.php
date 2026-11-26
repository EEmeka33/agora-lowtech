<?php
$e = $event;
$counts = array_count_values(array_column($attendees, 'status'));
$isMeeting = in_array($e['type'], ['GENERAL_ASSEMBLY', 'BOARD_MEETING', 'POLE_MEETING'], true);
?>
<p class="meta"><a href="<?= h(url('/events')) ?>">← <?= h(t('events.title')) ?></a></p>
<?= page_head($e['title'], ucfirst(fmt_date($e['start_at'], 'long')) . ' · ' . fmt_date($e['start_at'], 'time') . ($e['end_at'] ? ' → ' . (substr($e['end_at'], 0, 10) === substr($e['start_at'], 0, 10) ? fmt_date($e['end_at'], 'time') : fmt_date($e['end_at'], 'datetime')) : ''), badge(enum_label('eventType', $e['type']))) ?>

<div class="cols">
<div>
  <?php if ($e['location']): ?><p><strong><?= h(t('common.location')) ?> :</strong> <?= h($e['location']) ?></p><?php endif ?>
  <?php if ($e['description']): ?><div class="text"><?= text_html($e['description']) ?></div><?php endif ?>

  <div class="banner no-print">
    <p><?= h(t('events.areYouComing')) ?> <?= $mine ? status_badge('rsvp', $mine) : '' ?></p>
    <?php foreach (RSVP as $s): ?>
      <?= button_form("/events/{$e['id']}/rsvp", enum_label('rsvp', $s), ['status' => $s], $mine === $s ? 'primary' : '') ?>
    <?php endforeach ?>
  </div>

  <h2><?= h(t('events.agenda')) ?></h2>
  <?= $e['agenda'] ? '<div class="text">' . text_html($e['agenda']) . '</div>' : empty_html(t('events.noAgenda')) ?>

  <?php if ($isMeeting || $e['minutes']): ?>
  <h2 id="minutes"><?= h(t('events.minutes')) ?></h2>
  <?= $e['minutes'] ? '<div class="text">' . text_html($e['minutes']) . '</div>' : empty_html(t('events.noMinutes')) ?>
  <?php if (can('writeMinutes', $user)): ?>
  <details class="panel no-print"><summary><?= h(t('events.editMinutes')) ?></summary>
    <form method="post" action="<?= h(url("/events/{$e['id']}/minutes")) ?>" class="form"><?= csrf_field() ?>
      <?= textarea_field('minutes', t('events.minutes'), $e['minutes'], ['rows' => 12, 'hint' => t('events.minutesHint')]) ?>
      <p class="row"><button class="primary"><?= h(t('common.save')) ?></button></p></form>
  </details>
  <?php endif ?>
  <p class="meta no-print"><?= h(t('events.printHint')) ?></p>
  <?php endif ?>
</div>
<aside>
  <h2><?= h(t('events.attendees')) ?></h2>
  <p class="meta"><?php foreach (RSVP as $s): ?><?= h(enum_label('rsvp', $s)) ?> : <?= (int) ($counts[$s] ?? 0) ?> · <?php endforeach ?></p>
  <ul class="plain">
    <?php foreach ($attendees as $a): ?><li><?= person((int) $a['id'], $a['name']) ?> <?= status_badge('rsvp', $a['status']) ?></li><?php endforeach ?>
  </ul>
  <table>
    <tr><th><?= h(t('common.pole')) ?></th><td><?= $e['pole_id'] ? '<a href="' . h(url('/poles/' . $e['pole_id'])) . '">' . h($e['pole_name']) . '</a>' : '—' ?></td></tr>
    <tr><th><?= h(t('common.project')) ?></th><td><?= $e['project_id'] ? '<a href="' . h(url('/projects/' . $e['project_id'])) . '">' . h($e['project_title']) . '</a>' : '—' ?></td></tr>
    <tr><th><?= h(t('events.createdBy')) ?></th><td><?= h($e['creator_name'] ?? '—') ?></td></tr>
  </table>
  <p class="no-print"><a href="<?= h(url('/volunteering', ['event' => $e['id']])) ?>#new"><?= h(t('tasks.logTime')) ?></a></p>
  <?php if (can('manageEvents', $user)): ?>
  <details class="panel no-print"<?= form_failed('event') ? ' open' : '' ?>><summary><?= h(t('events.editEvent')) ?></summary>
    <form method="post" action="<?= h(url('/events/' . $e['id'])) ?>" class="form"><?= csrf_field('event') ?>
      <?= template('events/form', ['event' => $e]) ?>
      <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p></form>
  </details>
  <p class="no-print"><?= confirm_form("/events/{$e['id']}/delete", t('events.deleteEvent')) ?></p>
  <?php endif ?>
</aside>
</div>
