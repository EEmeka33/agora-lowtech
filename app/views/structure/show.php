<?php
$assoc = current_association();
$byPosition = [];
foreach ($board as $b) {
    $byPosition[$b['board_position']][] = $b;
}
$canEdit = can('manageStructure', $user);
?>
<a class="back" href="<?= h(url('/poles')) ?>">← <?= h(t('poles.title')) ?></a>
<?= page_head(t('structure.title'), t('structure.subtitle')) ?>

<h2><?= h(t('structure.orgChart')) ?></h2>
<ul class="tree">
  <li><strong><?= h($assoc['name']) ?></strong>
    <ul>
      <li><?= h(t('structure.boardTitle')) ?> <small>— <?= h(t('structure.boardHint')) ?></small>
        <ul>
          <?php foreach (BOARD_POSITIONS as $pos): if ($pos === 'BOARD_MEMBER' && empty($byPosition[$pos])) continue; ?>
          <li><?= h(enum_label('boardPosition', $pos)) ?><?= colon() ?>
            <?php if (empty($byPosition[$pos])): ?><span class="muted"><?= h(t('common.vacant')) ?></span><?php endif ?>
            <?= implode(', ', array_map(fn ($b) => person((int) $b['id'], $b['name']), $byPosition[$pos] ?? [])) ?></li>
          <?php endforeach ?>
        </ul>
      </li>
      <li><?= h(t('structure.polesTitle')) ?> <small>— <?= h(t('structure.polesHint')) ?></small>
        <ul>
          <?php if (!$poles): ?><li class="muted"><?= h(t('structure.noPoles')) ?></li><?php endif ?>
          <?php foreach ($poles as $p): ?>
          <li><a href="<?= h(url('/poles/' . $p['id'])) ?>"><?= h($p['name']) ?></a> <small>(<?= h($p['leads'] ?: t('poles.noLead')) ?>)</small></li>
          <?php endforeach ?>
        </ul>
      </li>
    </ul>
  </li>
</ul>
<p class="meta"><?= h(t('structure.boardEditHint')) ?></p>

<h2 id="duties"><?= h(t('structure.responsibilities')) ?></h2>
<p class="muted"><?= h(t('structure.responsibilitiesHint')) ?></p>
<?php if ($canEdit): ?>
<details class="panel"<?= form_failed('duty') ? ' open' : '' ?>>
  <summary><?= h(t('structure.addResponsibility')) ?></summary>
  <form method="post" action="<?= h(url('/responsibilities')) ?>" class="form">
    <?= csrf_field('duty') ?><?= responsibility_fields() ?>
    <p class="row"><button class="primary"><?= h(t('common.add')) ?></button></p>
  </form>
</details>
<?php endif ?>
<?php if (!$duties): ?><?= empty_html(t('structure.noResponsibilities')) ?><?php else: ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('structure.responsibility')) ?></th><th><?= h(t('common.owner')) ?></th><th><?= h(t('structure.frequency')) ?></th><th><?= h(t('common.pole')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($duties as $d): ?>
  <tr>
    <td><?= h($d['title']) ?><?= $d['description'] ? '<div class="meta text">' . text_html($d['description']) . '</div>' : '' ?>
      <?php if ($canEdit): ?> <a class="no-print" href="<?= h(url('/responsibilities/' . $d['id'])) ?>"><?= h(t('common.edit')) ?></a><?php endif ?></td>
    <td><?= person($d['owner_id'] ? (int) $d['owner_id'] : null, $d['owner_name']) ?></td>
    <td><?= h(enum_label('frequency', $d['frequency'])) ?></td>
    <td><?= h($d['pole_name'] ?? '—') ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>

<h2><?= h(t('structure.matrix')) ?></h2>
<p class="muted"><?= h(t('structure.matrixHint')) ?> <?= h(t('structure.matrixLegend')) ?></p>
<?php if ($poles && $people): ?>
<div class="table"><table>
  <thead><tr><th><?= h(t('common.member')) ?></th><?php foreach ($poles as $p): ?><th><?= h($p['name']) ?></th><?php endforeach ?><th class="num"><?= h(t('dashboard.stats.openTasks')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($people as $m): ?>
  <tr><td><?= person((int) $m['id'], $m['name']) ?></td>
    <?php foreach ($poles as $p): $role = $matrix[$m['id']][$p['id']] ?? null; ?>
    <td><?= $role === 'LEAD' ? '★' : ($role ? '●' : '') ?></td>
    <?php endforeach ?>
    <td class="num"><?= (int) $m['open_tasks'] ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
