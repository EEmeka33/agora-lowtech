import Link from "@/components/ui/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, formatTime, nowWall } from "@/lib/utils";
import type { Dictionary } from "@/lib/i18n/dictionaries";

type CalEvent = { id: string; title: string; type: string; startAt: Date };
type CalTask = { id: string; title: string; dueDate: Date };

/** Month grid. Weeks start on Monday. */
export function MonthCalendar({
  year,
  month,
  events,
  tasks,
  t,
  locale,
  hrefFor,
}: {
  year: number;
  month: number; // 0-11
  events: CalEvent[];
  tasks: CalTask[];
  t: Dictionary;
  locale: string;
  hrefFor: (year: number, month: number) => string;
}) {
  const first = new Date(Date.UTC(year, month, 1));
  const offset = (first.getUTCDay() + 6) % 7;
  const start = new Date(Date.UTC(year, month, 1 - offset));
  const days = Array.from({ length: 42 }, (_, i) => new Date(start.getTime() + i * 86_400_000));
  const todayKey = nowWall().toISOString().slice(0, 10);
  const key = (d: Date) => d.toISOString().slice(0, 10);
  const prev = month === 0 ? [year - 1, 11] : [year, month - 1];
  const next = month === 11 ? [year + 1, 0] : [year, month + 1];
  const weeks = days.slice(35).some((d) => d.getUTCMonth() === month) ? 6 : 5;

  return (
    <div className="overflow-hidden rounded-xl border bg-card card-shadow">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <h2 className="font-semibold capitalize">{formatDate(first, locale, { month: "long", year: "numeric" })}</h2>
        <div className="flex items-center gap-1">
          <Link
            href={hrefFor(prev[0], prev[1])}
            scroll={false}
            className="rounded-md p-1.5 hover:bg-hover"
            aria-label={t.common.previous}
          >
            <ChevronLeft className="size-4" />
          </Link>
          <Link
            href={hrefFor(nowWall().getUTCFullYear(), nowWall().getUTCMonth())}
            scroll={false}
            className="rounded-md px-2 py-1 text-sm hover:bg-hover"
          >
            {t.events.today}
          </Link>
          <Link
            href={hrefFor(next[0], next[1])}
            scroll={false}
            className="rounded-md p-1.5 hover:bg-hover"
            aria-label={t.common.next}
          >
            <ChevronRight className="size-4" />
          </Link>
        </div>
      </div>
      <div className="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-medium text-muted-foreground">
        {t.events.weekdays.map((d) => (
          <div key={d} className="py-2">
            {d}
          </div>
        ))}
      </div>
      <div className="grid grid-cols-7">
        {days.slice(0, weeks * 7).map((day) => {
          const k = key(day);
          const inMonth = day.getUTCMonth() === month;
          const dayEvents = events.filter((e) => key(e.startAt) === k);
          const dayTasks = tasks.filter((x) => key(x.dueDate) === k);
          const items = [
            ...dayEvents.map((e) => ({ kind: "event" as const, ...e })),
            ...dayTasks.map((x) => ({ kind: "task" as const, ...x })),
          ];
          return (
            <div
              key={k}
              className={cn(
                "min-h-24 border-r border-b p-1.5 last:border-r-0 sm:min-h-28 [&:nth-child(7n)]:border-r-0",
                !inMonth && "bg-muted/30",
              )}
            >
              <div className="mb-1 flex justify-end">
                <span
                  className={cn(
                    "flex size-6 items-center justify-center rounded-full text-xs tabular-nums",
                    k === todayKey
                      ? "bg-primary font-semibold text-primary-foreground"
                      : inMonth
                        ? ""
                        : "text-muted-foreground/60",
                  )}
                >
                  {day.getUTCDate()}
                </span>
              </div>
              <div className="space-y-1">
                {items.slice(0, 3).map((item) =>
                  item.kind === "event" ? (
                    <Link
                      key={item.id}
                      href={`/events/${item.id}`}
                      title={item.title}
                      className={cn(
                        "block truncate rounded px-1.5 py-0.5 text-[11px] font-medium",
                        colorOf(colorFor("eventType", item.type)).soft,
                      )}
                    >
                      <span className="hidden tabular-nums opacity-70 sm:inline">
                        {formatTime(item.startAt, locale)}{" "}
                      </span>
                      {item.title}
                    </Link>
                  ) : (
                    <Link
                      key={item.id}
                      href={`/tasks/${item.id}`}
                      title={item.title}
                      className="flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[11px] text-muted-foreground hover:bg-hover"
                    >
                      <span className="size-1.5 shrink-0 rounded-full border border-current" />
                      <span className="truncate">{item.title}</span>
                    </Link>
                  ),
                )}
                {items.length > 3 && (
                  <p className="px-1.5 text-[11px] text-muted-foreground">{t.events.moreEvents(items.length - 3)}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
