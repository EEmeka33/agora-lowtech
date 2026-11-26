"use client";

import Link from "@/components/ui/link";
import { CalendarClock, MessageSquare } from "lucide-react";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { useLocale, useT } from "@/lib/i18n/client";
import { cn, daysBetween, formatDate, isOverdue, nowWall } from "@/lib/utils";
import { colorFor, colorOf } from "@/lib/colors";

export type TaskCardData = {
  id: string;
  title: string;
  status: string;
  priority: string;
  dueDate: Date | null;
  commentCount: number;
  canEdit: boolean;
  project: { id: string; title: string; color: string } | null;
  assignee: { id: string; name: string; avatarColor: string } | null;
};

export function DueChip({ date, done }: { date: Date | null; done?: boolean }) {
  const t = useT();
  const locale = useLocale();
  if (!date) return null;
  const overdue = isOverdue(date, done);
  const days = daysBetween(nowWall(), new Date(date));
  const soon = !done && days >= 0 && days <= 2;
  return (
    <span
      title={formatDate(date, locale)}
      className={cn(
        "inline-flex items-center gap-1 text-xs",
        overdue ? "font-medium text-destructive" : soon ? "font-medium text-warning" : "text-muted-foreground",
      )}
    >
      <CalendarClock className="size-3.5" />
      {overdue
        ? t.common.overdueBy(-days)
        : days <= 1 && days >= 0
          ? t.common.dueIn(days)
          : formatDate(date, locale, { day: "numeric", month: "short" })}
    </span>
  );
}

export function TaskCard({ task, dragging }: { task: TaskCardData; dragging?: boolean }) {
  const t = useT();
  const done = task.status === "DONE";
  return (
    <Link
      href={`/tasks/${task.id}`}
      draggable={false}
      className={cn(
        "group block rounded-xl border bg-card p-3 card-shadow transition-[border-color,box-shadow,opacity] hover:border-primary/40",
        dragging && "opacity-40",
      )}
    >
      {task.project && (
        <span
          className={cn(
            "mb-1.5 inline-flex max-w-full items-center gap-1.5 truncate text-[11px] font-medium",
            colorOf(task.project.color).text,
          )}
        >
          <span className={cn("size-1.5 shrink-0 rounded-full", colorOf(task.project.color).dot)} />
          <span className="truncate">{task.project.title}</span>
        </span>
      )}
      <p
        className={cn(
          "text-sm leading-snug font-medium",
          done && "text-muted-foreground line-through decoration-muted-foreground/50",
        )}
      >
        {task.title}
      </p>
      <div className="mt-2.5 flex items-center gap-2">
        {(task.priority === "HIGH" || task.priority === "URGENT") && (
          <Badge color={colorFor("priority", task.priority)} className="px-1.5 text-[11px]">
            {t.enums.priority[task.priority]}
          </Badge>
        )}
        <DueChip date={task.dueDate} done={done} />
        {task.commentCount > 0 && (
          <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <MessageSquare className="size-3.5" />
            {task.commentCount}
          </span>
        )}
        <span className="ml-auto">
          {task.assignee ? (
            <Avatar name={task.assignee.name} color={task.assignee.avatarColor} size="sm" />
          ) : (
            <span className="inline-block size-6 rounded-full border border-dashed" title={t.common.unassigned} />
          )}
        </span>
      </div>
    </Link>
  );
}
