<?php
$assoc = current_association();
$maxMonth = max(1, ...array_values(array_map(fn ($m) => max($m['INCOME'], $m['EXPENSE']), $months)));
$shortMonths = explode(',', t('date.monthsShort'));
$unpaid = array_filter($members, fn ($m) => !$m['dues_id']);
$canManage = can('manageFinance', $user);
?>
<?= page_head(t('finance.title'), t('finance.subtitle'),
    '<a href="' . h(url('/finance', ['year' => $year - 1])) . '">←</a> <strong>' . h($label) . '</strong> <a href="' . h(url('/finance', ['year' => $year + 1])) . '">→</a>') ?>

<div class="grid stats">
  <div class="box"><span class="stat"><?= h(fmt_money($totals['INCOME'])) ?></span><?= h(t('finance.income')) ?></div>
  <div class="box"><span class="stat"><?= h(fmt_money($totals['EXPENSE'])) ?></span><?= h(t('finance.expenses')) ?></div>
  <div class="box"><span class="stat <?= $totals['INCOME'] < $totals['EXPENSE'] ? 'late' : '' ?>"><?= h(fmt_money($totals['INCOME'] - $totals['EXPENSE'])) ?></span><?= h(t('finance.yearBalance', ['y' => $label])) ?></div>
</div>

<?php if ($canManage): ?>
<details class="panel no-print" id="new"<?= get_str('new') || form_failed('tx') ? ' open' : '' ?>>
  <summary><?= h(t('finance.newTransaction')) ?></summary>
  <form method="post" action="<?= h(url('/finance')) ?>" class="form"><?= csrf_field('tx') ?><?= transaction_fields() ?>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p></form>
</details>
<?php endif ?>

<h2><?= h(t('finance.transactions')) ?> <small>(<?= count($transactions) ?>)</small></h2>
<?php if (!$transactions): ?><?= empty_html(t('finance.noTransactions')) ?><?php else: ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('common.date')) ?></th><th><?= h(t('finance.label')) ?></th><th><?= h(t('common.category')) ?></th><th class="num"><?= h(t('common.amount')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($transactions as $tx): ?>
  <tr>
    <td><?= h(fmt_date($tx['date'])) ?></td>
    <td><?= h($tx['label']) ?><div class="meta"><?= h(implode(' · ', array_filter([enum_label('paymentMethod', $tx['payment_method']), $tx['reference'], $tx['project_title'], $tx['pole_name']]))) ?></div>
      <?php if ($canManage): ?> <a class="no-print" href="<?= h(url('/finance/' . $tx['id'], ['back' => '/finance?year=' . $year])) ?>"><?= h(t('common.edit')) ?></a><?php endif ?></td>
    <td><?= h(enum_label('category', $tx['category'])) ?></td>
    <td class="num <?= $tx['type'] === 'EXPENSE' ? 'late' : '' ?>"><?= $tx['type'] === 'EXPENSE' ? '−' : '+' ?><?= h(fmt_money((int) $tx['amount_cents'])) ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
<?php if ($canManage): ?><p class="no-print"><a href="<?= h(url('/finance.csv', ['year' => $year])) ?>"><?= h(t('finance.export')) ?> (CSV)</a></p><?php endif ?>


<h2 id="dues"><?= h(t('finance.dues')) ?> <?= (int) $duesYear ?></h2>
<p class="muted"><?= h($assoc['membership_fee_cents'] ? t('finance.duesInfo', ['fee' => fmt_money((int) $assoc['membership_fee_cents'])]) : t('finance.duesNoFee')) ?>
  · <?= h(tn('finance.paidCount', count($members) - count($unpaid), ['paid' => count($members) - count($unpaid), 'total' => count($members)])) ?></p>
<?php if ($unpaid && $canManage): ?>
<details class="panel no-print"<?= form_failed('dues') ? ' open' : '' ?>>
  <summary><?= h(t('finance.recordDues')) ?></summary>
  <form method="post" action="<?= h(url('/dues')) ?>" class="form">
    <?= csrf_field('dues') ?><?= back_field() ?>
    <input type="hidden" name="year" value="<?= (int) $duesYear ?>">
    <?= select_field('user_id', t('common.member'), array_column($unpaid, 'name', 'id'), old('user_id'), ['required' => true, 'empty' => t('common.selectPlaceholder')]) ?>
    <?= input_field('amount', t('common.amount'), old('amount', money_input($assoc['membership_fee_cents'] !== null ? (int) $assoc['membership_fee_cents'] : null)), ['required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?>
    <?= input_field('paid_at', t('finance.paidAt'), old('paid_at', today()), ['type' => 'date']) ?>
    <?= select_field('method', t('finance.paymentMethod'), enum_options('paymentMethod', PAYMENT_METHODS), old('method'), ['empty' => '—']) ?>
    <p class="row"><button class="primary"><?= h(t('common.save')) ?></button></p>
  </form>
</details>
<?php endif ?>
<div class="table"><table>
  <?php foreach ($members as $m): ?>
  <tr><td><?= person((int) $m['id'], $m['name']) ?></td>
    <td><?= $m['dues_id'] ? badge(t('finance.paid'), 'ok') . ' ' . h(fmt_money((int) $m['amount_cents'])) . ' · ' . h(fmt_date($m['paid_at'])) : badge(t('finance.unpaid'), 'warn') ?></td>
    <td class="no-print"><?= $m['dues_id'] && $canManage ? confirm_form("/dues/{$m['dues_id']}/delete", t('common.delete'), t('finance.deleteDuesWarning')) : '' ?></td></tr>
  <?php endforeach ?>
</table></div>


<h2><?= h(t('finance.monthly')) ?></h2>
<div class="table"><table class="bars">
  <thead><tr><th><?= h(t('common.month')) ?></th><th class="num"><?= h(t('finance.income')) ?></th><th class="num"><?= h(t('finance.expenses')) ?></th><th></th></tr></thead>
  <?php foreach ($months as $m => $v): if ($m > date('Y-m') && !$v['INCOME'] && !$v['EXPENSE']) continue; ?>
  <tr><td><?= h($shortMonths[(int) substr($m, 5) - 1] . ' ' . substr($m, 0, 4)) ?></td>
    <td class="num"><?= $v['INCOME'] ? h(fmt_money($v['INCOME'])) : '' ?></td><td class="num"><?= $v['EXPENSE'] ? h(fmt_money($v['EXPENSE'])) : '' ?></td>
    <td><?= $v['INCOME'] ? bar($v['INCOME'], $maxMonth) : '' ?><?= $v['INCOME'] && $v['EXPENSE'] ? '<br>' : '' ?><?= $v['EXPENSE'] ? bar($v['EXPENSE'], $maxMonth, 'out') : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<p class="meta"><span class="legend"></span> <?= h(t('finance.income')) ?> · <span class="legend out"></span> <?= h(t('finance.expenses')) ?></p>

<div class="grid">
  <?php foreach (['EXPENSE' => 'finance.byCategory', 'INCOME' => 'finance.incomeByCategory'] as $type => $key): $max = $categories[$type] ? max($categories[$type]) : 0; ?>
  <section>
    <h2><?= h(t($key)) ?></h2>
    <?php if (!$categories[$type]): ?><?= empty_html(t('finance.noTransactions')) ?><?php endif ?>
    <table class="bars">
      <?php foreach ($categories[$type] as $c => $cents): ?>
      <tr><td><?= h(enum_label('category', $c)) ?></td><td class="num"><?= h(fmt_money($cents)) ?></td><td><?= bar($cents, $max, $type === 'EXPENSE' ? 'out' : '') ?></td></tr>
      <?php endforeach ?>
    </table>
  </section>
  <?php endforeach ?>
</div>

<?php if ($budgets): ?>
<h2><?= h(t('finance.projectBudgets')) ?></h2>
<div class="table"><table>
  <?php foreach ($budgets as $b): ?>
  <tr><td><a href="<?= h(url('/projects/' . $b['id'], ['tab' => 'budget'])) ?>"><?= h($b['title']) ?></a></td>
    <td class="num"><?= h(fmt_money((int) $b['spent'])) ?> / <?= h(fmt_money((int) $b['budget_cents'])) ?></td>
    <td><meter min="0" max="<?= max(1, (int) $b['budget_cents']) ?>" high="<?= (int) ($b['budget_cents'] * 0.9) ?>" value="<?= (int) $b['spent'] ?>"></meter> <?= pct((int) $b['spent'], (int) $b['budget_cents']) ?>&nbsp;%</td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
