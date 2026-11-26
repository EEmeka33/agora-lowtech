<?php $inPole = in_array($user['id'], array_map('intval', array_column($members, 'user_id')), true); ?>
<p class="meta"><a href="<?= h(url('/poles')) ?>">← <?= h(t('poles.title')) ?></a></p>
<?= page_head($pole['name'], (string) $pole['mission'], '<a href="' . h(url('/requests', ['pole' => $pole['id']])) . '#new">' . h(t('requests.askTitle', ['pole' => $pole['name']])) . '</a>') ?>
<?php if ($pole['description']): ?><div class="text"><?= text_html($pole['description']) ?></div><?php endif ?>

<div class="cols">
<div>
  <h2><?= h(t('requests.title')) ?> <small>(<?= count($requests) ?>)</small></h2>
  <?php if (!$requests): ?><?= empty_html(t('requests.empty')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($requests as $r): ?>
    <li><a href="<?= h(url('/requests/' . $r['id'])) ?>"><?= h($r['title']) ?></a> <?= status_badge('requestStatus', $r['status']) ?>
      <?= $r['status'] === 'OPEN' && ($inPole || is_manager($user)) ? button_form("/requests/{$r['id']}/take", t('requests.take'), [], 'link') : '' ?></li>
    <?php endforeach ?>
  </ul>

  <h2><?= h(t('poles.activeProjects')) ?></h2>
  <?php if (!$projects): ?><?= empty_html(t('dashboard.projectsEmpty')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($projects as $p): ?>
    <li><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h($p['title']) ?></a> · <?= progress_html($p['progress']) ?> <?= status_badge('health', $p['health']) ?></li>
    <?php endforeach ?>
  </ul>

  <h2><?= h(t('poles.openTasks')) ?></h2>
  <?= $tasks ? template('tasks/table', ['tasks' => $tasks]) : empty_html(t('tasks.emptyText')) ?>
  <p><a href="<?= h(url('/tasks', ['pole' => $pole['id']])) ?>"><?= h(t('common.viewAll')) ?></a></p>

  <?php if ($duties): ?>
  <h2><?= h(t('structure.responsibilities')) ?></h2>
  <ul class="plain">
    <?php foreach ($duties as $d): ?><li><?= h($d['title']) ?> · <span class="meta"><?= h(enum_label('frequency', $d['frequency'])) ?> · <?= h($d['owner_name'] ?? t('common.unassigned')) ?></span></li><?php endforeach ?>
  </ul>
  <?php endif ?>

  <?php if ($events): ?>
  <h2><?= h(t('poles.upcomingEvents')) ?></h2>
  <ul class="plain">
    <?php foreach ($events as $e): ?><li><a href="<?= h(url('/events/' . $e['id'])) ?>"><?= h($e['title']) ?></a> · <span class="meta"><?= h(fmt_date($e['start_at'], 'datetime')) ?></span></li><?php endforeach ?>
  </ul>
  <?php endif ?>
</div>

<aside>
  <h2><?= h(t('poles.team')) ?> <small>(<?= count($members) ?>)</small></h2>
  <?php if (!$members): ?><?= empty_html(t('poles.noMembers')) ?><?php endif ?>
  <ul class="plain">
    <?php foreach ($members as $m): ?>
    <li><?= person((int) $m['user_id'], $m['name']) ?> <?= $m['role'] === 'LEAD' ? badge(enum_label('poleRole', 'LEAD'), 'info') : '' ?>
      <?= $m['title'] ? '<div class="meta">' . h($m['title']) . '</div>' : '' ?>
      <?php if ($canEdit): ?><div class="no-print">
        <?= button_form("/poles/{$pole['id']}/members/{$m['user_id']}/role", $m['role'] === 'LEAD' ? t('poles.makeMember') : t('poles.makeLead'), ['role' => $m['role'] === 'LEAD' ? 'MEMBER' : 'LEAD'], 'link') ?>
        · <?= confirm_form("/poles/{$pole['id']}/members/{$m['user_id']}/remove", t('common.remove'), ' ') ?></div><?php endif ?></li>
    <?php endforeach ?>
  </ul>
  <?php if ($canEdit):
      $others = array_diff_key(member_options(), array_flip(array_column($members, 'user_id'))); ?>
    <?php if ($others): ?>
    <form method="post" action="<?= h(url("/poles/{$pole['id']}/members")) ?>" class="form no-print">
      <?= csrf_field() ?>
      <?= select_field('user_id', t('poles.addMember'), $others, '', ['required' => true, 'empty' => t('common.selectPlaceholder')]) ?>
      <?= select_field('role', t('common.role'), enum_options('poleRole', POLE_ROLES), 'MEMBER') ?>
      <p class="row"><button><?= h(t('common.add')) ?></button></p>
    </form>
    <?php else: ?><p class="meta"><?= h(t('poles.allMembersAdded')) ?></p><?php endif ?>
    <details class="panel edit no-print"<?= form_failed('pole') ? ' open' : '' ?>>
      <summary><?= h(t('poles.editPole')) ?></summary>
      <form method="post" action="<?= h(url('/poles/' . $pole['id'])) ?>" class="form">
        <?= csrf_field('pole') ?>
        <?= input_field('name', t('common.name'), old('name', $pole['name']), ['required' => true, 'class' => 'wide']) ?>
        <?= textarea_field('mission', t('poles.mission'), old('mission', $pole['mission']), ['rows' => 2]) ?>
        <?= textarea_field('description', t('common.description'), old('description', $pole['description']), ['rows' => 3]) ?>
        <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
      </form>
    </details>
  <?php endif ?>
  <?php if (can('managePoles', $user)): ?><p class="no-print"><?= confirm_form("/poles/{$pole['id']}/delete", t('poles.deletePole'), t('poles.deleteWarning')) ?></p><?php endif ?>
</aside>
</div>
