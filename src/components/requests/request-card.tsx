"use client";

import * as React from "react";
import Link from "@/components/ui/link";
import { useRouter } from "next/navigation";
import { ArrowRight, CalendarClock, Hand, Loader2, Undo2, UserPlus, XCircle } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { assignRequest, cancelRequest, releaseRequest, takeRequest } from "@/actions/requests";
import { RowActions, type RowAction } from "@/components/forms/row-actions";
import { SelectField } from "@/components/forms/fields";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { useLocale, useT } from "@/lib/i18n/client";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, formatRelative, isOverdue } from "@/lib/utils";

export type RequestCardData = {
  id: string;
  title: string;
  description: string | null;
  priority: string;
  status: string;
  dueDate: Date | null;
  createdAt: Date;
  pole: { id: string; name: string; color: string };
  fromPole: { name: string } | null;
  requester: { name: string; avatarColor: string } | null;
  takenBy: { id: string; name: string; avatarColor: string } | null;
  taskId: string | null;
  canTake: boolean;
  canAssign: boolean;
  canCancel: boolean;
  canRelease: boolean;
  poleMembers: { id: string; name: string }[];
};

export const requestStatusColor: Record<string, string> = {
  OPEN: "sky",
  TAKEN: "violet",
  DONE: "emerald",
  CANCELLED: "slate",
};

export function TakeButton({ id, size = "sm" }: { id: string; size?: "sm" | "md" }) {
  const t = useT();
  const router = useRouter();
  const [pending, startTransition] = React.useTransition();
  return (
    <Button
      size={size}
      disabled={pending}
      onClick={(e) => {
        e.preventDefault();
        startTransition(async () => {
          const r = await takeRequest(id);
          if (r?.ok) {
            toast.success(r.message, {
              action: r.data?.taskId
                ? { label: t.requests.openTask, onClick: () => router.push(`/tasks/${r.data!.taskId}`) }
                : undefined,
            });
          } else toast.error(r?.error ?? t.common.genericError);
        });
      }}
    >
      {pending ? <Loader2 className="animate-spin" /> : <Hand />}
      {t.requests.take}
    </Button>
  );
}

export function requestActions(r: RequestCardData, t: ReturnType<typeof useT>): RowAction[] {
  const items: RowAction[] = [];
  if (r.status === "OPEN" && r.canAssign && r.poleMembers.length) {
    items.push({
      kind: "dialog",
      label: t.requests.assign,
      icon: <UserPlus />,
      title: t.requests.assignTitle,
      description: r.title,
      action: assignRequest.bind(null, r.id),
      submitLabel: t.requests.assign.replace("…", ""),
      content: (
        <SelectField
          name="userId"
          label={t.requests.assignTo}
          placeholder={t.common.selectPlaceholder}
          options={r.poleMembers.map((m) => ({ value: m.id, label: m.name }))}
          required
        />
      ),
    });
  }
  if (r.status === "TAKEN" && r.canRelease) {
    items.push({
      kind: "confirm",
      label: t.requests.release,
      icon: <Undo2 />,
      title: t.requests.release,
      description: t.requests.releaseConfirm,
      confirmLabel: t.requests.release,
      action: releaseRequest.bind(null, r.id),
    });
  }
  if (r.status === "OPEN" && r.canCancel) {
    items.push({
      kind: "confirm",
      label: t.requests.cancel,
      icon: <XCircle />,
      title: t.requests.cancel,
      description: t.requests.cancelConfirm,
      confirmLabel: t.requests.cancel,
      action: cancelRequest.bind(null, r.id),
      destructive: true,
    });
  }
  return items;
}

export function RequestCard({ request: r, compact }: { request: RequestCardData; compact?: boolean }) {
  const t = useT();
  const locale = useLocale();
  const items = requestActions(r, t);
  const closed = r.status === "DONE" || r.status === "CANCELLED";
  return (
    <article
      className={cn(
        "group relative rounded-xl border bg-card p-4 card-shadow transition-colors hover:border-primary/40",
        closed && "opacity-70",
      )}
    >
      <Link href={`/requests/${r.id}`} className="absolute inset-0 rounded-xl" aria-label={r.title} />
      <div className="pointer-events-none relative flex flex-wrap items-center gap-1.5 text-xs">
        <Badge color={requestStatusColor[r.status]} dot>
          {t.enums.requestStatus[r.status]}
        </Badge>
        {(r.priority === "HIGH" || r.priority === "URGENT") && (
          <Badge color={colorFor("priority", r.priority)}>{t.enums.priority[r.priority]}</Badge>
        )}
        <span className="inline-flex items-center gap-1 text-muted-foreground">
          {r.fromPole?.name ?? r.requester?.name}
          <ArrowRight className="size-3" />
          <span className={cn("font-medium", colorOf(r.pole.color).text)}>{r.pole.name}</span>
        </span>
      </div>
      <h3 className="pointer-events-none relative mt-2 font-medium text-balance group-hover:text-primary">{r.title}</h3>
      {!compact && r.description && (
        <p className="pointer-events-none relative mt-1 line-clamp-2 text-sm text-muted-foreground">{r.description}</p>
      )}
      <div className="relative mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
        <span
          className="pointer-events-none flex items-center gap-1.5 text-xs text-muted-foreground"
          suppressHydrationWarning
        >
          {r.requester && <Avatar name={r.requester.name} color={r.requester.avatarColor} size="xs" />}
          {t.requests.askedBy(r.requester?.name ?? t.activity.someone, formatRelative(r.createdAt, locale))}
        </span>
        {r.dueDate && !closed && (
          <span
            className={cn(
              "pointer-events-none inline-flex items-center gap-1 text-xs",
              isOverdue(r.dueDate) ? "font-medium text-destructive" : "text-muted-foreground",
            )}
          >
            <CalendarClock className="size-3.5" /> {formatDate(r.dueDate, locale, { day: "numeric", month: "short" })}
          </span>
        )}
        <span className="ml-auto flex items-center gap-2">
          {r.status === "TAKEN" && r.takenBy && (
            <span className="pointer-events-none inline-flex items-center gap-1.5 text-xs text-muted-foreground">
              <Avatar name={r.takenBy.name} color={r.takenBy.avatarColor} size="xs" />
              {t.requests.takenBy(r.takenBy.name)}
            </span>
          )}
          {r.status === "OPEN" && r.canTake && <TakeButton id={r.id} />}
          {r.taskId && r.status !== "OPEN" && (
            <Link href={`/tasks/${r.taskId}`} className={buttonVariants({ size: "xs", variant: "outline" })}>
              {t.requests.openTask}
            </Link>
          )}
          {items.length > 0 && <RowActions items={items} />}
        </span>
      </div>
    </article>
  );
}
