# Changelog

All notable changes to Agora are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Low-tech edition

The same features and design, made lighter in ten measured steps. Details and numbers in
[docs/low-tech.md](docs/low-tech.md).

- Links prefetch a page when they are pointed at, touched or focused, not as soon as they are visible: the
  dashboard goes from 614 KB and 76 requests to 296 KB and 15 requests.
- The system font replaces the Inter web font (−48 KB per page).
- The finance charts are drawn on the server; Recharts is removed (finance page: 359 → 247 KB).
- The notification bell no longer polls every minute: it refreshes when the tab comes back into view.
- Only the dictionary of the page's language is sent (−10 KB per page).
- A small toast component replaces sonner (−8 KB per page).
- `npm start` runs the standalone server (88 MB instead of 1.2 GB of `node_modules`) with a memory cap
  (`AGORA_MAX_MEMORY_MB`, 192 MB of heap): about 325 MB under load instead of 790 MB or more, for 20 to 50 % more
  processor time per page.
- Uploaded photos are reduced to 1600 pixels without their metadata (a 5.3 MB test photo is stored as 0.2 MB).
- A daily cleanup removes read notifications after 90 days, the activity history after `RETENTION_DAYS` (365),
  expired sessions, old one-time links and orphan files.
- Emails: one summary a day by default, only with notifications not seen in Agora; members can choose each
  notification right away, or no emails (Settings → Notifications).
- Pages print cleanly: no navigation, buttons or background ink, light colours even from dark mode, meeting
  minutes in full.
- `tests/e2e/budget.spec.ts` fails if a page exceeds 260 KB, 210 KB of JavaScript or 20 requests, downloads a
  font, prefetches before a click, or if an open tab makes requests.

### Security

- Patched a high-severity ReDoS vulnerability in `effect`, a dependency of Prisma's configuration loader
  (GHSA-7589-6fvf-x4pw), by forcing version 3.22.5 until Prisma ships it.

### Changed

- Updated dependencies: React 19.3.1, ESLint 10, TypeScript 6.0, Playwright 1.65, Tailwind CSS 4.3.5, Vitest 5.1.2,
  lucide-react 1.80. `npm install` now reports 0 vulnerabilities.
- CI uses `actions/checkout` and `actions/setup-node` v7 (Node.js 20 runtime deprecation).
- Dependabot groups minor and patch updates, opens one pull request per major update, and skips TypeScript 7
  (not supported by the lint tooling yet) and Node.js type definitions newer than the Node.js version Agora runs on.

### Fixed

- Navigations inside a section (between projects, back to a list…) show the loading skeleton immediately.
- Hydration warnings on the projects timeline and on relative times.

## [1.0.0] – 2026-11-25

First public release.

### Founding and organization

- Founding checklist: 16 steps in 6 themes (purpose, statutes, constitutive general assembly, declaration,
  bank account, insurance…), assignable, with due dates and progress.
- Structure page: organization chart, "who does what" table of recurring duties, membership matrix.
- Poles (working groups) with leads, members, projects, tasks, requests and events.

### Working together

- **Requests**: ask a pole for help. The pole's members are notified, one of them takes it and it becomes their
  task; the requester is notified when it is taken and when it is done. Pole leads and managers can give a
  request to someone.
- Projects with lead, team, dates, budget, milestones, progress updates, automatic progress and health, cards and
  timeline views.
- Tasks: kanban board with drag and drop, list view, filters, comments.
- **Votes**: single or multiple choice, anonymous or not, deadline, optionally restricted to a pole.
- **Volunteering**: log time given to the association, totals per member and pole, estimated value, CSV export.
- Events and meetings: calendar, RSVP, agenda and minutes, private calendar feed (iCalendar) for Google,
  Apple and Outlook calendars.
- Announcements, activity feed, global search (Ctrl/⌘ K).

### Money and documents

- Income and expenses, monthly chart, project budgets, membership fees, CSV export.
- Documents: uploaded files and links, by category, served only to members.

### Accounts and associations

- Sign-up, sign-in, password reset by email or by an administrator's link.
- Create an association with a guided wizard, or join one with a **join code** limited in number of uses and
  time, optionally linked to a role and a pole.
- One instance can host several associations; members switch between theirs.
- Roles: administrator, manager, member. Pole and project leads get extra rights.
- Notification center, with optional emails (SMTP).
- Settings: profile, language (English / French), theme and accent color, security (password, active sessions),
  notification preferences.
- Instance settings: `SIGNUP_MODE` (open / invite / closed) and `ALLOW_NEW_ASSOCIATIONS`.

### Security

- Argon2id password hashing, NIST-style password rules, account lockout and rate limits.
- Database sessions with hashed tokens; hashed invitation, reset and calendar tokens.
- Automatic isolation between associations at the database client level, with tests.
- Content Security Policy and security headers, protected file downloads, safe CSV exports.

### Hosting

- Runs with Node.js (`npm run setup`) or Docker (`docker compose up -d`), SQLite database, no other service.
- `npm run db:backup` for consistent backups while running; JSON export of an association for administrators.
- Unit tests (Vitest), end-to-end tests (Playwright) and a GitHub Actions workflow.

> Builds shared before 1.0.0 used a different database schema. If you tried one, delete the `data/` folder and
> run `npm run setup` again.

[Unreleased]: https://github.com/EEmeka33/agora-lowtech
[1.0.0]: https://github.com/EEmeka33/agora/releases/tag/v1.0.0
