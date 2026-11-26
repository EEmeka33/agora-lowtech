# Architecture

How Agora low-tech works, for anyone who wants to understand or change it. You need to know a bit of HTML,
SQL and PHP; nothing else.

- [Folders](#folders)
- [How a request flows](#how-a-request-flows)
- [Pages and forms without JavaScript](#pages-and-forms-without-javascript)
- [Accounts, sessions and associations](#accounts-sessions-and-associations)
- [Isolation between associations](#isolation-between-associations)
- [Permissions](#permissions)
- [Notifications and email](#notifications-and-email)
- [Translations](#translations)
- [Conventions](#conventions)
- [Changing the database](#changing-the-database)
- [Recipe: adding a feature](#recipe-adding-a-feature)
- [Tests](#tests)

## Folders

```text
public/              The only folder served on the web
  index.php          Single entry point (front controller)
  style.css          The only stylesheet
app/
  boot.php           Loads the configuration and every library file
  routes.php         Every address of the site, in one list
  schema.sql         The whole data model, with comments
  migrations/        Later changes to the database (002_*.sql, 003_*.sql…), applied automatically
  foundation-template.php   The 16 recommended founding steps, in English and French
  lib/               Plain functions, grouped by topic (read them in the order of boot.php)
    db.php           SQLite, query helpers, isolation between associations
    http.php         Routing, reading forms, redirects, cookies, CSRF protection, security headers
    auth.php         Passwords, sessions, rate limiting
    context.php      The current association and the person's role in it
    permissions.php  Who can do what
    view.php         Rendering templates, form helpers
    i18n.php         Translations
    notify.php, mail.php, progress.php, maintenance.php, util.php, constants.php
  pages/             One file per section: the functions named in routes.php
  views/             The HTML templates, one folder per section
  lang/              en.php, fr.php: every text of the interface
bin/                 Command-line scripts: seed, backup, maintenance, check, create-admin
tests/               run.php, unit.php, e2e.php and a tiny test toolkit (lib.php)
data/                The database and uploaded files (never in git, never served)
docs/                This documentation, server configuration examples, screenshots
```

There is no framework, no class to inherit from, no autoloader, no dependency injection: functions call
functions. Everything a page does can be followed by reading from `routes.php` to the template.

## How a request flows

1. The web server sends every address to `public/index.php`, which loads `app/boot.php` and calls `run_app()`.
2. `run_app()` (`app/lib/http.php`) finds the address in `app/routes.php`:
   ```php
   'GET /tasks/{id}' => 'tasks_show',
   ```
   The handler `tasks_show` lives in `app/pages/tasks.php` (the file is the first word of the name), and
   receives the id as a number. Every POST is first checked against cross-site forgery (`check_csrf()`).
3. The handler checks who is asking (`require_member()`), reads the database, and calls
   `render('tasks/show', [...], $title)`.
4. `render()` fills the template `app/views/tasks/show.php`, puts it inside `app/views/layout.php`, then
   `send_html()` writes the weight of the page in the footer, compresses it, adds an ETag and sends it.
5. After the page is sent, `after_response()` sends the queued emails and, at most once an hour, runs the
   maintenance (digests, cleanup).

## Pages and forms without JavaScript

**Showing**: a GET handler loads data and renders a template. Templates are HTML with
`<?= h($value) ?>` wherever a value is printed: `h()` escapes it, so text typed by members is never run as code.

**Changing**: every change is a `<form method="post">`. The handler validates, writes, and **redirects**
(`redirect('/tasks/12', t('common.saved'))`): this is the Post/Redirect/Get pattern. The message is shown on the
next page (it travels in a cookie that lives one minute).

**When a form is invalid**, the handler calls `fail_form($errors, fn () => tasks_show($id))`: the same page is
rendered again with status 422, the errors next to the fields and what the person typed (`old('title')`).

**Helpers** (`app/lib/view.php`):

| Helper | Gives |
| --- | --- |
| `input_field('title', t('common.title'), $value, ['required' => true])` | `<label>` + `<input>` + error |
| `select_field('pole_id', $label, pole_options(), $selected, ['empty' => '—'])` | `<label>` + `<select>` |
| `textarea_field()`, `checkbox_field()`, `checkboxes_field()` | the same for other fields |
| `button_form('/tasks/12/status', 'Done', ['status' => 'DONE'])` | a one-button form (one click actions) |
| `confirm_form('/tasks/12/delete', 'Delete')` | a `<details>` that reveals the real "Yes, delete" button |
| `csrf_field()`, `back_field()` | the hidden anti-forgery token; the page to come back to (`back()`) |
| `status_badge()`, `progress_html()`, `due_html()`, `person()`, `text_bar()` | small pieces used everywhere |

**Bigger forms** (create a task, an event…) sit in a `<details class="panel">`: closed by default, open after
an error. Editing an item that appears in a long list has its own page (`GET /finance/{id}`), so that lists
stay light.

## Accounts, sessions and associations

- A **user** is a global account (email, password hash, profile). A person belongs to associations through
  **members** rows, which hold their role (ADMIN, MANAGER, MEMBER), board position and status.
- **Sessions** are rows of the `sessions` table. The browser keeps a random 256-bit token in the `agora_session`
  cookie (HttpOnly, SameSite=Lax); the database stores only its SHA-256. Sessions last 30 days and are extended
  while used. Changing a password signs out the other devices.
- The association being viewed is in the `agora_asso` cookie. It is only a preference: `current_association()`
  checks that the person is an active member, and falls back to their first association.
- **Join codes** have a number of uses, an optional expiry and a role. A use is consumed with a conditional
  `UPDATE … WHERE uses < max_uses`, so two people can't take the last place at once.
- **Activation and reset links** contain a random token; only its hash is stored; they work once.

## Isolation between associations

One Agora can host several associations. Every table that belongs to an association has an `association_id`
column (listed in `TENANT_TABLES`, `app/lib/db.php`) and **every query filters on it**:

```php
$task = find('tasks', $id);            // SELECT * FROM tasks WHERE id = ? AND association_id = ?
$task = find_or_404('tasks', $id);     // same, "404 Not found" if missing
rows('SELECT … FROM tasks WHERE association_id = ? AND …', [aid(), …]);
```

`aid()` is the id of the current association. Rows of child tables (comments, votes, milestones…) are reached
through their parent, after checking the parent with `find()`.

Ids **sent by a form** (the project of a new task, the person a task is assigned to) are checked with
`assert_refs(['project_id' => …, 'user_ids' => […]])`, which answers "403 Forbidden" if one belongs to
another association.

`tests/e2e.php` creates two associations and checks that the second one gets 404 or 403 everywhere.

## Permissions

All rules are in `app/lib/permissions.php`:

```php
can('manageFinance', $user)                     // administrators and managers
can('editTask', $user, $task)                   // managers, the creator, the assignee, the project lead
can('assignRequest', $user, ['pole_lead_ids' => […]])
```

Templates use them to show or hide buttons; handlers check them again with `ensure(can(…))`.
**Hiding a button is never the protection.**

## Notifications and email

`notify($userIds, 'request.new', ['title' => …, 'pole' => …], '/requests/12')` (`app/lib/notify.php`) stores
one row per recipient, never for the person who acted. The text is not stored: it is rebuilt from
`notifications.types.request.new` in each reader's language. People who chose INSTANT emails get one now;
DAILY people get a digest from `send_digests()`.

Emails are plain text, queued during the request and sent after the page (`app/lib/mail.php`). Transports:
`log` (file), `mail` (PHP's `mail()`), `smtp` (a 40-line SMTP client with STARTTLS).

## Translations

Every text lives in `app/lang/en.php` and `app/lang/fr.php` as `'key' => 'text'`:

```php
t('tasks.title')                               // "Tasks"
t('requests.askTitle', ['pole' => 'Workshops'])  // "Ask Workshops"
tn('common.countTasks', 3)                     // "3 tasks"   ("{n} task|{n} tasks")
enum_label('taskStatus', 'DONE')               // "Done"     (enums.taskStatus.DONE)
```

The tests fail if a key is used in the code but missing, or if the two files don't have the same keys and
placeholders. To add a language: copy `fr.php`, translate it, add its code to `LOCALES` in
`app/lib/constants.php` and a name under `enums.locale.*` in every file.

## Conventions

- **Dates typed by people** (events, due dates) are stored as typed, in local time: `2026-03-04` or
  `2026-03-04 18:30`. **Instants written by the server** (`created_at`…) are UTC: `2026-03-04 17:30:00`,
  shown in the configured time zone.
- **Money** is stored in cents (`amount_cents`).
- **Enumerated values** are UPPERCASE text, listed in `app/lib/constants.php`.
- **No inline styles and no scripts**: the Content-Security-Policy blocks them, and the tests check it.
- Comments explain *why*, in plain English.

## Changing the database

Never edit `app/schema.sql` once Agora is in use: it is applied only to empty databases. Add a numbered file
in `app/migrations/`, for example `app/migrations/002_tools.sql`. On the next request, `migrate()` applies the
files whose number is above `PRAGMA user_version`, in order, each in a transaction.

## Recipe: adding a feature

Example: a **tool library**, so members can see which tools the association lends and who has them.

1. **Data**: `app/migrations/002_tools.sql`
   ```sql
   CREATE TABLE tools (
     id             INTEGER PRIMARY KEY,
     association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
     name           TEXT NOT NULL,
     borrower_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
     borrowed_at    TEXT,
     created_at     TEXT NOT NULL
   );
   ```
   Add `'tools'` to `TENANT_TABLES` in `app/lib/db.php`.
2. **Addresses**: in `app/routes.php`
   ```php
   'GET /tools' => 'tools_list',
   'POST /tools' => 'tools_create',
   'POST /tools/{id}/lend' => 'tools_lend',
   ```
3. **Handlers**: `app/pages/tools.php`
   ```php
   <?php
   function tools_list(): void
   {
       $user = require_member();
       $tools = rows('SELECT t.*, u.name AS borrower FROM tools t LEFT JOIN users u ON u.id = t.borrower_id
                      WHERE t.association_id = ? ORDER BY t.name', [aid()]);
       render('tools/list', ['user' => $user, 'tools' => $tools], t('tools.title'));
   }

   function tools_lend(int $id): void
   {
       $user = require_member();
       $tool = find_or_404('tools', $id);
       update('tools', ['borrower_id' => $tool['borrower_id'] ? null : $user['id'], 'borrowed_at' => now_wall()], 'id = ?', [$id]);
       redirect('/tools', t('common.saved'));
   }
   ```
4. **Template**: `app/views/tools/list.php`, with `page_head()`, a `<table>`, and `button_form()` for "Borrow" /
   "Give back".
5. **Texts**: add `tools.title` and the others to **both** `app/lang/en.php` and `app/lang/fr.php`, and
   `nav.tools` for the menu (`$nav` at the top of `app/views/layout.php`).
6. **Tests**: add a story to `tests/e2e.php`, then run `php tests/run.php`. The low-tech budget checks your
   new page too.

## Tests

```bash
php tests/run.php        # everything (about 3 seconds)
php tests/run.php unit   # unit tests only
```

- `tests/unit.php`: dates, money, durations, passwords, permissions, progress and health, escaping, and the
  dictionaries (same keys in every language, no missing key).
- `tests/e2e.php`: starts a real Agora with PHP's built-in server on a temporary database and plays the life
  of an association through HTTP, like a browser: sign-up, founding, join codes, requests between poles,
  tasks, projects, events and the calendar feed, votes, volunteering, finance, documents, privacy between
  associations, roles, CSRF, account lockout, sessions, backups. It ends with the **low-tech budget**: every
  page visited must have no script, no inline style or event handler, no external resource but the
  stylesheet, and weigh less than 30 KB; the stylesheet less than 15 KB.
- `tests/lib.php`: the toolkit, ~150 lines: `test()`, `check()`, and a `Browser` class (cookies, forms, file
  uploads, redirects) built on PHP streams. Set `KEEP_TEST_DIR=1` to keep the test database for inspection.
