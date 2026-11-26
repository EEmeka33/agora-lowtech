<?php
/*
 * Frame of every workspace page. The content comes first in the HTML, the
 * menu after it: screen readers and text browsers (lynx, w3m) reach the content
 * at once, and on small screens the "Menu" link jumps down to the menu.
 * On large screens the stylesheet shows the menu as a left column.
 */
$user = current_user();
$assoc = current_association();
$section = explode('/', trim(request_path(), '/'))[0] ?: 'dashboard';
$unread = unread_notifications();
$nav = [
    'overview' => ['dashboard' => '/', 'foundation' => '/foundation', 'activity' => '/activity'],
    'organization' => ['structure' => '/structure', 'poles' => '/poles', 'members' => '/members'],
    'work' => ['requests' => '/requests', 'projects' => '/projects', 'tasks' => '/tasks'],
    'life' => ['events' => '/events', 'polls' => '/polls', 'volunteering' => '/volunteering', 'documents' => '/documents'],
    'admin' => ['finance' => '/finance', 'settings' => '/settings'],
];
$aliases = ['' => 'dashboard', 'announcements' => 'dashboard', 'responsibilities' => 'structure', 'dues' => 'finance', 'files' => 'documents'];
$active = $aliases[$section] ?? $section;
$flash = flash();
?><!doctype html>
<html lang="<?= h(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(($title !== '' ? $title . ' · ' : '') . ($assoc['name'] ?? 'Agora')) ?></title>
<link rel="stylesheet" href="<?= h(url('/style.css')) ?>?v=<?= filemtime(ROOT . '/public/style.css') ?>">
<link rel="icon" href="data:,">
</head>
<body>
<a class="skip" href="#main"><?= h(t('nav.skip')) ?></a>
<header class="top">
  <a class="brand" href="<?= h(url('/')) ?>"><?= h($assoc['short_name'] ?: $assoc['name'] ?? 'Agora') ?></a>
  <form class="search" role="search" action="<?= h(url('/search')) ?>">
    <input type="search" name="q" value="<?= h(request_path() === '/search' ? get_str('q') : '') ?>" placeholder="<?= h(t('nav.search')) ?>" aria-label="<?= h(t('common.search')) ?>">
    <button><?= h(t('common.search')) ?></button>
  </form>
  <a href="<?= h(url('/notifications')) ?>"<?= $active === 'notifications' ? ' aria-current="page"' : '' ?>><?= h(t('nav.notifications')) ?><?= $unread ? ' <b class="count">' . $unread . '</b>' : '' ?></a>
  <a href="<?= h(url('/settings')) ?>"><?= h(first_name($user['name'] ?? '')) ?></a>
  <form method="post" action="<?= h(url('/logout')) ?>" class="inline"><?= csrf_field() ?><button class="link"><?= h(t('nav.logout')) ?></button></form>
  <a class="menu-link" href="#nav"><?= h(t('nav.menu')) ?> ↓</a>
</header>
<div class="shell">
<main id="main">
<?php if ($flash): ?>
  <p class="flash <?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></p>
<?php endif ?>
<?= $content ?>
</main>
<nav id="nav" aria-label="<?= h(t('nav.menu')) ?>">
<?php foreach ($nav as $group => $links): ?>
  <h2><?= h(t("nav.sections.$group")) ?></h2>
  <ul>
  <?php foreach ($links as $key => $href): ?>
    <li><a href="<?= h(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= h(t("nav.$key")) ?></a></li>
  <?php endforeach ?>
  </ul>
<?php endforeach ?>
  <h2><?= h(t('nav.associations')) ?></h2>
<?php if (count(my_associations()) > 1): ?>
  <form method="post" action="<?= h(url('/switch')) ?>" class="switch">
    <?= csrf_field() ?>
    <select name="association_id" aria-label="<?= h(t('nav.switchAssociation')) ?>">
      <?php foreach (my_associations() as $a): ?>
      <option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === (int) $assoc['id'] ? ' selected' : '' ?>><?= h($a['name']) ?></option>
      <?php endforeach ?>
    </select>
    <button><?= h(t('nav.switch')) ?></button>
  </form>
<?php endif ?>
  <ul><li><a href="<?= h(url('/welcome')) ?>"><?= h(t('nav.newAssociation')) ?></a></li></ul>
</nav>
</div>
<?= template('footer') ?>
</body>
</html>
