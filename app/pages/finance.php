<?php
/*
 * Finance: income and expenses, monthly chart drawn with text, budgets of
 * projects, membership fees, CSV export.
 */
require_once APP . '/pages/volunteering.php';

/** The financial year being viewed: [year, first day, first day of the next year, label]. */
function fiscal_year(): array
{
    $start = (int) (current_association()['fiscal_year_start_month'] ?? 1);
    $year = (int) get_str('year');
    if ($year < 2000 || $year > 2100) {
        $year = (int) date('Y') - ((int) date('n') < $start ? 1 : 0);
    }
    $from = sprintf('%04d-%02d-01', $year, $start);
    $to = sprintf('%04d-%02d-01', $year + 1, $start);
    return [$year, $from, $to, $start === 1 ? (string) $year : $year . '–' . ($year + 1)];
}

function finance_show(): void
{
    $user = require_member(); // everyone sees the accounts (transparency); managers change them
    [$year, $from, $to, $label] = fiscal_year();
    $a = aid();
    $transactions = rows('SELECT tx.*, p.title AS project_title, po.name AS pole_name FROM transactions tx
                          LEFT JOIN projects p ON p.id = tx.project_id LEFT JOIN poles po ON po.id = tx.pole_id
                          WHERE tx.association_id = ? AND tx.date >= ? AND tx.date < ? ORDER BY tx.date DESC, tx.id DESC', [$a, $from, $to]);
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $months[date('Y-m', strtotime("$from +$i months"))] = ['INCOME' => 0, 'EXPENSE' => 0];
    }
    $categories = ['INCOME' => [], 'EXPENSE' => []];
    $totals = ['INCOME' => 0, 'EXPENSE' => 0];
    foreach ($transactions as $tx) {
        $months[substr($tx['date'], 0, 7)][$tx['type']] += (int) $tx['amount_cents'];
        $categories[$tx['type']][$tx['category']] = ($categories[$tx['type']][$tx['category']] ?? 0) + (int) $tx['amount_cents'];
        $totals[$tx['type']] += (int) $tx['amount_cents'];
    }
    arsort($categories['INCOME']);
    arsort($categories['EXPENSE']);
    $budgets = rows("SELECT p.id, p.title, p.budget_cents,
                       (SELECT COALESCE(SUM(amount_cents), 0) FROM transactions t WHERE t.project_id = p.id AND t.type = 'EXPENSE') AS spent
                     FROM projects p WHERE p.association_id = ? AND p.budget_cents IS NOT NULL AND p.status NOT IN ('CANCELLED') ORDER BY p.title", [$a]);
    $duesYear = $year;
    $members = rows("SELECT u.id, u.name, d.id AS dues_id, d.amount_cents, d.paid_at, d.method FROM members m
                     JOIN users u ON u.id = m.user_id
                     LEFT JOIN dues d ON d.user_id = u.id AND d.association_id = m.association_id AND d.year = ?
                     WHERE m.association_id = ? AND m.status = 'ACTIVE' ORDER BY d.id IS NOT NULL, u.name", [$duesYear, $a]);
    render('finance/show', compact('user', 'year', 'label', 'transactions', 'months', 'categories', 'totals', 'budgets', 'members', 'duesYear'), t('finance.title'));
}

/** Fields of a transaction form. */
function transaction_fields(array $tx = []): string
{
    $v = fn ($k, $d = '') => old($k, $tx[$k] ?? $d);
    $categories = [];
    foreach (transaction_categories() as $c) {
        $categories[$c] = enum_label('category', $c);
    }
    return select_field('type', t('common.type'), enum_options('transactionType', TRANSACTION_TYPES), $v('type', 'EXPENSE'))
        . input_field('label', t('finance.label'), $v('label'), ['required' => true, 'placeholder' => t('finance.labelPlaceholder')])
        . input_field('amount', t('common.amount'), old('amount', money_input(isset($tx['amount_cents']) ? (int) $tx['amount_cents'] : null)), ['required' => true, 'attrs' => ['inputmode' => 'decimal'], 'placeholder' => '12,50'])
        . input_field('date', t('common.date'), $v('date', today()), ['type' => 'date', 'required' => true])
        . select_field('category', t('common.category'), $categories, $v('category', 'OTHER'))
        . select_field('payment_method', t('finance.paymentMethod'), enum_options('paymentMethod', PAYMENT_METHODS), $v('payment_method'), ['empty' => '—'])
        . input_field('reference', t('finance.reference'), $v('reference'), ['placeholder' => t('finance.referencePlaceholder')])
        . select_field('project_id', t('common.project'), project_options(true), $v('project_id'), ['empty' => t('common.none')])
        . select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id'), ['empty' => t('common.none')])
        . textarea_field('notes', t('common.notes'), $v('notes'), ['rows' => 2]);
}

function finance_edit(int $id): void
{
    ensure(can('manageFinance', require_member()));
    $tx = find_or_404('transactions', $id);
    render('edit', ['heading' => t('finance.editTransaction'), 'back' => safe_local_path($_POST['_back'] ?? get_str('back'), '/finance'),
        'action' => "/finance/$id", 'fields' => transaction_fields($tx), 'delete' => "/finance/$id/delete"], $tx['label']);
}

function read_transaction(): array
{
    return [
        'label' => post_str('label'),
        'type' => post_enum('type', TRANSACTION_TYPES, 'EXPENSE'),
        'amount_cents' => post_money('amount'),
        'category' => post_enum('category', transaction_categories(), 'OTHER'),
        'date' => post_date('date'),
        'payment_method' => post_enum('payment_method', PAYMENT_METHODS, null),
        'reference' => post_opt('reference'),
        'notes' => post_opt('notes'),
        'project_id' => post_id('project_id'),
        'pole_id' => post_id('pole_id'),
    ];
}

function transaction_errors(array $data): array
{
    $errors = [];
    if ($data['label'] === '') {
        $errors['label'] = t('validation.required');
    }
    if ($data['amount_cents'] === null || $data['amount_cents'] <= 0) {
        $errors['amount'] = t('validation.invalidAmount');
    }
    if (!$data['date']) {
        $errors['date'] = t('validation.invalidDate');
    }
    return $errors;
}

function finance_create(): void
{
    $user = require_member();
    ensure(can('manageFinance', $user));
    $data = read_transaction();
    if ($errors = transaction_errors($data)) {
        fail_form($errors, 'finance_show');
    }
    assert_refs(['pole_id' => $data['pole_id'], 'project_id' => $data['project_id']]);
    $id = insert('transactions', $data + ['association_id' => aid(), 'recorded_by_id' => $user['id'], 'created_at' => now_utc(), 'updated_at' => now_utc()]);
    log_activity('created', 'transaction', $id, $data['label']);
    back('/finance', t('common.created'));
}

function finance_update(int $id): void
{
    ensure(can('manageFinance', require_member()));
    find_or_404('transactions', $id);
    $data = read_transaction();
    if ($errors = transaction_errors($data)) {
        fail_form($errors, fn () => finance_edit($id));
    }
    assert_refs(['pole_id' => $data['pole_id'], 'project_id' => $data['project_id']]);
    update('transactions', $data + ['updated_at' => now_utc()], 'id = ?', [$id]);
    log_activity('updated', 'transaction', $id, $data['label']);
    back('/finance', t('common.saved'));
}

function finance_delete(int $id): void
{
    ensure(can('manageFinance', require_member()));
    $tx = find_or_404('transactions', $id);
    q('DELETE FROM transactions WHERE id = ?', [$id]);
    log_activity('deleted', 'transaction', null, $tx['label']);
    back('/finance', t('common.deleted'));
}

/** Records a member's yearly fee and the matching income. */
function finance_dues(): void
{
    $user = require_member();
    ensure(can('manageFinance', $user));
    $userId = post_id('user_id');
    $year = post_int('year', (int) date('Y'));
    $amount = post_money('amount');
    $paidAt = post_date('paid_at') ?? today();
    $method = post_enum('method', PAYMENT_METHODS, null);
    $errors = [];
    if (!$userId) {
        $errors['user_id'] = t('validation.required');
    }
    if ($amount === null || $amount < 0) {
        $errors['amount'] = t('validation.invalidAmount');
    }
    if ($errors) {
        fail_form($errors, 'finance_show');
    }
    assert_refs(['user_ids' => [$userId]]);
    if (val('SELECT 1 FROM dues WHERE association_id = ? AND user_id = ? AND year = ?', [aid(), $userId, $year])) {
        fail_form(['user_id' => t('finance.alreadyPaid')], 'finance_show');
    }
    $name = (string) val('SELECT name FROM users WHERE id = ?', [$userId]);
    transaction(function () use ($user, $userId, $year, $amount, $paidAt, $method, $name) {
        $txId = $amount ? insert('transactions', [
            'association_id' => aid(),
            'label' => enum_label('category', 'MEMBERSHIP') . " $year — $name",
            'type' => 'INCOME',
            'category' => 'MEMBERSHIP',
            'amount_cents' => $amount,
            'date' => $paidAt,
            'payment_method' => $method,
            'recorded_by_id' => $user['id'],
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ]) : null;
        insert('dues', ['association_id' => aid(), 'user_id' => $userId, 'year' => $year, 'amount_cents' => $amount,
            'paid_at' => $paidAt, 'method' => $method, 'transaction_id' => $txId, 'created_at' => now_utc()]);
    });
    log_activity('paid', 'membership', $userId, $name);
    back('/finance#dues', t('common.saved'));
}

function finance_undues(int $id): void
{
    ensure(can('manageFinance', require_member()));
    $dues = find_or_404('dues', $id);
    q('DELETE FROM dues WHERE id = ?', [$id]);
    if ($dues['transaction_id']) {
        q('DELETE FROM transactions WHERE id = ? AND association_id = ?', [$dues['transaction_id'], aid()]);
    }
    back('/finance#dues', t('common.deleted'));
}

function finance_csv(): void
{
    ensure(can('manageFinance', require_member()));
    [$year, $from, $to] = fiscal_year();
    $lines = [[t('common.date'), t('finance.label'), t('common.type'), t('common.category'), t('common.amount'), t('finance.paymentMethod'), t('finance.reference'), t('common.project'), t('common.pole'), t('common.notes')]];
    foreach (rows('SELECT tx.*, p.title AS project, po.name AS pole FROM transactions tx LEFT JOIN projects p ON p.id = tx.project_id LEFT JOIN poles po ON po.id = tx.pole_id
                   WHERE tx.association_id = ? AND tx.date >= ? AND tx.date < ? ORDER BY tx.date', [aid(), $from, $to]) as $tx) {
        $amount = number_format(($tx['type'] === 'EXPENSE' ? -1 : 1) * $tx['amount_cents'] / 100, 2, '.', '');
        $lines[] = [$tx['date'], $tx['label'], enum_label('transactionType', $tx['type']), enum_label('category', $tx['category']), $amount,
            enum_label('paymentMethod', $tx['payment_method']), $tx['reference'], $tx['project'], $tx['pole'], $tx['notes']];
    }
    send_download(csv($lines), "transactions-$year.csv", 'text/csv; charset=utf-8');
}
