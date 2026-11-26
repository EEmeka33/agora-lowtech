import { Suspense } from "react";
import Link from "@/components/ui/link";
import { db } from "@/lib/db";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { I18nProvider } from "@/lib/i18n/client";
import { isAdmin, isManager } from "@/lib/permissions";
import { myPoleIds } from "@/lib/queries";
import { nowWall } from "@/lib/utils";
import { MobileNavProvider, Sidebar } from "@/components/layout/sidebar";
import { Topbar } from "@/components/layout/topbar";
import { CommandPalette } from "@/components/layout/command-palette";
import { DocumentSync } from "@/components/layout/theme";
import { Avatar } from "@/components/ui/avatar";
import { Skeleton } from "@/components/ui/page";

export default function AppLayout({ children }: LayoutProps<"/">) {
  return (
    <Suspense fallback={<ShellSkeleton />}>
      <Shell>{children}</Shell>
    </Suspense>
  );
}

async function Shell({ children }: { children: React.ReactNode }) {
  const { user, association, associations, db: tdb } = await requireContext();
  const { locale, t } = await getI18n();
  const poleIds = await myPoleIds(tdb, user.id);
  const now = nowWall();
  const [openTasks, steps, openRequests, openPolls, unread] = await Promise.all([
    tdb.task.count({ where: { assigneeId: user.id, status: { not: "DONE" } } }),
    tdb.foundationStep.groupBy({ by: ["status"], _count: true }),
    tdb.request.count({ where: { status: "OPEN", poleId: { in: poleIds }, requesterId: { not: user.id } } }),
    tdb.poll.count({
      where: {
        closedAt: null,
        OR: [{ closesAt: null }, { closesAt: { gt: now } }],
        votes: { none: { userId: user.id } },
        AND: [{ OR: [{ poleId: null }, { poleId: { in: poleIds } }] }],
      },
    }),
    db.notification.count({ where: { userId: user.id, readAt: null } }),
  ]);
  const relevant = steps.filter((s) => s.status !== "SKIPPED").reduce((n, s) => n + s._count, 0);
  const done = steps.find((s) => s.status === "DONE")?._count ?? 0;

  return (
    <I18nProvider locale={locale}>
      <DocumentSync accent={association.accentColor} locale={locale} />
      <MobileNavProvider>
        <div className="flex min-h-dvh">
          <Sidebar
            association={{ id: association.id, name: association.name, legalForm: association.legalForm }}
            associations={associations}
            badges={{
              tasks: openTasks,
              requests: openRequests,
              polls: openPolls,
              foundation: relevant > 0 && done < relevant ? `${done}/${relevant}` : undefined,
            }}
            footer={
              <Link
                href={`/members/${user.id}`}
                className="flex items-center gap-3 rounded-lg p-1.5 transition-colors hover:bg-hover"
              >
                <Avatar name={user.name} color={user.avatarColor} />
                <span className="min-w-0">
                  <span className="block truncate text-sm font-medium">{user.name}</span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {user.boardPosition ? t.enums.boardPosition[user.boardPosition] : t.enums.role[user.role]}
                  </span>
                </span>
              </Link>
            }
          />
          <div className="flex min-w-0 flex-1 flex-col">
            <Topbar
              user={{ id: user.id, name: user.name, email: user.email, avatarColor: user.avatarColor, role: user.role }}
              canManage={isManager(user)}
              isAdmin={isAdmin(user)}
              unread={unread}
              associationName={association.name}
            />
            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8 print:max-w-none print:p-0">
              {children}
            </main>
          </div>
        </div>
      </MobileNavProvider>
      <CommandPalette />
    </I18nProvider>
  );
}

function ShellSkeleton() {
  return (
    <div className="flex min-h-dvh">
      <aside className="hidden w-64 shrink-0 border-r bg-sidebar p-4 lg:block">
        <Skeleton className="mb-6 h-9 w-40" />
        <div className="space-y-2">
          {Array.from({ length: 12 }).map((_, i) => (
            <Skeleton key={i} className="h-7 w-full" />
          ))}
        </div>
      </aside>
      <div className="flex-1">
        <div className="h-14 border-b" />
        <div className="mx-auto max-w-7xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
          <Skeleton className="h-8 w-64" />
          <Skeleton className="h-4 w-96 max-w-full" />
          <div className="grid gap-4 pt-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-24" />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
