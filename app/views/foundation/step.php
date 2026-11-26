<a class="back" href="<?= h(url('/foundation')) ?>#step-<?= (int) $step['id'] ?>">← <?= h(t('foundation.title')) ?></a>
<?= page_head($step['title'], enum_label('stepCategory', $step['category']), status_badge('stepStatus', $step['status'])) ?>
<div class="cols">
<div>
  <?php if ($step['description']): ?><div class="box gap text"><?= text_html($step['description']) ?></div><?php endif ?>
  <?php if ($step['resource_url']): ?><p><?= h(t('foundation.resource')) ?><?= colon() ?><a href="<?= h($step['resource_url']) ?>" rel="noopener"><?= h($step['resource_url']) ?></a></p><?php endif ?>
  <?php if ($step['notes']): ?><h2><?= h(t('common.notes')) ?></h2><div class="text"><?= text_html($step['notes']) ?></div><?php endif ?>
  <?php if ($canEdit): ?>
  <p class="actions no-print">
    <?php foreach (['DONE' => 'primary', 'IN_PROGRESS' => '', 'TODO' => '', 'SKIPPED' => ''] as $status => $class): if ($status !== $step['status']): ?>
      <?= button_form("/foundation/{$step['id']}/status", enum_label('stepStatus', $status), ['status' => $status], $class) ?>
    <?php endif; endforeach ?>
  </p>
  <details class="panel edit no-print"<?= form_errors() ? ' open' : '' ?>><summary><?= h(t('foundation.editStep')) ?></summary>
    <form method="post" action="<?= h(url('/foundation/' . $step['id'])) ?>" class="form"><?= csrf_field() ?><?= step_fields($step) ?>
      <p class="row"><button class="primary"><?= h(t('common.saveChanges')) ?></button></p></form>
  </details>
  <?php endif ?>
</div>
<aside>
  <table>
    <tr><th><?= h(t('common.assignee')) ?></th><td><?= h($step['assignee_name'] ?? '—') ?></td></tr>
    <tr><th><?= h(t('common.dueDate')) ?></th><td><?= due_html($step['due_date'], in_array($step['status'], ['DONE', 'SKIPPED'], true)) ?: '—' ?></td></tr>
    <?php if ($step['completed_at']): ?><tr><th><?= h(t('enums.stepStatus.DONE')) ?></th><td><?= h(fmt_date(utc_to_local($step['completed_at']))) ?></td></tr><?php endif ?>
  </table>
  <?php if (can('manageFoundation', $user)): ?><p class="no-print"><?= confirm_form("/foundation/{$step['id']}/delete", t('common.delete'), '', ['_back' => '/foundation']) ?></p><?php endif ?>
</aside>
</div>
