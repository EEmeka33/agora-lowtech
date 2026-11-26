<?php
$canManage = can('manageFoundation', $user);
$byCategory = [];
foreach ($steps as $s) {
    $byCategory[$s['category']][] = $s;
}
?>
<?= page_head(t('foundation.title'), t('foundation.subtitle')) ?>

<?php if ($steps): ?>
<div class="banner"><?= progress_html($progress['percent']) ?> <span><?= h(tn('foundation.stepsDone', $progress['done'], ['done' => $progress['done'], 'total' => $progress['total']])) ?></span>
  <?php if ($progress['total'] && $progress['done'] === $progress['total']): ?><strong><?= h(t('foundation.complete')) ?></strong><?php endif ?></div>
<?php else: ?>
<div class="box gap"><h2><?= h(t('foundation.emptyTitle')) ?></h2><p><?= h(t('foundation.emptyText')) ?></p>
  <?= $canManage ? button_form('/foundation/template', t('foundation.loadTemplate'), [], 'primary') : '' ?></div>
<?php endif ?>

<?php foreach (STEP_CATEGORIES as $cat): if (empty($byCategory[$cat])) continue; $p = foundation_progress($byCategory[$cat]); $complete = $p['total'] > 0 && $p['done'] === $p['total']; ?>
<details class="theme"<?= $complete ? '' : ' open' ?>>
  <summary><strong><?= h(enum_label('stepCategory', $cat)) ?></strong> <span class="meta"><?= $p['done'] ?>/<?= $p['total'] ?></span> <?= $complete ? badge(t('enums.stepStatus.DONE'), 'ok') : '' ?>
    <span class="meta hint"><?= h(t("foundation.categoryHints.$cat")) ?></span></summary>
  <ul class="plain steps">
    <?php foreach ($byCategory[$cat] as $s): $done = $s['status'] === 'DONE'; ?>
    <li id="step-<?= (int) $s['id'] ?>" class="<?= $done ? 'done' : '' ?><?= $s['status'] === 'SKIPPED' ? ' skipped' : '' ?>">
      <?php if (can('editStep', $user, $s)): ?>
        <?= button_form("/foundation/{$s['id']}/status", $done ? '✓' : '', ['status' => $done ? 'TODO' : 'DONE'], 'tick' . ($done ? ' on' : ''), $done ? t('foundation.markTodo') : t('foundation.markDone')) ?>
      <?php else: ?><span class="tick<?= $done ? ' on' : '' ?>" aria-hidden="true"><?= $done ? '✓' : '' ?></span><?php endif ?>
      <div>
        <a href="<?= h(url('/foundation/' . $s['id'])) ?>"><?= h($s['title']) ?></a>
        <?= in_array($s['status'], ['IN_PROGRESS', 'SKIPPED'], true) ? status_badge('stepStatus', $s['status']) : '' ?>
        <div class="meta"><?= h($s['assignee_name'] ?? t('common.unassigned')) ?><?= $s['due_date'] && !$done ? ' · ' . due_html($s['due_date']) : '' ?><?= $s['completed_at'] && $done ? ' · ' . h(t('foundation.completedOn', ['d' => fmt_date(utc_to_local($s['completed_at']))])) : '' ?></div>
      </div>
    </li>
    <?php endforeach ?>
  </ul>
</details>
<?php endforeach ?>

<?php if ($canManage): ?>
<details class="panel no-print"<?= form_failed('step') ? ' open' : '' ?>>
  <summary><?= h(t('foundation.addStep')) ?></summary>
  <form method="post" action="<?= h(url('/foundation')) ?>" class="form"><?= csrf_field('step') ?><?= step_fields() ?>
    <p class="row"><button class="primary"><?= h(t('common.add')) ?></button></p></form>
</details>
<?php if ($steps): ?><p class="no-print meta"><?= h(t('foundation.tip')) ?> <?= button_form('/foundation/template', t('foundation.loadTemplate'), [], 'link') ?></p><?php endif ?>
<?php endif ?>
