import "server-only";
import { after } from "next/server";
import { db } from "./db";
import { dictionaries, isLocale } from "./i18n/dictionaries";
import { emailLayout, mailEnabled, sendMail } from "./mail";
import { baseUrl } from "./request-info";

export type NotificationType =
  | "request.new"
  | "request.taken"
  | "request.assigned"
  | "request.done"
  | "task.assigned"
  | "task.comment"
  | "event.new"
  | "poll.new"
  | "announcement.new"
  | "member.joined";

type NotifyContext = {
  user: { id: string; name: string };
  association: { id: string; name: string; locale: string };
};

/**
 * Sends an in-app notification to some members (never to the person who acted), and an email right away to
 * those who chose it (SMTP configured). Most people get the daily summary instead: see sendDigests().
 */
export async function notify(
  ctx: NotifyContext,
  input: {
    userIds: (string | null | undefined)[];
    type: NotificationType;
    data?: Record<string, string>;
    link?: string;
  },
) {
  const recipients = [...new Set(input.userIds.filter((id): id is string => !!id && id !== ctx.user.id))];
  if (recipients.length === 0) return;
  const data = { actor: ctx.user.name, association: ctx.association.name, ...input.data };
  await db.notification.createMany({
    data: recipients.map((userId) => ({
      userId,
      associationId: ctx.association.id,
      type: input.type,
      data: JSON.stringify(data),
      link: input.link ?? null,
      actorId: ctx.user.id,
    })),
  });

  if (!mailEnabled()) return;
  const origin = await baseUrl();
  after(async () => {
    const users = await db.user.findMany({
      where: { id: { in: recipients }, emailNotifications: true, emailDigest: false, passwordHash: { not: null } },
      select: { email: true, locale: true },
    });
    for (const u of users) {
      const locale = isLocale(u.locale) ? u.locale : isLocale(ctx.association.locale) ? ctx.association.locale : "en";
      const t = dictionaries[locale];
      const message = renderNotification(input.type, data, locale);
      const url = input.link ? `${origin}${input.link}` : origin;
      await sendMail({
        to: u.email,
        subject: `[${ctx.association.name}] ${message}`,
        text: `${message}\n\n${url}\n\n— ${t.notifications.emailFooter}`,
        html: emailLayout({
          heading: ctx.association.name,
          body: message,
          cta: t.notifications.open,
          url,
          footer: t.notifications.emailFooter,
        }),
      });
    }
  });
}

const DAY = 24 * 60 * 60 * 1000;
const DIGEST_LINES = 15;

/**
 * One email a day at most, listing the notifications the person has not seen in Agora since the previous
 * summary. Nothing is sent when everything was already read: the email only replaces a visit.
 */
export async function sendDigests(origin: string, now = new Date()) {
  if (!mailEnabled()) return 0;
  const yesterday = new Date(now.getTime() - DAY);
  const users = await db.user.findMany({
    where: {
      emailNotifications: true,
      emailDigest: true,
      passwordHash: { not: null },
      OR: [{ lastDigestAt: null }, { lastDigestAt: { lte: yesterday } }],
      notifications: { some: { readAt: null, createdAt: { gt: new Date(now.getTime() - 7 * DAY) } } },
    },
    select: { id: true, email: true, locale: true, lastDigestAt: true },
  });
  let sent = 0;
  for (const u of users) {
    const where = { userId: u.id, readAt: null, createdAt: { gt: u.lastDigestAt ?? yesterday } };
    const [count, items] = await Promise.all([
      db.notification.count({ where }),
      db.notification.findMany({
        where,
        orderBy: { createdAt: "desc" },
        take: DIGEST_LINES,
        select: { type: true, data: true, association: { select: { name: true, locale: true } } },
      }),
    ]);
    if (count === 0) continue;
    const fallback = items[0].association?.locale;
    const locale = isLocale(u.locale) ? u.locale : isLocale(fallback) ? fallback : "en";
    const t = dictionaries[locale];
    const lines = items.map((n) => {
      const text = renderNotification(n.type, JSON.parse(n.data) as Record<string, string>, locale);
      return n.association ? `• ${n.association.name} — ${text}` : `• ${text}`;
    });
    if (count > items.length) lines.push(t.notifications.digestMore(count - items.length));
    const body = `${t.notifications.digestIntro}\n\n${lines.join("\n")}`;
    const url = `${origin}/notifications`;
    const delivered = await sendMail({
      to: u.email,
      subject: t.notifications.digestSubject(count),
      text: `${body}\n\n${url}\n\n— ${t.notifications.digestFooter}`,
      html: emailLayout({
        heading: "Agora",
        body,
        cta: t.notifications.open,
        url,
        footer: t.notifications.digestFooter,
      }),
    });
    if (!delivered) continue;
    await db.user.update({ where: { id: u.id }, data: { lastDigestAt: now } });
    sent++;
  }
  return sent;
}

export function renderNotification(type: string, data: Record<string, string>, locale: string) {
  const t = dictionaries[isLocale(locale) ? locale : "en"];
  const render = t.notifications.types[type];
  return render ? render(data) : type;
}

/** Ids of the active members of a pole. */
export async function poleMemberIds(poleId: string) {
  const pole = await db.pole.findUnique({
    where: { id: poleId },
    select: { associationId: true, members: { select: { userId: true } } },
  });
  if (!pole) return [];
  const active = await db.member.findMany({
    where: { associationId: pole.associationId, status: "ACTIVE", userId: { in: pole.members.map((m) => m.userId) } },
    select: { userId: true },
  });
  return active.map((m) => m.userId);
}

/** Ids of all active members of an association (optionally only admins/managers). */
export async function associationMemberIds(associationId: string, roles?: string[]) {
  const rows = await db.member.findMany({
    where: { associationId, status: "ACTIVE", ...(roles ? { role: { in: roles } } : {}) },
    select: { userId: true },
  });
  return rows.map((r) => r.userId);
}
