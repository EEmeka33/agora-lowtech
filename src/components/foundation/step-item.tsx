"use client";

import * as React from "react";
import { Check, ChevronDown, CircleDashed, ExternalLink, Pencil, SkipForward, Trash2 } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { setStepStatus, deleteStep, updateStep } from "@/actions/foundation";
import { RowActions, type RowAction } from "@/components/forms/row-actions";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { RichText } from "@/components/ui/rich-text";
import { useLocale, useT } from "@/lib/i18n/client";
import { colorFor } from "@/lib/colors";
import { cn, formatDate, isOverdue } from "@/lib/utils";
import { StepFields } from "./step-fields";

export type StepData = {
  id: string;
  title: string;
  description: string | null;
  category: string;
  status: string;
  assigneeId: string | null;
  dueDate: Date | null;
  resourceUrl: string | null;
  notes: string | null;
  completedAt: Date | null;
  assignee: { id: string; name: string; avatarColor: string } | null;
};

export function StepItem({
  step,
  index,
  canManage,
  canToggle,
  members,
}: {
  step: StepData;
  index: number;
  canManage: boolean;
  canToggle: boolean;
  members: { id: string; name: string }[];
}) {
  const t = useT();
  const locale = useLocale();
  const [open, setOpen] = React.useState(false);
  const [optimistic, setOptimistic] = React.useOptimistic(step.status);
  const [pending, startTransition] = React.useTransition();
  const done = optimistic === "DONE";
  const skipped = optimistic === "SKIPPED";

  const setStatus = (status: string) =>
    startTransition(async () => {
      setOptimistic(status);
      const r = await setStepStatus(step.id, status);
      if (!r?.ok) toast.error(r?.error ?? t.common.genericError);
    });

  const items: RowAction[] = [];
  if (canManage || canToggle) {
    items.push(
      {
        kind: "dialog",
        label: t.common.edit,
        icon: <Pencil />,
        title: t.foundation.editStep,
        action: updateStep.bind(null, step.id),
        content: <StepFields step={step} members={members} />,
      },
      done || skipped
        ? {
            kind: "run",
            label: t.foundation.markTodo,
            icon: <CircleDashed />,
            action: setStepStatus.bind(null, step.id, "TODO"),
          }
        : {
            kind: "run",
            label: t.enums.stepStatus.SKIPPED,
            icon: <SkipForward />,
            action: setStepStatus.bind(null, step.id, "SKIPPED"),
          },
    );
  }
  if (canManage) {
    items.push(
      { kind: "separator" },
      {
        kind: "confirm",
        label: t.common.delete,
        icon: <Trash2 />,
        title: t.common.confirmDelete,
        action: deleteStep.bind(null, step.id),
        destructive: true,
      },
    );
  }

  return (
    <li className={cn("group rounded-xl transition-colors", open ? "bg-muted/50" : "hover:bg-hover/50")}>
      <div className="flex items-start gap-3 px-3 py-3">
        <button
          type="button"
          disabled={!canToggle || pending}
          onClick={() => setStatus(done ? "TODO" : "DONE")}
          aria-label={done ? t.foundation.markTodo : t.foundation.markDone}
          className={cn(
            "mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-semibold transition-all disabled:cursor-default",
            done
              ? "border-success bg-success text-white"
              : skipped
                ? "border-dashed border-muted-foreground/40 text-muted-foreground"
                : optimistic === "IN_PROGRESS"
                  ? "border-sky-500 text-sky-600 hover:bg-sky-500/10"
                  : "border-input text-muted-foreground hover:border-success hover:text-success",
          )}
        >
          {done ? <Check className="size-3.5" strokeWidth={3} /> : index + 1}
        </button>
        <button type="button" onClick={() => setOpen((o) => !o)} className="min-w-0 flex-1 text-left">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span
              className={cn(
                "text-sm font-medium",
                (done || skipped) && "text-muted-foreground",
                skipped && "line-through",
              )}
            >
              {step.title}
            </span>
            {optimistic === "IN_PROGRESS" && (
              <Badge color={colorFor("stepStatus", optimistic)}>{t.enums.stepStatus.IN_PROGRESS}</Badge>
            )}
            {skipped && <Badge color="slate">{t.enums.stepStatus.SKIPPED}</Badge>}
          </div>
          {!open && step.description && (
            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{step.description}</p>
          )}
        </button>
        <div className="flex shrink-0 items-center gap-2">
          {step.dueDate && !done && (
            <span
              className={cn(
                "hidden text-xs sm:inline",
                isOverdue(step.dueDate) ? "font-medium text-destructive" : "text-muted-foreground",
              )}
            >
              {formatDate(step.dueDate, locale, { day: "numeric", month: "short" })}
            </span>
          )}
          {step.assignee ? (
            <Avatar name={step.assignee.name} color={step.assignee.avatarColor} size="sm" />
          ) : (
            <span className="size-6 rounded-full border border-dashed" title={t.common.unassigned} />
          )}
          <button
            type="button"
            onClick={() => setOpen((o) => !o)}
            className="rounded p-1 text-muted-foreground hover:bg-hover"
            aria-label={t.common.details}
          >
            <ChevronDown className={cn("size-4 transition-transform", open && "rotate-180")} />
          </button>
          {items.length > 0 && <RowActions items={items} />}
        </div>
      </div>
      {open && (
        <div className="space-y-3 px-12 pb-4 text-sm">
          {step.description && <RichText text={step.description} className="text-muted-foreground" />}
          {step.notes && (
            <div className="rounded-lg border bg-card px-3 py-2">
              <p className="mb-0.5 text-xs font-medium text-muted-foreground">{t.common.notes}</p>
              <RichText text={step.notes} />
            </div>
          )}
          <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
            <span>
              {t.common.assignee}:{" "}
              <span className="font-medium text-foreground">{step.assignee?.name ?? t.common.unassigned}</span>
            </span>
            {step.dueDate && (
              <span>
                {t.common.dueDate}:{" "}
                <span className="font-medium text-foreground">{formatDate(step.dueDate, locale)}</span>
              </span>
            )}
            {step.completedAt && done && <span>{t.foundation.completedOn(formatDate(step.completedAt, locale))}</span>}
            {step.resourceUrl && (
              <a
                href={step.resourceUrl}
                target="_blank"
                rel="noreferrer noopener"
                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
              >
                <ExternalLink className="size-3.5" /> {t.foundation.resource}
              </a>
            )}
          </div>
          {canToggle && !done && (
            <div className="flex gap-2">
              {optimistic !== "IN_PROGRESS" && (
                <button
                  type="button"
                  onClick={() => setStatus("IN_PROGRESS")}
                  className="rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-hover"
                >
                  {t.enums.stepStatus.IN_PROGRESS}
                </button>
              )}
              <button
                type="button"
                onClick={() => setStatus("DONE")}
                className="rounded-md bg-success px-2.5 py-1 text-xs font-medium text-white hover:brightness-95"
              >
                {t.foundation.markDone}
              </button>
            </div>
          )}
        </div>
      )}
    </li>
  );
}
