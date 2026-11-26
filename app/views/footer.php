<footer class="foot">
  <form method="post" action="<?= h(url('/locale')) ?>" class="inline lang">
    <?= csrf_field() ?><?= back_field() ?>
    <?php foreach (LOCALES as $l): ?>
    <button name="locale" value="<?= h($l) ?>" class="link"<?= $l === locale() ? ' aria-current="true"' : '' ?>><?= h(t("enums.locale.$l")) ?></button>
    <?php endforeach ?>
  </form>
  <a href="<?= h(url('/about')) ?>"><?= h(t('footer.about')) ?></a>
<?php if (config('show_page_weight')): ?>
  <span class="weight">{{WEIGHT}}</span>
<?php endif ?>
</footer>
