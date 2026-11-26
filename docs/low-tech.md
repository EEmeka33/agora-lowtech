# Agora, low-tech edition: the process, the numbers and course material

This document tells how an existing web application was made lighter **without losing what made it good**,
step by step, with a measurement before and after each step. It is written to be used as **course material**:
each section can be read on its own, every number can be measured again by students, and every step is one
commit of this repository, so the whole process can be replayed.

- [1. What "low-tech" means for software](#1-what-low-tech-means-for-software)
- [2. Two attempts](#2-two-attempts)
- [3. The method](#3-the-method)
- [4. The starting point](#4-the-starting-point)
- [5. The steps](#5-the-steps)
- [6. Results](#6-results)
- [7. What is still heavy, and why it stays](#7-what-is-still-heavy-and-why-it-stays)
- [8. From bytes to energy](#8-from-bytes-to-energy)
- [9. Limits and questions](#9-limits-and-questions)
- [10. Course material](#10-course-material)
- [11. Further reading](#11-further-reading)

---

## 1. What "low-tech" means for software

The [Low-tech Lab](https://lowtechlab.org) describes low-tech as techniques that are **useful** (they answer an
essential need), **accessible** (in cost, skills and materials, so that people can make, repair and adapt them)
and **sustainable** (they use few resources and last). Low-tech is not "no tech" and not "old tech": it is the
habit of asking, for each part of a system, _is it needed, and is there a simpler way?_

| Principle       | Question for software                                                        | In this project                                                                                 |
| --------------- | ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| **Useful**      | Does it serve the people who use it? Would they still want to use it?        | Every feature and the whole design were kept. A tool nobody wants to use is not useful.         |
| **Accessible**  | Can it run on modest hardware, on the devices people already have?           | Pages 2.7 times lighter, a server that fits in a small machine, pages that print.               |
| **Sustainable** | What does it consume, on the server, on the network, on devices, over years? | No background traffic, less data kept, one email a day instead of one per event, a budget test. |

The largest environmental lever of digital technology is not the electricity of servers but the
**manufacturing of devices**: in France, the ADEME–Arcep study (2022) attributes about 80 % of the footprint of
digital technology to user devices, mostly when they are made. Software that stays light helps people keep
their devices longer.

## 2. Two attempts

This repository is the **second** attempt. The first one is kept on the `php-rewrite` branch, because what went
wrong is the most useful lesson of the course.

**First attempt: rewrite everything.** The original application was rewritten in plain PHP and SQLite, with no
dependency, no build step and no JavaScript. On paper it was a triumph: about **11 KB per page** instead of
600 KB, a server 13 times less busy, nothing to install. The first person who used it for a real association
said:

> _It might be a bit too much: the website isn't practical and it's ugly. It also seems broken: it doesn't fit
> in the window for no reason._

All three were true. The rewrite had lost the qualities nobody had measured: the comfort of the interface, its
care, the small interactions that make people want to use a tool. A second pass fixed the bugs and improved the
design, but the verdict stayed: "it isn't interesting".

**Second attempt: eco-design the existing application.** Start again from the original, keep its design and its
features, and remove only what costs a lot without giving anything to the people who use it. Measure before and
after every change, and keep a change only if the numbers say it was worth it.

**The lesson**: low-tech is a _means_ (useful, accessible, sustainable), not a purity contest. The rewrite
optimized one number (kilobytes) and broke the first principle (useful). The second attempt gains less on that
number (÷ 2.7 instead of ÷ 55) but keeps everything people liked. Which one is "more low-tech" is an excellent
question for a debate (section 9).

## 3. The method

The same loop, ten times:

1. **Measure**, the same way every time, on the same machine and the same data.
2. **Find what weighs**: which file, which library, which behaviour.
3. **Ask** what it gives to the people who use the tool. If it gives a lot, keep it, even if it is heavy.
4. **Act** on the heaviest thing that gives little.
5. **Measure again**, and write down the number and the trade-off in the commit message.

The tools, all free and available to students:

| What                                   | How                                                                                                                                                               |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Weight of a page, requests             | Browser developer tools (F12), tab _Network_, tick _Disable cache_, reload, read the bottom line. Automated: `tests/e2e/budget.spec.ts` prints it for every page. |
| What is in the JavaScript              | `npx next analyze` (Next.js bundle analyzer), or open the `.js` files of `.next/static/chunks` and search for a library's name.                                   |
| Server processor time per page         | Send 300 requests, read the CPU time of the server process in `/proc/<pid>/stat` (fields 14 and 15) before and after.                                             |
| Server memory                          | `ps -o rss,args -C node` (or the `next-server` process) at start and after a few hundred requests.                                                                |
| Disk and dependencies                  | `du -sh node_modules .next/standalone`, number of entries in `package-lock.json`.                                                                                 |
| Requests made by a tab nobody looks at | Leave the page open with the _Network_ tab and wait; automated with a fake clock in the budget test.                                                              |

**Caveats.** The numbers below were measured in a cloud container, with the demo data (`npm run db:seed`) and
Chromium. Absolute numbers will differ on your computer; ratios much less. Processor time varies a lot from one
run to the next on a shared machine: **always measure at least twice** (step 7 shows why).

## 4. The starting point

[Agora](https://github.com/EEmeka33/agora) is a workspace for associations: founding checklist, poles, requests
between poles, projects, tasks, votes, events and minutes, money, documents, members, in English and French. It
is built the way most web applications are built today: Next.js 16 (React 19, Server Components, Server Actions),
Tailwind CSS 4, Prisma 7 over SQLite, a web font (Inter), an icon library (lucide), a chart library (Recharts), a
toast library (sonner).

Where the **614 KB** and **76 requests** of the dashboard came from (empty cache, compressed bytes):

| Part                                                                | Weight  |
| ------------------------------------------------------------------- | ------- |
| JavaScript of the page (React, the Next.js router, the app's code…) | 210 KB  |
| Pages prefetched in advance: 42 requests, with their JavaScript     | ~318 KB |
| Web font (Inter)                                                    | 48 KB   |
| Stylesheet                                                          | 17 KB   |
| HTML                                                                | 21 KB   |

More than half of the weight was **pages the person had not asked for**: Next.js prefetches every link visible
on the screen (the menu, the cards of the dashboard…), with the code of each page.

## 5. The steps

Each step is one commit; `git log --oneline` lists them. Weights are for an empty cache.

### Step 1: prefetch on intent, not on sight

_Commit "Prefetch pages on intent instead of on sight"._ Links now prefetch a page when the pointer hovers it,
when it gets the keyboard focus or when a finger touches it (`src/components/ui/link.tsx`), instead of as soon as
the link is visible.

- Dashboard: **614 → 296 KB, 76 → 15 requests**. Every page: about ÷ 2.
- Trade-off: going to another page is no longer free (it was already downloaded); it now costs 15 to 20 KB at
  the moment of the click. With a mouse, hovering starts it a moment before the click, so it still feels
  instant. On a phone, the download starts with the touch.

### Step 2: the system font

_Commit "Use the system font instead of downloading Inter"._ The interface uses the font of each device (San
Francisco on Apple devices, Segoe UI on Windows, Roboto on Android).

- Dashboard: **296 → 248 KB**, one request less.
- Trade-off: the text looks slightly different from one device to another; sizes, spacing and colours stay the
  same.

### Step 3: charts drawn on the server

_Commit "Draw the finance charts on the server, without Recharts"._ The two finance charts are plain HTML and CSS
rendered on the server (`src/components/finance/charts.tsx`), with a hidden table for screen readers.

- Finance page: **359 → 247 KB**.
- Trade-off: no animation; the details of a month appear in a tooltip (`title`) instead of a floating box.

### Step 4: stop polling

_Commit "Stop polling for notifications"._ The bell used to ask the server for new notifications every minute,
in every open tab, all day. It now refreshes when the person comes back to the tab, and after each navigation.

- An idle tab: **1,440 requests a day → 0**. Each of those requests woke the server and read the database.
- Trade-off: a notification does not appear by itself while someone stares at the same page without moving.

### Step 5: one language at a time

_Commit "Load only the dictionary of the page's language"._ The English and French texts were both sent to
every visitor. Each language is now a separate file, loaded on demand (`src/lib/i18n/client.tsx`).

- Every page: **about −10 KB** (dashboard 248 → 238 KB).
- Trade-off: none visible.

### Step 6: a small toast component

_Commit "Replace the toast library with a small component"._ The small messages that confirm an action
("Saved") were shown by a library; a 90-line component does the same (`src/components/ui/toast.tsx`).

- Every page: **about −8 KB** (dashboard 238 → 230 KB, of which 192 KB of JavaScript).
- Trade-off: no swipe to dismiss, fewer animations.

### Step 7: a small server

_Commits "Run a small standalone server with a memory cap" and "Always apply the server memory cap"._
`npm start` runs Next.js's standalone output: only the files the server needs, and a cap on its memory
(`scripts/start.mjs`, `AGORA_MAX_MEMORY_MB`, 192 MB of JavaScript heap by default).

- Disk needed to run: **1.2 GB of `node_modules` → 88 MB**. Build on a computer, copy to a Raspberry Pi.
- Memory after 600 requests: **more than 900 MB → about 325 MB**, stable after 1,200 requests.
- Trade-off: **processor time**. The garbage collector works more often: each page costs 20 to 50 % more
  (about 25 ms instead of 18 ms). On a small machine memory is the hard limit; on a big one, raise the cap.
- **A lesson in measuring.** The first measurement of this step said "+5 % of processor time": one run, on a
  shared machine. Later, the final measurement showed the cap was not applied at all in that environment (it
  sets a large cap for every process through `NODE_OPTIONS`, and the start script gave way to it). Measured again,
  twice, the cost was 20 to 50 %. Both mistakes were caught only because the final numbers were measured again
  instead of copied from the step.

### Step 8: keep less data

_Commit "Keep less data: smaller photos, automatic cleanup, one email a day"._

- **Photos** uploaded as documents are reduced to 1600 pixels and lose their metadata, GPS position included
  (`src/lib/images.ts`). A 12-megapixel test photo of **5.3 MB is stored as 0.2 MB**. Every member who opens it
  downloads 0.2 MB instead of 5.3 MB, and backups shrink as much.
- **Cleanup** (`src/lib/maintenance.ts`), once a day, scheduled by page visits (no cron job, no timer: when
  nobody uses Agora, nothing runs): read notifications after 90 days, activity history after one year
  (`RETENTION_DAYS`), expired sessions, old one-time links, files no document points to. Money, documents,
  minutes and everything members wrote are never deleted.
- **Email**: by default, **one summary a day at most**, listing only the notifications the person has not seen in
  Agora; nothing is sent when everything was read. People can still choose each notification right away, or no
  email at all.
- Trade-off: the original photo is not kept (keep it elsewhere if it matters); the activity history stops at one
  year (adjustable).

### Step 9: paper

_Commit "Print pages cleanly"._ Minutes of a general assembly, a task list or the accounts are often read on
paper, in a meeting. Printed pages now show only the content: no menu, no buttons, no shadows and no background
ink; dark mode prints in light colours; meeting minutes print in full; chart bars are kept because they carry
information.

### Step 10: a budget, checked by a test

_Commit "Add a low-tech budget test"._ `tests/e2e/budget.spec.ts` turns the gains into rules that a future change
cannot silently break:

| Rule                                 | Limit  | Now        | Original                                |
| ------------------------------------ | ------ | ---------- | --------------------------------------- |
| Weight of a page, empty cache        | 260 KB | 220–228 KB | 430–576 KB                              |
| JavaScript of a page                 | 210 KB | 187–192 KB | 302–431 KB                              |
| Requests to show a page              | 20     | 15–16      | 53–63                                   |
| Fonts to download                    | 0      | 0          | 1                                       |
| Pages prefetched before a click      | 0      | 0          | 30 on the dashboard, 27 on the tasks    |
| Moving to another page               | 40 KB  | 7–15 KB    | 0 KB (everything was prefetched before) |
| Requests from a tab left open 10 min | 0      | 0          | 10                                      |

The same test, run on the original application, fails on every line but one. Run it with
`npm run build && npm run test:e2e`.

## 6. Results

Same machine, same demo data, Chromium.

| Measure                                     | Original             | Low-tech edition               | Change           |
| ------------------------------------------- | -------------------- | ------------------------------ | ---------------- |
| Dashboard, empty cache                      | 614 KB, 76 requests  | 230 KB, 15 requests            | ÷ 2.7            |
| Other pages, empty cache                    | 445–585 KB           | 220–230 KB                     | ÷ 2 to 2.6       |
| A page with the cache warm                  | 76–99 KB             | 20–24 KB                       | ÷ 4              |
| JavaScript per page                         | 300–450 KB           | 187–192 KB                     | ÷ 1.6 to 2.3     |
| Requests from an idle tab, per day          | 1,440                | 0                              | —                |
| Disk needed to run the server               | 1.2 GB               | 88 MB                          | ÷ 14             |
| Server memory after 600 requests            | 790 MB               | 322–326 MB                     | ÷ 2.4            |
| Server processor time per page              | 18–24 ms             | 23–28 ms                       | **+ 20 to 50 %** |
| A 12-megapixel photo, stored and downloaded | 5.3 MB               | 0.2 MB                         | ÷ 26             |
| Emails                                      | one per notification | at most one a day, unread only | —                |
| Features, design                            | —                    | unchanged                      | —                |

For comparison, the PHP rewrite of the first attempt (`php-rewrite` branch): about 11 KB per page, 1.5 to
1.8 ms of processor time, no JavaScript needed, works in a 10-year-old browser, and a much plainer interface.

## 7. What is still heavy, and why it stays

The 192 KB of JavaScript of a page are now mostly the framework itself:

| Part                                                     | Compressed |
| -------------------------------------------------------- | ---------- |
| React (`react-dom`)                                      | 70 KB      |
| The Next.js router and runtime                           | 60 KB      |
| The application's shell: menu, top bar, search and icons | 30 KB      |
| The texts of the interface, in one language              | 11 KB      |
| The code of the page itself                              | ~20 KB     |

Removing React means rewriting the application: that was the first attempt. What it pays for: drag and drop on
the task board, dialogs, instant search, navigation without reloading, the command palette (Ctrl K). The same
reasoning applies to the processor time of the server, which a framework that renders React costs more than
plain PHP templates.

Next.js also requires a browser from 2023 or later (Chrome, Edge and Firefox 111, Safari 16.4). The PHP rewrite
works in any browser. For an association whose members have old phones, that may decide.

## 8. From bytes to energy

It is tempting to convert kilobytes into grams of CO₂. The published models disagree by a factor of 10 to 100,
because the energy of a network barely depends on the data that goes through it at a given moment. Use them, if
at all, to compare ratios, never as precise values.

A **worked example** that holds without any model: an association of 30 members, each opening 20 pages a day,
220 days a year, that is 132,000 pages a year, most of them with a warm cache.

|                                                  | Original          | Low-tech edition |
| ------------------------------------------------ | ----------------- | ---------------- |
| Data downloaded per year (≈ 90 KB vs 22 KB/page) | ~12 GB            | ~3 GB            |
| Requests from 10 tabs left open 8 hours a day    | ~1 million a year | 0                |
| Server processor time per year (≈ 20 vs 25 ms)   | ~45 minutes       | ~55 minutes      |
| Server memory                                    | up to ~900 MB     | ~325 MB          |

Two things stand out. The **server side is tiny** in both cases: an association is a small workload, and the
memory cap even costs ten minutes of processor time a year. What it buys is the ability to run on a small,
second-hand machine that would not hold 900 MB. On the **device side**, the JavaScript each member downloads and
runs is halved, but still there: that is where the PHP rewrite remains far ahead.

## 9. Limits and questions

Questions without a single answer, to discuss:

- **Which attempt is more low-tech?** The PHP rewrite is 20 times lighter and runs everywhere; this edition is
  pleasant and was adopted. Is a light tool that nobody wants to use sustainable?
- **Is a web application low-tech at all?** For a small association, a notebook, a shared spreadsheet or a
  mailing list might be the really low-tech answer. Agora makes sense when several poles must coordinate.
- **Rebound effects.** A lighter, nicer tool can be used more. The daily summary and the data retention are
  attempts to limit this; are they enough?
- **Who maintains it?** This edition keeps 688 packages (with the development tools) and a framework that
  changes every year. The PHP
  rewrite has none, but nobody else fixes its bugs.
- **Trade-offs hidden in a single number.** Step 7 lowered memory and raised processor time. Which one matters
  depends on the machine. A single "score" would have hidden it.

## 10. Course material

### A possible sequence (3 × 2 hours)

1. **Diagnose** (2 h): install the [original Agora](https://github.com/EEmeka33/agora), measure the dashboard
   with the developer tools (section 3). Make the table of section 4 yourselves: where do the 614 KB come from?
2. **Replay the process** (2 h): in groups, each takes two steps. `git checkout` the commit before the step and
   the commit of the step, build both, measure, and present: what was removed, what it cost, what it gave, what
   was lost.
3. **Build and debate** (2 h): one exercise below, then a debate on section 9 with the `php-rewrite` branch open
   next to this one.

### Exercises

Ordered from easiest to hardest. Run `npm test` (and `npm run build && npm run test:e2e` for the last ones)
after each change.

1. **Measure.** Fill the table of section 6 on your own computer. Then throttle the network to "Slow 3G" in the
   developer tools and time the dashboard of the original and of this edition, empty cache and warm cache.
2. **Lower the budget.** In `tests/e2e/budget.spec.ts`, lower the page budget to 200 KB. The test fails: find
   what to remove to make it pass again, without removing anything people use. (Hint: `npx next analyze`.)
3. **The memory cap.** Start the server with `AGORA_MAX_MEMORY_MB` at 128, 256, 512 and 4096. For each, measure
   memory and processor time after 600 requests (twice!). Draw both curves. Which value would you choose for a
   Raspberry Pi with 1 GB of memory? For a server with 16 GB?
4. **JavaScript off.** Disable JavaScript in the browser and use Agora. What still works (pages are rendered on
   the server)? What breaks? Compare with the `php-rewrite` branch, which works entirely without it.
5. **Paper.** Print a meeting (agenda and minutes) and the finance page. Improve the print rules at the end of
   `src/app/globals.css` so that printed minutes can serve as an official record (association name, date, page
   numbers).
6. **Retention.** Read `src/lib/maintenance.ts`. Which data does the association really need, and for how long
   (the GDPR calls this data minimisation)? Propose a retention for each table and argue for it.
7. **A feature within the budget: a tool library.** Low-tech associations lend tools (drills, sewing machines,
   bike stands). Add a model, a page listing the tools and a form to lend and return one. Follow
   `docs/architecture.md`. The budget test must still pass.
8. **Icons.** Count the icons used (`lucide-react` imports) and measure what they weigh. Would an SVG sprite or
   fewer icons be worth it? Measure before deciding.
9. **Solar mode.** [Low-tech Magazine's website](https://solar.lowtechmagazine.com) runs on a solar panel and
   shows its battery level. Imagine Agora on a solar-powered Raspberry Pi: read the battery level from a file
   and make Agora more sober when it is low (no summaries, no uploads, a warning in the top bar).
10. **Where is the line?** Pick one thing this edition kept (drag and drop, the command palette, React itself)
    and one thing the PHP rewrite removed. For each, estimate what it costs (KB, ms, lines of code, maintenance)
    and what it gives. Present your choice.

### Questions for an essay or an oral exam

- The first attempt was lighter and was rejected. What does it say about how to measure "low-tech"?
- Step 7 improved one number and degraded another. Find a similar trade-off in another field.
- How would you decide, for an association you know, between this tool, the PHP rewrite, a spreadsheet and a
  notebook?
- What would make this software last 20 years? What could stop it?

## 11. Further reading

- Low-tech Lab, [lowtechlab.org](https://lowtechlab.org): definitions, tutorials, the "useful, accessible,
  sustainable" triad.
- Philippe Bihouix, _L'Âge des low tech_ (Seuil, 2014): the founding essay, in French.
- Low-tech Magazine, [solar.lowtechmagazine.com](https://solar.lowtechmagazine.com), and its article _How to
  build a low-tech website?_ (2018).
- W3C, [Web Sustainability Guidelines](https://www.w3.org/TR/web-sustainability-guidelines/): an international
  checklist for sustainable web design.
- French government, RGESN, _Référentiel général d'écoconception de services numériques_
  ([ecoresponsable.numerique.gouv.fr](https://ecoresponsable.numerique.gouv.fr)).
- ADEME and Arcep, studies on the environmental footprint of digital technology in France
  ([arcep.fr](https://www.arcep.fr)).
- Permacomputing, [permacomputing.net](https://permacomputing.net): computing that lasts and uses what exists.
