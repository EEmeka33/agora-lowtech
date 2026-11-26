"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { toast } from "@/components/ui/toast";
import { Loader2 } from "lucide-react";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n/client";
import type { ActionState } from "@/lib/action-utils";

export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  action,
  redirectTo,
  destructive = true,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: React.ReactNode;
  description?: React.ReactNode;
  confirmLabel?: string;
  action: () => Promise<ActionState>;
  redirectTo?: string;
  destructive?: boolean;
}) {
  const t = useT();
  const router = useRouter();
  const [pending, startTransition] = React.useTransition();

  return (
    <Dialog open={open} onOpenChange={onOpenChange} size="sm" title={title}>
      <div className="px-5 py-4 text-sm text-muted-foreground">{description ?? t.common.confirmDeleteText}</div>
      <div className="flex justify-end gap-2 border-t bg-muted/40 px-5 py-3">
        <Button variant="ghost" onClick={() => onOpenChange(false)}>
          {t.common.cancel}
        </Button>
        <Button
          variant={destructive ? "destructive" : "primary"}
          disabled={pending}
          onClick={() =>
            startTransition(async () => {
              const result = await action();
              if (result?.ok) {
                toast.success(result.message ?? t.common.deleted);
                onOpenChange(false);
                if (redirectTo) router.push(redirectTo);
              } else {
                toast.error(result?.error ?? t.common.genericError);
              }
            })
          }
        >
          {pending && <Loader2 className="animate-spin" />}
          {confirmLabel ?? t.common.delete}
        </Button>
      </div>
    </Dialog>
  );
}

/** A button that asks for confirmation before running a server action. */
export function ConfirmButton({
  children,
  ...props
}: Omit<React.ComponentProps<typeof ConfirmDialog>, "open" | "onOpenChange"> & { children: React.ReactNode }) {
  const [open, setOpen] = React.useState(false);
  return (
    <>
      <span className="contents" onClick={() => setOpen(true)}>
        {children}
      </span>
      <ConfirmDialog open={open} onOpenChange={setOpen} {...props} />
    </>
  );
}
