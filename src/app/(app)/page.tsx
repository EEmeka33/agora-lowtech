import Link from "@/components/ui/link";
import {
  ArrowRight,
  CalendarDays,
  CheckSquare,
  FolderKanban,
  Megaphone,
  Pin,
  PinOff,
  Plus,
  Rocket,
  Trash2,
  Users,
  Wallet,
} from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { loadProjects } from "@/lib/project-queries";
import { loadTaskCards } from "@/lib/task-queries";
import { loadRequests } from "@/lib/request-queries";
import { myPoleIds } from "@/lib/queries";
import { RequestCard } from "@/components/requests/request-card";
import { foundationProgress } from "@/lib/progress";
import { colorFor } from "@/lib/colors";
import { formatDate, formatMoney, formatRelative, formatTime, nowWall, startOfDayUTC } from "@/lib/utils";
import { createAnnouncement, deleteAnnouncement, toggleAnnouncementPin } from "@/actions/announcements";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { ProgressRing } from "@/components/ui/progress";
import { StatCard } from "@/components/ui/page";
import { RichText } from "@/components/ui/rich-text";
import { FormDialog } from "@/components/forms/form-dialog";
import { RowActions } from "@/components/forms/row-actions";
import { ProjectRow } from "@/components/projects/project-card";
import { TaskTable } from "@/components/tasks/task-table";
import { DateTile } from "@/components/events/date-tile";
import { ActivityFeed } from "@/components/dashboard/activity-feed";
import { AnnouncementFields } from "@/components/dashboard/announcement-fields";

export default async function DashboardPage() {
  const { user, db, association } = await requireContext();
  const { t, locale } = await getI18n();
  const now = nowWall();
  const today = startOfDayUTC(now);
  const poleIds = await myPoleIds(db, user.id);
  const requestsForMe = await loadRequests(
    db,
    { status: "OPEN", poleId: { in: poleIds }, requesterId: { not: user.id } },
    user,
    4,
  );

  const [myTasks, projects, events, announcements, activity, steps, memberCount, openTasks, overdue, totals] =
    await Promise.all([
      loadTaskCards(db, { assigneeId: user.id, status: { not: "DONE" } }, user),
      loadProjects(db, { status: "IN_PROGRESS" }),
      db.event.findMany({
        where: { startAt: { gte: today } },
        orderBy: { startAt: "asc" },
        take: 4,
        include: { attendees: { where: { userId: user.id }, select: { status: true } } },
      }),
      db.announcement.findMany({
        orderBy: [{ pinned: "desc" }, { createdAt: "desc" }],
        take: 4,
        include: { author: { select: { name: true } } },
      }),
      db.activity.findMany({
        orderBy: { createdAt: "desc" },
        take: 8,
        include: { actor: { select: { id: true, name: true, avatarColor: true } } },
      }),
      db.foundationStep.findMany({ orderBy: { order: "asc" }, select: { id: true, title: true, status: true } }),
      db.member.count({ where: { status: "ACTIVE" } }),
      db.task.count({ where: { status: { not: "DONE" } } }),
      db.task.count({ where: { status: { not: "DONE" }, dueDate: { lt: today } } }),
      db.transaction.groupBy({ by: ["type"], _sum: { amountCents: true } }),
    ]);

  const hour = new Date().getHours();
  const firstName = user.name.split(" ")[0];
  const greeting =
    hour < 12
      ? t.dashboard.greeting.morning(firstName)
      : hour < 18
        ? t.dashboard.greeting.afternoon(firstName)
        : t.dashboard.greeting.evening(firstName);
  const founding = foundationProgress(steps);
  const nextStep = steps.find((s) => s.status === "IN_PROGRESS") ?? steps.find((s) => s.status === "TODO");
  const balance =
    (totals.find((x) => x.type === "INCOME")?._sum.amountCents ?? 0) -
    (totals.find((x) => x.type === "EXPENSE")?._sum.amountCents ?? 0);
  const canAnnounce = can.manageAnnouncements(user);

  return (
    <>
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-medium text-muted-foreground capitalize">
            {formatDate(now, locale, { weekday: "long", day: "numeric", month: "long" })}
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-[28px]">{greeting}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{t.dashboard.subtitle}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link href="/tasks?new=1" className={buttonVariants({ variant: "outline" })}>
            <CheckSquare /> {t.nav.newTask}
          </Link>
          {can.manageEvents(user) && (
            <Link href="/events?new=1" className={buttonVariants({ variant: "outline" })}>
              <CalendarDays /> {t.nav.newEvent}
            </Link>
          )}
        </div>
      </div>

      {founding.total > 0 && founding.done < founding.total && (
        <Link
          href="/foundation"
          className="group relative mb-6 flex flex-col gap-4 overflow-hidden rounded-2xl bg-primary p-5 text-primary-foreground shadow-md sm:flex-row sm:items-center dark:bg-primary-soft dark:text-foreground"
        >
          <div className="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10 blur-2xl" />
          <ProgressRing
            value={founding.percent}
            size={64}
            stroke={6}
            className="[&_circle:first-child]:stroke-white/25 [&_circle:last-child]:stroke-white dark:[&_circle:last-child]:stroke-primary"
          />
          <div className="relative min-w-0 flex-1">
            <p className="flex items-center gap-2 font-semibold">
              <Rocket className="size-4" /> {t.dashboard.foundingTitle}
            </p>
            <p className="text-sm opacity-90">{t.dashboard.foundingText(founding.done, founding.total)}</p>
            {nextStep && (
              <p className="mt-1 truncate text-sm opacity-90">
                {t.dashboard.foundingNext}: <span className="font-medium">{nextStep.title}</span>
              </p>
            )}
          </div>
          <span className="relative inline-flex items-center gap-1.5 self-start rounded-lg bg-white/15 px-3 py-2 text-sm font-medium ring-1 ring-white/25 transition group-hover:bg-white/25 sm:self-center">
            {t.dashboard.foundingContinue}{" "}
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </span>
        </Link>
      )}

      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label={t.dashboard.stats.members} value={memberCount} icon={<Users />} href="/members" />
        <StatCard label={t.dashboard.stats.projects} value={projects.length} icon={<FolderKanban />} href="/projects" />
        <StatCard
          label={t.dashboard.stats.openTasks}
          value={openTasks}
          hint={t.dashboard.stats.overdue(overdue)}
          tone={overdue ? "danger" : "default"}
          icon={<CheckSquare />}
          href="/tasks"
        />
        <StatCard
          label={t.dashboard.stats.balance}
          value={formatMoney(balance, association.currency, locale)}
          hint={t.dashboard.stats.balanceHint}
          icon={<Wallet />}
          href="/finance"
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-[15px] font-semibold">
                {t.dashboard.myTasks} <span className="font-normal text-muted-foreground">{myTasks.length}</span>
              </h2>
              <Link href="/tasks?scope=mine" className="text-sm text-primary hover:underline">
                {t.common.viewAll}
              </Link>
            </div>
            {myTasks.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                {t.dashboard.myTasksEmpty}
              </p>
            ) : (
              <TaskTable tasks={myTasks.slice(0, 7)} />
            )}
          </section>

          {requestsForMe.length > 0 && (
            <section>
              <div className="mb-3 flex items-center justify-between">
                <h2 className="text-[15px] font-semibold">
                  {t.dashboard.requestsForYou}{" "}
                  <span className="font-normal text-muted-foreground">{requestsForMe.length}</span>
                </h2>
                <Link href="/requests" className="text-sm text-primary hover:underline">
                  {t.common.viewAll}
                </Link>
              </div>
              <div className="grid gap-3 md:grid-cols-2">
                {requestsForMe.map((r) => (
                  <RequestCard key={r.id} request={r} compact />
                ))}
              </div>
            </section>
          )}

          <Card>
            <CardHeader>
              <CardTitle>{t.dashboard.projectsInProgress}</CardTitle>
              <Link href="/projects" className="text-sm text-primary hover:underline">
                {t.common.viewAll}
              </Link>
            </CardHeader>
            <CardContent className="px-3">
              {projects.length === 0 ? (
                <p className="px-2 text-sm text-muted-foreground">{t.dashboard.projectsEmpty}</p>
              ) : (
                projects.slice(0, 6).map((p) => <ProjectRow key={p.id} project={p} />)
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Megaphone className="size-4 text-primary" /> {t.dashboard.announcements}
              </CardTitle>
              {canAnnounce && (
                <FormDialog
                  title={t.dashboard.newAnnouncement}
                  action={createAnnouncement}
                  submitLabel={t.common.create}
                  trigger={
                    <Button size="sm" variant="soft">
                      <Plus /> {t.dashboard.newAnnouncement}
                    </Button>
                  }
                >
                  <AnnouncementFields />
                </FormDialog>
              )}
            </CardHeader>
            <CardContent>
              {announcements.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t.dashboard.announcementsEmpty}</p>
              ) : (
                <ul className="divide-y">
                  {announcements.map((a) => (
                    <li key={a.id} className="py-3 first:pt-0 last:pb-0">
                      <div className="flex items-start gap-2">
                        <div className="min-w-0 flex-1">
                          <p className="flex items-center gap-2 font-medium">
                            {a.pinned && (
                              <Pin className="size-3.5 shrink-0 text-primary" aria-label={t.dashboard.pinned} />
                            )}
                            {a.title}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {a.author?.name} · {formatRelative(a.createdAt, locale)}
                          </p>
                        </div>
                        {canAnnounce && (
                          <RowActions
                            items={[
                              {
                                kind: "run",
                                label: a.pinned ? t.dashboard.unpin : t.dashboard.pin,
                                icon: a.pinned ? <PinOff /> : <Pin />,
                                action: toggleAnnouncementPin.bind(null, a.id),
                              },
                              {
                                kind: "confirm",
                                label: t.common.delete,
                                icon: <Trash2 />,
                                title: t.common.confirmDelete,
                                action: deleteAnnouncement.bind(null, a.id),
                                destructive: true,
                              },
                            ]}
                          />
                        )}
                      </div>
                      <RichText text={a.body} className="mt-1.5 text-muted-foreground" />
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{t.dashboard.upcomingEvents}</CardTitle>
              <Link href="/events" className="text-sm text-primary hover:underline">
                {t.common.viewAll}
              </Link>
            </CardHeader>
            <CardContent className="space-y-3">
              {events.length === 0 && <p className="text-sm text-muted-foreground">{t.dashboard.eventsEmpty}</p>}
              {events.map((e) => (
                <Link key={e.id} href={`/events/${e.id}`} className="group flex items-center gap-3">
                  <DateTile date={e.startAt} locale={locale} color={colorFor("eventType", e.type)} className="w-12" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium group-hover:text-primary">{e.title}</p>
                    <p className="text-xs text-muted-foreground">
                      {formatTime(e.startAt, locale)}
                      {e.location && ` · ${e.location}`}
                    </p>
                  </div>
                  {e.attendees[0] && (
                    <Badge
                      color={colorFor("rsvp", e.attendees[0].status)}
                      className="hidden sm:inline-flex xl:inline-flex"
                    >
                      {t.enums.rsvp[e.attendees[0].status]}
                    </Badge>
                  )}
                </Link>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t.dashboard.recentActivity}</CardTitle>
              <Link href="/activity" className="text-sm text-primary hover:underline">
                {t.common.viewAll}
              </Link>
            </CardHeader>
            <CardContent>
              <ActivityFeed items={activity} t={t} locale={locale} compact />
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}
