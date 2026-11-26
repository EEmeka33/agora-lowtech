<?= page_head(t('about.title'), t('about.subtitle')) ?>
<p><?= h(t('about.intro')) ?></p>

<h2><?= h(t('about.principlesTitle')) ?></h2>
<ol>
  <?php foreach (['noJs', 'light', 'noDeps', 'smallServer', 'sober', 'open', 'async', 'repair'] as $k): ?>
  <li><strong><?= h(t("about.principles.$k.title")) ?></strong> — <?= h(t("about.principles.$k.text")) ?></li>
  <?php endforeach ?>
</ol>

<h2><?= h(t('about.numbersTitle')) ?></h2>
<div class="table"><table>
  <tr><th><?= h(t('about.numbers.code')) ?></th><td class="num"><?= h(t('about.numbers.codeValue', ['files' => $code['files'], 'lines' => number_format($code['lines'], 0, '', ' '), 'size' => fmt_bytes($code['bytes'])])) ?></td></tr>
  <tr><th><?= h(t('about.numbers.dependencies')) ?></th><td class="num">0</td></tr>
  <tr><th><?= h(t('about.numbers.javascript')) ?></th><td class="num">0 B</td></tr>
  <tr><th><?= h(t('about.numbers.css')) ?></th><td class="num"><?= h(fmt_bytes($css)) ?></td></tr>
  <tr><th><?= h(t('about.numbers.database')) ?></th><td class="num"><?= h(fmt_bytes($dbBytes)) ?></td></tr>
  <tr><th><?= h(t('about.numbers.uploads')) ?></th><td class="num"><?= h(tn('about.numbers.files', $uploads['count'])) ?> · <?= h(fmt_bytes($uploads['bytes'])) ?></td></tr>
  <tr><th><?= h(t('about.numbers.memory')) ?></th><td class="num"><?= h(fmt_bytes(memory_get_peak_usage())) ?></td></tr>
  <tr><th><?= h(t('about.numbers.software')) ?></th><td class="num">PHP <?= h(PHP_VERSION) ?> · SQLite <?= h($sqlite) ?></td></tr>
</table></div>
<p class="meta"><?= h(t('about.compare')) ?></p>

<h2><?= h(t('about.tryTitle')) ?></h2>
<ul>
  <?php foreach (['print', 'text', 'nostyle', 'slow'] as $k): ?><li><?= h(t("about.try.$k")) ?></li><?php endforeach ?>
</ul>
<p><?= h(t('about.more')) ?> <code>docs/low-tech.md</code></p>
