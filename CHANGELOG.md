# Changelog

## 2.0.0-lowtech — 2026-11-26

A complete rewrite of [Agora](https://github.com/EEmeka33/agora) 1.0 as a low-tech web application.
The reasons for every change, and the measurements, are in [docs/low-tech.md](docs/low-tech.md).

**Same features**: founding checklist, structure and "who does what", poles, requests between poles,
projects (milestones, updates, progress, health, timeline), tasks (board and list, comments), events and
meetings (RSVP, agenda, minutes, calendar feed), votes, volunteering, finance and membership fees,
documents, members, roles, join codes, activation links, notifications, several associations per instance,
English and French.

**New**

- No dependency, no build step, no JavaScript: PHP 8.1+ and one SQLite file.
- Pages of 6–8 KB (2 requests) instead of 445–610 KB (about 60 requests); server CPU time per page ÷ 13.
- Daily email digest (default), plain-text emails.
- Big photos reduced on upload; old notifications and activity deleted after a year.
- Each page shows its own weight; "About" page with live numbers about the instance.
- Print stylesheet (minutes, finance, members).
- Works in text browsers and old browsers; dates can be typed as `05/12/2026 18:30` where there is no date picker.
- Command-line tools: `bin/seed.php` (a low-tech workshop association, in English or French), `bin/backup.php`,
  `bin/maintenance.php`, `bin/check.php`, `bin/create-admin.php`.
- Tests in plain PHP (unit and end-to-end, ~3 s), including a low-tech budget.
- Course material and exercises in `docs/low-tech.md`.

**Removed**: drag and drop (replaced by ◀ ▶ buttons), live notification polling, toasts, command palette
(replaced by a search page), icons, avatars, colours per pole and project, theme toggle (the device's
preference is used), Docker image, PostgreSQL option.
