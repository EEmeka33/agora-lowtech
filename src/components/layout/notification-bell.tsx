"use client";

import * as React from "react";
import Link from "@/components/ui/link";
import { Bell, CheckCheck, Loader2 } from "lucide-react";
import { Menu, MenuSeparator } from "@/components/ui/menu";
import { Avatar } from "@/components/ui/avatar";
import { useLocale, useT } from "@/lib/i18n/client";
import { cn, formatRelative } from "@/lib/utils";
import { markAllNotificationsRead, openNotification } from "@/actions/notifications";

type Item = {
  id: string;
  message: string;
  association: string | null;
  actor: { name: string; avatarColor: string } | null;
  read: boolean;
  createdAt: string;
};

export function NotificationBell({
  initialUnread,
  currentAssociation,
}: {
  initialUnread: number;
  currentAssociation: string;
}) {
  const t = useT();
  const locale = useLocale();
  const [unread, setUnread] = React.useState(initialUnread);
  const [items, setItems] = React.useState<Item[] | null>(null);
  const [, startTransition] = React.useTransition();

  // Server value wins after each navigation.
  const [prev, setPrev] = React.useState(initialUnread);
  if (prev !== initialUnread) {
    setPrev(initialUnread);
    setUnread(initialUnread);
  }

  // No polling: the count is refreshed when the person comes back to the tab (at most
  // twice a minute) and after each navigation. An open tab used to ask the server
  // every minute, day and night: 1,440 requests a day, even when nobody was looking.
  React.useEffect(() => {
    let last = Date.now();
    const refresh = async () => {
      if (document.visibilityState !== "visible" || Date.now() - last < 30_000) return;
      last = Date.now();
      try {
        const res = await fetch("/api/notifications", { cache: "no-store" });
        if (res.ok) setUnread(((await res.json()) as { unread: number }).unread);
      } catch {}
    };
    window.addEventListener("focus", refresh);
    document.addEventListener("visibilitychange", refresh);
    return () => {
      window.removeEventListener("focus", refresh);
      document.removeEventListener("visibilitychange", refresh);
    };
  }, []);

  const load = async () => {
    setItems(null);
    try {
      const res = await fetch("/api/notifications?limit=12", { cache: "no-store" });
      const data = (await res.json()) as { unread: number; items: Item[] };
      setItems(data.items);
      setUnread(data.unread);
    } catch {
      setItems([]);
    }
  };

  return (
    <Menu
      label={t.nav.notifications}
      className="w-[min(24rem,calc(100vw-1rem))] p-0"
      trigger={
        <button
          type="button"
          onClick={load}
          aria-label={`${t.nav.notifications}${unread ? ` (${unread})` : ""}`}
          className="relative inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-hover hover:text-foreground"
        >
          <Bell className="size-[18px]" />
          {unread > 0 && (
            <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white tabular-nums">
              {unread > 99 ? "99+" : unread}
            </span>
          )}
        </button>
      }
    >
      {(close) => (
        <div>
          <div className="flex items-center justify-between px-4 py-3">
            <p className="text-sm font-semibold">{t.notifications.title}</p>
            {unread > 0 && (
              <button
                type="button"
                onClick={() =>
                  startTransition(async () => {
                    await markAllNotificationsRead();
                    setUnread(0);
                    setItems((list) => list?.map((i) => ({ ...i, read: true })) ?? null);
                  })
                }
                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
              >
                <CheckCheck className="size-3.5" /> {t.notifications.markAllRead}
              </button>
            )}
          </div>
          <MenuSeparator />
          <div className="max-h-[60vh] scrollbar-thin overflow-y-auto p-1">
            {items === null ? (
              <div className="flex justify-center py-8">
                <Loader2 className="size-5 animate-spin text-muted-foreground" />
              </div>
            ) : items.length === 0 ? (
              <p className="px-3 py-8 text-center text-sm text-muted-foreground">{t.notifications.empty}</p>
            ) : (
              items.map((n) => (
                <button
                  key={n.id}
                  type="button"
                  onClick={() => {
                    close();
                    startTransition(() => openNotification(n.id));
                  }}
                  className={cn(
                    "flex w-full gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-hover",
                    !n.read && "bg-primary-soft/40",
                  )}
                >
                  <Avatar name={n.actor?.name ?? "A"} color={n.actor?.avatarColor ?? "slate"} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block text-[13px] leading-snug">{n.message}</span>
                    <span className="mt-0.5 block text-[11px] text-muted-foreground">
                      {formatRelative(n.createdAt, locale)}
                      {n.association && n.association !== currentAssociation && ` · ${n.association}`}
                    </span>
                  </span>
                  {!n.read && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />}
                </button>
              ))
            )}
          </div>
          <MenuSeparator />
          <Link
            href="/notifications"
            onClick={close}
            className="block px-4 py-2.5 text-center text-sm font-medium text-primary hover:bg-hover"
          >
            {t.notifications.seeAll}
          </Link>
        </div>
      )}
    </Menu>
  );
}
