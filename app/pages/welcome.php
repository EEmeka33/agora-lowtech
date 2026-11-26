<?php
/*
 * Create an association, join one with a code, or switch between one's associations.
 */
require_once APP . '/pages/auth.php';

const SUGGESTED_POLES = ['workshops', 'repair', 'communication', 'events', 'partnerships', 'treasury'];

function welcome_show(): void
{
    $account = require_account();
    render('welcome/show', ['code' => normalize_join_code(get_str('code')), 'codeInfo' => null],
        t('welcome.title', ['name' => first_name($account['name'])]), 'auth');
}

/** Creates a new association; the person becomes its administrator. */
function welcome_create(): void
{
    $account = require_account();
    $again = fn () => welcome_show();
    if (!new_associations_allowed() && (int) val('SELECT COUNT(*) FROM associations') > 0) {
        fail_form(['name' => t('welcome.creationDisabled')], $again);
    }
    if (!rate_limit("create-asso:{$account['id']}", 5, 3600)) {
        fail_form(['name' => t('account.tooMany')], $again);
    }
    $name = post_str('name');
    if ($name === '') {
        fail_form(['name' => t('validation.required')], $again);
    }
    $locale = post_enum('locale', LOCALES, locale());
    $poles = array_values(array_intersect(SUGGESTED_POLES, post_all('poles')));
    $id = transaction(function () use ($account, $name, $locale, $poles) {
        $now = now_utc();
        $id = insert('associations', [
            'name' => $name,
            'short_name' => post_opt('short_name'),
            'purpose' => post_opt('purpose'),
            'legal_form' => post_str('legal_form') ?: 'Loi 1901',
            'city' => post_opt('city'),
            'country' => post_opt('country'),
            'currency' => post_enum('currency', CURRENCIES, 'EUR'),
            'locale' => $locale,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        insert('members', [
            'association_id' => $id,
            'user_id' => $account['id'],
            'role' => 'ADMIN',
            'board_position' => post_enum('board_position', BOARD_POSITIONS, null),
            'joined_at' => today(),
            'created_at' => $now,
        ]);
        if (post_bool('checklist')) {
            add_foundation_template($id, $locale);
        }
        foreach ($poles as $i => $key) {
            $poleId = insert('poles', ['association_id' => $id, 'name' => t("setup.suggestedPoles.$key", [], $locale),
                'position' => $i, 'created_at' => $now, 'updated_at' => $now]);
            insert('pole_members', ['pole_id' => $poleId, 'user_id' => $account['id'], 'role' => 'LEAD', 'joined_at' => $now]);
        }
        log_activity('created', 'workspace', null, $name, $account['id'], $id);
        return $id;
    });
    switch_association($id);
    redirect(post_bool('checklist') ? '/foundation' : '/', t('common.created'));
}

/** Adds the recommended founding steps (after the existing ones). */
function add_foundation_template(int $associationId, string $locale): void
{
    $offset = (int) val('SELECT COUNT(*) FROM foundation_steps WHERE association_id = ?', [$associationId]);
    foreach (require APP . '/foundation-template.php' as $i => $step) {
        [$title, $description] = $step[$locale] ?? $step['en'];
        insert('foundation_steps', [
            'association_id' => $associationId,
            'title' => $title,
            'description' => $description,
            'category' => $step['category'],
            'resource_url' => $step['resource_url'] ?? null,
            'position' => $offset + $i,
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ]);
    }
}

function welcome_join(): void
{
    $account = require_account();
    $again = fn () => welcome_show();
    if (!rate_limit("join:{$account['id']}", 10, 600) || !rate_limit('join-ip:' . client_ip(), 30, 600)) {
        fail_form(['code' => t('account.tooMany')], $again);
    }
    $code = normalize_join_code(post_str('code'));
    $result = $code ? redeem_join_code($account['id'], $code) : ['ok' => false, 'reason' => 'invalid'];
    if ($result['ok'] || $result['reason'] === 'already') {
        switch_association($result['association_id']);
        redirect('/', $result['ok'] ? t('join.joined', ['name' => $result['name']]) : null);
    }
    fail_form(['code' => $result['reason'] === 'inactive' ? t('join.inactive') : t('join.invalid')], $again);
}

function welcome_switch(): void
{
    require_account();
    $id = post_id('association_id');
    foreach (my_associations() as $a) {
        if ((int) $a['id'] === $id) {
            switch_association($id);
        }
    }
    redirect('/');
}
