"use client";

import * as React from "react";
import { toast } from "@/components/ui/toast";
import { setTaskStatus } from "@/actions/tasks";
import { useT } from "@/lib/i18n/client";
import { TASK_STATUSES } from "@/lib/constants";
import { colorFor, colorOf } from "@/lib/colors";
import { cn } from "@/lib/utils";

export function TaskStatusPicker({ id, status, disabled }: { id: string; status: string; disabled?: boolean }) {
  const t = useT();
  const [optimistic, setOptimistic] = React.useOptimistic(status);
  const [, startTransition] = React.useTransition();
  return (
    <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
      {TASK_STATUSES.map((s) => {
        const active = optimistic === s;
        const c = colorOf(colorFor("taskStatus", s));
        return (
          <button
            key={s}
            type="button"
            disabled={disabled}
            onClick={() =>
              startTransition(async () => {
                setOptimistic(s);
                const r = await setTaskStatus(id, s);
                if (!r?.ok) toast.error(r?.error ?? t.common.genericError);
              })
            }
            className={cn(
              "flex items-center justify-center gap-1.5 rounded-lg border px-2 py-1.5 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60",
              active ? cn(c.soft, "border-transparent") : "text-muted-foreground hover:bg-hover",
            )}
          >
            <span className={cn("size-1.5 rounded-full", c.dot)} />
            {t.enums.taskStatus[s]}
          </button>
        );
      })}
    </div>
  );
}
