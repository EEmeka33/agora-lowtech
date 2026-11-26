# Architecture

This page explains how Agora is built, for anyone who wants to change it.

- [Stack](#stack)
- [Folders](#folders)
- [How a request flows](#how-a-request-flows)
- [Accounts, sessions and associations](#accounts-sessions-and-associations)
- [Isolation between associations](#isolation-between-associations)
- [Permissions](#permissions)
- [Server actions](#server-actions)
- [Notifications](#notifications)
- [Data model](#data-model)
- [Conventions](#conventions)
- [Tests](#tests)

## Stack

| Layer     | Choice                                                                                                 | Why                                                                                 |
| --------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| Framework | **Next.js 16** (App Router, Server Components, Server Actions, Cache Components)                       | One codebase for pages and back end, forms that work without client-side API code   |
| UI        | **React 19**, **Tailwind CSS 4**, [lucide](https://lucide.dev) icons, [Recharts](https://recharts.org) | Fast to build, easy to theme (light/dark, accent colors)                            |
| Database  | **Prisma 7** + **SQLite** (`better-sqlite3` adapter)                                                   | A single file, no database server to install; Prisma can switch to PostgreSQL later |
| Passwords | **Argon2id** (`@node-rs/argon2`)                                                                       | Current OWASP recommendation                                                        |
| Email     | **nodemailer**                                                                                         | Any SMTP server, optional                                                           |
| Tests     | **Vitest** (unit) and **Playwright** (end to end)                                                      |                                                                                     |

## Folders

```text
prisma/
  schema.prisma          Data model
  migrations/            SQL migrations (applied with npm run db:deploy)
  seed.ts                Demo data
scripts/                 setup, backup, Docker entrypoint, e2e server
src/
  proxy.ts               Sends visitors without a session cookie to /login
  app/
    (auth)/              Pages without the sidebar: login, signup, forgot, invite, join, welcome
    (app)/               The workspace: dashboard, foundation, structure, poles, requests, projects,
                         tasks, events, polls, volunteering, finance, documents, members,
                         notifications, activity, settings
    api/                 Route handlers: search, uploads, files, exports, calendar feed,
                         notifications, health
  actions/               Server actions, one file per domain (requests.ts, tasks.ts…)
  components/            React components, one folder per domain, plus ui/, forms/ and layout/
  lib/
    auth.ts              Accounts and sessions
    context.ts           Current association + tenant database client
    tenant.ts            Isolation between associations
    action-utils.ts      withUser / withAccount / assertRefs / reader
    permissions.ts       Who can do what
    notifications.ts     In-app notifications, emails and daily summaries
    maintenance.ts       Daily cleanup and summaries, scheduled by page visits
    images.ts            Reduces uploaded photos
    i18n/                English and French dictionaries
    …
tests/
  unit/                  Vitest
  e2e/                   Playwright
```

## How a request flows

1. `src/proxy.ts` checks only that a session cookie is present (no database access) and redirects to
   `/login` otherwise. It is a convenience, not the security check.
2. The page calls `requireContext()` (`src/lib/context.ts`). It reads the session from the database,
   finds the current association and returns `{ user, association, db, associations }`.
3. The page reads data with `ctx.db`, which can only see the current association.
4. Forms call **server actions** (`src/actions/*`). Each action runs inside `withUser()`, which loads the
   same context again (never trust the page), checks permissions and refreshes the UI after success.

Pages that need a session call `connection()` (through `getAccount()`), so they are rendered at request time.
They are wrapped in the `<Suspense>` of `src/app/(app)/layout.tsx` and `loading.tsx`, which shows a skeleton
while data loads. This is required by Cache Components.

## Accounts, sessions and associations

- A **User** is a global account (email + Argon2id password hash). A person can belong to several
  associations through **Member** rows, which hold their role (ADMIN, MANAGER, MEMBER), board position and
  status in that association.
- **Sessions** are stored in the database. The browser keeps a random 256-bit token in the httpOnly cookie
  `agora_session`; the database only stores its SHA-256 hash. Sessions last 30 days and are extended while
  used. Signing out, changing a password or "Sign out other devices" deletes the rows.
- The current association is stored in the `agora_asso` cookie. It is only a preference: `getContext()`
  checks that the person is an active member of it, and falls back to their first association otherwise.
- **Join codes** (`JoinCode`) have a maximum number of uses, an optional expiry date, a role and an optional
  pole. The use counter is incremented with a conditional update, so two people cannot use the last slot at
  the same time (`src/lib/join-codes.ts`).
- **Invitation links** and **password reset links** contain a random token. Only its hash is stored, and
  it can be used once.

Sign-up behavior is controlled by `SIGNUP_MODE` and `ALLOW_NEW_ASSOCIATIONS` (see
[self-hosting](self-hosting.md#configuration)).

## Isolation between associations

Several associations can share one Agora, so every query must stay inside the current association.
Rather than relying on every developer to remember a `where: { associationId }`, Agora uses a
**Prisma client extension**: `tenantDb(associationId)` in `src/lib/tenant.ts`.

| Kind of model                                                                      | What the extension does                                                                                                       |
| ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Models with an `associationId` column (Pole, Project, Task, Request, Event, Poll…) | Adds `associationId = current` to every filter (`findMany`, `findUnique`, `update`, `delete`, `count`…) and to every `create` |
| Child models (PoleMember, Milestone, TaskComment, PollVote…)                       | Filters through the parent: `{ project: { associationId } }`                                                                  |
| User                                                                               | Only users who are members of the association                                                                                 |

So `ctx.db.project.findUnique({ where: { id } })` returns `null` for another association's project, and
`ctx.db.task.update({ where: { id } })` fails for another association's task.

What the extension cannot know: whether ids **coming from a form** belong to the association (for example
the `projectId` of a new task). Actions check them with `assertRefs(ctx, { poleId, projectId, userIds… })`.

The global client (`db` from `src/lib/db.ts`) is reserved for things that are not tied to one association:
accounts, sessions, notifications, join code redemption and the calendar feed. Each of those checks
membership explicitly.

`tests/unit/tenant.test.ts` creates two associations in a temporary database and verifies that reads,
updates, deletes and counts never cross between them; the end-to-end test checks it again through the UI
and the search API.

## Permissions

All rules are in `src/lib/permissions.ts`, as small functions:

```ts
can.manageFinance(user); // admins and managers
can.editTask(user, { creatorId, assigneeId, projectLeadId });
can.assignRequest(user, poleLeadIds); // managers and leads of the pole
```

Pages use them to show or hide buttons; actions use them again with `ensure(can.x(...), t)`. Hiding a button
is never the protection.

| Role              | Can                                                                                                                       |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------- |
| **Administrator** | Everything, including association settings, roles, member accounts and the full backup                                    |
| **Manager**       | Content: poles, structure, founding steps, projects, events, finance, announcements, join codes for members               |
| **Member**        | Take and create requests, create tasks, vote, log time, RSVP, upload documents, edit what they created or are assigned to |

Pole leads and project leads get extra rights on their pole or project.

## Server actions

A typical action (`src/actions/volunteering.ts`):

```ts
"use server";

export async function logTime(_prev: ActionState, fd: FormData) {
  return withUser(async (ctx) => {
    const { user, t, db } = ctx;
    const f = reader(fd); // typed FormData reader
    const minutes = parseDuration(f.str("duration"));
    if (!minutes) return fail(t.volunteering.invalidDuration, { duration: t.volunteering.invalidDuration });
    const refs = { poleId: f.opt("poleId"), projectId: f.opt("projectId") };
    await assertRefs(ctx, refs); // ids must belong to this association
    await db.timeEntry.create({ data: { userId: user.id, minutes, ...refs } }); // associationId added by tenantDb
    return ok(t.common.saved);
  });
}
```

- `withUser` returns a "not allowed" error when there is no session or association, turns an `ActionError`
  into a message, logs unexpected errors and calls `revalidatePath` after success.
- `withAccount` is the same for account-level actions (profile, password, create or join an association).
- Forms use `<ActionForm>` or `<FormDialog>` (`src/components/forms/`), which show field errors and toasts
  from the returned `ActionState`.
- Actions that change something visible to others call `logActivity()` (activity feed) and `notify()`.

## Notifications

`notify(ctx, { userIds, type, data, link })` (`src/lib/notifications.ts`):

1. creates one `Notification` row per recipient (never the person who acted);
2. after the response is sent (`after()`), emails the people who chose "each notification, right away",
   if `SMTP_URL` is set.

By default people get **one summary a day** instead (`emailDigest`): `sendDigests()` lists the
notifications they have **not read** in Agora since the previous summary, and sends nothing when everything
was already seen.

Texts are not stored: `renderNotification(type, data, locale)` builds the sentence from the dictionaries,
so each person reads notifications in their own language. The bell does not poll: it refreshes
its count when the person comes back to the tab (at most twice a minute) and after each navigation.

Who is notified:

| Event                                                        | Recipients                                     |
| ------------------------------------------------------------ | ---------------------------------------------- |
| New request to a pole                                        | Members of the pole                            |
| Request taken / given / done                                 | Requester (and the person it was given to)     |
| Task assigned, task comment                                  | Assignee, task creator and previous commenters |
| New event (when "notify" is checked), new vote, announcement | Members concerned (whole association or pole)  |
| Someone joined with a code                                   | Administrators                                 |

## Maintenance

There is no cron job and no timer. `requireContext()` calls `scheduleMaintenance()`
(`src/lib/maintenance.ts`), which runs after the response, at most once an hour per server process for the
daily summaries and once a day for `cleanup()`:

- read notifications after 90 days, every notification and the activity history after `RETENTION_DAYS`
  (365 by default);
- expired sessions, and one-time links used or expired more than 30 days ago;
- uploaded files that no document points to (left by a deleted association or account).

Money, documents, minutes and everything members wrote are never deleted. When nobody uses Agora,
nothing runs.

Uploaded photos (JPEG, PNG, WebP over 300 KB) are reduced by `shrinkImage()` (`src/lib/images.ts`) to
1600 px on their longest side, without their metadata (GPS position included). It uses sharp, which is
installed with Next.js; without it, files are stored as they are.

## Data model

```text
User ──< Session
User ──< Member >── Association
Association ──< JoinCode, Pole, Responsibility, FoundationStep, Project, Task, Request, Event,
                Announcement, Poll, TimeEntry, Transaction, Dues, Document, Activity
Pole ──< PoleMember >── User
Project ──< ProjectMember, Milestone, ProjectUpdate, Task
Task ──< TaskComment
Request ── Task (when taken, the request creates a task; finishing the task completes the request)
Event ──< EventAttendee
Poll ──< PollOption ──< PollVote
User ──< Notification
```

The full schema, with comments, is in [`prisma/schema.prisma`](../prisma/schema.prisma).

To change it:

1. Edit `prisma/schema.prisma`.
2. Create the migration with `npm run db:migrate -- --name what_changed`.
3. Regenerate the client with `npx prisma generate`. `migrate dev` does not regenerate it with Prisma 7.
4. If the model belongs to an association, add it to `DIRECT` or `VIA` in `src/lib/tenant.ts`.

### Moving to PostgreSQL

Enums are stored as strings validated in TypeScript (`src/lib/constants.ts`), so the schema is portable.
Change `provider` in `schema.prisma`, replace the adapter in `src/lib/db.ts` with `@prisma/adapter-pg`, and
create a fresh migration.

## Conventions

- **Languages**: every text is in `src/lib/i18n/en.ts` and `fr.ts`. `en.ts` defines the shape and TypeScript
  refuses a French dictionary with missing keys. Server components use `getI18n()`, client components use
  `useT()`. To add a language, copy `fr.ts`, translate it, register it in `dictionaries.ts` and `LOCALES`
  (`constants.ts`), and add its name to `enums.locale` in every dictionary.
- **Dates** entered by people (event start, due date) are stored as "wall time" in UTC and always displayed
  with `timeZone: "UTC"`, so 18:00 stays 18:00 whatever the server's time zone.
- **Money** is stored in cents (`amountCents`, `budgetCents`).
- **Enums** (statuses, roles, categories) are listed in `src/lib/constants.ts`; colors per status in
  `src/lib/colors.ts`.
- **Uploads** are stored in `UPLOAD_DIR` under random names and served only to active members of the
  document's association.

## Tests

| Command                             | What it checks                                                                                                                                                                                                                                                                         |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm test`                          | Unit tests: progress and health calculation, date and money parsing, permissions, dictionaries, password policy and tokens, isolation between associations                                                                                                                             |
| `npm run build && npm run test:e2e` | A production server with a fresh database (`data/e2e.db`, port 3210) and a story covering sign-up, the founding steps, join codes, requests between poles, votes, volunteering, projects, events, finance, documents, privacy between associations, access control and account lockout |
