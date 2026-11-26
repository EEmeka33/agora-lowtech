"use client";

import Link from "@/components/ui/link";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/lib/i18n/client";
import { colorFor, colorOf } from "@/lib/colors";
import { cn } from "@/lib/utils";
import { DueChip, type TaskCardData } from "./task-card";
import { TaskCheck } from "./task-check";

export function TaskTable({ title, status, tasks }: { title?: string; status?: string; tasks: TaskCardData[] }) {
  const t = useT();
  return (
    <section>
      {title && (
        <h3 className="mb-2 flex items-center gap-2 text-[13px] font-semibold">
          <span className={cn("size-2 rounded-full", colorOf(colorFor("taskStatus", status)).dot)} />
          {title}
          <span className="text-muted-foreground">{tasks.length}</span>
        </h3>
      )}
      <ul className="divide-y overflow-hidden rounded-xl border bg-card card-shadow">
        {tasks.map((task) => (
          <li key={task.id} className="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-hover/50">
            <TaskCheck id={task.id} done={task.status === "DONE"} disabled={!task.canEdit} />
            <Link href={`/tasks/${task.id}`} className="min-w-0 flex-1">
              <span
                className={cn(
                  "block truncate text-sm font-medium",
                  task.status === "DONE" && "text-muted-foreground line-through",
                )}
              >
                {task.title}
              </span>
              {task.project && (
                <span className={cn("block truncate text-xs", colorOf(task.project.color).text)}>
                  {task.project.title}
                </span>
              )}
            </Link>
            {(task.priority === "HIGH" || task.priority === "URGENT") && (
              <Badge color={colorFor("priority", task.priority)} className="hidden sm:inline-flex">
                {t.enums.priority[task.priority]}
              </Badge>
            )}
            <span className="hidden w-28 justify-end sm:flex">
              <DueChip date={task.dueDate} done={task.status === "DONE"} />
            </span>
            {task.assignee ? (
              <Avatar name={task.assignee.name} color={task.assignee.avatarColor} size="sm" />
            ) : (
              <span className="size-6 rounded-full border border-dashed" title={t.common.unassigned} />
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
