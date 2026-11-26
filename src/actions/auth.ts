"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { createSession, destroySession, getAccount, hashPassword, revokeSessions, verifyPassword } from "@/lib/auth";
import { fakePasswordCheck } from "@/lib/password";
import { passwordIssue } from "@/lib/password-policy";
import { getI18n, LOCALE_COOKIE } from "@/lib/i18n/server";
import { dictionaries, isLocale, type Dictionary } from "@/lib/i18n/dictionaries";
import { EMAIL_RE, fail, ok, reader, withAccount, type ActionState } from "@/lib/action-utils";
import { rateLimit } from "@/lib/rate-limit";
import { clientIp, baseUrl } from "@/lib/request-info";
import { newToken, normalizeJoinCode, sha256 } from "@/lib/tokens";
import { emailLayout, mailEnabled, sendMail } from "@/lib/mail";
import { signupMode } from "@/lib/constants";
import { ASSOCIATION_COOKIE } from "@/lib/session-cookie";
import { redeemJoinCode } from "@/lib/join-codes";

const LOCK_AFTER = 5;
const LOCK_MINUTES = 15;
const YEAR = 60 * 60 * 24 * 365;

function passwordError(t: Dictionary, password: string, personal: { email?: string; name?: string }) {
  const issue = passwordIssue(password, personal);
  if (!issue) return null;
  return {
    length: t.validation.passwordLength,
    tooLong: t.validation.tooLong,
    common: t.validation.passwordCommon,
    personal: t.validation.passwordPersonal,
  }[issue];
}

async function setLocaleCookie(locale: string | null | undefined) {
  if (isLocale(locale)) (await cookies()).set(LOCALE_COOKIE, locale, { path: "/", maxAge: YEAR, sameSite: "lax" });
}

function safeNext(next: string) {
  return next.startsWith("/") && !next.startsWith("//") ? next : "/";
}

// ─── Sign in / out ──────────────────────────────────────────────────────────

export async function login(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const { t } = await getI18n();
  const f = reader(formData);
  const email = f.str("email").toLowerCase();
  const password = f.str("password");
  const ip = await clientIp();

  if (!rateLimit(`login:ip:${ip}`, 30, 10 * 60_000) || !rateLimit(`login:email:${email}`, 10, 10 * 60_000)) {
    return fail(t.account.tooMany);
  }

  const user = email ? await db.user.findUnique({ where: { email } }) : null;
  if (!user || !user.passwordHash) {
    await fakePasswordCheck(password);
    return fail(t.auth.invalidCredentials);
  }
  if (user.lockedUntil && user.lockedUntil > new Date()) return fail(t.account.locked);

  if (!(await verifyPassword(password, user.passwordHash))) {
    const failures = user.failedLoginCount + 1;
    await db.user.update({
      where: { id: user.id },
      data: {
        failedLoginCount: failures >= LOCK_AFTER ? 0 : failures,
        lockedUntil: failures >= LOCK_AFTER ? new Date(Date.now() + LOCK_MINUTES * 60_000) : null,
      },
    });
    return fail(failures >= LOCK_AFTER ? t.account.locked : t.auth.invalidCredentials);
  }

  await db.user.update({
    where: { id: user.id },
    data: { failedLoginCount: 0, lockedUntil: null, lastLoginAt: new Date() },
  });
  await createSession(user.id);
  await setLocaleCookie(user.locale);
  redirect(safeNext(f.str("next")));
}

export async function logout() {
  await destroySession();
  redirect("/login");
}

// ─── Sign up ────────────────────────────────────────────────────────────────

export async function signup(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const { t, locale } = await getI18n();
  const f = reader(formData);
  const name = f.str("name");
  const email = f.str("email").toLowerCase();
  const password = f.str("password");
  const confirm = f.str("confirm");
  const code = normalizeJoinCode(f.str("code"));
  const ip = await clientIp();

  const isFirst = (await db.user.count()) === 0;
  const mode = signupMode();
  if (mode === "closed" && !isFirst) return fail(t.account.signup.closed);
  if (mode === "invite" && !isFirst && !code)
    return fail(t.account.signup.codeRequired, { code: t.account.signup.codeRequired });
  if (!rateLimit(`signup:${ip}`, 10, 60 * 60_000)) return fail(t.account.tooMany);

  const errors: Record<string, string> = {};
  if (!name) errors.name = t.validation.required;
  if (!EMAIL_RE.test(email)) errors.email = t.validation.email;
  const pwError = passwordError(t, password, { email, name });
  if (pwError) errors.password = pwError;
  if (password !== confirm) errors.confirm = t.setup.passwordMismatch;
  if (f.str("code") && !code) errors.code = t.join.invalid;
  if (Object.keys(errors).length) return fail(Object.values(errors)[0], errors);

  const existing = await db.user.findUnique({ where: { email } });
  if (existing) {
    return fail(existing.passwordHash ? t.account.signup.emailExists : t.account.signup.emailInvited, {
      email: existing.passwordHash ? t.account.signup.emailExists : t.account.signup.emailInvited,
    });
  }

  // With "invite" mode, the code must be valid before the account is created.
  if (code && mode === "invite" && !isFirst) {
    const valid = await db.joinCode.findUnique({ where: { code } });
    if (!valid || valid.revokedAt || (valid.expiresAt && valid.expiresAt < new Date()) || valid.uses >= valid.maxUses) {
      return fail(t.join.invalid, { code: t.join.invalid });
    }
  }

  const user = await db.user.create({
    data: {
      name,
      email,
      passwordHash: await hashPassword(password),
      passwordChangedAt: new Date(),
      locale,
      lastLoginAt: new Date(),
    },
  });
  await createSession(user.id);

  if (code) {
    const result = await redeemJoinCode(user.id, code);
    if (result.ok) {
      (await cookies()).set(ASSOCIATION_COOKIE, result.associationId, { path: "/", maxAge: YEAR, sameSite: "lax" });
      redirect("/");
    }
  }
  redirect("/welcome");
}

// ─── Password reset ─────────────────────────────────────────────────────────

/** Self-service reset: emails a one-hour link (only when SMTP is configured). Never reveals if the email exists. */
export async function requestPasswordReset(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const { t } = await getI18n();
  if (!mailEnabled()) return fail(t.account.forgot.noMail);
  const email = reader(formData).str("email").toLowerCase();
  const ip = await clientIp();
  if (!rateLimit(`forgot:${ip}`, 5, 15 * 60_000) || !rateLimit(`forgot:${email}`, 3, 60 * 60_000)) {
    return ok(t.account.forgot.sent);
  }
  const user = EMAIL_RE.test(email) ? await db.user.findUnique({ where: { email } }) : null;
  if (user?.passwordHash) {
    const token = newToken();
    await db.invitation.updateMany({
      where: { userId: user.id, kind: "RESET", usedAt: null },
      data: { expiresAt: new Date() },
    });
    await db.invitation.create({
      data: { tokenHash: sha256(token), kind: "RESET", userId: user.id, expiresAt: new Date(Date.now() + 3600_000) },
    });
    const ut = dictionaries[isLocale(user.locale) ? user.locale : "en"];
    const url = `${await baseUrl()}/invite/${token}`;
    await sendMail({
      to: user.email,
      subject: ut.account.forgot.emailSubject,
      text: `${ut.account.forgot.emailBody}\n\n${url}`,
      html: emailLayout({
        heading: ut.account.forgot.emailSubject,
        body: ut.account.forgot.emailBody,
        cta: ut.account.forgot.emailCta,
        url,
        footer: "Agora",
      }),
    });
  }
  return ok(t.account.forgot.sent);
}

/** Activates an account created by an admin, or sets a new password from a reset link. */
export async function acceptInvitation(token: string, _prev: ActionState, formData: FormData): Promise<ActionState> {
  const { t } = await getI18n();
  const f = reader(formData);
  const password = f.str("password");
  const confirm = f.str("confirm");
  const name = f.str("name");

  const invitation = await db.invitation.findUnique({ where: { tokenHash: sha256(token) }, include: { user: true } });
  if (!invitation || invitation.usedAt || invitation.expiresAt < new Date()) return fail(t.auth.invite.invalid);

  const fieldErrors: Record<string, string> = {};
  const pwError = passwordError(t, password, { email: invitation.user.email, name: name || invitation.user.name });
  if (pwError) fieldErrors.password = pwError;
  if (password !== confirm) fieldErrors.confirm = t.setup.passwordMismatch;
  if (invitation.kind === "INVITE" && !name) fieldErrors.name = t.validation.required;
  if (Object.keys(fieldErrors).length) return fail(Object.values(fieldErrors)[0], fieldErrors);

  await db.$transaction([
    db.user.update({
      where: { id: invitation.userId },
      data: {
        passwordHash: await hashPassword(password),
        passwordChangedAt: new Date(),
        failedLoginCount: 0,
        lockedUntil: null,
        lastLoginAt: new Date(),
        ...(invitation.kind === "INVITE" && name ? { name } : {}),
      },
    }),
    db.invitation.update({ where: { id: invitation.id }, data: { usedAt: new Date() } }),
  ]);
  // A password reset signs out every other device.
  await revokeSessions(invitation.userId);
  if (invitation.associationId) {
    await db.activity.create({
      data: {
        associationId: invitation.associationId,
        actorId: invitation.userId,
        action: "joined",
        entityType: "workspace",
      },
    });
    (await cookies()).set(ASSOCIATION_COOKIE, invitation.associationId, { path: "/", maxAge: YEAR, sameSite: "lax" });
  }
  await createSession(invitation.userId);
  await setLocaleCookie(invitation.user.locale);
  redirect("/");
}

// ─── Account settings ───────────────────────────────────────────────────────

export async function changePassword(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return withAccount(async ({ account, t }) => {
    const f = reader(formData);
    const user = await db.user.findUniqueOrThrow({ where: { id: account.id } });
    if (!(await verifyPassword(f.str("current"), user.passwordHash))) {
      return fail(t.settings.wrongPassword, { current: t.settings.wrongPassword });
    }
    const password = f.str("password");
    const pwError = passwordError(t, password, { email: user.email, name: user.name });
    if (pwError) return fail(pwError, { password: pwError });
    if (password !== f.str("confirm")) return fail(t.setup.passwordMismatch, { confirm: t.setup.passwordMismatch });
    await db.user.update({
      where: { id: user.id },
      data: { passwordHash: await hashPassword(password), passwordChangedAt: new Date() },
    });
    await revokeSessions(user.id, account.sessionId);
    return ok(t.settings.passwordUpdatedOthers);
  });
}

export async function revokeSession(sessionId: string) {
  return withAccount(async ({ account, t }) => {
    await db.session.deleteMany({ where: { id: sessionId, userId: account.id, NOT: { id: account.sessionId } } });
    return ok(t.common.saved);
  });
}

export async function revokeOtherSessions() {
  return withAccount(async ({ account, t }) => {
    await revokeSessions(account.id, account.sessionId);
    return ok(t.common.saved);
  });
}

export async function updateNotificationSettings(_prev: ActionState, formData: FormData): Promise<ActionState> {
  return withAccount(async ({ account, t }) => {
    const mode = reader(formData).oneOf("emailMode", ["DAILY", "INSTANT", "NEVER"], "DAILY");
    await db.user.update({
      where: { id: account.id },
      data: { emailNotifications: mode !== "NEVER", emailDigest: mode === "DAILY" },
    });
    return ok(t.common.saved);
  });
}

/** Creates (or replaces) the private calendar feed link. Only its hash is stored. */
export async function createCalendarLink() {
  return withAccount(async ({ account, t }) => {
    const token = newToken();
    await db.user.update({ where: { id: account.id }, data: { calendarTokenHash: sha256(token) } });
    return ok(t.common.saved, { link: `${await baseUrl()}/api/calendar/${token}` });
  });
}

async function isLastAdmin(associationId: string, userId: string) {
  const admins = await db.member.findMany({
    where: { associationId, role: "ADMIN", status: "ACTIVE" },
    select: { userId: true },
  });
  return admins.length === 1 && admins[0].userId === userId;
}

export async function leaveAssociation(associationId: string) {
  return withAccount(async ({ account, t }) => {
    if (await isLastAdmin(associationId, account.id)) return fail(t.settings.lastAdmin);
    await db.member.deleteMany({ where: { associationId, userId: account.id } });
    await db.poleMember.deleteMany({ where: { userId: account.id, pole: { associationId } } });
    (await cookies()).delete(ASSOCIATION_COOKIE);
    return ok(t.settings.left);
  });
}

export async function deleteAccount(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const { t } = await getI18n();
  const account = await getAccount();
  if (!account) return fail(t.common.notAllowed);
  const user = await db.user.findUniqueOrThrow({
    where: { id: account.id },
    include: { memberships: { include: { association: true } } },
  });
  if (!(await verifyPassword(reader(formData).str("password"), user.passwordHash))) {
    return fail(t.settings.wrongPassword, { password: t.settings.wrongPassword });
  }
  for (const m of user.memberships) {
    if (m.role === "ADMIN" && (await isLastAdmin(m.associationId, user.id))) {
      return fail(t.settings.deleteLastAdmin(m.association.name));
    }
  }
  // Relations are set to null / cascaded by the database (see schema.prisma).
  await db.user.delete({ where: { id: user.id } });
  const store = await cookies();
  store.delete(ASSOCIATION_COOKIE);
  redirect("/login");
}

// ─── Preferences ────────────────────────────────────────────────────────────

export async function setLocale(locale: string) {
  if (!isLocale(locale)) return;
  await setLocaleCookie(locale);
  const account = await getAccount();
  if (account) await db.user.update({ where: { id: account.id }, data: { locale } });
  revalidatePath("/", "layout");
}

/** Makes another association (where the person is an active member) the current one. */
export async function switchAssociation(associationId: string) {
  const account = await getAccount();
  if (!account) redirect("/login");
  const member = await db.member.findUnique({ where: { associationId_userId: { associationId, userId: account.id } } });
  if (member?.status === "ACTIVE") {
    (await cookies()).set(ASSOCIATION_COOKIE, associationId, { path: "/", maxAge: YEAR, sameSite: "lax" });
  }
  redirect("/");
}
