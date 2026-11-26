"use client";

import Link from "@/components/ui/link";
import { useLocale, useT } from "@/lib/i18n/client";
import { colorOf } from "@/lib/colors";
import { cn, formatDate } from "@/lib/utils";
import type { ProjectCardData } from "./project-card";

type TimelineProject = ProjectCardData & { startDate: Date | null };

const DAY = 86_400_000;

/** Gantt-like view of projects across months. */
/** `now` is passed by the server page so the server and browser renders agree. */
export function ProjectTimeline({ projects, now }: { projects: TimelineProject[]; now: Date }) {
  const t = useT();
  const locale = useLocale();
  const scheduled = projects.filter((p) => p.startDate || p.dueDate);
  const unscheduled = projects.filter((p) => !p.startDate && !p.dueDate);

  if (scheduled.length === 0) {
    return (
      <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
        {t.projects.timelineEmpty}
      </p>
    );
  }

  const times = scheduled.flatMap((p) => [p.startDate, p.dueDate].filter(Boolean).map((d) => new Date(d!).getTime()));
  times.push(now.getTime());
  const minDate = new Date(Math.min(...times));
  const maxDate = new Date(Math.max(...times));
  const start = new Date(Date.UTC(minDate.getUTCFullYear(), minDate.getUTCMonth(), 1));
  let end = new Date(Date.UTC(maxDate.getUTCFullYear(), maxDate.getUTCMonth() + 1, 1));
  // Show at least 4 months.
  const minEnd = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 4, 1));
  if (end < minEnd) end = minEnd;
  const total = end.getTime() - start.getTime();
  const pos = (d: Date) => ((d.getTime() - start.getTime()) / total) * 100;

  const months: Date[] = [];
  for (let d = new Date(start); d < end; d = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1)))
    months.push(d);
  const todayPos = pos(now);

  return (
    <div className="overflow-hidden rounded-xl border bg-card card-shadow">
      <div className="scrollbar-thin overflow-x-auto">
        <div style={{ minWidth: Math.max(720, months.length * 110 + 220) }}>
          <div className="flex border-b bg-muted/40 text-xs font-medium text-muted-foreground">
            <div className="w-56 shrink-0 border-r px-4 py-2.5">{t.projects.title}</div>
            <div className="relative flex flex-1">
              {months.map((m, i) => (
                <div key={m.toISOString()} className="flex-1 border-r px-2 py-2.5 capitalize last:border-r-0">
                  {formatDate(
                    m,
                    locale,
                    i === 0 || m.getUTCMonth() === 0 ? { month: "short", year: "numeric" } : { month: "short" },
                  )}
                </div>
              ))}
            </div>
          </div>
          <div className="relative">
            {scheduled.map((p) => {
              const s = p.startDate ? new Date(p.startDate) : new Date(new Date(p.dueDate!).getTime() - 14 * DAY);
              const e = p.dueDate ? new Date(p.dueDate) : new Date(Math.max(now.getTime(), s.getTime()) + 30 * DAY);
              const left = pos(s);
              const width = Math.max(1.5, pos(e) - left);
              const c = colorOf(p.color);
              return (
                <div key={p.id} className="flex border-b last:border-b-0 hover:bg-hover/40">
                  <Link
                    href={`/projects/${p.id}`}
                    className="w-56 shrink-0 truncate border-r px-4 py-3 text-sm font-medium hover:text-primary"
                  >
                    {p.title}
                    {p.pole && (
                      <span className="block truncate text-xs font-normal text-muted-foreground">{p.pole.name}</span>
                    )}
                  </Link>
                  <div className="relative flex-1">
                    <div className="pointer-events-none absolute inset-0 flex">
                      {months.map((m) => (
                        <div
                          key={m.toISOString()}
                          className="flex-1 border-r border-dashed border-border/60 last:border-r-0"
                        />
                      ))}
                    </div>
                    <Link
                      href={`/projects/${p.id}`}
                      title={`${p.title} · ${p.progress}%`}
                      className={cn(
                        "absolute top-1/2 h-6 -translate-y-1/2 overflow-hidden rounded-md ring-1 ring-inset",
                        c.soft,
                        c.ring,
                      )}
                      style={{ left: `${left}%`, width: `${width}%` }}
                    >
                      <span
                        className={cn("absolute inset-y-0 left-0 opacity-80", c.bar)}
                        style={{ width: `${p.progress}%` }}
                      />
                      <span className="relative z-10 flex h-full items-center px-2 text-[11px] font-semibold whitespace-nowrap text-foreground mix-blend-normal">
                        {p.progress}%
                      </span>
                    </Link>
                  </div>
                </div>
              );
            })}
            {todayPos >= 0 && todayPos <= 100 && (
              <div className="pointer-events-none absolute inset-y-0 right-0 left-56">
                <div className="absolute inset-y-0 w-px bg-destructive/70" style={{ left: `${todayPos}%` }}>
                  <span className="absolute -top-0 -translate-x-1/2 rounded-b bg-destructive px-1 text-[9px] font-semibold text-white uppercase">
                    {t.common.today}
                  </span>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
      {unscheduled.length > 0 && (
        <div className="border-t bg-muted/30 px-4 py-3 text-sm">
          <span className="mr-2 text-xs font-medium text-muted-foreground">{t.projects.unscheduled}:</span>
          {unscheduled.map((p, i) => (
            <span key={p.id}>
              <Link href={`/projects/${p.id}`} className="hover:text-primary hover:underline">
                {p.title}
              </Link>
              {i < unscheduled.length - 1 && ", "}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
