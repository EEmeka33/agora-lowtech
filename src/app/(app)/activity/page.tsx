import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { formatDate } from "@/lib/utils";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState, PageHeader } from "@/components/ui/page";
import { buttonVariants } from "@/components/ui/button";
import { FilterSelect } from "@/components/forms/filters";
import { ActivityFeed } from "@/components/dashboard/activity-feed";
import { Activity } from "lucide-react";

export const metadata: Metadata = { title: "Activity" };

const PAGE_SIZE = 50;
const TYPES = ["task", "project", "pole", "member", "event", "document", "transaction", "step", "announcement"];

export default async function ActivityPage(props: PageProps<"/activity">) {
  const { db } = await requireContext();
  const { t, locale } = await getI18n();
  const sp = await props.searchParams;
  const page = Math.max(1, Number(sp.page) || 1);
  const type = typeof sp.type === "string" && TYPES.includes(sp.type) ? sp.type : undefined;
  const items = await db.activity.findMany({
    where: type ? { entityType: type } : {},
    orderBy: { createdAt: "desc" },
    take: PAGE_SIZE * page + 1,
    include: { actor: { select: { id: true, name: true, avatarColor: true } } },
  });
  const hasMore = items.length > PAGE_SIZE * page;
  const shown = items.slice(0, PAGE_SIZE * page);
  const groups = shown.reduce<Record<string, typeof shown>>((acc, a) => {
    const key = formatDate(a.createdAt, locale, {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
      timeZone: undefined,
    });
    (acc[key] ??= []).push(a);
    return acc;
  }, {});

  return (
    <div className="mx-auto max-w-3xl">
      <PageHeader
        title={t.activity.title}
        description={t.activity.subtitle}
        actions={
          <FilterSelect
            param="type"
            placeholder={t.common.all}
            options={TYPES.map((v) => ({ value: v, label: t.activity.entities[v] }))}
          />
        }
      />
      {shown.length === 0 ? (
        <EmptyState icon={<Activity />} title={t.activity.empty} />
      ) : (
        <div className="space-y-6">
          {Object.entries(groups).map(([day, list]) => (
            <section key={day}>
              <h2 className="mb-2 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">{day}</h2>
              <Card>
                <CardContent className="py-4">
                  <ActivityFeed items={list} t={t} locale={locale} />
                </CardContent>
              </Card>
            </section>
          ))}
          {hasMore && (
            <div className="text-center">
              <Link
                href={`/activity?page=${page + 1}${type ? `&type=${type}` : ""}`}
                scroll={false}
                className={buttonVariants({ variant: "outline" })}
              >
                {t.common.loadMore}
              </Link>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
