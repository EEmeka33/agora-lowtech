"use client";

import * as React from "react";
import { toast } from "@/components/ui/toast";
import { setTaskStatus } from "@/actions/tasks";
import { useT } from "@/lib/i18n/client";
import { TASK_STATUSES } from "@/lib/constants";
import { colorFor, colorOf } from "@/lib/colors";
import { cn } from "@/lib/utils";
import { TaskCard, type TaskCardData } from "./task-card";

/** Kanban board: drag cards between columns to change their status. */
export function TaskBoard({ tasks, compact }: { tasks: TaskCardData[]; compact?: boolean }) {
  const t = useT();
  const [items, setItems] = React.useState(tasks);
  const [dragId, setDragId] = React.useState<string | null>(null);
  const [over, setOver] = React.useState<string | null>(null);
  const [, startTransition] = React.useTransition();

  // Keep in sync with fresh server data.
  const [prevTasks, setPrevTasks] = React.useState(tasks);
  if (tasks !== prevTasks) {
    setPrevTasks(tasks);
    setItems(tasks);
  }

  const move = (id: string, status: string) => {
    const task = items.find((x) => x.id === id);
    if (!task || task.status === status) return;
    if (!task.canEdit) {
      toast.error(t.common.notAllowed);
      return;
    }
    const previous = items;
    setItems((list) => list.map((x) => (x.id === id ? { ...x, status } : x)));
    startTransition(async () => {
      const result = await setTaskStatus(id, status);
      if (!result?.ok) {
        setItems(previous);
        toast.error(result?.error ?? t.common.genericError);
      }
    });
  };

  return (
    <div
      className={cn(
        "grid gap-4 overflow-x-auto pb-2 print:grid-flow-row print:grid-cols-2 print:overflow-visible",
        compact ? "md:grid-cols-4" : "auto-cols-[minmax(260px,1fr)] grid-flow-col lg:grid-flow-row lg:grid-cols-4",
      )}
    >
      {TASK_STATUSES.map((status) => {
        const column = items.filter((x) => x.status === status);
        const c = colorOf(colorFor("taskStatus", status));
        return (
          <section
            key={status}
            onDragOver={(e) => {
              e.preventDefault();
              e.dataTransfer.dropEffect = "move";
              setOver(status);
            }}
            onDragLeave={(e) => {
              if (!e.currentTarget.contains(e.relatedTarget as Node)) setOver(null);
            }}
            onDrop={(e) => {
              e.preventDefault();
              const id = e.dataTransfer.getData("text/plain");
              setOver(null);
              setDragId(null);
              if (id) move(id, status);
            }}
            className={cn(
              "flex min-h-40 flex-col rounded-2xl border border-transparent bg-muted/50 p-2 transition-colors",
              over === status && "border-primary/40 bg-primary-soft/50",
            )}
          >
            <header className="flex items-center gap-2 px-2 pt-1 pb-2.5">
              <span className={cn("size-2 rounded-full", c.dot)} />
              <h3 className="text-[13px] font-semibold">{t.enums.taskStatus[status]}</h3>
              <span className="ml-auto rounded-full bg-card px-2 py-px text-xs text-muted-foreground tabular-nums">
                {column.length}
              </span>
            </header>
            <div className="flex flex-1 flex-col gap-2">
              {column.map((task) => (
                <div
                  key={task.id}
                  draggable={task.canEdit}
                  onDragStart={(e) => {
                    e.dataTransfer.setData("text/plain", task.id);
                    e.dataTransfer.effectAllowed = "move";
                    setDragId(task.id);
                  }}
                  onDragEnd={() => {
                    setDragId(null);
                    setOver(null);
                  }}
                  className={cn(task.canEdit && "cursor-grab active:cursor-grabbing")}
                >
                  <TaskCard task={task} dragging={dragId === task.id} />
                </div>
              ))}
              {column.length === 0 && (
                <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed px-3 py-6 text-center text-xs text-muted-foreground">
                  {t.tasks.emptyColumn}
                </div>
              )}
            </div>
          </section>
        );
      })}
    </div>
  );
}
