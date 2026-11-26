<?php $flash = flash(); ?><!doctype html>
<html lang="<?= h(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(($title !== '' ? $title . ' · ' : '') . 'Agora') ?></title>
<link rel="stylesheet" href="<?= h(url('/style.css')) ?>?v=<?= filemtime(ROOT . '/public/style.css') ?>">
<link rel="icon" href="data:,">
</head>
<body class="auth">
<main id="main">
  <p class="brand"><a href="<?= h(url('/')) ?>">Agora</a> <small><?= h(t('meta.tagline')) ?></small></p>
<?php if ($flash): ?>
  <p class="flash <?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></p>
<?php endif ?>
<?= $content ?>
</main>
<?= template('footer') ?>
</body>
</html>
