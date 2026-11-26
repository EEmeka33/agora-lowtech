<?= template('settings/tabs', ['tab' => $tab]) ?>
<h2><?= h(t('settings.dataTitle')) ?></h2>
<p><?= h(t('settings.dataHint')) ?> (<?= h(fmt_bytes($size)) ?>)</p>
<ul>
  <li><a href="<?= h(url('/settings/backup', ['download' => 1])) ?>"><?= h(t('settings.exportJson')) ?></a></li>
  <li><a href="<?= h(url('/members.csv')) ?>"><?= h(t('members.export')) ?> (CSV)</a></li>
  <li><a href="<?= h(url('/finance.csv')) ?>"><?= h(t('settings.exportCsv')) ?></a></li>
  <li><a href="<?= h(url('/volunteering.csv')) ?>"><?= h(t('volunteering.export')) ?> (CSV)</a></li>
</ul>
<p class="muted"><?= h(t('settings.backupHint')) ?> <code>php bin/backup.php</code></p>
