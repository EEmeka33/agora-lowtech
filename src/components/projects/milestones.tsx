"use client";

import * as React from "react";
import { Check, Flag, Plus, Trash2 } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { addMilestone, deleteMilestone, toggleMilestone } from "@/actions/projects";
import { ActionForm, SubmitButton } from "@/components/forms/action-form";
import { Input } from "@/components/ui/input";
import { useLocale, useT } from "@/lib/i18n/client";
import { cn, formatDate, isOverdue } from "@/lib/utils";

type Milestone = { id: string; title: string; dueDate: Date | null; done: boolean };

export function Milestones({
  projectId,
  milestones,
  canEdit,
}: {
  projectId: string;
  milestones: Milestone[];
  canEdit: boolean;
}) {
  const t = useT();
  const locale = useLocale();
  const [pending, startTransition] = React.useTransition();
  const run = (fn: () => Promise<{ ok: boolean; error?: string } | null>) =>
    startTransition(async () => {
      const r = await fn();
      if (!r?.ok) toast.error(r?.error ?? t.common.genericError);
    });

  return (
    <div>
      {milestones.length === 0 && <p className="mb-3 text-sm text-muted-foreground">{t.projects.noMilestones}</p>}
      <ol className="relative space-y-1">
        {milestones.map((m, i) => (
          <li key={m.id} className="group relative flex items-center gap-3 rounded-lg py-1.5 pr-1">
            {i < milestones.length - 1 && <span className="absolute top-7 bottom-[-10px] left-[11px] w-px bg-border" />}
            <button
              type="button"
              disabled={!canEdit || pending}
              onClick={() => run(() => toggleMilestone(m.id))}
              className={cn(
                "relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2 bg-card transition-colors disabled:cursor-default",
                m.done ? "border-success bg-success text-white" : "border-input hover:border-success",
              )}
              aria-label={m.done ? t.foundation.markTodo : t.foundation.markDone}
            >
              {m.done ? (
                <Check className="size-3.5" strokeWidth={3} />
              ) : (
                <Flag className="size-3 text-muted-foreground" />
              )}
            </button>
            <span className={cn("flex-1 text-sm", m.done && "text-muted-foreground line-through")}>{m.title}</span>
            {m.dueDate && (
              <span
                className={cn(
                  "text-xs",
                  isOverdue(m.dueDate, m.done) ? "font-medium text-destructive" : "text-muted-foreground",
                )}
              >
                {formatDate(m.dueDate, locale, { day: "numeric", month: "short" })}
              </span>
            )}
            {canEdit && (
              <button
                type="button"
                onClick={() => run(() => deleteMilestone(m.id))}
                className="rounded p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive focus:opacity-100"
                aria-label={t.common.delete}
              >
                <Trash2 className="size-3.5" />
              </button>
            )}
          </li>
        ))}
      </ol>
      {canEdit && (
        <ActionForm
          action={addMilestone.bind(null, projectId)}
          resetOnSuccess
          successToast={false}
          className="mt-3 grid grid-cols-[1fr_auto] gap-2"
        >
          <Input
            name="title"
            placeholder={t.projects.milestonePlaceholder}
            className="col-span-2 h-8 text-[13px]"
            required
            aria-label={t.projects.addMilestone}
          />
          <Input name="dueDate" type="date" className="h-8 text-[13px]" aria-label={t.common.dueDate} />
          <SubmitButton size="sm" variant="soft" aria-label={t.projects.addMilestone}>
            <Plus />
          </SubmitButton>
        </ActionForm>
      )}
    </div>
  );
}
