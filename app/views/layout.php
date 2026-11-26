<?php
/*
 * Frame of every workspace page: a top bar (association, search, notifications,
 * user menu), a short menu, the content, a footer.
 *
 * Everything works without JavaScript: the user menu is a <details>, the phone
 * menu is a checkbox (#menu-toggle) that shows or hides the navigation.
 * public/app.js only adds comfort (confirmations, instant filters).
 */
$user = current_user();
$assoc = current_association();
$section = explode('/', trim(request_path(), '/'))[0] ?: 'dashboard';
$unread = unread_notifications();
$nav = [
    'main' => ['dashboard' => '/', 'tasks' => '/tasks', 'requests' => '/requests', 'projects' => '/projects', 'events' => '/events', 'polls' => '/polls'],
    'association' => ['poles' => '/poles', 'members' => '/members', 'documents' => '/documents', 'finance' => '/finance', 'volunteering' => '/volunteering', 'foundation' => '/foundation'],
];
$aliases = ['' => 'dashboard', 'announcements' => 'dashboard', 'activity' => 'dashboard', 'structure' => 'poles',
    'responsibilities' => 'poles', 'dues' => 'finance', 'files' => 'documents'];
$active = $aliases[$section] ?? $section;
$flash = flash();
?><!doctype html>
<html lang="<?= h(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(($title !== '' ? $title . ' · ' : '') . ($assoc['name'] ?? 'Agora')) ?></title>
<link rel="stylesheet" href="<?= h(url('/style.css')) ?>?v=<?= filemtime(ROOT . '/public/style.css') ?>">
<script src="<?= h(url('/app.js')) ?>?v=<?= filemtime(ROOT . '/public/app.js') ?>" defer></script>
<link rel="icon" href="data:,">
</head>
<body>
<a class="skip" href="#main"><?= h(t('nav.skip')) ?></a>
<input type="checkbox" id="menu-toggle" class="menu-toggle" aria-label="<?= h(t('nav.menu')) ?>">
<header class="top">
  <label for="menu-toggle" class="menu-button"><?= h(t('nav.menu')) ?></label>
  <a class="brand" href="<?= h(url('/')) ?>"><?= h($assoc['name'] ?? 'Agora') ?></a>
  <form class="search" role="search" action="<?= h(url('/search')) ?>">
    <input type="search" name="q" value="<?= h(request_path() === '/search' ? get_str('q') : '') ?>" placeholder="<?= h(t('nav.search')) ?>" aria-label="<?= h(t('common.search')) ?>">
    <button><?= h(t('common.search')) ?></button>
  </form>
  <a class="top-link" href="<?= h(url('/notifications')) ?>"><span class="notif-label"><?= h(t('nav.notifications')) ?></span><?= $unread ? ' <b class="count">' . $unread . '</b>' : '' ?></a>
  <details class="usermenu">
    <summary><?= h(first_name($user['name'] ?? '')) ?></summary>
    <div class="dropdown">
      <p><?= h($user['name'] ?? '') ?></p>
      <a href="<?= h(url('/settings')) ?>"><?= h(t('nav.mySettings')) ?></a>
      <?php if (is_admin($user)): ?><a href="<?= h(url('/settings/association')) ?>"><?= h(t('nav.associationSettings')) ?></a><?php endif ?>
      <hr>
      <p><?= h(t('nav.associations')) ?></p>
      <?php foreach (my_associations() as $a): ?>
      <form method="post" action="<?= h(url('/switch')) ?>"><?= csrf_field() ?><input type="hidden" name="association_id" value="<?= (int) $a['id'] ?>">
        <button<?= (int) $a['id'] === (int) $assoc['id'] ? ' class="current"' : '' ?>><?= h($a['name']) ?></button></form>
      <?php endforeach ?>
      <a href="<?= h(url('/welcome')) ?>"><?= h(t('nav.newAssociation')) ?></a>
      <hr>
      <form method="post" action="<?= h(url('/logout')) ?>"><?= csrf_field() ?><button><?= h(t('nav.logout')) ?></button></form>
    </div>
  </details>
</header>
<div class="shell">
<nav id="nav" aria-label="<?= h(t('nav.menu')) ?>">
  <form class="search nav-search" role="search" action="<?= h(url('/search')) ?>">
    <input type="search" name="q" placeholder="<?= h(t('nav.search')) ?>" aria-label="<?= h(t('common.search')) ?>"><button><?= h(t('common.search')) ?></button>
  </form>
<?php foreach ($nav as $group => $links): ?>
  <?php if ($group !== 'main'): ?><p><?= h(t("nav.sections.$group")) ?></p><?php endif ?>
  <ul>
  <?php foreach ($links as $key => $href): ?>
    <li><a href="<?= h(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= h(t("nav.$key")) ?></a></li>
  <?php endforeach ?>
  <?php if ($group === 'main'): ?>
    <li class="mobile-only"><a href="<?= h(url('/notifications')) ?>"><?= h(t('nav.notifications')) ?><?= $unread ? ' <b class="count">' . $unread . '</b>' : '' ?></a></li>
  <?php endif ?>
  </ul>
<?php endforeach ?>
</nav>
<main id="main">
<?php if ($flash): ?>
  <p class="flash <?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></p>
<?php endif ?>
<?= $content ?>
</main>
</div>
<?= template('footer') ?>
</body>
</html>
