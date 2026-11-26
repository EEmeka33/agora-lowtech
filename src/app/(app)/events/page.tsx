import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { CalendarDays, CalendarRange, Clock, List, MapPin, Plus, Users } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getPoleOptions, getProjectOptions } from "@/lib/queries";
import { colorFor } from "@/lib/colors";
import { formatTime, nowWall } from "@/lib/utils";
import { createEvent } from "@/actions/events";
import { EmptyState, PageHeader, Segmented } from "@/components/ui/page";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormDialog } from "@/components/forms/form-dialog";
import { EventFields } from "@/components/events/event-fields";
import { RsvpButtons } from "@/components/events/rsvp-buttons";
import { DateTile } from "@/components/events/date-tile";
import { MonthCalendar } from "@/components/events/calendar";

export const metadata: Metadata = { title: "Events" };

export default async function EventsPage(props: PageProps<"/events">) {
  const { user, db } = await requireContext();
  const { t, locale } = await getI18n();
  const sp = await props.searchParams;
  const view = sp.view === "calendar" ? "calendar" : "list";
  const now = nowWall();
  const monthParam = typeof sp.month === "string" && /^\d{4}-\d{2}$/.test(sp.month) ? sp.month : null;
  const year = monthParam ? Number(monthParam.slice(0, 4)) : now.getUTCFullYear();
  const month = monthParam ? Number(monthParam.slice(5, 7)) - 1 : now.getUTCMonth();
  const [poles, projects] = await Promise.all([getPoleOptions(db), getProjectOptions(db)]);

  const newEvent = (autoOpen: boolean) =>
    can.manageEvents(user) && (
      <FormDialog
        title={t.events.newEvent}
        action={createEvent}
        submitLabel={t.common.create}
        size="lg"
        defaultOpen={autoOpen && sp.new === "1"}
        trigger={
          <Button>
            <Plus /> {t.events.newEvent}
          </Button>
        }
      >
        <EventFields isNew poles={poles} projects={projects.map((p) => ({ id: p.id, name: p.title }))} />
      </FormDialog>
    );

  const toggle = (
    <Segmented
      items={[
        { href: "/events", label: t.common.list, active: view === "list", icon: <List /> },
        {
          href: "/events?view=calendar",
          label: t.common.calendar,
          active: view === "calendar",
          icon: <CalendarRange />,
        },
      ]}
    />
  );

  if (view === "calendar") {
    const from = new Date(Date.UTC(year, month, 1 - 7));
    const to = new Date(Date.UTC(year, month + 1, 14));
    const [events, tasks] = await Promise.all([
      db.event.findMany({
        where: { startAt: { gte: from, lt: to } },
        orderBy: { startAt: "asc" },
        select: { id: true, title: true, type: true, startAt: true },
      }),
      db.task.findMany({
        where: { dueDate: { gte: from, lt: to }, status: { not: "DONE" } },
        select: { id: true, title: true, dueDate: true },
      }),
    ]);
    return (
      <>
        <PageHeader
          title={t.events.title}
          description={t.events.subtitle}
          actions={
            <>
              {toggle}
              {newEvent(true)}
            </>
          }
        />
        <MonthCalendar
          year={year}
          month={month}
          events={events}
          tasks={tasks.map((x) => ({ ...x, dueDate: x.dueDate! }))}
          t={t}
          locale={locale}
          hrefFor={(y, m) => `/events?view=calendar&month=${y}-${String(m + 1).padStart(2, "0")}`}
        />
      </>
    );
  }

  const include = {
    attendees: { select: { userId: true, status: true } },
    pole: { select: { name: true, color: true } },
  } as const;
  const [upcoming, past] = await Promise.all([
    db.event.findMany({
      where: { startAt: { gte: new Date(now.getTime() - 3 * 3600_000) } },
      orderBy: { startAt: "asc" },
      include,
      take: 50,
    }),
    db.event.findMany({
      where: { startAt: { lt: new Date(now.getTime() - 3 * 3600_000) } },
      orderBy: { startAt: "desc" },
      include,
      take: 20,
    }),
  ]);

  const renderEvent = (e: (typeof upcoming)[number], isPast: boolean) => {
    const mine = e.attendees.find((a) => a.userId === user.id)?.status ?? null;
    const going = e.attendees.filter((a) => a.status === "GOING").length;
    return (
      <li
        key={e.id}
        className="group relative flex flex-col gap-4 rounded-xl border bg-card p-4 card-shadow transition-colors hover:border-primary/40 sm:flex-row sm:items-center"
      >
        <Link href={`/events/${e.id}`} className="absolute inset-0 rounded-xl" aria-label={e.title} />
        <div className="pointer-events-none flex min-w-0 flex-1 gap-4">
          <DateTile
            date={e.startAt}
            locale={locale}
            color={colorFor("eventType", e.type)}
            className={isPast ? "opacity-60" : ""}
          />
          <div className="min-w-0">
            <div className="mb-1 flex flex-wrap items-center gap-2">
              <Badge color={colorFor("eventType", e.type)}>{t.enums.eventType[e.type]}</Badge>
              {e.pole && <Badge color={e.pole.color}>{e.pole.name}</Badge>}
            </div>
            <p className="truncate font-medium group-hover:text-primary">{e.title}</p>
            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span className="inline-flex items-center gap-1">
                <Clock className="size-3.5" />
                {formatTime(e.startAt, locale)}
                {e.endAt && ` – ${formatTime(e.endAt, locale)}`}
              </span>
              {e.location && (
                <span className="inline-flex items-center gap-1">
                  <MapPin className="size-3.5" /> {e.location}
                </span>
              )}
              <span className="inline-flex items-center gap-1">
                <Users className="size-3.5" /> {t.common.countAttendees(going)}
              </span>
            </div>
          </div>
        </div>
        {!isPast && (
          <div className="relative z-10">
            <RsvpButtons eventId={e.id} status={mine} compact />
          </div>
        )}
      </li>
    );
  };

  return (
    <>
      <PageHeader
        title={t.events.title}
        description={t.events.subtitle}
        actions={
          <>
            {toggle}
            {newEvent(true)}
          </>
        }
      />
      <section className="mb-10">
        <h2 className="mb-3 text-[15px] font-semibold">{t.events.upcoming}</h2>
        {upcoming.length === 0 ? (
          <EmptyState icon={<CalendarDays />} title={t.events.noUpcoming} action={newEvent(false)} />
        ) : (
          <ul className="space-y-3">{upcoming.map((e) => renderEvent(e, false))}</ul>
        )}
      </section>
      {past.length > 0 && (
        <section>
          <h2 className="mb-3 text-[15px] font-semibold text-muted-foreground">{t.events.past}</h2>
          <ul className="space-y-3">{past.map((e) => renderEvent(e, true))}</ul>
        </section>
      )}
    </>
  );
}
