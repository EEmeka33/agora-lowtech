<?= page_head(t('members.title'), t('members.subtitle'),
    (can('manageCodes', $user) ? '<a href="' . h(url('/members/codes')) . '">' . h(t('codes.invitePeople')) . '</a>' : '')
    . (can('exportMembers', $user) ? ' · <a href="' . h(url('/members.csv')) . '">' . h(t('members.export')) . ' (CSV)</a>' : '')) ?>

<?php if (can('manageMembers', $user)): ?>
<details class="panel no-print"<?= get_str('new') || form_failed('member') ? ' open' : '' ?>>
  <summary><?= h(t('members.addMember')) ?></summary>
  <p class="meta"><?= h(t('members.addHint')) ?></p>
  <form method="post" action="<?= h(url('/members')) ?>" class="form">
    <?= csrf_field('member') ?>
    <?= input_field('name', t('common.name'), old('name'), ['required' => true]) ?>
    <?= input_field('email', t('common.email'), old('email'), ['type' => 'email', 'required' => true]) ?>
    <?= input_field('phone', t('common.phone'), old('phone'), ['type' => 'tel']) ?>
    <?= select_field('role', t('members.role'), enum_options('role', ROLES), old('role', 'MEMBER')) ?>
    <?= select_field('board_position', t('members.boardPosition'), enum_options('boardPosition', BOARD_POSITIONS), old('board_position'), ['empty' => t('common.none')]) ?>
    <?= input_field('title', t('members.memberTitle'), old('title')) ?>
    <?= input_field('joined_at', t('members.joinedAt'), old('joined_at', today()), ['type' => 'date']) ?>
    <?php if (pole_options()): ?><?= checkboxes_field('poles', t('common.poles'), pole_options(), old('poles', [])) ?><?php endif ?>
    <div class="row"><?= checkbox_field('invite', t('members.createInvite'), true, t('members.createInviteHint')) ?></div>
    <p class="row"><button class="primary"><?= h(t('common.add')) ?></button></p>
  </form>
</details>
<?php endif ?>

<?= tabs_html([
    'ACTIVE' => [enum_label('memberStatus', 'ACTIVE'), url('/members')],
    'INACTIVE' => [enum_label('memberStatus', 'INACTIVE'), url('/members', ['status' => 'INACTIVE'])],
    'all' => [t('common.all'), url('/members', ['status' => 'all'])],
], $status) ?>
<?php if (!$members): ?><?= empty_html(t('members.empty')) ?><?php else: ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('common.name')) ?></th><th><?= h(t('members.role')) ?></th><th><?= h(t('common.poles')) ?></th><th><?= h(t('finance.dues')) ?> <?= $year ?></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): ?>
  <tr>
    <td><?= person((int) $m['user_id'], $m['name']) ?>
      <div class="meta"><?= h(implode(' · ', array_filter([$m['title'], enum_label('boardPosition', $m['board_position'])]))) ?></div>
      <?= !$m['activated'] ? badge(t('members.notActivated'), 'warn') : '' ?> <?= $m['status'] !== 'ACTIVE' ? badge(enum_label('memberStatus', $m['status'])) : '' ?></td>
    <td><?= h(enum_label('role', $m['role'])) ?></td>
    <td><?= h($m['poles'] ?? '') ?></td>
    <td><?= $m['paid'] ? badge(t('finance.paid'), 'ok') : badge(t('finance.unpaid'), 'warn') ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<p class="meta"><?= h(tn('common.countMembers', count($members))) ?></p>
<?php endif ?>
