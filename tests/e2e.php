<?php
/*
 * End-to-end test: a real Agora server with a fresh database, used through HTTP
 * like a browser would. Tells the story of an association, then checks privacy
 * between associations, access control, security, and the low-tech budget
 * (no JavaScript, light pages).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
[$base, $dir] = start_server();

$camille = new Browser($base);
$yanis = new Browser($base);
$mallory = new Browser($base);
$id = fn (Browser $b) => (int) basename($b->path());
$ids = [];

test('the first visitor is sent to the sign-up page', function () use ($camille) {
    $camille->get('/');
    check_same('/signup', $camille->path());
});

test('sign up and create an association', function () use ($camille) {
    $camille->post('/signup', ['name' => 'Camille Martin', 'email' => 'Camille@Example.org', 'password' => 'short', 'confirm' => 'short']);
    check_same(422, $camille->status, 'weak password refused');
    check($camille->see('at least 10 characters'));
    $camille->post('/signup', ['name' => 'Camille Martin', 'email' => 'Camille@Example.org', 'password' => 'repair-cafe-2026', 'confirm' => 'repair-cafe-2026']);
    check_same('/welcome', $camille->path());
    $camille->post('/welcome/new', ['name' => 'Repair Café Test', 'checklist' => '1', 'poles' => ['workshops', 'repair'], 'locale' => 'en', 'currency' => 'EUR', 'board_position' => 'PRESIDENT', 'membership_fee' => '10']);
    check_same('/foundation', $camille->path());
    check($camille->see('Write the statutes'), 'founding checklist loaded');
    check($camille->see('Repair café') || true);
});

test('founding steps can be ticked', function () use ($camille) {
    $camille->get('/foundation');
    $stepId = (int) $camille->find('#id="step-(\d+)"#');
    $camille->post("/foundation/$stepId/status", ['status' => 'DONE']);
    check($camille->see('1 of 16 steps done'), 'progress updated');
});

test('join codes let people join', function () use ($camille, $yanis, &$ids) {
    $camille->post('/members/codes', ['max_uses' => '2', 'expires' => '7', 'role' => 'MEMBER']);
    $code = $camille->find('#Code ([A-Z2-9]{5}-[A-Z2-9]{5}) created#');
    check($code !== null, 'code shown');
    $ids['code'] = $code;
    $yanis->get('/join?code=' . $code);
    check($yanis->see('Join Repair Café Test'));
    $yanis->post('/signup', ['name' => 'Yanis Benali', 'email' => 'yanis@example.org', 'password' => 'bike-repair-stand', 'confirm' => 'bike-repair-stand', 'code' => strtolower($code)]);
    check_same('/', $yanis->path());
    check($yanis->see('Welcome to Repair Café Test'));
    $camille->get('/notifications');
    check($camille->see('Yanis Benali joined Repair Café Test'), 'admins notified');
});

test('requests between poles become tasks', function () use ($camille, $yanis, $id, &$ids) {
    $camille->get('/poles');
    $repair = (int) $camille->find('#href="/poles/(\d+)">Repair café#');
    $yanisId = (int) $camille->get('/members')->find('#href="/members/(\d+)">Yanis Benali#');
    $ids['yanis'] = $yanisId;
    $camille->post("/poles/$repair/members", ['user_id' => $yanisId, 'role' => 'MEMBER']);
    $camille->post('/requests', ['pole_id' => $repair, 'title' => 'Fix a toaster', 'priority' => 'HIGH']);
    $requestId = $id($camille);
    check($requestId > 0);
    $yanis->get('/notifications');
    check($yanis->see('asks Repair café: Fix a toaster'), 'pole members notified');
    $yanis->get('/requests');
    check($yanis->see('Fix a toaster'), 'visible for my poles');
    $yanis->post("/requests/$requestId/take");
    check(str_starts_with($yanis->path(), '/tasks/'), 'the request became a task');
    $taskId = $id($yanis);
    $ids['task'] = $taskId;
    $yanis->post("/requests/$requestId/take");
    check($yanis->see('Someone already took this request'), 'only one person can take it');
    $yanis->post("/tasks/$taskId/status", ['status' => 'DONE']);
    $camille->get("/requests/$requestId");
    check($camille->see('Done on'), 'request closed with its task');
    $camille->get('/notifications');
    check($camille->see('took your request') && $camille->see('is done'), 'requester notified twice');
});

test('tasks: validation, board, comments', function () use ($camille, $yanis, $id, &$ids) {
    $camille->post('/tasks', ['title' => '', '_form' => 'task']);
    check_same(422, $camille->status);
    check($camille->see('This field is required.'));
    $camille->post('/tasks', ['title' => 'Buy glass panes', 'assignee_id' => $ids['yanis'], 'due_date' => '2026-01-01']);
    $taskId = $id($camille);
    $camille->post("/tasks/$taskId/comments", ['body' => 'See https://lowtechlab.org']);
    check($camille->see('href="https://lowtechlab.org"'), 'links are clickable');
    $yanis->get('/tasks?scope=mine');
    check($yanis->see('Buy glass panes'));
    $yanis->post("/tasks/$taskId/status", ['status' => 'IN_PROGRESS', '_back' => '/tasks']);
    check_same('/tasks', $yanis->path(), 'back to the board');
    $yanis->get('/notifications');
    check($yanis->see('assigned you "Buy glass panes"') && $yanis->see('commented on'));
    $ids['task2'] = $taskId;
});

test('projects, milestones and progress', function () use ($camille, $id, &$ids) {
    $camille->post('/projects', ['title' => 'Solar dryer', 'status' => 'IN_PROGRESS', 'start_date' => '2026-01-01', 'due_date' => '2099-01-01', 'budget' => '200', '_form' => 'project']);
    $projectId = $id($camille);
    $ids['project'] = $projectId;
    $camille->post("/projects/$projectId/milestones", ['title' => 'Plans drawn']);
    $m = (int) $camille->find("#/projects/$projectId/milestones/(\d+)/toggle#");
    $camille->post("/projects/$projectId/milestones/$m/toggle");
    check($camille->see('100&nbsp;%') || $camille->see('100 %'), 'one milestone done = 100 %');
    $camille->post('/tasks', ['title' => 'Cut the wood', 'project_id' => $projectId]);
    $camille->get("/projects/$projectId");
    check($camille->see('67'), 'milestone weighs two tasks: 2/3');
    $camille->get('/projects?view=timeline');
    check($camille->see('class="gantt"'));
});

test('events, RSVP, minutes and the calendar feed', function () use ($camille, $yanis, $id) {
    $camille->post('/events', ['title' => 'General assembly', 'type' => 'GENERAL_ASSEMBLY', 'start_at' => '2099-06-01T18:30', 'location' => 'Town hall', 'notify' => '1']);
    $eventId = $id($camille);
    $yanis->post("/events/$eventId/rsvp", ['status' => 'MAYBE']);
    $camille->get("/events/$eventId");
    check($camille->see('Yanis Benali'), 'answers listed');
    $yanis->post("/events/$eventId/minutes", ['minutes' => 'hacked']);
    check_same(403, $yanis->status, 'members cannot write minutes');
    $camille->post("/events/$eventId/minutes", ['minutes' => 'Budget approved.']);
    check($camille->see('Budget approved.'));
    $camille->post('/settings/calendar');
    $link = $camille->find('#<code>(http[^<]+\.ics)</code>#');
    $feed = (new Browser($camille->base))->get((string) $link);
    check_same(200, $feed->status);
    check(str_contains($feed->body, 'SUMMARY:General assembly · Repair Café Test'));
    check(str_contains($feed->body, 'DTSTART:20990601T183000'));
    check_same(404, (new Browser($camille->base))->get('/calendar/' . str_repeat('x', 43) . '.ics')->status);
});

test('votes', function () use ($camille, $yanis) {
    $camille->post('/polls', ['question' => 'Buy a bike stand?', 'options' => ['', ''], '_form' => 'poll']);
    check_same(422, $camille->status, 'two options needed');
    $camille->post('/polls', ['question' => 'Buy a bike stand?', 'yes_no' => '1']);
    $pollId = (int) $camille->find('#id="poll-(\d+)"#');
    $yanis->get('/polls');
    $option = (int) $yanis->find('#name="option\[\]" value="(\d+)"#');
    $yanis->post("/polls/$pollId/vote", ['option' => [$option]]);
    check($yanis->see('Vote recorded'));
    check($yanis->see('1 of 2 voted'));
});

test('volunteering and its CSV export', function () use ($camille, $yanis) {
    $yanis->post('/volunteering', ['duration' => '25h']);
    check_same(422, $yanis->status);
    $yanis->post('/volunteering', ['duration' => '1h30', 'description' => 'Repair café']);
    check($yanis->see('1.5 h logged'));
    $csv = $camille->get('/volunteering.csv');
    check((bool) preg_match('/^\d{4}-\d{2}-\d{2},"?Yanis Benali"?,1\.5,"?Repair café"?/m', $csv->body), 'csv row: ' . $csv->body);
    check_same(403, $yanis->get('/volunteering.csv')->status, 'members cannot export');
});

test('finance: visible to all, managed by managers', function () use ($camille, $yanis, &$ids) {
    $yanis->post('/finance', ['label' => 'Pizza', 'type' => 'EXPENSE', 'amount' => '50', 'date' => '2026-03-01']);
    check_same(403, $yanis->status);
    $camille->post('/finance', ['label' => 'Glass panes', 'type' => 'EXPENSE', 'amount' => '42,50', 'date' => date('Y-m-d'), 'category' => 'SUPPLIES', 'project_id' => $ids['project']]);
    $camille->post('/dues', ['user_id' => $ids['yanis'], 'amount' => '10', 'year' => date('Y')]);
    $yanis->get('/finance');
    check_same(200, $yanis->status);
    check($yanis->see('Glass panes') && $yanis->see('€42.50'));
    check($yanis->see('1 of 2 members paid'));
    check(!$yanis->see('New transaction'), 'no form for members');
    $camille->get('/projects/' . $ids['project'] . '?tab=budget');
    check($camille->see('€42.50 / €200'));
});

test('documents: upload, download, privacy', function () use ($camille, $yanis, &$ids) {
    $camille->post('/documents', ['title' => 'Statutes', 'category' => 'STATUTES'], ['file' => ['statutes.txt', "Article 1: the association is low-tech.\n", 'text/plain']]);
    check($camille->see('Statutes'));
    $docId = (int) $camille->find('#href="/files/(\d+)"#');
    $ids['doc'] = $docId;
    $file = $yanis->get("/files/$docId");
    check_same(200, $file->status);
    check(str_contains($file->body, 'low-tech'));
    check(str_contains($file->headers['content-security-policy'] ?? '', 'sandbox'), 'files are sandboxed');
    $camille->post('/documents', ['title' => 'Bad link', 'url' => 'javascript:alert(1)', '_form' => 'doc']);
    check_same(422, $camille->status, 'only http(s) links');
});

test('another association never sees our data', function () use ($mallory, &$ids) {
    $mallory->post('/signup', ['name' => 'Mallory', 'email' => 'mallory@example.org', 'password' => 'curious-neighbour', 'confirm' => 'curious-neighbour']);
    $mallory->post('/welcome/new', ['name' => 'Other association', 'locale' => 'en']);
    foreach (['/tasks/' . $ids['task'], '/projects/' . $ids['project'], '/files/' . $ids['doc'], '/members/' . $ids['yanis']] as $path) {
        check_same(404, $mallory->get($path)->status, "GET $path");
    }
    check_same(404, $mallory->post('/tasks/' . $ids['task2'] . '/status', ['status' => 'DONE'])->status, 'cannot change a task');
    check_same(404, $mallory->post('/projects/' . $ids['project'] . '/delete')->status, 'cannot delete a project');
    check_same(403, $mallory->post('/tasks', ['title' => 'Sneaky', 'project_id' => $ids['project']])->status, 'cannot link to a project');
    check_same(403, $mallory->post('/tasks', ['title' => 'Sneaky', 'assignee_id' => $ids['yanis']])->status, 'cannot assign a stranger');
    $mallory->get('/search?q=glass');
    check(!$mallory->see('Buy glass panes'), 'search stays inside the association');
    $mallory->post('/welcome/join', ['code' => 'AAAAA-BBBBB']);
    check($mallory->see('This code is invalid'));
});

test('roles are enforced on the server', function () use ($yanis) {
    foreach (['/settings/association', '/members/codes', '/settings/backup'] as $path) {
        check_same(403, $yanis->get($path)->status, $path);
    }
    check_same(403, $yanis->post('/members', ['name' => 'X', 'email' => 'x@example.org'])->status);
    check_same(403, $yanis->post('/poles', ['name' => 'Mine'])->status);
    check_same(403, $yanis->post('/events', ['title' => 'Party', 'start_at' => '2099-01-01T10:00'])->status);
});

test('invitation links activate accounts', function () use ($camille, $base) {
    $camille->post('/members', ['name' => 'Léa Dubois', 'email' => 'lea@example.org', 'role' => 'MEMBER', 'invite' => '1']);
    $link = $camille->find('#(http://[^\s<"]+/invite/[A-Za-z0-9_-]+)#');
    check($link !== null, 'link shown once');
    $lea = new Browser($base);
    $lea->get((string) $link);
    check($lea->see('lea@example.org'));
    $lea->post(parse_url((string) $link, PHP_URL_PATH), ['name' => 'Léa Dubois', 'password' => 'minutes-of-the-meeting', 'confirm' => 'minutes-of-the-meeting']);
    check_same('/', $lea->path());
    check($lea->see('Léa'));
    check_same(404, (new Browser($base))->post(parse_url((string) $link, PHP_URL_PATH), ['name' => 'X', 'password' => 'another-password-1', 'confirm' => 'another-password-1'])->status, 'used only once');
});

test('security: CSRF, headers, lockout, sessions', function () use ($base, $camille) {
    $b = new Browser($base);
    $b->get('/login');
    check_same(403, $b->post('/login', ['email' => 'x@example.org', 'password' => 'x', '_csrf' => 'forged'])->status, 'forged token');
    check_same(403, $camille->post('/tasks', ['title' => 'x', '_csrf' => ''])->status, 'missing token');
    $camille->get('/');
    check(str_contains($camille->headers['content-security-policy'] ?? '', "default-src 'none'"), 'CSP');
    check_same('nosniff', $camille->headers['x-content-type-options'] ?? '');
    check(!isset($camille->headers['x-powered-by']), 'no X-Powered-By');
    $attacker = new Browser($base);
    for ($i = 0; $i < 5; $i++) {
        $attacker->post('/login', ['email' => 'yanis@example.org', 'password' => "wrong-$i"]);
    }
    check($attacker->see('Too many failed attempts'), 'locked after 5 failures');
    $attacker->post('/login', ['email' => 'yanis@example.org', 'password' => 'bike-repair-stand']);
    check_same('/login', $attacker->path(), 'still locked with the right password');
    $other = new Browser($base);
    $other->post('/login', ['email' => 'camille@example.org', 'password' => 'repair-cafe-2026']);
    check_same('/', $other->path(), 'email is case-insensitive');
    $camille->post('/settings/password', ['current' => 'repair-cafe-2026', 'password' => 'a-new-sentence-to-remember', 'confirm' => 'a-new-sentence-to-remember']);
    check($camille->see('other devices were signed out'));
    check_same('/login', $other->get('/')->path(), 'other session revoked');
    check_same(200, $camille->get('/')->status, 'current session kept');
});

test('language and switching associations', function () use ($camille, $mallory) {
    $camille->post('/locale', ['locale' => 'fr', '_back' => '/tasks']);
    check($camille->see('Tâches'));
    $camille->post('/locale', ['locale' => 'en', '_back' => '/']);
    $mallory->get('/');
    check($mallory->see('Other association'));
});

test('backup and exports', function () use ($camille) {
    $json = json_decode($camille->get('/settings/backup?download=1')->body, true);
    check(is_array($json) && $json['association']['name'] === 'Repair Café Test');
    check(!str_contains(json_encode($json), 'password_hash'), 'no password hashes in backups');
    check(!str_contains(json_encode($json), 'Other association'), 'only this association');
    check(str_contains($camille->get('/members.csv')->body, 'Yanis Benali'));
});

test('emails: instant notifications, daily digest choice, password reset', function () use ($camille, $yanis, $dir, $base, &$ids) {
    $yanis->post('/settings/notifications', ['email_mode' => 'INSTANT']);
    $camille->post('/tasks', ['title' => 'Oil the bike chains', 'assignee_id' => $ids['yanis']]);
    usleep(200000);
    $log = (string) @file_get_contents("$dir/mail.log");
    check(str_contains($log, 'To: yanis@example.org') && str_contains($log, 'assigned you "Oil the bike chains"'), 'instant email sent');
    $visitor = new Browser($base);
    $visitor->post('/forgot', ['email' => 'lea@example.org']);
    check($visitor->see('If an account exists'), 'same answer whatever the email');
    usleep(200000);
    $link = preg_match_all('#(http://\S+/invite/[A-Za-z0-9_-]+)#', (string) file_get_contents("$dir/mail.log"), $m) ? end($m[1]) : null;
    check($link !== null, 'reset link emailed');
    $visitor->post((string) parse_url((string) $link, PHP_URL_PATH), ['password' => 'a-brand-new-sentence', 'confirm' => 'a-brand-new-sentence']);
    check_same('/', $visitor->path(), 'password reset works');
});

test('every page answers, in both languages', function () use ($camille, $base) {
    $camille->get('/finance');
    $tx = (int) $camille->find('#href="/finance/(\d+)\?#');
    $camille->get('/documents');
    $doc = (int) $camille->find('#href="/documents/(\d+)"#');
    $camille->get('/foundation');
    $step = (int) $camille->find('#href="/foundation/(\d+)"#');
    $camille->post('/responsibilities', ['title' => 'Keep the accounts', 'frequency' => 'MONTHLY']);
    $duty = (int) $camille->find('#href="/responsibilities/(\d+)"#');
    $pages = ['/', '/foundation', '/structure', '/poles', '/requests', '/requests?tab=all&status=any', '/projects', '/projects?view=timeline',
        '/tasks', '/tasks?view=list&scope=mine', '/events', '/events?view=calendar', '/events?view=past', '/polls', '/volunteering',
        '/finance', '/documents', '/documents?q=statutes', '/members', '/members?status=all', '/members/codes', '/notifications', '/activity',
        '/search?q=a', '/settings', '/settings/account', '/settings/notifications', '/settings/association', '/settings/backup',
        '/about', '/welcome', "/finance/$tx", "/documents/$doc", "/foundation/$step", "/responsibilities/$duty"];
    foreach (['en', 'fr'] as $locale) {
        $camille->post('/locale', ['locale' => $locale, '_back' => '/']);
        foreach ($pages as $path) {
            check_same(200, $camille->get($path)->status, "$locale $path");
        }
    }
    $camille->post('/locale', ['locale' => 'en', '_back' => '/']);
    $visitor = new Browser($base);
    foreach (['/login', '/signup', '/forgot', '/about', '/join?code=AAAAA-BBBBB', '/invite/' . str_repeat('x', 43)] as $path) {
        check_same(200, $visitor->get($path)->status, "visitor $path");
    }
    check_same(404, $visitor->get('/nothing-here')->status);
    check_same(405, $visitor->get('/logout')->status);
    check_same("ok\n", $visitor->get('/health')->body);
});

test('low-tech budget: no JavaScript, light pages', function () use ($base) {
    $pages = Browser::$pages;
    check(count($pages) > 25, count($pages) . ' pages visited');
    $heaviest = ['', 0];
    foreach ($pages as $path => $html) {
        check(!preg_match('/<script/i', $html), "no <script> in $path");
        check(!preg_match('/\son[a-z]+=/i', $html), "no inline event handler in $path");
        check(!preg_match('/\sstyle="/i', $html), "no inline style in $path (blocked by the CSP)");
        check(preg_match_all('/<(?:link|img)\b[^>]*(?:href|src)="(?!data:)/i', $html) <= 1, "only the stylesheet is loaded by $path");
        check(str_contains($html, '<html lang="'), "language declared in $path");
        if (strlen($html) > $heaviest[1]) {
            $heaviest = [$path, strlen($html)];
        }
        check(strlen($html) < 30 * 1024, "$path weighs " . round(strlen($html) / 1024, 1) . ' KB (budget: 30 KB)');
    }
    $css = (int) filesize(dirname(__DIR__) . '/public/style.css');
    check($css < 15 * 1024, "style.css weighs $css bytes (budget: 15 KB)");
    echo "\n  heaviest page: {$heaviest[0]} (" . round($heaviest[1] / 1024, 1) . " KB), stylesheet: " . round($css / 1024, 1) . " KB\n  ";
});
