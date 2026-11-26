"use client";

import * as React from "react";
import { CircleAlert, CircleCheck, X } from "lucide-react";
import { cn } from "@/lib/utils";

/*
 * Toast messages ("Saved", "Not allowed"…), with an optional action button.
 * About 80 lines instead of a library: Agora only needs success and error
 * messages, so the 13 KB (compressed) of sonner were replaced by this.
 */

type Kind = "success" | "error";
type Action = { label: string; onClick: () => void };
type Item = { id: number; kind: Kind; message: string; action?: Action };

const listeners = new Set<(item: Item) => void>();
let nextId = 1;

function emit(kind: Kind, message: string, options?: { action?: Action }) {
  const item = { id: nextId++, kind, message, action: options?.action };
  listeners.forEach((listener) => listener(item));
}

export const toast = {
  success: (message: string | undefined, options?: { action?: Action }) => emit("success", message ?? "", options),
  error: (message: string | undefined, options?: { action?: Action }) => emit("error", message ?? "", options),
};

export function Toaster() {
  const [items, setItems] = React.useState<Item[]>([]);
  const dismiss = React.useCallback((id: number) => setItems((all) => all.filter((x) => x.id !== id)), []);

  React.useEffect(() => {
    const listener = (item: Item) => {
      setItems((all) => [...all.slice(-2), item]);
      setTimeout(() => dismiss(item.id), item.kind === "error" ? 6000 : 4000);
    };
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  }, [dismiss]);

  return (
    <ol className="pointer-events-none fixed right-4 bottom-4 z-[100] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2 print:hidden">
      {items.map((item) => (
        <li
          key={item.id}
          role={item.kind === "error" ? "alert" : "status"}
          className={cn(
            "pointer-events-auto flex animate-pop-in items-start gap-2.5 rounded-xl border bg-popover px-4 py-3 text-sm pop-shadow",
            item.kind === "error" ? "border-destructive/30 text-destructive" : "border-success/30 text-success",
          )}
        >
          {item.kind === "error" ? (
            <CircleAlert className="mt-0.5 size-4 shrink-0" />
          ) : (
            <CircleCheck className="mt-0.5 size-4 shrink-0" />
          )}
          <span className="flex-1 text-foreground">{item.message}</span>
          {item.action && (
            <button
              type="button"
              onClick={() => {
                item.action!.onClick();
                dismiss(item.id);
              }}
              className="shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold text-primary hover:bg-hover"
            >
              {item.action.label}
            </button>
          )}
          <button
            type="button"
            onClick={() => dismiss(item.id)}
            aria-label="×"
            className="-mr-1 rounded-md p-0.5 text-muted-foreground hover:bg-hover hover:text-foreground"
          >
            <X className="size-3.5" />
          </button>
        </li>
      ))}
    </ol>
  );
}
