"use client";

import * as React from "react";
import Link from "@/components/ui/link";
import { useRouter } from "next/navigation";
import {
  CalendarPlus,
  CheckSquare,
  Inbox,
  Vote,
  FolderPlus,
  Languages,
  LogOut,
  Menu as MenuIcon,
  Plus,
  Search,
  Settings,
  User,
  UserPlus,
} from "lucide-react";
import { Menu, MenuItem, MenuLabel, MenuSeparator } from "@/components/ui/menu";
import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "./theme";
import { useMobileNav } from "./sidebar";
import { useLocale, useT } from "@/lib/i18n/client";
import { logout, setLocale } from "@/actions/auth";
import { openCommandPalette } from "./command-palette";
import { NotificationBell } from "./notification-bell";

export function Topbar({
  user,
  canManage,
  isAdmin,
  unread,
  associationName,
}: {
  user: { id: string; name: string; email: string; avatarColor: string; role: string };
  canManage: boolean;
  isAdmin: boolean;
  unread: number;
  associationName: string;
}) {
  const t = useT();
  const locale = useLocale();
  const router = useRouter();
  const { setOpen } = useMobileNav();
  const [pending, startTransition] = React.useTransition();

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-background/80 px-3 backdrop-blur-md sm:px-5">
      <Button
        variant="ghost"
        size="icon"
        className="lg:hidden"
        onClick={() => setOpen(true)}
        aria-label={t.nav.toggleMenu}
      >
        <MenuIcon className="!size-5" />
      </Button>

      <button
        type="button"
        onClick={openCommandPalette}
        className="flex h-9 w-full max-w-sm items-center gap-2 rounded-lg border bg-card px-3 text-sm text-muted-foreground shadow-xs transition-colors hover:border-ring/60"
      >
        <Search className="size-4" />
        <span className="flex-1 truncate text-left">{t.nav.search}</span>
        <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 font-sans text-[10px] font-medium sm:inline">
          Ctrl K
        </kbd>
      </button>

      <div className="ml-auto flex items-center gap-1">
        <Menu
          trigger={
            <Button size="sm" className="gap-1.5">
              <Plus />
              <span className="hidden sm:inline">{t.nav.quickCreate}</span>
            </Button>
          }
        >
          {(close) => (
            <>
              <MenuItem
                icon={<CheckSquare />}
                onClick={() => {
                  close();
                  router.push("/tasks?new=1");
                }}
              >
                {t.nav.newTask}
              </MenuItem>
              <MenuItem
                icon={<Inbox />}
                onClick={() => {
                  close();
                  router.push("/requests?new=1");
                }}
              >
                {t.requests.new}
              </MenuItem>
              <MenuItem
                icon={<Vote />}
                onClick={() => {
                  close();
                  router.push("/polls?new=1");
                }}
              >
                {t.polls.new}
              </MenuItem>
              {canManage && (
                <>
                  <MenuItem
                    icon={<FolderPlus />}
                    onClick={() => {
                      close();
                      router.push("/projects?new=1");
                    }}
                  >
                    {t.nav.newProject}
                  </MenuItem>
                  <MenuItem
                    icon={<CalendarPlus />}
                    onClick={() => {
                      close();
                      router.push("/events?new=1");
                    }}
                  >
                    {t.nav.newEvent}
                  </MenuItem>
                </>
              )}
              {isAdmin && (
                <MenuItem
                  icon={<UserPlus />}
                  onClick={() => {
                    close();
                    router.push("/members?new=1");
                  }}
                >
                  {t.nav.newMember}
                </MenuItem>
              )}
            </>
          )}
        </Menu>

        <NotificationBell initialUnread={unread} currentAssociation={associationName} />
        <ThemeToggle />

        <Menu
          trigger={
            <button
              type="button"
              className="ml-1 rounded-full ring-offset-2 ring-offset-background transition hover:ring-2 hover:ring-ring/40"
              aria-label={user.name}
            >
              <Avatar name={user.name} color={user.avatarColor} size="md" />
            </button>
          }
          className="min-w-60"
        >
          {(close) => (
            <>
              <div className="px-2.5 py-2">
                <p className="truncate text-sm font-medium">{user.name}</p>
                <p className="truncate text-xs text-muted-foreground">{user.email}</p>
              </div>
              <MenuSeparator />
              <Link
                href={`/members/${user.id}`}
                onClick={close}
                className="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-hover [&_svg]:size-4 [&_svg]:text-muted-foreground"
              >
                <User /> {t.nav.myProfile}
              </Link>
              <Link
                href="/settings"
                onClick={close}
                className="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-hover [&_svg]:size-4 [&_svg]:text-muted-foreground"
              >
                <Settings /> {t.nav.settings}
              </Link>
              <MenuSeparator />
              <MenuLabel>
                <span className="inline-flex items-center gap-1.5">
                  <Languages className="size-3.5" /> {t.common.language}
                </span>
              </MenuLabel>
              <div className="grid grid-cols-2 gap-1 px-1 pb-1">
                {(["en", "fr"] as const).map((l) => (
                  <button
                    key={l}
                    type="button"
                    disabled={pending}
                    onClick={() => {
                      close();
                      startTransition(async () => {
                        await setLocale(l);
                      });
                    }}
                    className={
                      "rounded-md px-2 py-1 text-[13px] transition-colors " +
                      (locale === l ? "bg-primary-soft font-medium text-primary-soft-foreground" : "hover:bg-hover")
                    }
                  >
                    {t.enums.locale[l]}
                  </button>
                ))}
              </div>
              <MenuSeparator />
              <form action={logout}>
                <MenuItem type="submit" icon={<LogOut />}>
                  {t.nav.logout}
                </MenuItem>
              </form>
            </>
          )}
        </Menu>
      </div>
    </header>
  );
}
