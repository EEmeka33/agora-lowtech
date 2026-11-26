<?php $m = $member; $id = (int) $m['user_id']; ?>
<p class="meta"><a href="<?= h(url('/members')) ?>">← <?= h(t('members.title')) ?></a></p>
<?= page_head($m['name'], implode(' · ', array_filter([$m['title'], enum_label('boardPosition', $m['board_position']), enum_label('role', $m['role'])])),
    ($m['status'] !== 'ACTIVE' ? badge(enum_label('memberStatus', $m['status'])) : '') . (!$m['activated'] ? badge(t('members.notActivated'), 'warn') : '')) ?>
<div class="cols">
<div>
  <table>
    <tr><th><?= h(t('common.email')) ?></th><td><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></td></tr>
    <?php if ($m['phone']): ?><tr><th><?= h(t('common.phone')) ?></th><td><a href="tel:<?= h($m['phone']) ?>"><?= h($m['phone']) ?></a></td></tr><?php endif ?>
    <?php if ($m['skills']): ?><tr><th><?= h(t('members.skills')) ?></th><td><?= h($m['skills']) ?></td></tr><?php endif ?>
    <tr><th><?= h(t('members.joinedAt')) ?></th><td><?= h(fmt_date($m['joined_at'])) ?></td></tr>
    <tr><th><?= h(t('common.poles')) ?></th><td><?= $poles ? implode(', ', array_map(fn ($p) => '<a href="' . h(url('/poles/' . $p['id'])) . '">' . h($p['name']) . '</a>' . ($p['role'] === 'LEAD' ? ' ★' : ''), $poles)) : '—' ?></td></tr>
    <tr><th><?= h(t('volunteering.title')) ?></th><td><?= h(t('volunteering.hours', ['h' => minutes_to_hours($minutes)])) ?> (<?= date('Y') ?>)</td></tr>
    <tr><th><?= h(t('finance.dues')) ?></th><td><?= $dues ? implode(', ', array_map(fn ($d) => (int) $d['year'] . ' (' . h(fmt_money((int) $d['amount_cents'])) . ')', $dues)) : '—' ?></td></tr>
    <?php if ($m['last_login_at']): ?><tr><th><?= h(t('members.lastSeen')) ?></th><td><?= h(fmt_relative($m['last_login_at'])) ?></td></tr><?php endif ?>
  </table>
  <?php if ($m['bio']): ?><div class="text"><?= text_html($m['bio']) ?></div><?php endif ?>

  <?php if ($projects): ?><h2><?= h(t('members.leads')) ?></h2>
  <ul><?php foreach ($projects as $p): ?><li><a href="<?= h(url('/projects/' . $p['id'])) ?>"><?= h($p['title']) ?></a></li><?php endforeach ?></ul><?php endif ?>
  <h2><?= h(t('members.openTasks')) ?></h2>
  <?= $tasks ? template('tasks/table', ['tasks' => $tasks, 'showAssignee' => false]) : empty_html(t('dashboard.myTasksEmpty')) ?>
</div>
<aside class="no-print">
  <?php if ($canEdit): ?>
  <details class="panel edit"<?= form_errors() ? ' open' : '' ?>><summary><?= h(t('members.editMember')) ?></summary>
    <form method="post" action="<?= h(url('/members/' . $id)) ?>" class="form"><?= csrf_field() ?>
      <?php if ($canEditAccount): ?>
      <?= input_field('name', t('common.name'), old('name', $m['name']), ['required' => true]) ?>
      <?= input_field('email', t('common.email'), old('email', $m['email']), ['type' => 'email', 'required' => true]) ?>
      <?= input_field('phone', t('common.phone'), old('phone', $m['phone']), ['type' => 'tel']) ?>
      <?= input_field('skills', t('members.skills'), old('skills', $m['skills'])) ?>
      <?= textarea_field('bio', t('members.bio'), old('bio', $m['bio']), ['rows' => 3]) ?>
      <?php endif ?>
      <?= input_field('title', t('members.memberTitle'), old('title', $m['title'])) ?>
      <?php if (is_admin($user)): ?>
      <?= select_field('role', t('members.role'), enum_options('role', ROLES), old('role', $m['role'])) ?>
      <?= select_field('board_position', t('members.boardPosition'), enum_options('boardPosition', BOARD_POSITIONS), old('board_position', $m['board_position']), ['empty' => t('common.none')]) ?>
      <?= input_field('joined_at', t('members.joinedAt'), old('joined_at', $m['joined_at']), ['type' => 'date']) ?>
      <?php endif ?>
      <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p>
    </form>
  </details>
  <?php endif ?>
  <?php if (can('manageMembers', $user) && $id !== $user['id']): ?>
  <h2><?= h(t('members.adminActions')) ?></h2>
  <p><?= button_form("/members/$id/link", $m['activated'] ? t('members.resetLink') : t('members.inviteLink'), [], 'link') ?><br>
    <small><?= h($m['activated'] ? t('members.resetLinkHint') : t('members.inviteLinkHint')) ?></small></p>
  <p><?= button_form("/members/$id/status", $m['status'] === 'ACTIVE' ? t('members.deactivate') : t('members.reactivate'), ['status' => $m['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'], 'link') ?></p>
  <p><?= confirm_form("/members/$id/delete", t('members.remove'), t('members.removeWarning')) ?></p>
  <?php endif ?>
</aside>
</div>
