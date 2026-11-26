<?= page_head(t('requests.title'), t('requests.subtitle')) ?>
<details class="panel" id="new"<?= get_str('new') || get_str('pole') || form_failed('request') ? ' open' : '' ?>>
  <summary><?= h(t('requests.newRequest')) ?></summary>
  <?= template('requests/form') ?>
</details>

<?= tabs_html([
    'mine' => [t('requests.tabs.mine') . ($openForMe ? " ($openForMe)" : ''), url('/requests', ['tab' => 'mine'])],
    'asked' => [t('requests.tabs.asked'), url('/requests', ['tab' => 'asked'])],
    'all' => [t('requests.tabs.all'), url('/requests', ['tab' => 'all'])],
], $tab) ?>
<form class="filters auto no-print" action="<?= h(url("/requests")) ?>">
  <input type="hidden" name="tab" value="<?= h($tab) ?>">
  <?= select_field('status', t('common.status'), ['active' => t('requests.active')] + enum_options('requestStatus', REQUEST_STATUSES) + ['any' => t('common.all')], $status) ?>
  <button><?= h(t('common.filter')) ?></button>
</form>

<?php if (!$requests): ?>
  <?= empty_html(t('requests.empty')) ?>
<?php else: ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('requests.request')) ?></th><th><?= h(t('requests.toPole')) ?></th><th><?= h(t('common.status')) ?></th><th><?= h(t('common.dueDate')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($requests as $r): ?>
  <tr>
    <td><a href="<?= h(url('/requests/' . $r['id'])) ?>"><?= h($r['title']) ?></a> <?= in_array($r['priority'], ['HIGH', 'URGENT'], true) ? status_badge('priority', $r['priority']) : '' ?>
      <div class="meta"><?= h(t('requests.askedBy', ['name' => $r['requester_name'] ?? '—', 'when' => fmt_relative($r['created_at'])])) ?><?= $r['from_pole_name'] ? ' · ' . h(t('requests.from', ['pole' => $r['from_pole_name']])) : '' ?></div></td>
    <td><a href="<?= h(url('/poles/' . $r['pole_id'])) ?>"><?= h($r['pole_name']) ?></a></td>
    <td><?= status_badge('requestStatus', $r['status']) ?><?= $r['taken_by_name'] ? '<div class="meta">' . h($r['taken_by_name']) . '</div>' : '' ?></td>
    <td><?= due_html($r['due_date'], in_array($r['status'], ['DONE', 'CANCELLED'], true)) ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
