import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { ArrowRight, CheckCircle2, ChevronLeft, CircleDot, Hand, Info } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { loadRequests } from "@/lib/request-queries";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, formatInstant } from "@/lib/utils";
import { Card, CardContent } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { KeyValue } from "@/components/ui/page";
import { RichText } from "@/components/ui/rich-text";
import { RequestDetailActions } from "@/components/requests/request-detail-actions";
import { requestStatusColor } from "@/components/requests/request-card";

export const metadata: Metadata = { title: "Request" };

export default async function RequestPage(props: PageProps<"/requests/[id]">) {
  const { user, db } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const [request] = await loadRequests(db, { id }, user, 1);
  if (!request) notFound();
  const project = request.projectId
    ? await db.project.findUnique({ where: { id: request.projectId }, select: { id: true, title: true } })
    : null;

  const steps = [
    { icon: CircleDot, label: t.requests.created, at: request.createdAt, who: request.requester?.name },
    ...(request.takenAt
      ? [{ icon: Hand, label: t.requests.takenAt, at: request.takenAt, who: request.takenBy?.name }]
      : []),
    ...(request.status === "DONE" && request.closedAt
      ? [{ icon: CheckCircle2, label: t.requests.doneAt, at: request.closedAt, who: undefined }]
      : []),
  ];

  return (
    <div className="mx-auto max-w-4xl">
      <Link
        href="/requests"
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {t.requests.title}
      </Link>
      <div className="mb-6">
        <div className="mb-2 flex flex-wrap items-center gap-2 text-sm">
          <Badge color={requestStatusColor[request.status]} dot>
            {t.enums.requestStatus[request.status]}
          </Badge>
          <Badge color={colorFor("priority", request.priority)}>{t.enums.priority[request.priority]}</Badge>
          <span className="inline-flex items-center gap-1 text-muted-foreground">
            {request.fromPole?.name ?? request.requester?.name}
            <ArrowRight className="size-3.5" />
            <Link
              href={`/poles/${request.pole.id}`}
              className={cn("font-medium hover:underline", colorOf(request.pole.color).text)}
            >
              {request.pole.name}
            </Link>
          </span>
        </div>
        <h1 className="text-2xl font-semibold tracking-tight text-balance">{request.title}</h1>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div className="space-y-6">
          <Card>
            <CardContent className="py-5">
              {request.description ? (
                <RichText text={request.description} />
              ) : (
                <p className="text-sm text-muted-foreground">—</p>
              )}
            </CardContent>
          </Card>
          <RequestDetailActions request={request} />
          <p className="flex items-start gap-2 text-xs text-muted-foreground">
            <Info className="mt-0.5 size-3.5 shrink-0" /> {t.requests.hint}
          </p>
        </div>
        <div className="space-y-4">
          <Card>
            <CardContent className="py-2">
              <dl className="divide-y">
                <KeyValue label={t.requests.toPole}>{request.pole.name}</KeyValue>
                <KeyValue label={t.requests.fromPole}>
                  {request.fromPole?.name ?? request.requester?.name ?? "—"}
                </KeyValue>
                <KeyValue label={t.common.dueDate}>
                  {request.dueDate ? formatDate(request.dueDate, locale) : "—"}
                </KeyValue>
                <KeyValue label={t.common.project}>
                  {project ? (
                    <Link href={`/projects/${project.id}`} className="hover:underline">
                      {project.title}
                    </Link>
                  ) : (
                    "—"
                  )}
                </KeyValue>
              </dl>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="py-4">
              <ol className="space-y-4">
                {steps.map((s) => (
                  <li key={s.label} className="flex gap-3">
                    <s.icon className="mt-0.5 size-4 shrink-0 text-primary" />
                    <div className="text-sm">
                      <p className="font-medium">{s.label}</p>
                      <p className="text-xs text-muted-foreground">
                        {formatInstant(s.at, locale)}
                        {s.who && ` · ${s.who}`}
                      </p>
                    </div>
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
          {request.takenBy && (
            <div className="flex items-center gap-2 px-1 text-sm">
              <Avatar name={request.takenBy.name} color={request.takenBy.avatarColor} size="sm" />
              {t.requests.takenBy(request.takenBy.name)}
            </div>
          )}
          {request.taskId && (
            <Link
              href={`/tasks/${request.taskId}`}
              className={buttonVariants({ variant: "outline", className: "w-full" })}
            >
              {t.requests.openTask}
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}
