import * as React from "react";
import { cn } from "@/lib/utils";
import { colorOf } from "@/lib/colors";

export function Badge({
  color = "slate",
  dot = false,
  className,
  children,
  ...props
}: React.ComponentProps<"span"> & { color?: string; dot?: boolean }) {
  const c = colorOf(color);
  return (
    <span
      className={cn(
        "inline-flex max-w-full items-center gap-1.5 truncate rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        c.soft,
        className,
      )}
      {...props}
    >
      {dot && <span className={cn("size-1.5 shrink-0 rounded-full print-exact", c.dot)} />}
      {children}
    </span>
  );
}
