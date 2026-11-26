<?php
$hour = (int) date('G');
$greeting = t('dashboard.greeting.' . ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')), ['name' => first_name($user['name'])]);
$canAnnounce = can('manageAnnouncements', $user);
$head = fn (string $title, ?string $href = null, string $link = '') => '<div class="card-head"><h2>' . h($title) . '</h2>'
    . ($href ? '<a href="' . h(url($href)) . '">' . h($link ?: t('common.viewAll')) . '</a>' : '') . '</div>';
?>
<?= page_head($greeting, ucfirst(fmt_date(today(), 'long')),
    '<a class="btn primary" href="' . h(url('/tasks', ['new' => 1])) . '#new">' . h(t('nav.newTask')) . '</a>'
    . '<a class="btn" href="' . h(url('/requests', ['new' => 1])) . '#new">' . h(t('requests.newRequest')) . '</a>') ?>

<?php if ($founding['total'] > 0 && $founding['done'] < $founding['total']): ?>
<div class="banner">
  <strong><?= h(t('dashboard.foundingTitle')) ?></strong>
  <?= progress_html($founding['percent']) ?>
  <span><?= $nextStep ? h(t('dashboard.foundingNext')) . colon() . h($nextStep['title']) : '' ?></span>
  <a href="<?= h(url('/foundation')) ?>"><?= h(t('dashboard.foundingContinue')) ?> →</a>
</div>
<?php endif ?>

<div class="grid stats">
  <a class="box" href="<?= h(url('/tasks', ['scope' => 'all'])) ?>"><span class="stat"><?= (int) $stats['open_tasks'] ?></span><?= h(t('dashboard.stats.openTasks')) ?>
    <?= $stats['overdue'] ? '<span class="late"> · ' . h(tn('dashboard.stats.overdue', (int) $stats['overdue'])) . '</span>' : '' ?></a>
  <a class="box" href="<?= h(url('/projects')) ?>"><span class="stat"><?= (int) $stats['projects'] ?></span><?= h(t('dashboard.stats.projects')) ?></a>
  <a class="box" href="<?= h(url('/members')) ?>"><span class="stat"><?= (int) $stats['members'] ?></span><?= h(t('dashboard.stats.members')) ?></a>
  <a class="box" href="<?= h(url('/finance')) ?>"><span class="stat"><?= h(fmt_money((int) $stats['balance'])) ?></span><?= h(t('dashboard.stats.balance')) ?></a>
</div>

<div class="cols">
<div>
  <section class="box gap">
    <?= $head(t('dashboard.myTasks') . ($myTasks ? ' (' . count($myTasks) . ')' : ''), '/tasks?scope=mine') ?>
    <?php if (!$myTasks): ?><?= empty_html(t('dashboard.myTasksEmpty')) ?><?php else: ?>
    <ul class="plain">
      <?php foreach ($myTasks as $task): ?>
      <li><a href="<?= h(url('/tasks/' . $task['id'])) ?>"><?= h($task['title']) ?></a> <?= status_badge('taskStatus', $task['status']) ?>
        <?= in_array($task['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $task['priority']) : '' ?>
        <div class="meta"><?= h(implode(' · ', array_filter([$task['project_title']]))) ?><?= $task['due_date'] ? ($task['project_title'] ? ' · ' : '') . due_html($task['due_date']) : '' ?></div></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
  </section>

  <?php if ($requests): ?>
  <section class="box gap">
    <?= $head(t('dashboard.requestsForYou') . ' (' . count($requests) . ')', '/requests') ?>
    <ul class="plain">
      <?php foreach ($requests as $r): ?>
      <li><a href="<?= h(url('/requests/' . $r['id'])) ?>"><?= h($r['title']) ?></a> <?= in_array($r['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $r['priority']) : '' ?>
        <div class="meta"><?= h($r['pole_name']) ?> · <?= h(t('requests.askedBy', ['name' => $r['requester_name'] ?? '—', 'when' => fmt_relative($r['created_at'])])) ?></div></li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <section class="box gap">
    <?= $head(t('dashboard.projectsInProgress'), '/projects') ?>
    <?php if (!$projects): ?><?= empty_html(t('dashboard.projectsEmpty')) ?><?php else: ?>
    <ul class="plain">
      <?php foreach ($projects as $p): ?>
      <li><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h($p['title']) ?></a> <?= status_badge('health', $p['health']) ?>
        <div class="meta"><?= progress_html($p['progress']) ?><?= $p['lead_name'] ? ' · ' . h($p['lead_name']) : '' ?><?= $p['due_date'] ? ' · ' . due_html($p['due_date']) : '' ?></div></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
  </section>

  <section class="box gap">
    <?= $head(t('dashboard.announcements')) ?>
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
    <?php if (!$announcements): ?><?= empty_html(t('dashboard.announcementsEmpty')) ?><?php endif ?>
    <?php foreach ($announcements as $an): ?>
    <article class="announcement">
      <h3><?= h($an['title']) ?> <?= $an['pinned'] ? badge(t('dashboard.pinned'), 'info') : '' ?></h3>
      <p class="meta"><?= h($an['author_name'] ?? '') ?> · <?= h(fmt_relative($an['created_at'])) ?></p>
      <div class="text"><?= text_html($an['body']) ?></div>
      <?php if ($canAnnounce): ?>
      <p class="actions no-print"><?= button_form("/announcements/{$an['id']}/pin", $an['pinned'] ? t('dashboard.unpin') : t('dashboard.pin'), [], 'link') ?>
        <?= confirm_form("/announcements/{$an['id']}/delete", t('common.delete')) ?></p>
      <?php endif ?>
    </article>
    <?php endforeach ?>
  </section>
</div>

<div>
  <section class="box gap">
    <?= $head(t('dashboard.upcomingEvents'), '/events') ?>
    <?php if (!$events): ?><?= empty_html(t('dashboard.eventsEmpty')) ?><?php else: ?>
    <ul class="plain">
      <?php foreach ($events as $e): ?>
      <li><a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h($e['title']) ?></a>
        <div class="meta"><?= h(fmt_date($e['start_at'], 'datetime')) ?><?= $e['location'] ? ' · ' . h($e['location']) : '' ?></div>
        <?= $e['my_rsvp'] ? status_badge('rsvp', $e['my_rsvp']) : '' ?></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
  </section>

  <?php if ($polls): ?>
  <section class="box gap">
    <?= $head(t('dashboard.openVotes'), '/polls') ?>
    <ul class="plain">
      <?php foreach ($polls as $p): ?>
      <li><a href="<?= h(url('/polls')) ?>#poll-<?= (int) $p['id'] ?>"><?= h($p['question']) ?></a>
        <?= $p['voted'] ? badge(t('polls.youVoted'), 'ok') : badge(t('polls.notVoted'), 'warn') ?></li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <section class="box gap">
    <?= $head(t('dashboard.recentActivity'), '/activity') ?>
    <?= template('activity/items', ['items' => array_slice($activity, 0, 6)]) ?>
  </section>
</div>
</div>
