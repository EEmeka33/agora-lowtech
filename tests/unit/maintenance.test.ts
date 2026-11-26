import { execSync } from "node:child_process";
import { existsSync, mkdirSync, mkdtempSync, rmSync, utimesSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";

// A throw-away database and upload folder; emails are printed instead of sent.
const dir = mkdtempSync(path.join(tmpdir(), "agora-maintenance-"));
const url = `file:${path.join(dir, "test.db")}`;
const uploads = path.join(dir, "uploads");
process.env.DATABASE_URL = url;
process.env.UPLOAD_DIR = uploads;
process.env.SMTP_URL = "log";

const DAY = 24 * 60 * 60 * 1000;
const ago = (days: number) => new Date(Date.now() - days * DAY);

type Db = typeof import("@/lib/db").db;
let db: Db;
const ids: Record<string, string> = {};

beforeAll(async () => {
  execSync("npx prisma migrate deploy", { env: { ...process.env, DATABASE_URL: url }, stdio: "ignore" });
  db = (await import("@/lib/db")).db;
  const asso = await db.association.create({ data: { name: "Repair Café" } });
  ids.asso = asso.id;
  const user = (name: string, data: object = {}) =>
    db.user.create({ data: { name, email: `${name.toLowerCase()}@test`, passwordHash: "x", ...data } });
  ids.daily = (await user("Daily")).id;
  ids.instant = (await user("Instant", { emailDigest: false })).id;
  ids.never = (await user("Never", { emailNotifications: false })).id;
});

afterAll(async () => {
  await db?.$disconnect();
  rmSync(dir, { recursive: true, force: true });
});

const notification = (userId: string, data: { createdAt?: Date; readAt?: Date | null } = {}) =>
  db.notification.create({
    data: {
      userId,
      associationId: ids.asso,
      type: "poll.new",
      data: JSON.stringify({ actor: "Camille", title: "Opening hours" }),
      ...data,
    },
  });

describe("cleanup", () => {
  it("deletes old read notifications, old history, expired sessions and orphan files only", async () => {
    const oldRead = await notification(ids.never, { createdAt: ago(200), readAt: ago(120) });
    const oldUnread = await notification(ids.never, { createdAt: ago(200) });
    const recentRead = await notification(ids.never, { readAt: ago(5) });
    const veryOld = await notification(ids.never, { createdAt: ago(400) });
    await db.activity.create({
      data: { associationId: ids.asso, action: "created", entityType: "task", createdAt: ago(400) },
    });
    const recentActivity = await db.activity.create({
      data: { associationId: ids.asso, action: "created", entityType: "task", createdAt: ago(10) },
    });
    await db.session.create({ data: { userId: ids.never, tokenHash: "expired", expiresAt: ago(1) } });
    await db.session.create({ data: { userId: ids.never, tokenHash: "valid", expiresAt: ago(-10) } });

    mkdirSync(uploads, { recursive: true });
    const file = (name: string, ageDays: number) => {
      writeFileSync(path.join(uploads, name), "x");
      utimesSync(path.join(uploads, name), ago(ageDays), ago(ageDays));
    };
    file("kept.pdf", 30);
    file("orphan.pdf", 30);
    file("uploading.pdf", 0);
    await db.document.create({ data: { associationId: ids.asso, title: "Statutes", storedName: "kept.pdf" } });

    const { cleanup } = await import("@/lib/maintenance");
    const removed = await cleanup();
    expect(removed).toEqual({ notifications: 2, activity: 1, sessions: 1, links: 0, files: 1 });

    const left = (await db.notification.findMany({ select: { id: true } })).map((n) => n.id);
    expect(left).toContain(oldUnread.id);
    expect(left).toContain(recentRead.id);
    expect(left).not.toContain(oldRead.id);
    expect(left).not.toContain(veryOld.id);
    expect((await db.activity.findMany()).map((a) => a.id)).toEqual([recentActivity.id]);
    expect(await db.session.count()).toBe(1);
    expect(existsSync(path.join(uploads, "kept.pdf"))).toBe(true);
    expect(existsSync(path.join(uploads, "orphan.pdf"))).toBe(false);
    expect(existsSync(path.join(uploads, "uploading.pdf"))).toBe(true);
  });
});

describe("daily summary", () => {
  it("sends one email a day with what was not seen, only to people who chose it", async () => {
    await db.notification.deleteMany();
    await notification(ids.daily, { createdAt: ago(0.2) });
    await notification(ids.daily, { createdAt: ago(0.1) });
    await notification(ids.daily, { createdAt: ago(0.1), readAt: new Date() });
    await notification(ids.instant);
    await notification(ids.never);

    const log = vi.spyOn(console, "log").mockImplementation(() => {});
    const { sendDigests } = await import("@/lib/notifications");
    expect(await sendDigests("https://agora.test")).toBe(1);
    const mail = log.mock.calls.map((c) => c.join(" ")).join("\n");
    expect(mail).toContain("daily@test");
    expect(mail).toContain("2 notifications");
    expect(mail).not.toContain("instant@test");
    expect(mail).not.toContain("never@test");

    // Same day: nothing more, even with a new notification.
    await notification(ids.daily);
    expect(await sendDigests("https://agora.test")).toBe(0);
    // The next day, only what is new since the previous summary.
    log.mockClear();
    expect(await sendDigests("https://agora.test", new Date(Date.now() + DAY + 1000))).toBe(1);
    expect(log.mock.calls.map((c) => c.join(" ")).join("\n")).toContain("1 notification you have not seen");
    log.mockRestore();
  });
});
