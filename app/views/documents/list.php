<?php
$current = '';
?>
<?= page_head(t('documents.title'), t('documents.subtitle')) ?>
<details class="panel no-print"<?= get_str('new') || form_failed('doc') ? ' open' : '' ?>>
  <summary><?= h(t('documents.add')) ?></summary>
  <form method="post" action="<?= h(url('/documents')) ?>" class="form" enctype="multipart/form-data">
    <?= csrf_field('doc') ?>
    <label class="field wide"><span><?= h(t('documents.file')) ?></span><input type="file" name="file">
      <small><?= h(t('documents.fileHint', ['mb' => config('max_upload_mb')])) ?></small><?= field_error('file') ?></label>
    <?= document_fields([], true) ?>
    <p class="row"><button class="primary"><?= h(t('common.add')) ?></button></p>
  </form>
</details>

<form class="filters no-print" action="<?= h(url('/documents')) ?>">
  <?= input_field('q', t('common.search'), $search, ['type' => 'search']) ?>
  <?= select_field('category', t('common.category'), enum_options('documentCategory', DOCUMENT_CATEGORIES), $category, ['empty' => t('common.all')]) ?>
  <button><?= h(t('common.filter')) ?></button>
</form>

<?php if (!$documents): ?><?= empty_html(t('documents.emptyText')) ?><?php endif ?>
<?php foreach ($documents as $d): ?>
  <?php if ($d['category'] !== $current): $current = $d['category']; ?>
  <?= $current !== $documents[0]['category'] ? '</ul>' : '' ?><h2><?= h(enum_label('documentCategory', $current)) ?></h2><ul class="plain">
  <?php endif ?>
  <li><a href="<?= h($d['stored_name'] ? url('/files/' . $d['id']) : $d['url']) ?>"<?= $d['stored_name'] ? '' : ' rel="noopener"' ?>><?= h($d['title']) ?></a>
    <span class="meta"><?= $d['stored_name'] ? h(strtoupper(pathinfo($d['stored_name'], PATHINFO_EXTENSION)) . ' · ' . fmt_bytes((int) $d['size_bytes'])) : h(t('documents.linkBadge') . ' · ' . (parse_url($d['url'], PHP_URL_HOST) ?: '')) ?></span>
    <div class="meta"><?= h(implode(' · ', array_filter([t('documents.uploadedBy', ['name' => $d['uploader_name'] ?? '—']), fmt_date(utc_to_local($d['created_at'])), $d['pole_name'], $d['project_title']]))) ?></div>
    <?php if ($d['description']): ?><div class="text meta"><?= text_html($d['description']) ?></div><?php endif ?>
    <?php if (can('editDocument', $user, $d)): ?><div class="no-print"><a href="<?= h(url('/documents/' . $d['id'])) ?>"><?= h(t('common.edit')) ?></a></div><?php endif ?>
  </li>
<?php endforeach ?>
<?= $documents ? '</ul>' : '' ?>
<p class="meta"><?= h(t('documents.storage', ['size' => fmt_bytes($storage)])) ?></p>
