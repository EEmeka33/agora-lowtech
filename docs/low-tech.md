# Agora low-tech: choices, measurements and course material

This document explains how a modern web application was turned into a low-tech one, what was gained, what
was lost, and how to measure it. It is written to be used as **course material**: each section can be read
on its own, and the [exercises](#exercises) at the end use the code of this repository.

- [1. What "low-tech" means for software](#1-what-low-tech-means-for-software)
- [2. The starting point](#2-the-starting-point)
- [3. What changed and why](#what-changed-and-why)
- [4. What was lost](#4-what-was-lost)
- [5. Measurements](#measurements)
- [6. From bytes to energy](#6-from-bytes-to-energy)
- [7. Limits: is it really low-tech?](#7-limits-is-it-really-low-tech)
- [8. Course material](#8-course-material)
- [9. Further reading](#9-further-reading)

---

## 1. What "low-tech" means for software

The [Low-tech Lab](https://lowtechlab.org) describes low-tech as techniques that are **useful** (they answer
an essential need), **accessible** (in cost, skills and materials, so that people can make, repair and adapt
them) and **sustainable** (they use few resources and last). Low-tech is not "no tech" and not "old tech": it
is the habit of asking, for each part of a system, *is it needed, and is there a simpler way?*

For a web application, these three questions become concrete ones:

| Low-tech principle | Question for software | What it led to in Agora |
| --- | --- | --- |
| **Useful** | Does this feature serve the association, or the developer's taste? | Every feature of the original was kept; decoration was removed (icons, avatars, colours per pole). |
| **Accessible** | Can anyone install it, use it on any device, understand and change the code? | No dependencies, no build; works in any browser, including text browsers and 10-year-old phones; code that a beginner can read. |
| **Sustainable** | How much does it consume, for how long will it keep working? | Pages 70 times lighter, server work 13 times smaller, few moving parts that can break with time. |

The biggest environmental lever of digital technology is not the electricity of servers but the
**manufacturing of devices**. In France, the ADEME–Arcep study on the environmental impact of digital technology
(2022) attributes most of the carbon footprint of digital technology (around 80 %) to user devices, mostly
when they are made. **Software that keeps working on old devices helps people keep them longer.** That is why
"works on an old phone, in an old browser, on a slow connection" is a central goal here, not a detail.

## 2. The starting point

Agora is an open-source workspace for associations ([original repository](https://github.com/EEmeka33/agora)).
Its first version was built the way most web applications are built today:

| Layer | Original choice |
| --- | --- |
| Framework | Next.js 16 (React 19, Server Components, Server Actions) |
| Interface | Tailwind CSS 4, a web font (Inter), an icon library (lucide), a chart library (Recharts), toasts (sonner) |
| Data | Prisma 7 (an ORM with a generated client) over SQLite |
| Tooling | TypeScript, ESLint, Prettier, Vitest, Playwright, Docker |

It is a good application, carefully made. It also illustrates the hidden weight of the "standard" stack:
**590 packages (1.2 GB)** to install before anything runs, a build step, a server process using hundreds of
megabytes of memory, and **about 60 requests and 500 KB per page**, most of it JavaScript that every visitor's
device has to download, parse and run.

<a id="what-changed-and-why"></a>

## 3. What changed and why

### 3.1 The stack: PHP and SQLite, nothing else

| | Original | Low-tech | Why |
| --- | --- | --- | --- |
| Language | TypeScript, compiled | PHP 8.1+, interpreted | PHP is installed on almost every web host, including the €2/month shared hostings associations already use. No build step: the code you read is the code that runs. |
| Server | A Node.js process always running | Nothing runs between requests (PHP-FPM, or Apache's PHP) | "Share-nothing": each request starts from zero and frees everything at the end. Idle cost: zero. |
| Database | Prisma + SQLite | PDO (built into PHP) + SQLite | SQLite is a single file: no database server, backups by copying a file. Queries are plain SQL, readable in `app/` without learning an ORM. |
| Templates | React components (TSX) | PHP files that are HTML with `<?= h($value) ?>` | A page template *is* the page. Someone who knows HTML can change it. |
| Dependencies | 590 | 0 | Each dependency is code to trust, update and eventually replace. Zero means the software can sleep for years and still work. |

**Why not Python, Go, or Node.js without a framework?** All three could make a low-tech Agora. PHP was chosen
for the *deployment* story: associations can upload the folder to a cheap shared hosting by FTP, with no
process to keep alive, which is exactly how established association software (Paheko, Galette) works. Python
or Go would need a server you control. Discuss this trade-off in class: it is a real one.

### 3.2 No JavaScript

The interface is made only of **links (GET) and forms (POST)**. The browser does what it has done since 1995:
display HTML and send forms. The Content-Security-Policy header even forbids scripts (`default-src 'none'`),
so the "no JavaScript" rule is enforced by the browser, and the test suite checks that no page contains one.

How the interactive parts were replaced:

| Original (JavaScript) | Low-tech (HTML) |
| --- | --- |
| Drag and drop between columns of the task board | ◀ ▶ buttons on each card: two tiny forms |
| Modal dialogs to create things | `<details>`: a native disclosure widget, open/closed without code |
| "Are you sure?" confirmation dialogs | A `<details>` whose content is the real "Yes, delete" button |
| Toast notifications | A message shown at the top of the next page (passed in a short-lived cookie) |
| Command palette (Ctrl+K) | A search form in the header, and a search page you can bookmark |
| Live form validation | HTML attributes (`required`, `type="email"`, `min`) and the server's answer, shown next to the fields |
| Progress rings, charts (Recharts) | Native `<progress>` and `<meter>` elements, and bars drawn with text characters (`█▌`) |
| Gantt timeline (JavaScript) | A table: one cell per month, coloured by CSS |
| Bell that polls the server every 60 s | A count computed when the page loads |
| Dark mode toggle | The device's preference (`prefers-color-scheme`), no toggle |

After each POST, the server answers with a redirect (*Post/Redirect/Get*): reloading a page never submits a
form twice, and the back button works.

### 3.3 Light pages

- **No web font, no icon, no image** in the interface: the system font of each device is used.
- **One stylesheet** of 12 KB (3.4 KB compressed), cached for a year. The pages also work *without* it.
- **Compression and caching**: HTML is compressed (a 10 KB page travels as about 3 KB) and has an ETag, so an
  unchanged page is answered with "304 Not modified" and no body.
- **Print stylesheet**: menus and buttons disappear on paper. Meeting minutes can be printed and signed.
- **Each page loads only what it shows.** A project is split in tabs (overview, tasks, updates, team, budget)
  that each run their own queries; the task board shows done tasks of the last 30 days only; the activity feed
  has pages. During the rewrite, the finance page first carried a hidden edit form for each transaction: it
  weighed **71 KB**. Moving editing to its own page brought it to **20 KB**. Interesting detail for class:
  compressed, the difference was much smaller (4.9 KB against 3.9 KB), because repeated forms compress well;
  but the server still had to build, and the browser still had to parse, all those forms.
- The footer of every page shows **its own weight** and the time it took to build it.

### 3.4 Sober by default

- **Photos are reduced on upload** (longest side 1600 px, JPEG quality 80): a 4 MB phone picture of a receipt
  becomes 200–400 KB. Upload size is limited to 5 MB by default.
- **Emails are plain text**, and each person receives by default **one daily summary** instead of one email per
  notification. Fewer emails, fewer interruptions.
- **Data has an expiry date**: read notifications and activity older than a year are deleted automatically
  (`retention_days`). Expired sessions and links too. Less data means smaller backups and less to protect.
- **No live updates**: what's new is shown when you open a page. The application is calm.
- The README screenshots are dithered to 8 colours (like [Low-tech Magazine](https://solar.lowtechmagazine.com)'s
  images): 12 to 31 KB each instead of 64 to 184 KB.

### 3.5 Simple operations

| Original | Low-tech |
| --- | --- |
| `npm install`, `npm run setup`, `npm run build`, then a process manager or Docker | Copy the folder; point the web server to `public/` |
| Prisma migrations, regenerated client | `app/schema.sql` applied on the first request; later changes in `app/migrations/`, applied automatically |
| A backup script in Node.js | `php bin/backup.php` (SQLite's `VACUUM INTO` + a copy of the files) |
| Notification emails sent by the Node.js process | Sent after the page is delivered; daily digests and cleanup run by a "lazy cron" (at most once an hour, on a visit) or a real cron job |
| Docker image | None needed. A systemd unit and nginx/Caddy examples are in `docs/server/` |

### 3.6 What was kept on purpose

Low-tech is not a reason to lower quality where it matters:

- **Security**: Argon2id passwords, sessions stored hashed and revocable, account lockout, rate limits,
  anti-CSRF tokens, strict Content-Security-Policy, uploaded files served in a sandbox. See [SECURITY.md](../SECURITY.md).
- **Privacy between associations**: every query of association data filters on `association_id`; the tests
  check that a member of another association gets "404 Not found" everywhere.
- **Two languages**, and the way to add more: one file per language in `app/lang/`.
- **Tests**: 400+ checks, written in plain PHP, run in about 3 seconds, including a **low-tech budget**: no page
  may contain a script, an inline style or an external resource, or weigh more than 30 KB.
- **Accessibility**: semantic HTML (headings, tables, labels around inputs, `aria-current`), a "skip to
  content" link, the content before the menu in the HTML, readable without styles.

## 4. What was lost

Being honest about trade-offs is part of the method. Things that are worse, or gone:

- **Fewer conveniences**: no drag and drop, no instant search while typing, no autocomplete in member lists,
  no "copy to clipboard" button. Each action reloads the page (a few kilobytes: fast, but visible).
- **No real-time**: a new notification appears when you open a page, not by itself.
- **Less decoration**: no avatars, icons or colours per pole and project. Some people find the original nicer
  to look at, and that matters for adoption in an association. Discuss it.
- **Fewer deployment options out of the box**: no Docker image; the PostgreSQL switch that Prisma allowed is
  gone (SQLite comfortably serves an association, or many).
- **PHP's built-in server** is fine for a local network but not meant for the Internet: behind Apache, nginx or
  Caddy (examples in `docs/server/`), PHP-FPM handles the load.
- **TypeScript's safety net** is gone; it is replaced by small functions, `declare(strict_types=1)` in tests,
  and an end-to-end test that clicks through every page.

<a id="measurements"></a>

## 5. Measurements

### Results

Measured on the same machine (a cloud container, 2026), with the demo data of each version (similar sizes:
one association of 9 members with projects, tasks, events, votes and transactions).

| Measure | Original | Low-tech | Ratio |
| --- | --- | --- | --- |
| Packages installed | 590 | 0 | — |
| Disk taken by dependencies | 1.2 GB | 0 | — |
| Install + build time | 17 s + 16 s | 0 s | — |
| Size of the code you can read | ~20,000 lines | ~8,500 lines | ÷ 2.3 |
| Page weight, empty cache (dashboard) | 610 KB, 72 requests | 7 KB, 2 requests | ÷ 85 |
| Page weight, empty cache (other pages) | 445–585 KB, 58–66 requests | 6–8 KB, 2 requests | ÷ 70 |
| JavaScript per page | 300–450 KB | 0 | — |
| Page weight, stylesheet cached | ~120 KB, 73 requests | 3.5 KB | ÷ 35 |
| HTML of the dashboard (uncompressed) | 118 KB | 9 KB | ÷ 13 |
| Server CPU per page (dashboard, tasks) | 21–26 ms | 1.6–1.7 ms | ÷ 13 to 15 |
| Server memory | 290 MB after browsing, 950 MB after 600 requests | ~20 MB per PHP worker; nothing when idle with PHP-FPM | ÷ 15 or more |
| Time to run the whole test suite | Vitest + a Playwright story, minutes (needs a build) | ~3 s | — |

### How to reproduce them

All measurements can be repeated by students, on any computer:

1. **Page weight**: open the browser's developer tools (F12), tab *Network*, tick *Disable cache*, reload.
   Read the bottom line ("transferred" and "resources"). Untick *Disable cache* and reload for the cached weight.
   The footer of each Agora page also shows its weight.
2. **JavaScript**: same tab, filter on *JS*.
3. **Server CPU per page**: send 300 requests and read the CPU time of the server process in
   `/proc/<pid>/stat` (fields 14 to 17, in hundredths of a second) before and after. On a Raspberry Pi, a
   wattmeter between the plug and the Pi gives the energy directly.
4. **Memory**: `ps -o rss,args -C php` (or `-C node`) while the server runs.
5. **Dependencies**: `npm ls --all --parseable | wc -l` and `du -sh node_modules` for the original; for this
   version, there is nothing to count.

**Caveats.** A cloud container is not a real computer: absolute numbers will differ on your machine, ratios
much less. CPU time is not energy, but on the same machine they are roughly proportional. The demo data of the
two versions are similar, not identical.

## 6. From bytes to energy

It is tempting to convert kilobytes into grams of CO₂. The published models disagree by a factor of 10 to 100,
because the energy of a network barely depends on the data that goes through it at a given moment. Use them,
if at all, to compare *ratios*, never as precise values.

A **worked example** that holds without any model: an association of 30 members, each opening 20 pages a day,
220 days a year, i.e. 132,000 page views a year.

| | Original | Low-tech |
| --- | --- | --- |
| Data downloaded per year (≈120 KB per page with cache, 500 KB without) | ~16 to 66 GB | ~0.5 to 1 GB |
| Server CPU time per year (≈23 ms vs 1.7 ms per page) | ~50 minutes | ~4 minutes |
| JavaScript each member's device must download and run | 300–450 KB per visit, kept running while browsing | none |

The server side is tiny in both cases: an association is a small workload. The decisive difference is on the
**devices**: the original needs a recent browser and enough processing power to run hundreds of kilobytes of
JavaScript per page; the low-tech version runs on whatever device members already have. That is where most of
the footprint lies (section 1).

## 7. Limits: is it really low-tech?

Questions without a single answer, to discuss:

- **Is a web application low-tech at all?** It still needs the Internet, a server and devices. For a small
  association, a notebook, a shared spreadsheet or a mailing list might be the *really* low-tech answer.
  Agora makes sense when several poles must coordinate, but this should be asked first.
- **PHP, SQLite and browsers are huge pieces of software.** Low-tech here means *not adding* complexity on top of
  what is already installed everywhere, not that the whole stack is simple.
- **Rebound effects**: a lighter app could encourage more use, more notifications, more associations hosted.
  The daily digest and the data retention are attempts to limit this.
- **Who maintains it?** Zero dependencies means fewer security updates to apply, but also that nobody else
  fixes bugs for you. The code is short on purpose so that a volunteer can take it over.
- **Accessibility vs. comfort**: people used to modern apps may find it austere. Low-tech that nobody uses is
  not useful.

## 8. Course material

### A possible sequence (3 × 2 hours)

1. **Diagnose** (2 h): install the [original Agora](https://github.com/EEmeka33/agora) and this version. Measure
   both (section 5). List every part of the original stack and say what it is for.
2. **Read and discuss** (2 h): read `app/routes.php` (the whole surface of the app), `app/schema.sql` (the whole
   data model), one page (`app/pages/tasks.php` and `app/views/tasks/`). Discuss sections 4 and 7.
3. **Build** (2 h): pick one or two exercises below, in small groups.

### Exercises

Ordered from easiest to hardest. Run `php tests/run.php` after each change.

1. **Measure.** Fill the table of section 5 on your own computer. Then throttle the network to "Slow 3G" in the
   developer tools and time the opening of the dashboard in both versions.
2. **Use it without a mouse, without styles, without a screen.** Navigate with the keyboard only; disable CSS
   (Firefox: *View → Page Style → No Style*); try `lynx http://localhost:8080` or `w3m`; try a screen reader.
   Note what breaks and propose fixes.
3. **Paper.** Print the page of a meeting (agenda and minutes) and of the finance page. Improve the print
   stylesheet at the end of `public/style.css` so that a printed page is useful as an official record.
4. **Run it on a Raspberry Pi or an old laptop.** Follow `docs/self-hosting.md`. Measure the power with a
   wattmeter at rest and while 10 people use it. Compare with the power of a phone charger.
5. **Translate.** Add a language: copy `app/lang/fr.php`, translate, add it to `LOCALES` in
   `app/lib/constants.php`. How long did it take? What does it say about repairability?
6. **Remove something.** Find a feature or a piece of code you think is not needed. Argue for removing it, remove
   it, and measure what was gained (lines, kilobytes, milliseconds).
7. **Add a feature the low-tech way: a tool library.** Low-tech associations often lend tools (drills, sewing
   machines, bike stands). Add a `tools` table, a page listing them and a form to lend/return one, without
   JavaScript. Follow the recipe in `docs/architecture.md`. Check the low-tech budget test still passes.
8. **A repair log for the repair café.** Record each object brought (type, weight, repaired or not) and show,
   on the pole page, the kilograms of waste avoided this year. Present it with text bars, like the finance page.
9. **Solar mode.** [Low-tech Magazine's website](https://solar.lowtechmagazine.com) runs on a solar panel and
   shows its battery level. Imagine Agora on a solar-powered Raspberry Pi: show the battery level in the footer
   (read it from a file), and make Agora sober when the battery is low (e.g. no digests, no uploads).
10. **Offline and asynchronous.** How could members who have no Internet access during the week still take part
    (printed weekly summary, SMS, email replies)? Prototype one idea.

### Questions for an essay or an oral exam

- Which of the original's dependencies was the hardest to give up, and why?
- Is "zero JavaScript" a principle or a means? When would a little JavaScript be justified?
- How would you decide, for an association you know, between this tool, a spreadsheet and a notebook?
- What would make this software last 20 years? What could stop it?

## 9. Further reading

- Low-tech Lab — [lowtechlab.org](https://lowtechlab.org): definitions, tutorials, the "useful, accessible,
  sustainable" triad.
- Philippe Bihouix, *L'Âge des low tech* (Seuil, 2014): the founding essay in French.
- Low-tech Magazine — [solar.lowtechmagazine.com](https://solar.lowtechmagazine.com), and its article *How to
  build a low-tech website?* (2018): the solar-powered website, dithered images, a static site.
- W3C — [Web Sustainability Guidelines](https://www.w3.org/TR/web-sustainability-guidelines/): an international
  checklist for sustainable web design.
- French government — RGESN, *Référentiel général d'écoconception de services numériques*
  ([ecoresponsable.numerique.gouv.fr](https://ecoresponsable.numerique.gouv.fr)).
- ADEME and Arcep — studies on the environmental footprint of digital technology in France
  ([arcep.fr](https://www.arcep.fr)).
- Permacomputing — [permacomputing.net](https://permacomputing.net): computing that lasts and uses what exists.
