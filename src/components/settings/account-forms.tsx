"use client";

import * as React from "react";
import { CalendarPlus, Copy, LogOut, Monitor, Smartphone } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { ActionForm, SubmitButton } from "@/components/forms/action-form";
import { SelectField, TextField } from "@/components/forms/fields";
import { ConfirmButton } from "@/components/forms/confirm-dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  createCalendarLink,
  deleteAccount,
  leaveAssociation,
  revokeOtherSessions,
  revokeSession,
  updateNotificationSettings,
} from "@/actions/auth";
import { useLocale, useT } from "@/lib/i18n/client";
import { formatRelative } from "@/lib/utils";

type Session = { id: string; userAgent: string | null; ip: string | null; lastSeenAt: Date; current: boolean };

function describeAgent(ua: string | null, unknown: string) {
  if (!ua) return unknown;
  const browser = /Edg\//.test(ua)
    ? "Edge"
    : /Firefox\//.test(ua)
      ? "Firefox"
      : /Chrome\//.test(ua)
        ? "Chrome"
        : /Safari\//.test(ua)
          ? "Safari"
          : "Browser";
  const os = /Windows/.test(ua)
    ? "Windows"
    : /Android/.test(ua)
      ? "Android"
      : /iPhone|iPad/.test(ua)
        ? "iOS"
        : /Mac OS X/.test(ua)
          ? "macOS"
          : /Linux/.test(ua)
            ? "Linux"
            : "";
  return os ? `${browser} · ${os}` : browser;
}

export function SessionList({ sessions }: { sessions: Session[] }) {
  const t = useT();
  const locale = useLocale();
  const [pending, startTransition] = React.useTransition();
  const run = (fn: () => Promise<{ ok: boolean; error?: string } | null>) =>
    startTransition(async () => {
      const r = await fn();
      if (r?.ok) toast.success(t.common.saved);
      else toast.error(r?.error ?? t.common.genericError);
    });
  return (
    <div className="space-y-3">
      <ul className="divide-y rounded-xl border">
        {sessions.map((s) => {
          const mobile = /Mobile|Android|iPhone/.test(s.userAgent ?? "");
          return (
            <li key={s.id} className="flex items-center gap-3 px-4 py-3">
              {mobile ? (
                <Smartphone className="size-5 text-muted-foreground" />
              ) : (
                <Monitor className="size-5 text-muted-foreground" />
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{describeAgent(s.userAgent, t.settings.unknownDevice)}</p>
                <p className="text-xs text-muted-foreground" suppressHydrationWarning>
                  {t.settings.lastActive(formatRelative(s.lastSeenAt, locale))}
                  {s.ip && ` · ${s.ip}`}
                </p>
              </div>
              {s.current ? (
                <Badge color="emerald" dot>
                  {t.settings.thisDevice}
                </Badge>
              ) : (
                <Button size="xs" variant="outline" disabled={pending} onClick={() => run(() => revokeSession(s.id))}>
                  {t.settings.signOutDevice}
                </Button>
              )}
            </li>
          );
        })}
      </ul>
      {sessions.length > 1 && (
        <Button variant="outline" size="sm" disabled={pending} onClick={() => run(() => revokeOtherSessions())}>
          <LogOut /> {t.settings.signOutOthers}
        </Button>
      )}
    </div>
  );
}

export function AssociationList({ associations }: { associations: { id: string; name: string; role: string }[] }) {
  const t = useT();
  return (
    <ul className="divide-y rounded-xl border">
      {associations.map((a) => (
        <li key={a.id} className="flex items-center gap-3 px-4 py-3">
          <span className="flex size-8 items-center justify-center rounded-lg bg-muted text-sm font-bold">
            {a.name.charAt(0).toUpperCase()}
          </span>
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm font-medium">{a.name}</span>
            <span className="text-xs text-muted-foreground">{t.enums.role[a.role]}</span>
          </span>
          <ConfirmButton
            title={t.settings.leaveConfirm(a.name)}
            description={t.settings.leaveText}
            confirmLabel={t.settings.leave}
            action={leaveAssociation.bind(null, a.id)}
            redirectTo="/"
          >
            <Button size="xs" variant="destructive-ghost">
              {t.settings.leave}
            </Button>
          </ConfirmButton>
        </li>
      ))}
    </ul>
  );
}

export function DeleteAccountForm() {
  const t = useT();
  return (
    <ActionForm action={deleteAccount} successToast={false} className="max-w-md space-y-3">
      <TextField
        name="password"
        type="password"
        label={t.settings.deleteConfirm}
        autoComplete="current-password"
        required
      />
      <SubmitButton variant="destructive">{t.settings.deleteButton}</SubmitButton>
    </ActionForm>
  );
}

export function NotificationSettingsForm({
  emailMode,
  mailEnabled,
}: {
  emailMode: "DAILY" | "INSTANT" | "NEVER";
  mailEnabled: boolean;
}) {
  const t = useT();
  return (
    <ActionForm action={updateNotificationSettings} className="max-w-xl space-y-3">
      <SelectField
        name="emailMode"
        defaultValue={emailMode}
        label={t.settings.emailNotifications}
        hint={mailEnabled ? undefined : t.settings.emailNotConfigured}
        options={[
          { value: "DAILY", label: t.settings.emailDaily },
          { value: "INSTANT", label: t.settings.emailInstant },
          { value: "NEVER", label: t.settings.emailNever },
        ]}
      />
      <SubmitButton size="sm">{t.common.save}</SubmitButton>
    </ActionForm>
  );
}

export function CalendarLink({ hasLink }: { hasLink: boolean }) {
  const t = useT();
  const [link, setLink] = React.useState<string | null>(null);
  const [pending, startTransition] = React.useTransition();
  return (
    <div className="space-y-3">
      {link ? (
        <>
          <p className="text-sm text-muted-foreground">{t.settings.calendarCopy}</p>
          <div className="flex gap-2">
            <Input readOnly value={link} onFocus={(e) => e.currentTarget.select()} className="font-mono text-xs" />
            <Button
              variant="soft"
              onClick={async () => {
                try {
                  await navigator.clipboard.writeText(link);
                  toast.success(t.common.copied);
                } catch {}
              }}
            >
              <Copy /> {t.common.copy}
            </Button>
          </div>
        </>
      ) : (
        <Button
          variant="outline"
          disabled={pending}
          onClick={() =>
            startTransition(async () => {
              const r = await createCalendarLink();
              if (r?.ok && r.data?.link) setLink(r.data.link);
              else toast.error(r?.error ?? t.common.genericError);
            })
          }
        >
          <CalendarPlus /> {hasLink ? t.settings.calendarRenew : t.settings.calendarCreate}
        </Button>
      )}
    </div>
  );
}
