<?php
$hour = (int) date('G');
$greeting = t('dashboard.greeting.' . ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')), ['name' => first_name($user['name'])]);
$canAnnounce = can('manageAnnouncements', $user);
?>
<?= page_head($greeting, ucfirst(fmt_date(today(), 'long')) . ' · ' . t('dashboard.subtitle'),
    '<a href="' . h(url('/tasks', ['new' => 1])) . '#new">' . h(t('nav.newTask')) . '</a>'
    . (can('manageEvents', $user) ? ' · <a href="' . h(url('/events', ['new' => 1])) . '#new">' . h(t('nav.newEvent')) . '</a>' : '')) ?>

<?php if ($founding['total'] > 0 && $founding['done'] < $founding['total']): ?>
<p class="banner">
  <strong><?= h(t('dashboard.foundingTitle')) ?></strong> · <?= progress_html($founding['percent']) ?>
  · <?= h(tn('dashboard.foundingText', $founding['done'], ['done' => $founding['done'], 'total' => $founding['total']])) ?>
  <?php if ($nextStep): ?><br><?= h(t('dashboard.foundingNext')) ?> : <?= h($nextStep['title']) ?><?php endif ?>
  <br><a href="<?= h(url('/foundation')) ?>"><?= h(t('dashboard.foundingContinue')) ?> →</a>
</p>
<?php endif ?>

<div class="grid stats">
  <a class="box" href="<?= h(url('/members')) ?>"><span class="stat"><?= (int) $stats['members'] ?></span><?= h(t('dashboard.stats.members')) ?></a>
  <a class="box" href="<?= h(url('/projects')) ?>"><span class="stat"><?= (int) $stats['projects'] ?></span><?= h(t('dashboard.stats.projects')) ?></a>
  <a class="box" href="<?= h(url('/tasks', ['scope' => 'all'])) ?>"><span class="stat"><?= (int) $stats['open_tasks'] ?></span><?= h(t('dashboard.stats.openTasks')) ?>
    <small class="<?= $stats['overdue'] ? 'late' : '' ?>">(<?= h(tn('dashboard.stats.overdue', (int) $stats['overdue'])) ?>)</small></a>
  <a class="box" href="<?= h(url('/finance')) ?>"><span class="stat"><?= h(fmt_money((int) $stats['balance'])) ?></span><?= h(t('dashboard.stats.balance')) ?></a>
</div>

<div class="cols">
<div>
  <h2><?= h(t('dashboard.myTasks')) ?> <small>(<?= count($myTasks) ?>)</small></h2>
  <?php if (!$myTasks): ?>
    <?= empty_html(t('dashboard.myTasksEmpty')) ?>
  <?php else: ?>
    <?= template('tasks/table', ['tasks' => $myTasks, 'showAssignee' => false]) ?>
    <p><a href="<?= h(url('/tasks', ['scope' => 'mine'])) ?>"><?= h(t('common.viewAll')) ?></a></p>
  <?php endif ?>

  <?php if ($requests): ?>
  <h2><?= h(t('dashboard.requestsForYou')) ?> <small>(<?= count($requests) ?>)</small></h2>
  <ul class="plain">
    <?php foreach ($requests as $r): ?>
    <li>
      <a href="<?= h(url('/requests/' . $r['id'])) ?>"><?= h($r['title']) ?></a> <?= status_badge('priority', $r['priority'] !== 'MEDIUM' ? $r['priority'] : null) ?>
      <div class="meta"><?= h($r['pole_name']) ?> · <?= h(t('requests.askedBy', ['name' => $r['requester_name'] ?? '—', 'when' => fmt_relative($r['created_at'])])) ?></div>
    </li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>

  <h2><?= h(t('dashboard.projectsInProgress')) ?></h2>
  <?php if (!$projects): ?>
    <?= empty_html(t('dashboard.projectsEmpty')) ?>
  <?php else: ?>
  <ul class="plain">
    <?php foreach ($projects as $p): ?>
    <li><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h($p['title']) ?></a> <?= status_badge('health', $p['health']) ?>
      <div class="meta"><?= progress_html($p['progress']) ?><?= $p['lead_name'] ? ' · ' . h($p['lead_name']) : '' ?><?= $p['due_date'] ? ' · ' . due_html($p['due_date']) : '' ?></div></li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>

  <h2><?= h(t('dashboard.announcements')) ?></h2>
  <?php if ($canAnnounce): ?>
  <details class="panel"<?= form_failed('announce') ? ' open' : '' ?>>
    <summary><?= h(t('dashboard.newAnnouncement')) ?></summary>
    <form method="post" action="<?= h(url('/announcements')) ?>" class="form">
      <?= csrf_field('announce') ?>
      <?= input_field('title', t('dashboard.announcementTitle'), old('title'), ['required' => true, 'class' => 'wide']) ?>
      <?= textarea_field('body', t('dashboard.announcementBody'), old('body'), ['required' => true]) ?>
      <div class="row"><?= checkbox_field('pinned', t('dashboard.pin')) ?></div>
      <p class="row"><button class="primary"><?= h(t('common.create')) ?></button> <small><?= h(t('dashboard.announceHint')) ?></small></p>
    </form>
  </details>
  <?php endif ?>
  <?php if (!$announcements): ?>
    <?= empty_html(t('dashboard.announcementsEmpty')) ?>
  <?php endif ?>
  <?php foreach ($announcements as $an): ?>
  <article class="box gap">
    <h3><?= h($an["title"]) ?> <?= $an["pinned"] ? badge(t("dashboard.pinned"), "info") : "" ?></h3>
    <p class="meta"><?= h($an['author_name'] ?? '') ?> · <?= h(fmt_relative($an['created_at'])) ?></p>
    <div class="text"><?= text_html($an['body']) ?></div>
    <?php if ($canAnnounce): ?>
    <p class="actions no-print"><?= button_form("/announcements/{$an['id']}/pin", $an['pinned'] ? t('dashboard.unpin') : t('dashboard.pin'), [], 'link') ?>
      <?= confirm_form("/announcements/{$an['id']}/delete", t('common.delete')) ?></p>
    <?php endif ?>
  </article>
  <?php endforeach ?>
</div>

<div>
  <h2><?= h(t('dashboard.upcomingEvents')) ?></h2>
  <?php if (!$events): ?>
    <?= empty_html(t('dashboard.eventsEmpty')) ?>
  <?php else: ?>
  <ul class="plain">
    <?php foreach ($events as $e): ?>
    <li><a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h($e['title']) ?></a>
      <div class="meta"><?= h(fmt_date($e['start_at'], 'datetime')) ?><?= $e['location'] ? ' · ' . h($e['location']) : '' ?>
      <?= $e['my_rsvp'] ? ' · ' . status_badge('rsvp', $e['my_rsvp']) : '' ?></div></li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>

  <?php if ($polls): ?>
  <h2><?= h(t('dashboard.openVotes')) ?></h2>
  <ul class="plain">
    <?php foreach ($polls as $p): ?>
    <li><a href="<?= h(url('/polls')) ?>#poll-<?= (int) $p['id'] ?>"><?= h($p['question']) ?></a>
      <?= $p['voted'] ? badge(t('polls.youVoted'), 'ok') : badge(t('polls.notVoted'), 'warn') ?></li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>

  <h2><?= h(t('dashboard.recentActivity')) ?></h2>
  <?= template('activity/items', ['items' => $activity]) ?>
  <p><a href="<?= h(url('/activity')) ?>"><?= h(t('common.viewAll')) ?></a></p>
</div>
</div>
