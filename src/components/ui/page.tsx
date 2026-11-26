import * as React from "react";
import Link from "@/components/ui/link";
import { cn } from "@/lib/utils";

export function PageHeader({
  title,
  description,
  actions,
  eyebrow,
  className,
}: {
  title: React.ReactNode;
  description?: React.ReactNode;
  actions?: React.ReactNode;
  eyebrow?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between", className)}>
      <div className="min-w-0">
        {eyebrow && <div className="mb-2">{eyebrow}</div>}
        <h1 className="text-2xl font-semibold tracking-tight text-balance sm:text-[28px]">{title}</h1>
        {description && <p className="mt-1.5 max-w-2xl text-sm text-pretty text-muted-foreground">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function EmptyState({
  icon,
  title,
  description,
  action,
  className,
  compact,
}: {
  icon?: React.ReactNode;
  title: React.ReactNode;
  description?: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
  compact?: boolean;
}) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center rounded-xl border border-dashed text-center",
        compact ? "gap-2 px-4 py-8" : "gap-3 px-6 py-14",
        className,
      )}
    >
      {icon && (
        <div className="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground [&_svg]:size-5">
          {icon}
        </div>
      )}
      <div>
        <p className="font-medium">{title}</p>
        {description && <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
      </div>
      {action && <div className="mt-1">{action}</div>}
    </div>
  );
}

export function StatCard({
  label,
  value,
  hint,
  icon,
  href,
  tone = "default",
}: {
  label: React.ReactNode;
  value: React.ReactNode;
  hint?: React.ReactNode;
  icon?: React.ReactNode;
  href?: string;
  tone?: "default" | "danger" | "success";
}) {
  const body = (
    <div className="flex h-full items-start justify-between gap-3 rounded-xl border bg-card p-4 card-shadow transition-colors group-hover:border-primary/40">
      <div className="min-w-0">
        <p className="text-[13px] font-medium text-muted-foreground">{label}</p>
        <p className="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums">{value}</p>
        {hint && (
          <p
            className={cn(
              "mt-1 truncate text-xs text-muted-foreground",
              tone === "danger" && "text-destructive",
              tone === "success" && "text-success",
            )}
          >
            {hint}
          </p>
        )}
      </div>
      {icon && (
        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground [&_svg]:size-[18px]">
          {icon}
        </div>
      )}
    </div>
  );
  return href ? (
    <Link href={href} className="group block">
      {body}
    </Link>
  ) : (
    body
  );
}

/** Tabs rendered as links (state lives in the URL). */
export function TabLinks({
  items,
  className,
}: {
  items: { href: string; label: React.ReactNode; active: boolean; count?: number }[];
  className?: string;
}) {
  return (
    <nav className={cn("-mx-1 mb-5 flex scrollbar-thin gap-1 overflow-x-auto border-b px-1", className)}>
      {items.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          scroll={false}
          className={cn(
            "relative -mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors",
            item.active
              ? "border-primary text-foreground"
              : "border-transparent text-muted-foreground hover:text-foreground",
          )}
        >
          {item.label}
          {item.count !== undefined && (
            <span className="rounded-full bg-muted px-1.5 py-px text-[11px] text-muted-foreground tabular-nums">
              {item.count}
            </span>
          )}
        </Link>
      ))}
    </nav>
  );
}

/** Pill-style segmented links, e.g. Board / List. */
export function Segmented({
  items,
  className,
}: {
  items: { href: string; label: React.ReactNode; active: boolean; icon?: React.ReactNode }[];
  className?: string;
}) {
  return (
    <div className={cn("inline-flex rounded-lg border bg-muted/60 p-0.5", className)}>
      {items.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          scroll={false}
          className={cn(
            "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[13px] font-medium transition-colors [&_svg]:size-3.5",
            item.active ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
          )}
        >
          {item.icon}
          {item.label}
        </Link>
      ))}
    </div>
  );
}

export function SectionTitle({
  children,
  action,
  className,
}: {
  children: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("mb-3 flex items-center justify-between gap-3", className)}>
      <h2 className="text-[15px] font-semibold tracking-tight">{children}</h2>
      {action}
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn("animate-pulse rounded-lg bg-muted", className)} />;
}

export function KeyValue({ label, children }: { label: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="min-w-0 truncate text-right font-medium">{children}</dd>
    </div>
  );
}
