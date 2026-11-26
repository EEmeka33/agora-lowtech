<?= page_head(t('polls.title'), t('polls.subtitle')) ?>
<details class="panel"<?= get_str('new') || form_failed('poll') ? ' open' : '' ?>>
  <summary><?= h(t('polls.newPoll')) ?></summary>
  <form method="post" action="<?= h(url('/polls')) ?>" class="form">
    <?= csrf_field('poll') ?>
    <?= input_field('question', t('polls.question'), old('question'), ['required' => true, 'class' => 'wide', 'placeholder' => t('polls.questionPlaceholder')]) ?>
    <?= textarea_field('description', t('common.description'), old('description'), ['rows' => 2]) ?>
    <fieldset class="field wide"><legend><?= h(t('polls.options')) ?></legend>
      <?php $typed = old('options', []); for ($i = 0; $i < 6; $i++): ?>
      <input name="options[]" value="<?= h($typed[$i] ?? '') ?>" placeholder="<?= h(t('polls.option', ['n' => $i + 1])) ?>" aria-label="<?= h(t('polls.option', ['n' => $i + 1])) ?>" maxlength="200">
      <?php endfor ?>
      <?= field_error('options') ?>
      <?= checkbox_field('yes_no', t('polls.yesNo'), false, t('polls.yesNoHint')) ?>
    </fieldset>
    <?= input_field('closes_at', t('polls.closesAt'), old('closes_at'), ['type' => 'datetime-local']) ?>
    <?= select_field('pole_id', t('polls.onlyPole'), pole_options(), old('pole_id'), ['empty' => t('polls.everyone')]) ?>
    <div class="row">
      <?= checkbox_field('multiple', t('polls.multiple')) ?>
      <?= checkbox_field('anonymous', t('polls.anonymous')) ?>
    </div>
    <p class="row"><button class="primary"><?= h(t('polls.create')) ?></button></p>
  </form>
</details>

<?php if (!$polls): ?><?= empty_html(t('polls.emptyText')) ?><?php endif ?>
<?php foreach ($polls as $p):
    $open = poll_is_open($p);
    $pollVotes = $votes[$p['id']] ?? [];
    $voters = array_unique(array_column($pollVotes, 'user_id'));
    $myVotes = array_map('intval', array_column(array_filter($pollVotes, fn ($v) => (int) $v['user_id'] === $user['id']), 'option_id'));
    $canVote = $open && (!$p['pole_id'] || in_array((int) $p['pole_id'], $myPoles, true));
    $counts = array_count_values(array_map('intval', array_column($pollVotes, 'option_id')));
    $max = $counts ? max($counts) : 0;
?>
<section class="box gap" id="poll-<?= (int) $p['id'] ?>">
  <h2><?= h($p['question']) ?> <?= $open ? badge(t('polls.openBadge'), 'ok') : badge(t('polls.closedBadge')) ?></h2>
  <p class="meta"><?= h(t('polls.by', ['name' => $p['creator_name'] ?? '—'])) ?> · <?= h(fmt_relative($p['created_at'])) ?>
    <?= $p['closes_at'] && $open ? ' · ' . h(t('polls.closesIn', ['when' => fmt_relative($p['closes_at'], false)])) : '' ?>
    <?= $p['closed_at'] ? ' · ' . h(t('polls.closedOn', ['when' => fmt_instant($p['closed_at'])])) : '' ?>
    <?= $p['pole_name'] ? ' · ' . h(t('polls.poleOnly', ['pole' => $p['pole_name']])) : '' ?>
    <?= $p['multiple'] ? ' · ' . h(t('polls.multiple')) : '' ?><?= $p['anonymous'] ? ' · ' . h(t('polls.anonymous')) : '' ?></p>
  <?php if ($p['description']): ?><div class="text"><?= text_html($p['description']) ?></div><?php endif ?>

  <?php if ($canVote): ?>
  <form method="post" action="<?= h(url("/polls/{$p['id']}/vote")) ?>" class="no-print">
    <?= csrf_field() ?>
    <?php foreach ($options[$p['id']] ?? [] as $o): ?>
    <label class="check"><input type="<?= $p['multiple'] ? 'checkbox' : 'radio' ?>" name="option[]" value="<?= (int) $o['id'] ?>"<?= in_array((int) $o['id'], $myVotes, true) ? ' checked' : '' ?>> <?= h($o['label']) ?></label>
    <?php endforeach ?>
    <p><button class="primary"><?= h($myVotes ? t('polls.changeVote') : t('polls.vote')) ?></button></p>
  </form>
  <?php endif ?>

  <?php if (!$open || $myVotes || !$canVote): ?>
  <table class="bars">
    <?php foreach ($options[$p['id']] ?? [] as $o): $n = $counts[(int) $o['id']] ?? 0; ?>
    <tr><td><?= h($o['label']) ?><?= in_array((int) $o['id'], $myVotes, true) ? ' ✓' : '' ?></td>
      <td class="num"><?= $n ?></td><td class="num"><?= pct($n, max(1, count($voters))) ?>&nbsp;%</td>
      <td><?= bar($n, max(1, count($voters))) ?>
        <?php if (!$p['anonymous'] && $n): ?><div class="meta"><?= h(implode(', ', array_column(array_filter($pollVotes, fn ($v) => (int) $v['option_id'] === (int) $o['id']), 'name'))) ?></div><?php endif ?></td></tr>
    <?php endforeach ?>
  </table>
  <?php else: ?>
  <p class="meta"><?= h(t('polls.resultsAfterVote')) ?></p>
  <?php endif ?>
  <p class="meta"><?= h(tn('polls.participation', count($voters), ['v' => count($voters), 'total' => $eligible])) ?></p>

  <?php if (can('editPoll', $user, $p)): ?>
  <p class="actions no-print"><?= $open ? button_form("/polls/{$p['id']}/close", t('polls.close'), [], 'link') : '' ?> <?= confirm_form("/polls/{$p['id']}/delete", t('common.delete')) ?></p>
  <?php endif ?>
</section>
<?php endforeach ?>
