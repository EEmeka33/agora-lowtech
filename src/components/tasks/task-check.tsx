"use client";

import * as React from "react";
import { Check } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { setTaskStatus } from "@/actions/tasks";
import { useT } from "@/lib/i18n/client";
import { cn } from "@/lib/utils";

/** Round checkbox that marks a task as done / to do. */
export function TaskCheck({ id, done, disabled }: { id: string; done: boolean; disabled?: boolean }) {
  const t = useT();
  const [optimistic, setOptimistic] = React.useOptimistic(done);
  const [pending, startTransition] = React.useTransition();
  return (
    <button
      type="button"
      disabled={disabled || pending}
      aria-pressed={optimistic}
      aria-label={optimistic ? t.foundation.markTodo : t.foundation.markDone}
      onClick={() =>
        startTransition(async () => {
          setOptimistic(!optimistic);
          const r = await setTaskStatus(id, optimistic ? "TODO" : "DONE");
          if (!r?.ok) toast.error(r?.error ?? t.common.genericError);
        })
      }
      className={cn(
        "flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-all disabled:cursor-not-allowed disabled:opacity-50",
        optimistic ? "border-success bg-success text-white" : "border-input hover:border-success",
      )}
    >
      {optimistic && <Check className="size-3" strokeWidth={3.5} />}
    </button>
  );
}
