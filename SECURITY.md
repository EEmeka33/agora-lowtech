# Security

Agora holds personal data (names, emails, phone numbers, membership fees). Low-tech does not mean careless:
fewer moving parts also means fewer places for problems to hide.

## What is protected, and how

| Risk | Protection | Where |
| --- | --- | --- |
| Stolen passwords | Argon2id hashes (bcrypt if PHP lacks Argon2), minimum 10 characters, common and personal passwords refused, rehash on sign-in | `app/lib/auth.php`, `password_issue()` in `app/lib/util.php` |
| Password guessing | Account locked 15 minutes after 5 failures; rate limits per IP and per email, stored in the database; same response time whether the email exists or not | `auth_login_submit()` |
| Stolen sessions or links | Random 256-bit tokens; only their SHA-256 is stored; cookies HttpOnly, SameSite=Lax, Secure over HTTPS; changing a password signs out other devices; devices can be signed out | `app/lib/auth.php` |
| Cross-site forms (CSRF) | A random token in every form, checked against a cookie; foreign `Origin` refused | `check_csrf()` in `app/lib/http.php` |
| Injected code (XSS) | Every value printed with `h()`; Content-Security-Policy `default-src 'none'; script-src 'self'` allows only Agora's own small script, never inline code; links typed by members only if `http(s)` | `app/lib/view.php`, `send_security_headers()` |
| SQL injection | Only prepared statements with `?` placeholders; table and column names never come from forms | `app/lib/db.php` |
| One association reading another's data | Every query filters on `association_id`; ids from forms checked with `assert_refs()`; tested end to end | `find()`, `assert_refs()` |
| Doing more than one's role allows | Every handler checks `ensure(can(...))`, whatever the page shows | `app/lib/permissions.php` |
| Uploaded files | Random file names, stored outside `public/`, served only to members of the association, as attachments unless harmless (images, PDF, text), in a sandbox (CSP `sandbox`) | `app/pages/documents.php` |
| Clickjacking, sniffing, leaks | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy: same-origin` | `send_security_headers()` |
| Spreadsheet formulas in exports | Cells starting with `= + - @` are neutralised in CSV files | `csv()` |
| Data kept forever | Old read notifications, activity, expired sessions and links are deleted automatically | `app/lib/maintenance.php` |
| Backups leaking secrets | The JSON export contains no password hash, session or token | `settings_backup()` |

Run the checks with `php tests/run.php`: the end-to-end test covers isolation, roles, CSRF, lockout and sessions.

## What the person who installs Agora must do

See the [security checklist](docs/self-hosting.md#security-checklist): HTTPS, a `data/` folder that cannot be
downloaded, `debug` off, backups, PHP updates.

## Reporting a vulnerability

Please do not open a public issue. Contact the maintainers privately (GitHub's *Report a vulnerability* in the
*Security* tab of the repository), with the steps to reproduce.
