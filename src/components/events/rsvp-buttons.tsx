"use client";

import * as React from "react";
import { Check, HelpCircle, X } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { rsvp } from "@/actions/events";
import { useT } from "@/lib/i18n/client";
import { cn } from "@/lib/utils";

const OPTIONS = [
  { value: "GOING", icon: Check, active: "bg-emerald-500 text-white border-emerald-500" },
  { value: "MAYBE", icon: HelpCircle, active: "bg-amber-500 text-white border-amber-500" },
  { value: "NOT_GOING", icon: X, active: "bg-rose-500 text-white border-rose-500" },
] as const;

export function RsvpButtons({
  eventId,
  status,
  compact,
}: {
  eventId: string;
  status: string | null;
  compact?: boolean;
}) {
  const t = useT();
  const [optimistic, setOptimistic] = React.useOptimistic(status);
  const [, startTransition] = React.useTransition();
  return (
    <div className="inline-flex gap-1">
      {OPTIONS.map(({ value, icon: Icon, active }) => (
        <button
          key={value}
          type="button"
          title={t.enums.rsvp[value]}
          aria-pressed={optimistic === value}
          onClick={(e) => {
            e.preventDefault();
            startTransition(async () => {
              setOptimistic(value);
              const r = await rsvp(eventId, value);
              if (!r?.ok) toast.error(r?.error ?? t.common.genericError);
            });
          }}
          className={cn(
            "inline-flex items-center gap-1.5 rounded-lg border text-xs font-medium transition-colors",
            compact ? "size-7 justify-center" : "px-2.5 py-1.5",
            optimistic === value ? active : "text-muted-foreground hover:bg-hover",
          )}
        >
          <Icon className="size-3.5" />
          {!compact && t.enums.rsvp[value]}
        </button>
      ))}
    </div>
  );
}
