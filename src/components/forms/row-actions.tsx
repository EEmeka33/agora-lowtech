"use client";

import * as React from "react";
import Link from "@/components/ui/link";
import { toast } from "@/components/ui/toast";
import { MoreHorizontal } from "lucide-react";
import { Menu, MenuItem, MenuSeparator } from "@/components/ui/menu";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n/client";
import type { ActionState } from "@/lib/action-utils";
import { FormDialog } from "./form-dialog";
import { ConfirmDialog } from "./confirm-dialog";
import type { FormAction } from "./action-form";
import { LinkResultDialog } from "./link-result";

export type RowAction =
  | { kind: "separator" }
  | { kind: "link"; label: string; icon?: React.ReactNode; href: string }
  | {
      kind: "dialog";
      label: string;
      icon?: React.ReactNode;
      title: string;
      description?: string;
      action: FormAction;
      submitLabel?: string;
      content: React.ReactNode;
      size?: "sm" | "md" | "lg";
    }
  | {
      kind: "confirm";
      label: string;
      icon?: React.ReactNode;
      title: string;
      description?: string;
      confirmLabel?: string;
      action: () => Promise<ActionState>;
      redirectTo?: string;
      destructive?: boolean;
    }
  | { kind: "run"; label: string; icon?: React.ReactNode; action: () => Promise<ActionState>; destructive?: boolean };

/** "…" menu whose items can open edit dialogs, confirmations or run actions. */
export function RowActions({
  items,
  trigger,
  label,
}: {
  items: RowAction[];
  trigger?: React.ReactNode;
  label?: string;
}) {
  const t = useT();
  const [active, setActive] = React.useState<number | null>(null);
  const [pending, startTransition] = React.useTransition();
  const [link, setLink] = React.useState<string | null>(null);
  const current = active !== null ? items[active] : null;

  return (
    <>
      <Menu
        label={label ?? t.common.actions}
        trigger={
          trigger ?? (
            <Button variant="ghost" size="icon-sm" aria-label={t.common.actions} disabled={pending}>
              <MoreHorizontal />
            </Button>
          )
        }
      >
        {(close) =>
          items.map((item, i) => {
            if (item.kind === "separator") return <MenuSeparator key={i} />;
            if (item.kind === "link")
              return (
                <Link
                  key={i}
                  href={item.href}
                  onClick={close}
                  className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 transition-colors hover:bg-hover [&_svg]:size-4 [&_svg]:text-muted-foreground"
                >
                  {item.icon}
                  {item.label}
                </Link>
              );
            return (
              <MenuItem
                key={i}
                icon={item.icon}
                destructive={"destructive" in item ? item.destructive : false}
                onClick={() => {
                  close();
                  if (item.kind === "run") {
                    startTransition(async () => {
                      const r = await item.action();
                      if (r?.ok) {
                        if (r.data?.link) setLink(r.data.link);
                        else if (r.message) toast.success(r.message);
                      } else toast.error(r?.error ?? t.common.genericError);
                    });
                  } else setActive(i);
                }}
              >
                {item.label}
              </MenuItem>
            );
          })
        }
      </Menu>
      {current?.kind === "dialog" && (
        <FormDialog
          open
          onOpenChange={(o) => !o && setActive(null)}
          title={current.title}
          description={current.description}
          action={current.action}
          submitLabel={current.submitLabel}
          size={current.size}
          resetOnSuccess={false}
        >
          {current.content}
        </FormDialog>
      )}
      <LinkResultDialog link={link} onClose={() => setLink(null)} />
      {current?.kind === "confirm" && (
        <ConfirmDialog
          open
          onOpenChange={(o) => !o && setActive(null)}
          title={current.title}
          description={current.description}
          confirmLabel={current.confirmLabel}
          action={current.action}
          redirectTo={current.redirectTo}
          destructive={current.destructive ?? true}
        />
      )}
    </>
  );
}
