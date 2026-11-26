<?= page_head(t('poles.title'), t('poles.subtitle')) ?>
<?php if (can('managePoles', $user)): ?>
<details class="panel"<?= get_str('new') || form_failed('pole') ? ' open' : '' ?>>
  <summary><?= h(t('poles.newPole')) ?></summary>
  <form method="post" action="<?= h(url('/poles')) ?>" class="form">
    <?= csrf_field('pole') ?>
    <?= input_field('name', t('common.name'), old('name'), ['required' => true]) ?>
    <?= select_field('lead_id', t('common.lead'), member_options(), old('lead_id'), ['empty' => t('poles.noLead')]) ?>
    <?= textarea_field('mission', t('poles.mission'), old('mission'), ['rows' => 2, 'placeholder' => t('poles.missionPlaceholder')]) ?>
    <?= textarea_field('description', t('common.description'), old('description'), ['rows' => 2]) ?>
    <p class="row"><button class="primary"><?= h(t('common.create')) ?></button></p>
  </form>
</details>
<?php endif ?>
<?php if (!$poles): ?>
  <?= empty_html(t('poles.emptyText')) ?>
<?php endif ?>
<div class="grid">
  <?php foreach ($poles as $p): ?>
  <section class="box">
    <h2><a href="<?= h(url('/poles/' . $p['id'])) ?>"><?= h($p['name']) ?></a></h2>
    <?php if ($p['mission']): ?><p><?= h(str_cut($p['mission'], 160)) ?></p><?php endif ?>
    <p class="meta"><?= h(t('common.leads')) ?> : <?= h($p['leads'] ?: t('poles.noLead')) ?><br>
      <?= h(tn('common.countMembers', (int) $p['member_count'])) ?> · <?= h(tn('common.countProjects', (int) $p['project_count'])) ?> · <?= h(tn('common.countTasks', (int) $p['task_count'])) ?>
      <?= $p['request_count'] ? '<br>' . h(tn('requests.openCount', (int) $p['request_count'])) : '' ?></p>
    <p><a href="<?= h(url('/requests', ['pole' => $p['id']])) ?>#new"><?= h(t('requests.askTitle', ['pole' => $p['name']])) ?></a></p>
  </section>
  <?php endforeach ?>
</div>
