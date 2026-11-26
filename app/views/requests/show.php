<?php $r = $request; ?>
<p class="meta"><a href="<?= h(url('/requests')) ?>">← <?= h(t('requests.title')) ?></a></p>
<?= page_head($r['title'], t('requests.askedBy', ['name' => $r['requester_name'] ?? '—', 'when' => fmt_relative($r['created_at'])]), status_badge('requestStatus', $r['status']) . ' ' . status_badge('priority', $r['priority'])) ?>
<div class="cols">
<div>
  <?php if ($r['description']): ?><div class="text"><?= text_html($r['description']) ?></div><?php endif ?>

  <?php if ($r['status'] === 'OPEN'): ?>
    <div class="banner no-print">
      <?php if ($inPole || is_manager($user)): ?>
        <p><?= h(t('requests.takeHint')) ?></p>
        <?= button_form("/requests/{$r['id']}/take", t('requests.take'), [], 'primary') ?>
      <?php else: ?>
        <p><?= h(t('requests.waiting', ['pole' => $r['pole_name']])) ?></p>
      <?php endif ?>
      <?php if ($canAssign && $poleMembers): ?>
      <form method="post" action="<?= h(url("/requests/{$r['id']}/assign")) ?>" class="filters">
        <?= csrf_field() ?>
        <?= select_field('user_id', t('requests.giveTo'), $poleMembers, '', ['required' => true, 'empty' => t('common.selectPlaceholder')]) ?>
        <button><?= h(t('requests.give')) ?></button>
      </form>
      <?php endif ?>
    </div>
    <?php if (can('cancelRequest', $user, $r)): ?><p class="no-print"><?= confirm_form("/requests/{$r['id']}/cancel", t('requests.cancel'), t('requests.cancelWarning')) ?></p><?php endif ?>
  <?php elseif ($r['status'] === 'TAKEN'): ?>
    <p class="banner"><?= h(t('requests.takenBy', ['name' => $r['taken_by_name'] ?? '—'])) ?>, <?= h(fmt_relative($r['taken_at'])) ?>.
      <?php if ($task): ?><a href="<?= h(url('/tasks/' . $task['id'])) ?>"><?= h(t('requests.seeTask')) ?></a> (<?= h(enum_label('taskStatus', $task['status'])) ?>)<?php endif ?></p>
    <?php if ((int) $r['taken_by_id'] === $user['id'] || is_manager($user)): ?>
    <p class="no-print"><?= confirm_form("/requests/{$r['id']}/release", t('requests.release'), t('requests.releaseWarning')) ?></p>
    <?php endif ?>
  <?php elseif ($r['status'] === 'DONE'): ?>
    <p class="banner"><?= h(t('requests.doneOn', ['date' => fmt_instant($r['closed_at'])])) ?><?= $task ? ' · <a href="' . h(url('/tasks/' . $task['id'])) . '">' . h(t('requests.seeTask')) . '</a>' : '' ?></p>
  <?php endif ?>
</div>
<aside>
  <table>
    <tr><th><?= h(t('requests.toPole')) ?></th><td><a href="<?= h(url('/poles/' . $r['pole_id'])) ?>"><?= h($r['pole_name']) ?></a></td></tr>
    <tr><th><?= h(t('requests.fromPole')) ?></th><td><?= h($r['from_pole_name'] ?? '—') ?></td></tr>
    <tr><th><?= h(t('common.project')) ?></th><td><?= $r['project_id'] ? '<a href="' . h(url('/projects/' . $r['project_id'])) . '">' . h($r['project_title']) . '</a>' : '—' ?></td></tr>
    <tr><th><?= h(t('common.dueDate')) ?></th><td><?= due_html($r['due_date'], in_array($r['status'], ['DONE', 'CANCELLED'], true)) ?: '—' ?></td></tr>
    <tr><th><?= h(t('requests.requester')) ?></th><td><?= person($r['requester_id'] ? (int) $r['requester_id'] : null, $r['requester_name']) ?></td></tr>
  </table>
</aside>
</div>
