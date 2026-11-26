# Self-hosting

Agora needs **PHP 8.1 or newer** with the **pdo_sqlite** extension. That's all. The extensions *gd* (to reduce
big photos), *zlib* (compressed pages) and *fileinfo* (file types) are used when present.
Run `php bin/check.php` on the server to check.

- [On your computer, to try it](#on-your-computer)
- [On a shared hosting (FTP)](#on-a-shared-hosting)
- [On a Raspberry Pi or an old laptop](#on-a-raspberry-pi-or-an-old-laptop)
- [On a VPS with nginx or Caddy](#on-a-vps)
- [Configuration](#configuration)
- [Email](#email)
- [Backups](#backups)
- [Updates](#updates)
- [Maintenance](#maintenance)
- [Security checklist](#security-checklist)
- [Troubleshooting](#troubleshooting)

## On your computer

```bash
php -S localhost:8080 -t public public/index.php
```

Open http://localhost:8080. The database is created in `data/agora.db` on the first visit.
`php bin/seed.php` loads a demo association.

## On a shared hosting

Most association hostings (OVH, o2switch, Infomaniak, Gandi, alwaysdata…) run Apache with PHP.

1. Download the code (the green *Code* button on GitHub → *Download ZIP*), unzip it.
2. Copy `config.example.php` to `config.php`. Set at least `'mail' => ['transport' => 'mail', 'from' => 'Agora <contact@your-domain.org>']`
   so that password resets and digests work (PHP's `mail()` works out of the box on shared hostings).
3. Upload the whole folder by FTP (FileZilla), for example to `www/agora/`.
4. If your hosting lets you choose the **document root** of a (sub)domain, point it to the `public/` folder.
   Otherwise, the `.htaccess` file at the root forwards everything to `public/` and forbids the other folders.
5. Open the address in your browser and create the first account.

Then check that your data is private: opening `https://your-domain.org/agora/data/agora.db` must answer
**403 Forbidden** (or 404). If it downloads a file, your host ignores `.htaccess` files: move `data/` outside
the web folder and set `database` and `upload_dir` in `config.php`.

## On a Raspberry Pi or an old laptop

Any Raspberry Pi (even a Pi Zero 2) or a laptop from the 2010s is enough for an association. With Debian,
Raspberry Pi OS or Ubuntu:

```bash
sudo apt install php-cli php-sqlite3 php-gd git
sudo adduser --disabled-password agora
sudo -u agora git clone https://github.com/EEmeka33/agora-lowtech.git /home/agora/agora
```

**For a local network** (the association's room, a third place): PHP's own server is enough. Install the
service in `docs/server/agora.service`:

```bash
sudo cp /home/agora/agora/docs/server/agora.service /etc/systemd/system/
sudo systemctl enable --now agora
```

Agora is then at `http://<address-of-the-pi>:8080`.

**For the Internet**, put Caddy (automatic HTTPS) or nginx in front of PHP-FPM, as on a VPS (below), and open
ports 80 and 443 on your router. A Raspberry Pi uses about 2 to 6 W, about as much as a box router.

## On a VPS

Install PHP-FPM and a web server, then use one of the examples in `docs/server/`:

- `Caddyfile`: Caddy, with automatic HTTPS certificates.
- `nginx.conf`: nginx; add HTTPS with `certbot`.

In both cases the web server serves **only `public/`**, and passes every address to `public/index.php`.
The folder `data/` must be writable by the PHP user (`www-data` on Debian):

```bash
sudo chown -R www-data: /var/www/agora/data
```

## Configuration

Copy `config.example.php` to `config.php` and change what you need. Every setting is explained in the file.

| Setting | Default | Description |
| --- | --- | --- |
| `base_url` | detected | Public address, used in emails and calendar links |
| `timezone` | `Europe/Paris` | Time zone of server events (sign-ins, comments) |
| `default_locale` | `en` | Language for visitors who are not signed in (`en`, `fr`) |
| `database`, `upload_dir` | `data/…` | Where the data lives. Keep it outside `public/` |
| `max_upload_mb` | `5` | Maximum file size (also check PHP's `upload_max_filesize` and `post_max_size`) |
| `shrink_images` | `true` | Reduce big photos on upload |
| `signup_mode` | `open` | `open`, `invite` (a join code is required) or `closed` |
| `allow_new_associations` | `true` | `false`: only the first association can be created |
| `secure_cookies` | automatic | Cookies only over HTTPS |
| `mail` | `log` | See below |
| `retention_days` | `365` | Read notifications and activity older than this are deleted |
| `show_page_weight` | `true` | Show the weight of each page in the footer |
| `debug` | `false` | Show errors in the browser (never on a public server) |

## Email

Agora sends plain-text emails: password resets and notifications (one daily summary by default; each person
chooses in *Settings → Notifications*).

| `mail.transport` | When to use it |
| --- | --- |
| `off` | No email at all. Administrators create password reset links from the member's page. |
| `log` | Emails are written to `data/mail.log`. For development and courses. |
| `mail` | PHP's `mail()`: works on most shared hostings; on a Pi, install `msmtp-mta` and configure it. |
| `smtp` | Any SMTP server (your provider, your association's mailbox). Set `smtp_host`, `smtp_port`, `smtp_secure` (`tls` for port 587, `ssl` for 465), `smtp_user`, `smtp_password`. |

Always set `mail.from` to an address of a domain you own, or emails will end up in spam.

## Backups

The data is **one file and one folder**: `data/agora.db` and `data/uploads/`.

```bash
php bin/backup.php                 # → data/backups/2026-11-26-0300/
php bin/backup.php /media/usb-key  # → on a USB key
```

The copy is consistent even while people use Agora. The 14 most recent backups are kept. Run it every night
with cron (`crontab -e`):

```cron
0 3 * * *  php /home/agora/agora/bin/backup.php /media/usb-key
```

A backup that stays on the same disk is not a backup: copy it elsewhere too. Administrators can also download
a JSON export of their association in *Settings → Data*.

**Restore**: stop the web server, copy `agora.db` and `uploads/` back into `data/`, start it again.

## Updates

1. Make a backup.
2. Replace the code: `git pull`, or upload the new files by FTP (keep your `config.php` and `data/`).
3. Open any page: database changes (`app/migrations/`) are applied automatically.

## Maintenance

Agora cleans up by itself (expired sessions, old notifications) and sends the daily digests, at most once an
hour, right after sending a page to a visitor. To send the digests at a fixed time, add a cron job:

```cron
0 7 * * *  php /home/agora/agora/bin/maintenance.php
```

## Security checklist

- [ ] HTTPS on any public address (Caddy does it by itself, otherwise `certbot`).
- [ ] `data/agora.db` cannot be downloaded (see [shared hosting](#on-a-shared-hosting)).
- [ ] `debug` is `false`.
- [ ] Backups run every night, and you have restored one at least once.
- [ ] PHP is updated by your host or your system (`sudo apt upgrade`).
- [ ] After creating your association, consider `signup_mode => 'invite'` and `allow_new_associations => false`.

## Troubleshooting

| Problem | Solution |
| --- | --- |
| "This form has expired or comes from another site" | Cookies are blocked, or the page was open for very long: reload and submit again. |
| Every page except the home page answers 404 | URL rewriting is off: enable `mod_rewrite` and `AllowOverride All` (Apache), or use the nginx/Caddy examples. |
| "Agora needs the PHP extension pdo_sqlite" | `sudo apt install php-sqlite3`, or enable SQLite in your hosting's PHP settings. |
| Uploads fail | Raise `upload_max_filesize` and `post_max_size` in PHP's settings, above `max_upload_mb`. |
| Nobody can sign in any more | `php bin/create-admin.php you@example.org "Association name"` sets a new password. |
| Something else | Set `'debug' => true` in `config.php` temporarily, or read the PHP error log. |
