"use client";

import Link from "@/components/ui/link";
import { CalendarDays, CheckSquare } from "lucide-react";
import { AvatarStack } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { useLocale, useT } from "@/lib/i18n/client";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, isOverdue } from "@/lib/utils";

export type ProjectCardData = {
  id: string;
  title: string;
  description: string | null;
  status: string;
  color: string;
  dueDate: Date | null;
  progress: number;
  health: string | null;
  tasksDone: number;
  tasksTotal: number;
  pole: { name: string; color: string } | null;
  lead: { id: string; name: string; avatarColor: string } | null;
  members: { id: string; name: string; avatarColor: string }[];
};

export function HealthBadge({ health }: { health: string | null }) {
  const t = useT();
  if (!health) return null;
  return (
    <Badge color={colorFor("health", health)} dot>
      {t.enums.health[health]}
    </Badge>
  );
}

export function ProjectCard({ project }: { project: ProjectCardData }) {
  const t = useT();
  const locale = useLocale();
  const c = colorOf(project.color);
  const done = project.status === "COMPLETED";
  return (
    <Link
      href={`/projects/${project.id}`}
      className="group relative flex flex-col overflow-hidden rounded-xl border bg-card card-shadow transition-[border-color,transform,box-shadow] hover:-translate-y-0.5 hover:border-primary/40 hover:pop-shadow"
    >
      <span className={cn("absolute inset-x-0 top-0 h-1", c.bar)} />
      <div className="flex flex-1 flex-col p-5">
        <div className="mb-3 flex flex-wrap items-center gap-1.5">
          <Badge color={colorFor("projectStatus", project.status)}>{t.enums.projectStatus[project.status]}</Badge>
          <HealthBadge health={project.health} />
        </div>
        <h3 className="font-semibold tracking-tight text-balance group-hover:text-primary">{project.title}</h3>
        {project.pole && (
          <p className={cn("mt-0.5 text-xs font-medium", colorOf(project.pole.color).text)}>{project.pole.name}</p>
        )}
        {project.description && (
          <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{project.description}</p>
        )}
        <div className="mt-auto pt-5">
          <div className="mb-1.5 flex items-center justify-between text-xs">
            <span className="text-muted-foreground">{t.common.progress}</span>
            <span className="font-semibold tabular-nums">{project.progress}%</span>
          </div>
          <Progress value={project.progress} color={done ? "emerald" : project.color} size="sm" />
          <div className="mt-4 flex items-center gap-3 text-xs text-muted-foreground">
            <span className="inline-flex items-center gap-1">
              <CheckSquare className="size-3.5" />
              {t.projects.tasksDone(project.tasksDone, project.tasksTotal)}
            </span>
            {project.dueDate && (
              <span
                className={cn(
                  "inline-flex items-center gap-1",
                  isOverdue(project.dueDate, done) && "font-medium text-destructive",
                )}
              >
                <CalendarDays className="size-3.5" />
                {formatDate(project.dueDate, locale, { day: "numeric", month: "short" })}
              </span>
            )}
            <span className="ml-auto">
              <AvatarStack
                people={
                  project.lead
                    ? [project.lead, ...project.members.filter((m) => m.id !== project.lead!.id)]
                    : project.members
                }
                max={3}
                size="sm"
              />
            </span>
          </div>
        </div>
      </div>
    </Link>
  );
}

/** Compact row used on dashboards and pole pages. */
export function ProjectRow({ project }: { project: ProjectCardData }) {
  const t = useT();
  return (
    <Link
      href={`/projects/${project.id}`}
      className="group flex items-center gap-4 rounded-lg px-2 py-2.5 transition-colors hover:bg-hover/60"
    >
      <span className={cn("h-9 w-1 shrink-0 rounded-full", colorOf(project.color).bar)} />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <p className="truncate text-sm font-medium group-hover:text-primary">{project.title}</p>
          <HealthBadge health={project.health} />
        </div>
        <div className="mt-1.5 flex items-center gap-3">
          <Progress value={project.progress} color={project.color} size="sm" className="max-w-56" />
          <span className="text-xs text-muted-foreground tabular-nums">{project.progress}%</span>
        </div>
      </div>
      <span className="hidden text-xs text-muted-foreground sm:block">
        {t.projects.tasksDone(project.tasksDone, project.tasksTotal)}
      </span>
    </Link>
  );
}
