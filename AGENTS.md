# Notes for contributors (humans and AI agents)

Read `docs/architecture.md` first. The essentials:

- **Stack**: PHP 8.1+ and SQLite through PDO. **No dependencies, no build step.** Do not add a framework or a
  package manager.
- **JavaScript is optional**: everything must work without it. `public/app.js` (under 3 KB, no library) only adds
  comfort: confirmations, instant filters, closing menus. No other script, no inline script: the CSP and the
  low-tech budget test enforce it.
- **Addresses** are listed in `app/routes.php` (`'GET /tasks/{id}' => 'tasks_show'`); the handler
  `tasks_show()` lives in `app/pages/tasks.php`; templates in `app/views/`.
- **Every query of association data filters on `association_id`**: use `find()` / `find_or_404()` and `aid()`.
  A new table that belongs to an association goes into `TENANT_TABLES` (`app/lib/db.php`). Check ids that come
  from forms with `assert_refs()`.
- **Permissions**: `ensure(can('...', $user, $row))` in every handler that changes something
  (`app/lib/permissions.php`).
- **Forms**: POST, then `redirect()` (or `back()`); on invalid input `fail_form($errors, fn () => page())`.
  Use the helpers of `app/lib/view.php`. No inline `style=""`, no `on*=""` attributes (blocked by the CSP).
  Irreversible actions use `confirm_form()` and an address ending in `/delete`, `/remove`, `/revoke`, `/cancel`
  or `/release`: the router asks for confirmation when JavaScript is off.
- **Layout**: check new pages at phone width (360 px) and on a large screen; nothing may be wider than the window.
- **Texts**: every string in `app/lang/en.php` **and** `app/lang/fr.php`; `t()`, `tn()` for plurals,
  `enum_label()` for enumerated values.
- **Escape** every printed value with `h()` (or `text_html()` for text typed by members).
- **Dates** typed by people are stored as typed (`2026-03-04 18:30`); server instants are UTC (`now_utc()`).
  **Money** in cents.
- **Schema changes**: a new numbered file in `app/migrations/` (never edit `schema.sql` once in use).
- **Checks**: `php tests/run.php` (about 3 s) must pass; `find . -name '*.php' -exec php -l {} \;` for syntax.
- Keep pages under 30 KB, the stylesheet under 25 KB and the script under 3 KB. If a list page grows, move
  per-item forms to their own page.
