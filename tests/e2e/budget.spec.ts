import { expect, test, type Page } from "@playwright/test";

/**
 * The low-tech budget of Agora: what showing a page may cost, measured in Chromium with an empty cache
 * (compressed bytes, as they travel on the network). Before the low-tech work the dashboard cost 614 KB and
 * 76 requests (docs/low-tech.md). Raise a limit only on purpose, and write down why.
 *
 * Runs after the story of smoke.spec.ts, on the association it created.
 */
const BUDGET = {
  /** Everything needed to show a page with an empty cache. */
  pageKB: 260,
  /** JavaScript of a page. */
  scriptKB: 210,
  /** Requests to show a page. */
  requests: 20,
  /** Going to another page once the app is loaded. */
  navigationKB: 40,
};
const PAGES = ["/", "/tasks", "/requests", "/projects", "/events", "/polls", "/finance", "/documents", "/members"];

type Entry = { url: string; type: string; bytes: number; prefetch: boolean };

let page: Page;
let entries: Entry[] = [];
let clearCache: () => Promise<unknown>;

test.beforeAll(async ({ browser }) => {
  page = await browser.newPage();
  await page.goto("/login");
  await page.locator('input[name="email"]').fill("alex@example.org");
  await page.locator('input[name="password"]').fill("garden-founder-2026");
  await Promise.all([
    page.waitForURL((u) => !u.pathname.startsWith("/login")),
    page.getByRole("button", { name: "Sign in" }).click(),
  ]);
  await page.waitForLoadState("networkidle");

  // Every response, with its size on the network (Chrome DevTools Protocol).
  const cdp = await page.context().newCDPSession(page);
  await cdp.send("Network.enable");
  const started = new Map<string, Omit<Entry, "bytes">>();
  cdp.on("Network.requestWillBeSent", (e) => {
    const prefetch = Object.keys(e.request.headers).some((h) => h.toLowerCase() === "next-router-prefetch");
    started.set(e.requestId, { url: e.request.url, type: e.type ?? "Other", prefetch });
  });
  cdp.on("Network.responseReceived", (e) => {
    const r = started.get(e.requestId);
    if (r) r.type = e.type;
  });
  cdp.on("Network.loadingFinished", (e) => {
    const r = started.get(e.requestId);
    if (r) entries.push({ ...r, bytes: e.encodedDataLength });
  });
  clearCache = () => cdp.send("Network.clearBrowserCache");
});

test.afterAll(async () => {
  await page?.close();
});

const kb = (list: Entry[]) => Math.round(list.reduce((n, e) => n + e.bytes, 0) / 102.4) / 10;

test("each page stays within the budget, with an empty cache", async () => {
  for (const path of PAGES) {
    await clearCache();
    entries = [];
    await page.goto(path, { waitUntil: "networkidle" });
    await page.waitForTimeout(300);
    const scripts = entries.filter((e) => e.type === "Script");
    const summary = `${path}: ${kb(entries)} KB in ${entries.length} requests, ${kb(scripts)} KB of JavaScript`;
    console.log(summary);
    expect.soft(kb(entries), summary).toBeLessThanOrEqual(BUDGET.pageKB);
    expect.soft(kb(scripts), summary).toBeLessThanOrEqual(BUDGET.scriptKB);
    expect.soft(entries.length, summary).toBeLessThanOrEqual(BUDGET.requests);
    expect
      .soft(
        entries.filter((e) => e.type === "Font"),
        `${path}: no font to download`,
      )
      .toEqual([]);
    expect
      .soft(
        entries.filter((e) => e.prefetch),
        `${path}: nothing prefetched before a click`,
      )
      .toEqual([]);
  }
});

test("moving to another page only loads that page", async () => {
  await page.goto("/", { waitUntil: "networkidle" });
  entries = [];
  await page.locator('nav a[href="/tasks"]').filter({ visible: true }).first().click();
  await page.waitForURL((u) => u.pathname === "/tasks");
  await page.waitForLoadState("networkidle");
  console.log(`/ → /tasks: ${kb(entries)} KB in ${entries.length} requests`);
  expect(kb(entries)).toBeLessThanOrEqual(BUDGET.navigationKB);
});

test("an open tab stays quiet", async () => {
  await page.clock.install();
  await page.goto("/", { waitUntil: "networkidle" });
  entries = [];
  // Ten minutes of an idle tab, compressed into a moment.
  await page.clock.runFor("10:00");
  await page.waitForTimeout(500);
  expect(entries.map((e) => e.url)).toEqual([]);
});
