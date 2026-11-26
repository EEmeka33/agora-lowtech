<div align="center">

# Agora, low-tech edition

**The self-hosted workspace to found and run an association, made light.**

Set up the organization, see who does what, ask other poles for help, follow projects and their progress,
decide together, and keep members, events, money and documents in one place. In English and French.

![Dashboard](docs/screenshots/dashboard.webp)

</div>

> This is the **low-tech edition** of [Agora](https://github.com/EEmeka33/agora): the same features and the same
> design, with pages 2.7 times lighter, no background traffic, less data kept and a server that fits on a small
> machine. Every change was measured before and after: the process, the numbers and course material are in
> **[docs/low-tech.md](docs/low-tech.md)**.

## Low-tech edition

|                                        | Original             | Low-tech edition                  |
| -------------------------------------- | -------------------- | --------------------------------- |
| Dashboard, empty cache                 | 614 KB, 76 requests  | **230 KB, 15 requests**           |
| A page with the cache warm             | 76–99 KB             | **20–24 KB**                      |
| Requests from a tab left open, per day | 1,440                | **0**                             |
| Disk needed to run the server          | 1.2 GB               | **88 MB**                         |
| Server memory after 600 requests       | 790 MB               | **~325 MB** (for +20–50 % of CPU) |
| A 12-megapixel photo, stored           | 5.3 MB               | **0.2 MB**                        |
| Emails                                 | one per notification | **at most one summary a day**     |

What changed, in ten measured steps (one commit each):

1. Pages are prefetched when you point at a link, not as soon as it is visible.
2. The system font of each device instead of a downloaded web font.
3. Finance charts drawn on the server instead of with a chart library.
4. No polling: the notification bell refreshes when you come back to the tab.
5. Only the texts of your language are sent.
6. A small toast component instead of a library.
7. A small standalone server with a memory cap.
8. Photos reduced on upload, old notifications and history cleaned up, one email summary a day.
9. Pages that print cleanly (minutes, accounts, task lists), even from dark mode.
10. A budget test that fails if a change makes pages heavier or brings background traffic back.

A first attempt, a complete rewrite in plain PHP without JavaScript, was 20 times lighter but much less pleasant
to use; it is kept on the `php-rewrite` branch for comparison (see section 2 of the course material).

---

## Why Agora?

Associations usually juggle several tools: a membership tool, a spreadsheet for money, a group chat for
"who can do this?", a shared drive for the statutes… Agora puts the everyday life of an association in
one place, and runs on your own computer or server.

| Existing tool                                                               | Good at                         | What Agora adds                                                |
| --------------------------------------------------------------------------- | ------------------------------- | -------------------------------------------------------------- |
| [Paheko](https://paheko.cloud), [Galette](https://galette.eu) (open source) | Members, accounting             | Founding guide, poles, projects, requests between poles, votes |
| [Admidio](https://www.admidio.de) (open source)                             | Members, roles, events          | Projects and tasks, requests, finance, founding guide          |
| AssoConnect, HelloAsso, Yapla (paid / hosted)                               | Payments, memberships, websites | Self-hosted, free, project management                          |
| Holaspirit, GlassFrog                                                       | Roles and responsibilities      | Built for associations, everything else included               |
| Notion, Trello, ClickUp                                                     | Flexible project management     | Ready-made for associations, nothing to build yourself         |

## Features

|                             |                                                                                                                                                                                                                                      |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 🚀 **Founding checklist**   | 16 steps from the purpose to the bank account (statutes, constitutive general assembly, declaration, insurance…), assignable, with progress per theme (legal, governance, finance…).                                                 |
| 🏛️ **Structure**            | Live organization chart (association → board → poles), **who does what** table of recurring duties, membership matrix.                                                                                                               |
| 🧩 **Poles**                | Working groups with leads and members, their projects, tasks, requests and events.                                                                                                                                                   |
| 📨 **Requests**             | Ask a pole for help: **its members are notified**, one of them clicks **Take it** and the request becomes their task. The requester is notified when it is taken and when it is done. Pole leads can also give a request to someone. |
| 📁 **Projects**             | Lead, team, dates, budget, milestones, progress updates, automatic **progress** and **health** (on track / at risk / off track), cards and **timeline** views.                                                                       |
| ✅ **Tasks**                | Kanban board with drag and drop, lists, filters, comments, due dates.                                                                                                                                                                |
| 🗳️ **Votes**                | Polls for decisions: single or multiple choice, anonymous or not, deadline, restricted to a pole if needed.                                                                                                                          |
| ⏱️ **Volunteering**         | Members log the time they give; totals per member and pole, estimated value, CSV export (useful for grant applications).                                                                                                             |
| 📅 **Events & meetings**    | General assemblies, board and pole meetings, workshops. Calendar, RSVP, agenda, minutes, **calendar feed** for Google/Apple/Outlook.                                                                                                 |
| 💶 **Finance**              | Income and expenses, monthly chart, project budgets, **membership fees**, CSV export.                                                                                                                                                |
| 📄 **Documents**            | Files and links (statutes, minutes, guides…), by category.                                                                                                                                                                           |
| 👥 **Members & accounts**   | Anyone can create an account, create an association or **join one with a code**. Codes have a **limited number of uses** and an expiry date. Roles: administrator, manager, member.                                                  |
| 🔔 **Notifications**        | In-app notification center, plus one email summary a day (or each notification) when SMTP is configured.                                                                                                                             |
| 🏢 **Several associations** | One Agora can host several associations; each person switches between theirs, and data never leaks from one to another.                                                                                                              |
| 🔐 **Security**             | Argon2id passwords, revocable sessions, account lockout, hashed tokens, security headers. See [SECURITY.md](SECURITY.md).                                                                                                            |

<table>
  <tr>
    <td><img src="docs/screenshots/requests.webp" alt="Requests between poles"></td>
    <td><img src="docs/screenshots/founding.webp" alt="Founding checklist"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/structure.webp" alt="Structure"></td>
    <td><img src="docs/screenshots/project.webp" alt="Project"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/polls.webp" alt="Votes"></td>
    <td><img src="docs/screenshots/volunteering.webp" alt="Volunteering"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/tasks-board.webp" alt="Task board"></td>
    <td><img src="docs/screenshots/join-codes.webp" alt="Join codes"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/requests-dark.webp" alt="Dark mode"></td>
    <td><img src="docs/screenshots/founding-fr.webp" alt="In French"></td>
  </tr>
</table>

<p align="center">
  <img src="docs/screenshots/mobile-dashboard.webp" alt="Mobile dashboard" width="220">
  <img src="docs/screenshots/mobile-requests.webp" alt="Mobile requests" width="220">
  <img src="docs/screenshots/mobile-menu.webp" alt="Mobile menu" width="220">
</p>

---

## Quick start

### With Node.js (to try it and to modify the code)

Requirements: [Node.js](https://nodejs.org) **20.19 or newer** (22 LTS recommended).

```bash
git clone https://github.com/EEmeka33/agora-lowtech.git && cd agora-lowtech
```

```bash
npm install
```

```bash
npm run setup
```

```bash
npm run dev
```

Open http://localhost:3000, **create your account**, then **create your association**: a wizard prepares the
founding checklist and your first poles. Invite the others from **Members → Invite people** with a join code.

Want to look around first? Load the demo:

```bash
npm run db:seed
```

Sign in with `camille@greencampus.example` / `agora-demo-2026` (administrator, member of two associations).
Other demo accounts: `yanis@`, `lea@`, `thomas@`, `ines@`, `lucas@`, `sarah@greencampus.example` (same password).
You can also create a new account with the join code `GREEN-CAMPS`.
To start over: `npm run db:seed -- --reset` (⚠️ deletes everything).

### With Docker (to keep it running)

```bash
docker compose up -d --build
```

Open http://localhost:3000. Data lives in the `agora-data` volume and migrations run at each start.
See the [self-hosting guide](docs/self-hosting.md) for HTTPS, email, backups and updates.

---

## Documentation

|                                                         |                                                                       |
| ------------------------------------------------------- | --------------------------------------------------------------------- |
| 🌱 [Low-tech](docs/low-tech.md)                         | The process, the measurements, the trade-offs, and course material    |
| 📘 [User guide](docs/user-guide.md)                     | How each part of Agora works, for members and administrators          |
| 🖥️ [Self-hosting](docs/self-hosting.md)                 | Install, configure (sign-up policy, email, HTTPS), back up and update |
| 🧱 [Architecture](docs/architecture.md)                 | How the code is organized, multi-association isolation, sessions      |
| 🛠️ [Contributing](CONTRIBUTING.md)                      | Development workflow, checks, recipes to add features                 |
| 🔐 [Security](SECURITY.md)                              | What is protected and how, reporting a vulnerability                  |
| 🚢 [Publishing to GitHub](docs/publishing-to-github.md) | First push, CI, releases                                              |
| 📝 [Changelog](CHANGELOG.md)                            | What changed in each version                                          |

## Configuration at a glance

Settings live in `.env` (created by `npm run setup` from [.env.example](.env.example)):

| Variable                 | Default                | Description                                         |
| ------------------------ | ---------------------- | --------------------------------------------------- |
| `DATABASE_URL`           | `file:./data/agora.db` | SQLite database file                                |
| `UPLOAD_DIR`             | `./data/uploads`       | Where uploaded documents are stored                 |
| `SIGNUP_MODE`            | `open`                 | `open`, `invite` (join code required) or `closed`   |
| `ALLOW_NEW_ASSOCIATIONS` | `true`                 | `false` = only the first association can be created |
| `SMTP_URL` / `MAIL_FROM` | —                      | Optional email (notifications, password reset)      |
| `APP_URL`                | auto                   | Public address, used in emails and links            |
| `SECURE_COOKIES`         | `false`                | `true` behind HTTPS                                 |
| `MAX_UPLOAD_MB`          | `20`                   | Maximum upload size                                 |
| `RETENTION_DAYS`         | `365`                  | How long the activity history is kept               |
| `AGORA_MAX_MEMORY_MB`    | `192`                  | Memory cap of the server started by `npm start`     |

## Tech stack

Next.js 16 (App Router, Server Actions, Cache Components) · React 19 · TypeScript · Prisma 7 + SQLite ·
Tailwind CSS 4 · Argon2id · Vitest · Playwright. Details in [docs/architecture.md](docs/architecture.md).

## Useful commands

| Command                                                 | What it does                                            |
| ------------------------------------------------------- | ------------------------------------------------------- |
| `npm run dev`                                           | Development server with hot reload                      |
| `npm run build` then `npm start`                        | Small production server (standalone, memory cap)        |
| `npm run db:backup`                                     | Back up the database and uploads (safe while running)   |
| `npm run db:studio`                                     | Browse the database in your browser                     |
| `npm run db:migrate -- --name my_change`                | Create a migration after editing `prisma/schema.prisma` |
| `npm run lint` · `npm run typecheck` · `npm run format` | Code quality                                            |
| `npm test`                                              | Unit tests (including tenant isolation)                 |
| `npm run build && npm run test:e2e`                     | End-to-end test of the whole journey, then the budget   |

## License

No license has been chosen yet. Before making the repository public, add a `LICENSE` file:
[MIT](https://choosealicense.com/licenses/mit/) lets anyone reuse the code freely;
[AGPL-3.0](https://choosealicense.com/licenses/agpl-3.0/) (like Paheko) requires anyone who hosts a modified
version to share their changes.
