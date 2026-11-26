import "server-only";
import { readdir, stat, unlink } from "node:fs/promises";
import path from "node:path";
import { after } from "next/server";
import { db } from "./db";
import { sendDigests } from "./notifications";
import { baseUrl } from "./request-info";
import { uploadDir } from "./uploads";

const HOUR = 60 * 60 * 1000;
const DAY = 24 * HOUR;

/** How many days the activity history is kept (RETENTION_DAYS, 365 by default, at least 30). */
export function retentionDays() {
  const days = Number(process.env.RETENTION_DAYS ?? 365);
  return Number.isFinite(days) && days >= 30 ? Math.round(days) : 365;
}

/**
 * Deletes what nobody will look at again: read notifications after 90 days (all of them after the retention
 * period), activity history after the retention period, expired sessions, used or expired one-time links after
 * 30 days, and uploaded files that no document points to any more. Money, documents, minutes and everything
 * members wrote are never deleted.
 */
export async function cleanup(now = new Date()) {
  const ago = (days: number) => new Date(now.getTime() - days * DAY);
  const history = ago(retentionDays());
  const [notifications, activity, sessions, links] = await db.$transaction([
    db.notification.deleteMany({ where: { OR: [{ readAt: { lt: ago(90) } }, { createdAt: { lt: history } }] } }),
    db.activity.deleteMany({ where: { createdAt: { lt: history } } }),
    db.session.deleteMany({ where: { expiresAt: { lt: now } } }),
    db.invitation.deleteMany({ where: { OR: [{ usedAt: { lt: ago(30) } }, { expiresAt: { lt: ago(30) } }] } }),
  ]);
  return {
    notifications: notifications.count,
    activity: activity.count,
    sessions: sessions.count,
    links: links.count,
    files: await removeOrphanFiles(now),
  };
}

/** Files left in the upload folder by a deleted association or account (older than a day, so never an upload in progress). */
async function removeOrphanFiles(now: Date) {
  const dir = uploadDir();
  const names = await readdir(dir).catch(() => [] as string[]);
  if (names.length === 0) return 0;
  const docs = await db.document.findMany({ where: { storedName: { not: null } }, select: { storedName: true } });
  const known = new Set(docs.map((d) => d.storedName));
  let removed = 0;
  for (const name of names) {
    if (known.has(name)) continue;
    const file = path.join(dir, name);
    const info = await stat(file).catch(() => null);
    if (!info?.isFile() || now.getTime() - info.mtimeMs < DAY) continue;
    await unlink(file).then(
      () => removed++,
      () => {},
    );
  }
  return removed;
}

let nextDigest = 0;
let nextCleanup = 0;

/**
 * Housekeeping without a timer or a cron job: a page visit schedules it after the response, at most once an
 * hour for the daily email summaries and once a day for the cleanup. When nobody uses Agora, nothing runs.
 */
export async function scheduleMaintenance() {
  const now = Date.now();
  const digest = now >= nextDigest;
  const clean = now >= nextCleanup;
  if (!digest && !clean) return;
  if (digest) nextDigest = now + HOUR;
  if (clean) nextCleanup = now + DAY;
  const origin = digest ? await baseUrl() : "";
  after(async () => {
    try {
      if (clean) {
        const removed = await cleanup();
        if (Object.values(removed).some(Boolean)) console.log("[maintenance] removed", removed);
      }
      if (digest) await sendDigests(origin);
    } catch (error) {
      console.error("[maintenance] failed", error);
    }
  });
}
