<?php
/*
 * Votes: a question, two or more options, single or multiple choice, anonymous
 * or not, optional closing date, optionally only for the members of a pole.
 * Results are drawn with text characters.
 */

function polls_list(): void
{
    $user = require_member();
    $polls = rows('SELECT p.*, u.name AS creator_name, po.name AS pole_name FROM polls p
                   LEFT JOIN users u ON u.id = p.created_by_id LEFT JOIN poles po ON po.id = p.pole_id
                   WHERE p.association_id = ? ORDER BY (p.closed_at IS NULL AND (p.closes_at IS NULL OR p.closes_at >= ?)) DESC, p.created_at DESC LIMIT 100',
        [aid(), now_wall()]);
    $ids = array_column($polls, 'id');
    $options = $votes = [];
    if ($ids) {
        foreach (rows('SELECT * FROM poll_options WHERE poll_id IN (' . placeholders($ids) . ') ORDER BY position, id', $ids) as $o) {
            $options[$o['poll_id']][] = $o;
        }
        foreach (rows('SELECT v.poll_id, v.option_id, v.user_id, u.name FROM poll_votes v JOIN users u ON u.id = v.user_id WHERE v.poll_id IN (' . placeholders($ids) . ')', $ids) as $v) {
            $votes[$v['poll_id']][] = $v;
        }
    }
    $myPoles = my_pole_ids($user['id']);
    $eligible = (int) val("SELECT COUNT(*) FROM members WHERE association_id = ? AND status = 'ACTIVE'", [aid()]);
    render('polls/list', compact('user', 'polls', 'options', 'votes', 'myPoles', 'eligible'), t('polls.title'));
}

function poll_is_open(array $poll): bool
{
    return !$poll['closed_at'] && (!$poll['closes_at'] || $poll['closes_at'] >= now_wall());
}

function polls_create(): void
{
    $user = require_member();
    $question = post_str('question');
    $options = post_bool('yes_no')
        ? [t('polls.yes'), t('polls.no'), t('polls.abstain')]
        : array_slice(array_values(array_unique(post_all('options'))), 0, 12);
    $errors = [];
    if ($question === '') {
        $errors['question'] = t('validation.required');
    }
    if (count($options) < 2) {
        $errors['options'] = t('polls.needTwo');
    }
    if ($errors) {
        fail_form($errors, 'polls_list');
    }
    $poleId = post_id('pole_id');
    assert_refs(['pole_id' => $poleId]);
    $id = transaction(function () use ($user, $question, $options, $poleId) {
        $id = insert('polls', [
            'association_id' => aid(),
            'question' => $question,
            'description' => post_opt('description'),
            'multiple' => post_bool('multiple') ? 1 : 0,
            'anonymous' => post_bool('anonymous') ? 1 : 0,
            'closes_at' => post_date('closes_at', true),
            'pole_id' => $poleId,
            'created_by_id' => $user['id'],
            'created_at' => now_utc(),
        ]);
        foreach ($options as $i => $label) {
            insert('poll_options', ['poll_id' => $id, 'label' => str_cut($label, 200), 'position' => $i]);
        }
        return $id;
    });
    log_activity('created', 'poll', $id, $question);
    notify($poleId ? pole_member_ids($poleId) : association_member_ids(aid()), 'poll.new', ['title' => $question], "/polls#poll-$id");
    redirect("/polls#poll-$id", t('common.created'));
}

function polls_vote(int $id): void
{
    $user = require_member();
    $poll = find_or_404('polls', $id);
    if (!poll_is_open($poll)) {
        redirect("/polls#poll-$id", t('polls.closed'), 'error');
    }
    if ($poll['pole_id'] && !in_array((int) $poll['pole_id'], my_pole_ids($user['id']), true)) {
        redirect("/polls#poll-$id", t('polls.notEligible'), 'error');
    }
    $valid = array_map('intval', col('SELECT id FROM poll_options WHERE poll_id = ?', [$id]));
    $chosen = array_values(array_intersect($valid, array_map('intval', post_all('option'))));
    if (!$poll['multiple']) {
        $chosen = array_slice($chosen, 0, 1);
    }
    if (!$chosen) {
        redirect("/polls#poll-$id", t('polls.chooseOne'), 'error');
    }
    transaction(function () use ($id, $user, $chosen) {
        q('DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?', [$id, $user['id']]);
        foreach ($chosen as $optionId) {
            insert('poll_votes', ['poll_id' => $id, 'option_id' => $optionId, 'user_id' => $user['id'], 'created_at' => now_utc()]);
        }
    });
    log_activity('voted', 'poll', $id, $poll['question']);
    redirect("/polls#poll-$id", t('polls.voted'));
}

function polls_close(int $id): void
{
    $user = require_member();
    $poll = find_or_404('polls', $id);
    ensure(can('editPoll', $user, $poll));
    update('polls', ['closed_at' => now_utc()], 'id = ?', [$id]);
    redirect("/polls#poll-$id", t('common.saved'));
}

function polls_delete(int $id): void
{
    $user = require_member();
    $poll = find_or_404('polls', $id);
    ensure(can('editPoll', $user, $poll));
    q('DELETE FROM polls WHERE id = ?', [$id]);
    redirect('/polls', t('common.deleted'));
}
