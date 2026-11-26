<?php
$canManage = can('manageFoundation', $user);
$byCategory = [];
foreach ($steps as $s) {
    $byCategory[$s['category']][] = $s;
}
?>
<?= page_head(t('foundation.title'), t('foundation.subtitle')) ?>

<?php if ($steps): ?>
<p class="banner"><?= progress_html($progress['percent']) ?> · <?= h(tn('foundation.stepsDone', $progress['done'], ['done' => $progress['done'], 'total' => $progress['total']])) ?>
  <?php if ($progress['total'] && $progress['done'] === $progress['total']): ?><br><strong><?= h(t('foundation.complete')) ?></strong> <?= h(t('foundation.completeText')) ?><?php endif ?></p>
<p class="meta"><?= h(t('foundation.tip')) ?></p>
<?php else: ?>
<div class="box gap"><h2><?= h(t('foundation.emptyTitle')) ?></h2><p><?= h(t('foundation.emptyText')) ?></p>
  <?= $canManage ? button_form('/foundation/template', t('foundation.loadTemplate'), [], 'primary') : '' ?></div>
<?php endif ?>

<?php foreach (STEP_CATEGORIES as $cat): if (empty($byCategory[$cat])) continue; $p = foundation_progress($byCategory[$cat]); ?>
<h2><?= h(enum_label('stepCategory', $cat)) ?> <small><?= $p['done'] ?>/<?= $p['total'] ?></small></h2>
<p class="meta"><?= h(t("foundation.categoryHints.$cat")) ?></p>
<ul class="plain">
  <?php foreach ($byCategory[$cat] as $s): $canEdit = can('editStep', $user, $s); ?>
  <li id="step-<?= (int) $s['id'] ?>">
    <?= ['DONE' => '☑', 'SKIPPED' => '⊘', 'IN_PROGRESS' => '◐'][$s['status']] ?? '☐' ?>
    <strong><?= $s['status'] === 'SKIPPED' ? '<s>' . h($s['title']) . '</s>' : h($s['title']) ?></strong> <?= status_badge('stepStatus', $s['status']) ?>
    <div class="meta"><?= h($s['assignee_name'] ?? t('common.unassigned')) ?><?= $s['due_date'] ? ' · ' . due_html($s['due_date'], in_array($s['status'], ['DONE', 'SKIPPED'], true)) : '' ?>
      <?= $s['completed_at'] ? ' · ' . h(t('foundation.completedOn', ['d' => fmt_date(utc_to_local($s['completed_at']))])) : '' ?></div>
    <details class="panel"><summary><?= h(t('common.details')) ?></summary>
      <?php if ($s['description']): ?><div class="text"><?= text_html($s['description']) ?></div><?php endif ?>
      <?php if ($s['resource_url']): ?><p><?= h(t('foundation.resource')) ?> : <a href="<?= h($s['resource_url']) ?>" rel="noopener"><?= h($s['resource_url']) ?></a></p><?php endif ?>
      <?php if ($s['notes']): ?><p><strong><?= h(t('common.notes')) ?></strong></p><div class="text"><?= text_html($s['notes']) ?></div><?php endif ?>
      <?php if ($canEdit): ?>
      <p class="actions no-print">
        <?php if ($s['status'] !== 'DONE'): ?><?= button_form("/foundation/{$s['id']}/status", t('foundation.markDone'), ['status' => 'DONE'], 'primary') ?><?php else: ?><?= button_form("/foundation/{$s['id']}/status", t('foundation.markTodo'), ['status' => 'TODO']) ?><?php endif ?>
      </p>
      <p class="no-print"><a href="<?= h(url('/foundation/' . $s['id'])) ?>"><?= h(t('foundation.editStep')) ?></a></p>
      <?php endif ?>
    </details>
  </li>
  <?php endforeach ?>
</ul>
<?php endforeach ?>

<?php if ($canManage): ?>
<details class="panel no-print"<?= form_failed('step') ? ' open' : '' ?>>
  <summary><?= h(t('foundation.addStep')) ?></summary>
  <form method="post" action="<?= h(url('/foundation')) ?>" class="form"><?= csrf_field('step') ?><?= step_fields() ?>
    <p class="row"><button class="primary"><?= h(t('common.add')) ?></button></p></form>
</details>
<?php if ($steps): ?><p class="no-print"><?= button_form('/foundation/template', t('foundation.loadTemplate'), [], 'link') ?></p><?php endif ?>
<?php endif ?>
