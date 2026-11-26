-- Agora low-tech: the whole data model, in plain SQL.
--
-- One Agora can host several associations. Every table that belongs to an
-- association has an `association_id` column, and every query filters on it
-- (see `find()` and `aid()` in app/lib/db.php).
--
-- Conventions
--   * ids are integers (INTEGER PRIMARY KEY);
--   * enumerated values are UPPERCASE text, listed in app/lib/constants.php;
--   * dates typed by people ("wall time") are text: '2026-03-04' or '2026-03-04 18:30';
--   * instants written by the server (created_at…) are UTC text: '2026-03-04 17:30:00';
--   * money is stored in cents (columns ending in _cents);
--   * booleans are 0 / 1.
--
-- This file is applied once, on the first request, when the database is empty.
-- Later changes go into app/migrations/ (see docs/architecture.md).

-- ─── Accounts ──────────────────────────────────────────────────────────────

CREATE TABLE users (
  id                  INTEGER PRIMARY KEY,
  email               TEXT NOT NULL UNIQUE,
  name                TEXT NOT NULL,
  password_hash       TEXT,              -- NULL until the person activates an account created for them
  password_changed_at TEXT,
  failed_logins       INTEGER NOT NULL DEFAULT 0,
  locked_until        TEXT,
  last_login_at       TEXT,
  phone               TEXT,
  bio                 TEXT,
  skills              TEXT,
  locale              TEXT,
  email_mode          TEXT NOT NULL DEFAULT 'daily', -- OFF | INSTANT | DAILY (one digest a day)
  last_digest_at      TEXT,
  calendar_token_hash TEXT UNIQUE,       -- SHA-256 of the private calendar feed token
  created_at          TEXT NOT NULL,
  updated_at          TEXT NOT NULL
);

-- A signed-in browser. The cookie holds a random token; only its SHA-256 is stored.
CREATE TABLE sessions (
  id           INTEGER PRIMARY KEY,
  token_hash   TEXT NOT NULL UNIQUE,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_agent   TEXT,
  ip           TEXT,
  created_at   TEXT NOT NULL,
  last_seen_at TEXT NOT NULL,
  expires_at   TEXT NOT NULL
);
CREATE INDEX sessions_user ON sessions(user_id);

-- One-time link: INVITE (activate an account created by an admin) or RESET (new password).
CREATE TABLE invitations (
  id             INTEGER PRIMARY KEY,
  token_hash     TEXT NOT NULL UNIQUE,
  kind           TEXT NOT NULL DEFAULT 'INVITE',
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  association_id INTEGER REFERENCES associations(id) ON DELETE CASCADE,
  invited_by_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  expires_at     TEXT NOT NULL,
  used_at        TEXT,
  created_at     TEXT NOT NULL
);

-- ─── Associations and members ──────────────────────────────────────────────

CREATE TABLE associations (
  id                      INTEGER PRIMARY KEY,
  name                    TEXT NOT NULL,
  short_name              TEXT,
  tagline                 TEXT,
  purpose                 TEXT,          -- the "objet social"
  legal_form              TEXT NOT NULL DEFAULT 'Loi 1901',
  registration_number     TEXT,          -- e.g. RNA number in France
  siret                   TEXT,
  founded_at              TEXT,
  email                   TEXT,
  phone                   TEXT,
  website                 TEXT,
  address                 TEXT,
  postal_code             TEXT,
  city                    TEXT,
  country                 TEXT,
  locale                  TEXT NOT NULL DEFAULT 'en',
  currency                TEXT NOT NULL DEFAULT 'EUR',
  membership_fee_cents    INTEGER,
  volunteer_hour_cents    INTEGER,       -- value of one hour of volunteering
  fiscal_year_start_month INTEGER NOT NULL DEFAULT 1,
  created_at              TEXT NOT NULL,
  updated_at              TEXT NOT NULL
);

-- A person's membership in an association, with their role there.
CREATE TABLE members (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role           TEXT NOT NULL DEFAULT 'MEMBER',  -- ADMIN | MANAGER | MEMBER
  board_position TEXT,                            -- PRESIDENT | TREASURER | SECRETARY | …
  title          TEXT,                            -- free text, e.g. "Repair café lead"
  status         TEXT NOT NULL DEFAULT 'ACTIVE',  -- ACTIVE | INACTIVE
  joined_at      TEXT NOT NULL,
  created_at     TEXT NOT NULL,
  UNIQUE (association_id, user_id)
);
CREATE INDEX members_user ON members(user_id);

-- Code that lets people join an association, with a limited number of uses.
CREATE TABLE join_codes (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  code           TEXT NOT NULL UNIQUE,
  label          TEXT,
  role           TEXT NOT NULL DEFAULT 'MEMBER',
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  max_uses       INTEGER NOT NULL DEFAULT 10,
  uses           INTEGER NOT NULL DEFAULT 0,
  expires_at     TEXT,
  revoked_at     TEXT,
  created_by_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL
);

-- ─── Structure ─────────────────────────────────────────────────────────────

-- A pole: a working group of the association (repair café, workshops, treasury…).
CREATE TABLE poles (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  name           TEXT NOT NULL,
  description    TEXT,
  mission        TEXT,
  position       INTEGER NOT NULL DEFAULT 0,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX poles_association ON poles(association_id);

CREATE TABLE pole_members (
  pole_id   INTEGER NOT NULL REFERENCES poles(id) ON DELETE CASCADE,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role      TEXT NOT NULL DEFAULT 'MEMBER',       -- LEAD | MEMBER
  joined_at TEXT NOT NULL,
  PRIMARY KEY (pole_id, user_id)
);

-- "Who does what": a recurring duty owned by someone.
CREATE TABLE responsibilities (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  frequency      TEXT NOT NULL DEFAULT 'ONGOING', -- ONGOING | WEEKLY | MONTHLY | QUARTERLY | YEARLY | ONE_OFF
  owner_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

-- A step of the founding checklist (statutes, general assembly, declaration…).
CREATE TABLE foundation_steps (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  category       TEXT NOT NULL DEFAULT 'PROJECT', -- PROJECT | LEGAL | GOVERNANCE | FINANCE | OPERATIONS | COMMUNICATION
  status         TEXT NOT NULL DEFAULT 'TODO',    -- TODO | IN_PROGRESS | DONE | SKIPPED
  position       INTEGER NOT NULL DEFAULT 0,
  resource_url   TEXT,
  notes          TEXT,
  due_date       TEXT,
  completed_at   TEXT,
  assignee_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

-- ─── Work ──────────────────────────────────────────────────────────────────

CREATE TABLE projects (
  id              INTEGER PRIMARY KEY,
  association_id  INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title           TEXT NOT NULL,
  description     TEXT,
  objectives      TEXT,
  status          TEXT NOT NULL DEFAULT 'PLANNED', -- IDEA | PLANNED | IN_PROGRESS | ON_HOLD | COMPLETED | CANCELLED
  priority        TEXT NOT NULL DEFAULT 'MEDIUM',  -- LOW | MEDIUM | HIGH | URGENT
  progress_mode   TEXT NOT NULL DEFAULT 'AUTO',    -- AUTO (from tasks and milestones) | MANUAL
  manual_progress INTEGER NOT NULL DEFAULT 0,
  start_date      TEXT,
  due_date        TEXT,
  budget_cents    INTEGER,
  pole_id         INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  lead_id         INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);
CREATE INDEX projects_association ON projects(association_id);

CREATE TABLE project_members (
  project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role       TEXT,
  joined_at  TEXT NOT NULL,
  PRIMARY KEY (project_id, user_id)
);

CREATE TABLE milestones (
  id         INTEGER PRIMARY KEY,
  project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  title      TEXT NOT NULL,
  due_date   TEXT,
  done       INTEGER NOT NULL DEFAULT 0,
  done_at    TEXT,
  position   INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

-- A dated progress report posted on a project.
CREATE TABLE project_updates (
  id         INTEGER PRIMARY KEY,
  project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  body       TEXT NOT NULL,
  health     TEXT NOT NULL DEFAULT 'ON_TRACK',   -- ON_TRACK | AT_RISK | OFF_TRACK
  progress   INTEGER,
  author_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE tasks (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  status         TEXT NOT NULL DEFAULT 'TODO',     -- TODO | IN_PROGRESS | REVIEW | DONE
  priority       TEXT NOT NULL DEFAULT 'MEDIUM',
  due_date       TEXT,
  completed_at   TEXT,
  position       INTEGER NOT NULL DEFAULT 0,
  project_id     INTEGER REFERENCES projects(id) ON DELETE CASCADE,
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  assignee_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  creator_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX tasks_status ON tasks(association_id, status);
CREATE INDEX tasks_assignee ON tasks(assignee_id);
CREATE INDEX tasks_project ON tasks(project_id);

CREATE TABLE task_comments (
  id         INTEGER PRIMARY KEY,
  task_id    INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  body       TEXT NOT NULL,
  author_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL
);

-- A request sent to a pole ("can someone do this?"). Pole members are notified;
-- one of them takes it and it becomes a task assigned to them.
CREATE TABLE requests (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  priority       TEXT NOT NULL DEFAULT 'MEDIUM',
  due_date       TEXT,
  status         TEXT NOT NULL DEFAULT 'OPEN',     -- OPEN | TAKEN | DONE | CANCELLED
  pole_id        INTEGER NOT NULL REFERENCES poles(id) ON DELETE CASCADE, -- the pole being asked
  from_pole_id   INTEGER REFERENCES poles(id) ON DELETE SET NULL,        -- the pole asking
  project_id     INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  requester_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  taken_by_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  task_id        INTEGER UNIQUE REFERENCES tasks(id) ON DELETE SET NULL,
  taken_at       TEXT,
  closed_at      TEXT,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX requests_status ON requests(association_id, status);
CREATE INDEX requests_pole ON requests(pole_id);

-- ─── Life of the association ───────────────────────────────────────────────

CREATE TABLE events (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  type           TEXT NOT NULL DEFAULT 'EVENT', -- GENERAL_ASSEMBLY | BOARD_MEETING | POLE_MEETING | EVENT | WORKSHOP | OTHER
  start_at       TEXT NOT NULL,
  end_at         TEXT,
  location       TEXT,
  agenda         TEXT,
  minutes        TEXT,                          -- meeting minutes ("procès-verbal")
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  project_id     INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  created_by_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX events_start ON events(association_id, start_at);

CREATE TABLE event_attendees (
  event_id   INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status     TEXT NOT NULL DEFAULT 'GOING',     -- GOING | MAYBE | NOT_GOING
  updated_at TEXT NOT NULL,
  PRIMARY KEY (event_id, user_id)
);

CREATE TABLE announcements (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  body           TEXT NOT NULL,
  pinned         INTEGER NOT NULL DEFAULT 0,
  author_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

-- A question put to the vote.
CREATE TABLE polls (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  question       TEXT NOT NULL,
  description    TEXT,
  multiple       INTEGER NOT NULL DEFAULT 0,    -- several options can be chosen
  anonymous      INTEGER NOT NULL DEFAULT 0,    -- hide who voted for what
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL, -- only members of this pole vote
  closes_at      TEXT,
  closed_at      TEXT,
  created_by_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL
);
CREATE INDEX polls_association ON polls(association_id);

CREATE TABLE poll_options (
  id       INTEGER PRIMARY KEY,
  poll_id  INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  label    TEXT NOT NULL,
  position INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE poll_votes (
  id         INTEGER PRIMARY KEY,
  poll_id    INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  option_id  INTEGER NOT NULL REFERENCES poll_options(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  UNIQUE (option_id, user_id)
);
CREATE INDEX poll_votes_user ON poll_votes(poll_id, user_id);

-- Volunteer time given to the association.
CREATE TABLE time_entries (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  date           TEXT NOT NULL,
  minutes        INTEGER NOT NULL,
  description    TEXT,
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  project_id     INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  task_id        INTEGER REFERENCES tasks(id) ON DELETE SET NULL,
  event_id       INTEGER REFERENCES events(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL
);
CREATE INDEX time_entries_date ON time_entries(association_id, date);

-- In-app notification (also sent by email, at once or in a daily digest).
-- The text is not stored: it is rebuilt from `type` and `data` in the reader's language.
CREATE TABLE notifications (
  id             INTEGER PRIMARY KEY,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  association_id INTEGER REFERENCES associations(id) ON DELETE CASCADE,
  type           TEXT NOT NULL,                 -- request.new | task.assigned | event.new | …
  data           TEXT NOT NULL DEFAULT '{}',    -- JSON values used to render the message
  link           TEXT,
  actor_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
  read_at        TEXT,
  created_at     TEXT NOT NULL
);
CREATE INDEX notifications_user ON notifications(user_id, read_at);

-- ─── Money ─────────────────────────────────────────────────────────────────

CREATE TABLE transactions (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  label          TEXT NOT NULL,
  type           TEXT NOT NULL,                 -- INCOME | EXPENSE
  amount_cents   INTEGER NOT NULL,
  category       TEXT NOT NULL DEFAULT 'OTHER',
  date           TEXT NOT NULL,
  payment_method TEXT,
  reference      TEXT,
  notes          TEXT,
  project_id     INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  recorded_by_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX transactions_date ON transactions(association_id, date);

-- Yearly membership fee ("cotisation") paid by a member.
CREATE TABLE dues (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  year           INTEGER NOT NULL,
  amount_cents   INTEGER NOT NULL,
  paid_at        TEXT NOT NULL,
  method         TEXT,
  transaction_id INTEGER UNIQUE REFERENCES transactions(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  UNIQUE (association_id, user_id, year)
);

-- ─── Knowledge ─────────────────────────────────────────────────────────────

CREATE TABLE documents (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  title          TEXT NOT NULL,
  description    TEXT,
  category       TEXT NOT NULL DEFAULT 'OTHER', -- STATUTES | RULES | MINUTES | FINANCE | LEGAL | COMMUNICATION | GUIDE | OTHER
  url            TEXT,                          -- a link…
  file_name      TEXT,                          -- …or an uploaded file
  stored_name    TEXT,
  mime_type      TEXT,
  size_bytes     INTEGER,
  pole_id        INTEGER REFERENCES poles(id) ON DELETE SET NULL,
  project_id     INTEGER REFERENCES projects(id) ON DELETE SET NULL,
  uploaded_by_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

-- Audit trail shown in the activity feed.
CREATE TABLE activities (
  id             INTEGER PRIMARY KEY,
  association_id INTEGER NOT NULL REFERENCES associations(id) ON DELETE CASCADE,
  action         TEXT NOT NULL,                 -- created | updated | deleted | completed | joined | …
  entity_type    TEXT NOT NULL,                 -- task | project | pole | member | event | …
  entity_id      INTEGER,
  entity_label   TEXT,
  actor_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at     TEXT NOT NULL
);
CREATE INDEX activities_date ON activities(association_id, created_at);

-- ─── Housekeeping ──────────────────────────────────────────────────────────

-- Failed sign-ins and other attempts, for rate limiting (purged automatically).
CREATE TABLE attempts (
  key TEXT NOT NULL,
  at  INTEGER NOT NULL                          -- Unix time
);
CREATE INDEX attempts_key ON attempts(key, at);

-- Small key/value store (last maintenance run…).
CREATE TABLE meta (
  key   TEXT PRIMARY KEY,
  value TEXT
);
