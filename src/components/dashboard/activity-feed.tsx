import Link from "@/components/ui/link";
import { Avatar } from "@/components/ui/avatar";
import { formatRelative } from "@/lib/utils";
import type { Dictionary } from "@/lib/i18n/dictionaries";

export type ActivityItem = {
  id: string;
  action: string;
  entityType: string;
  entityId: string | null;
  entityLabel: string | null;
  createdAt: Date;
  actor: { id: string; name: string; avatarColor: string } | null;
};

function hrefFor(a: ActivityItem) {
  const id = a.entityId;
  switch (a.entityType) {
    case "task":
      return id ? `/tasks/${id}` : "/tasks";
    case "project":
    case "update":
    case "milestone":
      return id ? `/projects/${id}` : "/projects";
    case "pole":
      return id ? `/poles/${id}` : "/poles";
    case "member":
    case "membership":
      return id ? `/members/${id}` : "/members";
    case "event":
      return id ? `/events/${id}` : "/events";
    case "step":
      return "/foundation";
    case "document":
      return "/documents";
    case "transaction":
      return "/finance";
    case "responsibility":
      return "/structure";
    case "association":
      return "/settings?tab=association";
    default:
      return "/";
  }
}

export function ActivityFeed({
  items,
  t,
  locale,
  compact,
}: {
  items: ActivityItem[];
  t: Dictionary;
  locale: string;
  compact?: boolean;
}) {
  if (items.length === 0) return <p className="text-sm text-muted-foreground">{t.dashboard.activityEmpty}</p>;
  return (
    <ol className="space-y-3.5">
      {items.map((a) => (
        <li key={a.id} className="flex gap-3">
          <Avatar name={a.actor?.name ?? "?"} color={a.actor?.avatarColor ?? "slate"} size={compact ? "sm" : "md"} />
          <div className="min-w-0 flex-1 text-sm leading-snug">
            <p className="text-pretty">
              <span className="font-medium">{a.actor?.name ?? t.activity.someone}</span>{" "}
              <span className="text-muted-foreground">
                {t.activity.verbs[a.action] ?? a.action} {t.activity.entities[a.entityType] ?? a.entityType}
              </span>{" "}
              {a.entityLabel &&
                (a.action === "deleted" ? (
                  <span className="font-medium">{a.entityLabel}</span>
                ) : (
                  <Link href={hrefFor(a)} className="font-medium hover:text-primary hover:underline">
                    {a.entityLabel}
                  </Link>
                ))}
            </p>
            <p className="mt-0.5 text-xs text-muted-foreground">{formatRelative(a.createdAt, locale)}</p>
          </div>
        </li>
      ))}
    </ol>
  );
}
