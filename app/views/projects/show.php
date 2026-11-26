<?php $p = $project; $done = in_array($p['status'], ['COMPLETED', 'CANCELLED'], true); ?>
<p class="meta"><a href="<?= h(url('/projects')) ?>">← <?= h(t('projects.title')) ?></a></p>
<?= page_head($p['title'], $p['pole_name'] ? t('common.pole') . colon() . $p['pole_name'] : '', status_badge('projectStatus', $p['status']) . ' ' . status_badge('health', $p['health'])) ?>

<?= tabs_html([
    'overview' => [t('projects.tabs.overview'), url("/projects/{$p['id']}")],
    'tasks' => [t('projects.tabs.tasks') . " ({$p['tasks_total']})", url("/projects/{$p['id']}", ['tab' => 'tasks'])],
    'updates' => [t('projects.tabs.updates'), url("/projects/{$p['id']}", ['tab' => 'updates'])],
    'team' => [t('projects.tabs.team') . ' (' . count($team) . ')', url("/projects/{$p['id']}", ['tab' => 'team'])],
    'budget' => [t('projects.tabs.budget'), url("/projects/{$p['id']}", ['tab' => 'budget'])],
], $tab) ?>

<?php if ($tab === 'overview'): ?>
<div class="cols">
<div>
  <?php if ($p['description']): ?><div class="text"><?= text_html($p['description']) ?></div><?php endif ?>
  <?php if ($p['objectives']): ?><h2><?= h(t('projects.objectives')) ?></h2><div class="text"><?= text_html($p['objectives']) ?></div><?php endif ?>

  <?php if ($latest): ?>
  <h2><?= h(t('projects.latestUpdate')) ?></h2>
  <div class="box"><p class="meta"><?= h($latest['author_name'] ?? '') ?> · <?= h(fmt_relative($latest['created_at'])) ?> · <?= status_badge('health', $latest['health']) ?></p>
    <div class="text"><?= text_html($latest['body']) ?></div></div>
  <?php endif ?>

  <h2><?= h(t('projects.milestones')) ?></h2>
  <?php if (!$milestones): ?><?= empty_html(t('projects.noMilestones')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($milestones as $m): ?>
    <li><?= $m['done'] ? '☑' : '☐' ?> <?= $m['done'] ? '<s>' . h($m['title']) . '</s>' : h($m['title']) ?>
      <?= $m['due_date'] ? ' · ' . due_html($m['due_date'], (bool) $m['done']) : '' ?>
      <?php if ($canEdit): ?><span class="no-print"> · <?= button_form("/projects/{$p['id']}/milestones/{$m['id']}/toggle", $m['done'] ? t('foundation.markTodo') : t('foundation.markDone'), [], 'link') ?>
        · <?= confirm_form("/projects/{$p['id']}/milestones/{$m['id']}/delete", t('common.delete')) ?></span><?php endif ?></li>
    <?php endforeach ?>
  </ul>
  <?php if ($canEdit): ?>
  <form method="post" action="<?= h(url("/projects/{$p['id']}/milestones")) ?>" class="filters no-print">
    <?= csrf_field('milestone') ?>
    <?= input_field('title', t('projects.addMilestone'), form_failed('milestone') ? old('title') : '', ['required' => true, 'placeholder' => t('projects.milestonePlaceholder')]) ?>
    <?= input_field('due_date', t('common.dueDate'), '', ['type' => 'date']) ?>
    <button><?= h(t('common.add')) ?></button>
  </form>
  <?php endif ?>

  <?php if (!empty($events)): ?>
  <h2><?= h(t('projects.events')) ?></h2>
  <ul><?php foreach ($events as $e): ?><li><a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h($e['title']) ?></a> · <?= h(fmt_date($e['start_at'])) ?></li><?php endforeach ?></ul>
  <?php endif ?>
  <?php if (!empty($documents)): ?>
  <h2><?= h(t('projects.documents')) ?></h2>
  <ul><?php foreach ($documents as $d): ?><li><a href="<?= h($d['stored_name'] ? url('/files/' . $d['id']) : $d['url']) ?>"><?= h($d['title']) ?></a></li><?php endforeach ?></ul>
  <?php endif ?>
</div>
<aside>
  <table>
    <tr><th><?= h(t('common.progress')) ?></th><td><?= progress_html($p['progress']) ?><div class="meta"><?= h(t('projects.tasksDone', ['done' => $p['tasks_done'], 'total' => $p['tasks_total']])) ?></div></td></tr>
    <tr><th><?= h(t('common.lead')) ?></th><td><?= person($p['lead_id'] ? (int) $p['lead_id'] : null, $p['lead_name']) ?></td></tr>
    <tr><th><?= h(t('common.priority')) ?></th><td><?= h(enum_label('priority', $p['priority'])) ?></td></tr>
    <tr><th><?= h(t('common.startDate')) ?></th><td><?= h(fmt_date($p['start_date']) ?: '—') ?></td></tr>
    <tr><th><?= h(t('common.dueDate')) ?></th><td><?= due_html($p['due_date'], $done) ?: '—' ?></td></tr>
    <tr><th><?= h(t('common.budget')) ?></th><td><?= $p['budget_cents'] !== null ? h(fmt_money((int) $spent)) . ' / ' . h(fmt_money((int) $p['budget_cents'])) : '—' ?></td></tr>
  </table>
  <?php if ($canEdit): ?>
  <details class="panel no-print"<?= form_failed('project') ? ' open' : '' ?>>
    <summary><?= h(t('projects.editProject')) ?></summary>
    <form method="post" action="<?= h(url('/projects/' . $p['id'])) ?>" class="form">
      <?= template('projects/form', ['project' => $p]) ?>
      <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
    </form>
  </details>
  <p class="no-print"><?= confirm_form("/projects/{$p['id']}/delete", t('projects.deleteProject'), t('projects.deleteWarning')) ?></p>
  <?php endif ?>
</aside>
</div>

<?php elseif ($tab === 'tasks'): ?>
  <details class="panel no-print"<?= form_failed('task') ? ' open' : '' ?>>
    <summary><?= h(t('tasks.newTask')) ?></summary>
    <form method="post" action="<?= h(url('/tasks')) ?>" class="form">
      <?= back_field() ?>
      <?= template('tasks/form', ['task' => ['project_id' => $p['id'], 'pole_id' => $p['pole_id']], 'form' => 'task']) ?>
      <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
    </form>
  </details>
  <?= $tasks ? template('tasks/table', ['tasks' => $tasks]) : empty_html(t('tasks.emptyText')) ?>

<?php elseif ($tab === 'updates'): ?>
  <?php if ($isTeam || $canEdit): ?>
  <details class="panel no-print" open>
    <summary><?= h(t('projects.postUpdate')) ?></summary>
    <form method="post" action="<?= h(url("/projects/{$p['id']}/updates")) ?>" class="form">
      <?= csrf_field('update') ?>
      <?= textarea_field('body', t('projects.updates'), old('body'), ['required' => true, 'placeholder' => t('projects.updatePlaceholder')]) ?>
      <?= select_field('health', t('projects.health'), enum_options('health', HEALTH), old('health', 'ON_TRACK')) ?>
      <?= input_field('progress', t('projects.manualProgress'), old('progress'), ['type' => 'number', 'attrs' => ['min' => 0, 'max' => 100]]) ?>
      <p class="row"><button class="primary"><?= h(t('projects.postUpdate')) ?></button></p>
    </form>
  </details>
  <?php endif ?>
  <?php if (!$updates): ?><?= empty_html(t('projects.noUpdates')) ?><?php endif ?>
  <?php foreach ($updates as $u): ?>
  <article class="box gap"><p class="meta"><?= h($u['author_name'] ?? '') ?> · <?= h(fmt_instant($u['created_at'])) ?> · <?= status_badge('health', $u['health']) ?><?= $u['progress'] !== null ? ' · ' . (int) $u['progress'] . '&nbsp;%' : '' ?></p>
    <div class="text"><?= text_html($u['body']) ?></div></article>
  <?php endforeach ?>

<?php elseif ($tab === 'team'): ?>
  <?php if (!$team): ?><?= empty_html(t('projects.noTeam')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($team as $m): ?>
    <li><?= person((int) $m['user_id'], $m['name']) ?><?= $m['role'] ? ' · ' . h($m['role']) : '' ?><?= (int) $m['user_id'] === (int) $p['lead_id'] ? ' ' . badge(t('common.lead'), 'info') : '' ?>
      <?php if ($canEdit): ?> · <?= confirm_form("/projects/{$p['id']}/team/{$m['user_id']}/remove", t('common.remove'), ' ') ?><?php endif ?></li>
    <?php endforeach ?>
  </ul>
  <?php if ($canEdit): ?>
  <form method="post" action="<?= h(url("/projects/{$p['id']}/team")) ?>" class="filters no-print">
    <?= csrf_field() ?>
    <?= select_field('user_id', t('projects.addToTeam'), array_diff_key(member_options(), array_flip(array_column($team, 'user_id'))), '', ['required' => true, 'empty' => t('common.selectPlaceholder')]) ?>
    <?= input_field('role', t('projects.teamRole'), '', ['placeholder' => t('projects.teamRolePlaceholder')]) ?>
    <button><?= h(t('common.add')) ?></button>
  </form>
  <?php endif ?>

<?php else: ?>
  <?php if ($p['budget_cents'] !== null): ?>
  <p><?= h(t('projects.budgetUsed')) ?><?= colon() ?><?= h(fmt_money($spent)) ?> / <?= h(fmt_money((int) $p['budget_cents'])) ?>
    <meter min="0" max="<?= max(1, (int) $p['budget_cents']) ?>" high="<?= (int) ($p['budget_cents'] * 0.9) ?>" value="<?= (int) $spent ?>"></meter> <?= pct($spent, (int) $p['budget_cents']) ?>&nbsp;%</p>
  <?php else: ?><?= empty_html(t('projects.noBudget')) ?><?php endif ?>
  <h2><?= h(t('projects.linkedTransactions')) ?></h2>
  <?php if (!$transactions): ?><?= empty_html(t('projects.noTransactions')) ?><?php else: ?>
  <div class="table"><table>
    <?php foreach ($transactions as $tx): ?>
    <tr><td><?= h(fmt_date($tx['date'])) ?></td><td><?= h($tx['label']) ?></td><td><?= h(enum_label('category', $tx['category'])) ?></td>
      <td class="num <?= $tx['type'] === 'EXPENSE' ? 'late' : '' ?>"><?= $tx['type'] === 'EXPENSE' ? '−' : '+' ?><?= h(fmt_money((int) $tx['amount_cents'])) ?></td></tr>
    <?php endforeach ?>
  </table></div>
  <?php endif ?>
<?php endif ?>
