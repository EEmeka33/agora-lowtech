import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { ChevronLeft, Clock, FolderKanban, Layers, MapPin, Pencil, ScrollText, Trash2 } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getPoleOptions, getProjectOptions } from "@/lib/queries";
import { colorFor } from "@/lib/colors";
import { formatDate, formatTime, nowWall } from "@/lib/utils";
import { deleteEvent, saveMinutes, updateEvent } from "@/actions/events";
import { RSVP } from "@/lib/constants";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RichText } from "@/components/ui/rich-text";
import { FormDialog } from "@/components/forms/form-dialog";
import { ConfirmButton } from "@/components/forms/confirm-dialog";
import { InlineTextForm } from "@/components/forms/comment-form";
import { EventFields } from "@/components/events/event-fields";
import { RsvpButtons } from "@/components/events/rsvp-buttons";
import { DateTile } from "@/components/events/date-tile";

export const metadata: Metadata = { title: "Event" };

export default async function EventPage(props: PageProps<"/events/[id]">) {
  const { user, db } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const event = await db.event.findUnique({
    where: { id },
    include: {
      pole: { select: { id: true, name: true, color: true } },
      project: { select: { id: true, title: true } },
      createdBy: { select: { name: true } },
      attendees: {
        include: { user: { select: { id: true, name: true, avatarColor: true } } },
        orderBy: { updatedAt: "asc" },
      },
    },
  });
  if (!event) notFound();
  const canManage = can.manageEvents(user);
  const canMinutes = canManage || ["SECRETARY", "DEPUTY_SECRETARY", "PRESIDENT"].includes(user.boardPosition ?? "");
  const [poles, projects] = await Promise.all([getPoleOptions(db), getProjectOptions(db)]);
  const mine = event.attendees.find((a) => a.userId === user.id)?.status ?? null;
  const isPast = (event.endAt ?? event.startAt) < nowWall();
  const isMeeting = ["GENERAL_ASSEMBLY", "BOARD_MEETING", "POLE_MEETING"].includes(event.type);

  return (
    <div className="mx-auto max-w-5xl">
      <Link
        href="/events"
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {t.events.title}
      </Link>

      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex gap-4">
          <DateTile date={event.startAt} locale={locale} color={colorFor("eventType", event.type)} className="w-16" />
          <div className="min-w-0">
            <Badge color={colorFor("eventType", event.type)} className="mb-2">
              {t.enums.eventType[event.type]}
            </Badge>
            <h1 className="text-2xl font-semibold tracking-tight text-balance">{event.title}</h1>
            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
              <span className="inline-flex items-center gap-1.5">
                <Clock className="size-4" />
                {formatDate(event.startAt, locale, { weekday: "long", day: "numeric", month: "long" })} ·{" "}
                {formatTime(event.startAt, locale)}
                {event.endAt && ` – ${formatTime(event.endAt, locale)}`}
              </span>
              {event.location && (
                <span className="inline-flex items-center gap-1.5">
                  <MapPin className="size-4" /> {event.location}
                </span>
              )}
              {event.pole && (
                <Link
                  href={`/poles/${event.pole.id}`}
                  className="inline-flex items-center gap-1.5 hover:text-foreground"
                >
                  <Layers className="size-4" /> {event.pole.name}
                </Link>
              )}
              {event.project && (
                <Link
                  href={`/projects/${event.project.id}`}
                  className="inline-flex items-center gap-1.5 hover:text-foreground"
                >
                  <FolderKanban className="size-4" /> {event.project.title}
                </Link>
              )}
            </div>
          </div>
        </div>
        {canManage && (
          <div className="flex shrink-0 gap-2">
            <FormDialog
              title={t.events.editEvent}
              action={updateEvent.bind(null, event.id)}
              size="lg"
              resetOnSuccess={false}
              trigger={
                <Button variant="outline">
                  <Pencil /> {t.common.edit}
                </Button>
              }
            >
              <EventFields
                event={{
                  title: event.title,
                  description: event.description,
                  type: event.type,
                  startAt: event.startAt,
                  endAt: event.endAt,
                  location: event.location,
                  agenda: event.agenda,
                  poleId: event.poleId,
                  projectId: event.projectId,
                }}
                poles={poles}
                projects={projects.map((p) => ({ id: p.id, name: p.title }))}
              />
            </FormDialog>
            <ConfirmButton title={t.events.deleteEvent} action={deleteEvent.bind(null, event.id)} redirectTo="/events">
              <Button variant="outline" size="icon" aria-label={t.common.delete}>
                <Trash2 />
              </Button>
            </ConfirmButton>
          </div>
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          {(event.description || event.agenda) && (
            <Card>
              <CardContent className="space-y-5 py-5">
                {event.description && <RichText text={event.description} />}
                {event.agenda && (
                  <div>
                    <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                      {t.events.agenda}
                    </p>
                    <RichText text={event.agenda} className="rounded-xl bg-muted/50 p-4" />
                  </div>
                )}
              </CardContent>
            </Card>
          )}
          {(isMeeting || event.minutes || isPast) && (
            <Card>
              <CardHeader>
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <ScrollText className="size-4 text-primary" /> {t.events.minutes}
                  </CardTitle>
                  <CardDescription className="print:hidden">{t.events.minutesHint}</CardDescription>
                </div>
              </CardHeader>
              <CardContent>
                {canMinutes ? (
                  <>
                    <div className="print:hidden">
                      <InlineTextForm
                        action={saveMinutes.bind(null, event.id)}
                        name="minutes"
                        defaultValue={event.minutes ?? ""}
                        placeholder={t.events.minutesPlaceholder}
                        submitLabel={t.events.saveMinutes}
                        rows={10}
                        resetOnSuccess={false}
                        successToast
                      />
                    </div>
                    {/* A text box cannot grow on paper: print the minutes as text. */}
                    {event.minutes && <RichText text={event.minutes} className="hidden print:block" />}
                  </>
                ) : event.minutes ? (
                  <RichText text={event.minutes} />
                ) : (
                  <p className="text-sm text-muted-foreground">—</p>
                )}
              </CardContent>
            </Card>
          )}
        </div>

        <div className="space-y-4">
          {!isPast && (
            <Card className="print:hidden">
              <CardContent className="space-y-3 py-4">
                <p className="text-sm font-medium">{t.events.yourAnswer}</p>
                <RsvpButtons eventId={event.id} status={mine} />
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader>
              <CardTitle>{t.events.attendance}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {event.attendees.length === 0 && <p className="text-sm text-muted-foreground">{t.events.noAnswers}</p>}
              {RSVP.map((status) => {
                const list = event.attendees.filter((a) => a.status === status);
                if (!list.length) return null;
                return (
                  <div key={status}>
                    <p className="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                      <Badge color={colorFor("rsvp", status)} dot>
                        {t.enums.rsvp[status]}
                      </Badge>
                      {list.length}
                    </p>
                    <ul className="space-y-1.5">
                      {list.map((a) => (
                        <li key={a.userId}>
                          <Link
                            href={`/members/${a.userId}`}
                            className="flex items-center gap-2 text-sm hover:underline"
                          >
                            <Avatar name={a.user.name} color={a.user.avatarColor} size="sm" />
                            {a.user.name}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </div>
                );
              })}
            </CardContent>
          </Card>
          {event.createdBy && (
            <p className="px-1 text-xs text-muted-foreground">{t.tasks.createdBy(event.createdBy.name)}</p>
          )}
        </div>
      </div>
    </div>
  );
}
