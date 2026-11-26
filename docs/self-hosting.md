# Self-hosting Agora

Agora is a single Node.js application with a SQLite database file. It runs on a laptop, a Raspberry Pi,
a NAS or a small server.

- [Option 1: Node.js](#option-1-nodejs)
- [Option 2: Docker](#option-2-docker)
- [Access from other devices](#access-from-other-devices)
- [Putting it on the internet (HTTPS)](#putting-it-on-the-internet-https)
- [Configuration](#configuration)
- [Email](#email)
- [Backups and restore](#backups-and-restore)
- [Updating](#updating)
- [Troubleshooting](#troubleshooting)

---

## Option 1: Node.js

Requirements: Node.js **20.19+** (22 LTS recommended) and about 500 MB of disk space.

```bash
git clone https://github.com/EEmeka33/agora.git
```

```bash
cd agora
```

```bash
npm install
```

```bash
npm run setup
```

`npm run setup` creates `.env`, the `data/` folder (readable by your user only) and the database.

- **Development** (hot reload): `npm run dev`
- **Production** (faster): `npm run build`, then `npm start`

Agora listens on port 3000. Use `PORT=8080 npm start` to choose another port.

`npm start` runs the small production server: `.next/standalone` contains only what the server needs (about
85 MB, instead of the 1.1 GB of `node_modules` used to build it), and its memory is capped at 192 MB of
JavaScript heap (`AGORA_MAX_MEMORY_MB`): about 325 MB in total under load instead of ~780 MB. On a machine
with little memory (a Raspberry Pi), you can build on a bigger computer and copy the project folder with
`.next/` and `data/`; `node_modules` is then only needed for `npx prisma migrate deploy`.

To keep it running after you close the terminal, use a process manager such as
[pm2](https://pm2.keymetrics.io/) (`pm2 start npm --name agora -- start`) or a systemd service:

```ini
# /etc/systemd/system/agora.service
[Unit]
Description=Agora
After=network.target

[Service]
WorkingDirectory=/opt/agora
ExecStart=/usr/bin/npm start
Restart=always
User=agora
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

## Option 2: Docker

```bash
docker compose up -d --build
```

- Data (database, uploads) is stored in the `agora-data` volume.
- Migrations are applied automatically at every start.
- Settings are in `docker-compose.yml` (`environment:`).
- Logs: `docker compose logs -f`
- Demo data: `docker compose exec agora npm run db:seed`

## Access from other devices

Agora listens on all network interfaces. Find your computer's local IP address (for example `192.168.1.20`)
and open `http://192.168.1.20:3000` from a phone or another computer on the same network.

In development mode (`npm run dev`), add that address to `allowedDevOrigins` in `next.config.ts`.

## Putting it on the internet (HTTPS)

Never expose Agora on the internet without HTTPS. The simplest way is a reverse proxy such as
[Caddy](https://caddyserver.com), which gets certificates automatically:

```caddy
# /etc/caddy/Caddyfile
agora.my-association.org {
    reverse_proxy localhost:3000
}
```

Then set in `.env` (or `docker-compose.yml`):

```env
APP_URL="https://agora.my-association.org"
SECURE_COOKIES="true"
```

With Nginx, forward the `Host`, `X-Forwarded-Host` and `X-Forwarded-Proto` headers.

Consider `SIGNUP_MODE="invite"` on a public server so only people with a join code can create an account.

## Configuration

All variables are optional; defaults work for a local installation.

| Variable                 | Default                      | Description                                                                                                                                                                                          |
| ------------------------ | ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABASE_URL`           | `file:./data/agora.db`       | SQLite database file                                                                                                                                                                                 |
| `UPLOAD_DIR`             | `./data/uploads`             | Folder for uploaded documents                                                                                                                                                                        |
| `MAX_UPLOAD_MB`          | `20`                         | Maximum size of an upload                                                                                                                                                                            |
| `APP_URL`                | detected                     | Public address, used in emails, invitation and join links                                                                                                                                            |
| `SECURE_COOKIES`         | `false`                      | Set to `true` behind HTTPS                                                                                                                                                                           |
| `SIGNUP_MODE`            | `open`                       | `open`: anyone can create an account. `invite`: a valid join code is required (the very first account is always allowed). `closed`: no sign-up, administrators add members and send invitation links |
| `ALLOW_NEW_ASSOCIATIONS` | `true`                       | `false`: only the first association can be created (single-association instance)                                                                                                                     |
| `SMTP_URL`               | —                            | Email server, see below                                                                                                                                                                              |
| `MAIL_FROM`              | `Agora <no-reply@localhost>` | Sender of emails                                                                                                                                                                                     |

### Common setups

| You want…                          | Settings                                                                        |
| ---------------------------------- | ------------------------------------------------------------------------------- |
| One association, on your computer  | defaults                                                                        |
| One association, public server     | `SIGNUP_MODE="invite"`, `ALLOW_NEW_ASSOCIATIONS="false"`, HTTPS                 |
| A federation hosting several clubs | `SIGNUP_MODE="open"` or `invite`, `ALLOW_NEW_ASSOCIATIONS="true"`, HTTPS, email |

## Email

Email is optional. Without it, notifications only appear in Agora, and password resets are done with links
generated by an administrator (Members → … → Create password reset link).

With an SMTP account (your host, your association's email provider, or a transactional email service):

```env
SMTP_URL="smtps://username:password@smtp.example.org:465"
MAIL_FROM="Agora <agora@my-association.org>"
```

Special characters in the password must be URL-encoded (`@` → `%40`, `:` → `%3A`…).
In development, `SMTP_URL="log"` prints emails in the terminal instead of sending them.

With email configured:

- members receive their notifications by email too (they can turn it off in Settings → Notifications);
- **Forgot password?** on the sign-in page sends a reset link valid for one hour.

## Backups and restore

Everything is in two places: the database file and the uploads folder (both in `data/` by default).

**Make a backup** (safe while Agora is running):

```bash
npm run db:backup
```

This writes `backups/agora-YYYYMMDD-HHMM/` with `agora.db` and `uploads/`. Copy it somewhere else
(another disk, a cloud drive). Schedule it daily with cron, for example:

```cron
0 3 * * * cd /opt/agora && npm run db:backup -- /mnt/backup-disk
```

With Docker:

```bash
docker compose exec agora npm run db:backup -- /app/data/backups
```

**Restore**: stop Agora, copy the backed-up `agora.db` to `data/agora.db` and `uploads/` to `data/uploads/`,
then start Agora again (pending migrations are applied with `npm run db:deploy`).

Administrators can also download a JSON export of their association (Settings → Data). It never contains
passwords or session tokens.

Backups contain personal data: keep them private, ideally on an encrypted disk.

## Updating

```bash
npm run db:backup
```

```bash
git pull
```

```bash
npm ci
```

```bash
npm run db:deploy
```

```bash
npm run build
```

Then restart Agora. With Docker: `git pull` then `docker compose up -d --build`.

## Troubleshooting

| Problem                                             | Solution                                                                                                                                                  |
| --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm install` fails while building `better-sqlite3` | Install build tools (`build-essential` and `python3` on Debian/Ubuntu, Xcode command line tools on macOS) or use Node 22 LTS, which has prebuilt binaries |
| "Too many attempts" when signing in                 | The account is locked for 15 minutes after 5 wrong passwords                                                                                              |
| Invitation or join links point to `localhost`       | Set `APP_URL`                                                                                                                                             |
| Signed out immediately behind a proxy               | Set `SECURE_COOKIES="true"` only when the site is served over HTTPS                                                                                       |
| Forms fail from another device in development       | Add the device's address to `allowedDevOrigins` in `next.config.ts`                                                                                       |
| Start from scratch                                  | Stop Agora, delete `data/agora.db`, run `npm run db:deploy`                                                                                               |
