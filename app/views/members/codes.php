<p class="meta"><a href="<?= h(url('/members')) ?>">← <?= h(t('members.title')) ?></a></p>
<?= page_head(t('codes.title'), t('codes.subtitle')) ?>
<details class="panel"<?= !$codes || form_failed('code') ? ' open' : '' ?>>
  <summary><?= h(t('codes.newCode')) ?></summary>
  <form method="post" action="<?= h(url('/members/codes')) ?>" class="form">
    <?= csrf_field('code') ?>
    <?= input_field('label', t('codes.label'), old('label'), ['placeholder' => t('codes.labelPlaceholder')]) ?>
    <?= input_field('max_uses', t('codes.maxUses'), old('max_uses', 10), ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 500]]) ?>
    <?= select_field('expires', t('codes.expires'), ['7' => t('codes.expiresIn', ['n' => 7]), '30' => t('codes.expiresIn', ['n' => 30]), '90' => t('codes.expiresIn', ['n' => 90]), 'never' => t('codes.never')], old('expires', '30')) ?>
    <?= select_field('role', t('members.role'), is_admin($user) ? enum_options('role', ROLES) : ['MEMBER' => enum_label('role', 'MEMBER')], old('role', 'MEMBER')) ?>
    <?= select_field('pole_id', t('codes.pole'), pole_options(), old('pole_id'), ['empty' => t('common.none')]) ?>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
  </form>
</details>
<?php if (!$codes): ?><?= empty_html(t('codes.empty')) ?><?php else: ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('codes.code')) ?></th><th><?= h(t('codes.usesLabel')) ?></th><th><?= h(t('common.status')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($codes as $c):
      $expired = $c['expires_at'] && $c['expires_at'] < now_utc();
      $usable = !$c['revoked_at'] && !$expired && $c['uses'] < $c['max_uses'];
      $link = absolute_url('/join?code=' . $c['code']); ?>
  <tr>
    <td><code><?= h($c['code']) ?></code><?= $c['label'] ? ' · ' . h($c['label']) : '' ?>
      <div class="meta"><?= h(enum_label('role', $c['role'])) ?><?= $c['pole_name'] ? ' · ' . h($c['pole_name']) : '' ?><?= $c['creator_name'] ? ' · ' . h($c['creator_name']) : '' ?></div>
      <?php if ($usable): ?><div class="meta mono"><?= h($link) ?></div><?php endif ?></td>
    <td><?= h(tn('codes.uses', (int) $c['uses'], ['u' => $c['uses'], 'm' => $c['max_uses']])) ?></td>
    <td><?= $c['revoked_at'] ? badge(t('codes.revoked'), 'bad') : ($expired ? badge(t('codes.expired')) : ($usable ? badge(t('codes.active'), 'ok') : badge(t('codes.full'))))?>
      <?= $c['expires_at'] && !$expired && !$c['revoked_at'] ? '<div class="meta">' . h(t('codes.expiresOn', ['d' => fmt_instant($c['expires_at'])])) . '</div>' : '' ?></td>
    <td><?= $usable ? confirm_form("/members/codes/{$c['id']}/revoke", t('codes.revoke'), t('codes.revokeWarning')) : '' ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
<p class="meta"><?= h(t('codes.howTo')) ?></p>
