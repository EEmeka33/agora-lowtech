import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { ChevronLeft, Clock, Inbox, Pencil, Trash2 } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getMemberOptions, getPoleOptions, getProjectOptions } from "@/lib/queries";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, formatRelative } from "@/lib/utils";
import { addComment, deleteComment, deleteTask, updateTask } from "@/actions/tasks";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { KeyValue } from "@/components/ui/page";
import { FormDialog } from "@/components/forms/form-dialog";
import { ConfirmButton } from "@/components/forms/confirm-dialog";
import { InlineTextForm } from "@/components/forms/comment-form";
import { RowActions } from "@/components/forms/row-actions";
import { TaskFields } from "@/components/tasks/task-fields";
import { TaskStatusPicker } from "@/components/tasks/status-picker";
import { DueChip } from "@/components/tasks/task-card";
import { RichText } from "@/components/ui/rich-text";
import { TimeFields } from "@/components/volunteering/time-fields";
import { logTime } from "@/actions/volunteering";

export const metadata: Metadata = { title: "Task" };

export default async function TaskPage(props: PageProps<"/tasks/[id]">) {
  const { user, db } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const task = await db.task.findUnique({
    where: { id },
    include: {
      project: { select: { id: true, title: true, color: true, leadId: true } },
      pole: { select: { id: true, name: true, color: true } },
      assignee: { select: { id: true, name: true, avatarColor: true } },
      creator: { select: { id: true, name: true } },
      request: { select: { id: true } },
      comments: {
        orderBy: { createdAt: "asc" },
        include: { author: { select: { id: true, name: true, avatarColor: true } } },
      },
    },
  });
  if (!task) notFound();

  const projectLeadId = task.project?.leadId ?? null;
  const canEdit = can.editTask(user, { ...task, projectLeadId });
  const canDelete = can.deleteTask(user, { creatorId: task.creatorId, projectLeadId });
  const [members, projects, poles] = await Promise.all([
    getMemberOptions(db),
    getProjectOptions(db),
    getPoleOptions(db),
  ]);
  const projectOpts = projects.map((p) => ({ id: p.id, name: p.title }));
  if (task.project && !projectOpts.some((p) => p.id === task.project!.id))
    projectOpts.push({ id: task.project.id, name: task.project.title });

  return (
    <div className="mx-auto max-w-5xl">
      <Link
        href={task.project ? `/projects/${task.project.id}?tab=tasks` : "/tasks"}
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {task.project?.title ?? t.tasks.title}
      </Link>

      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <Badge color={colorFor("taskStatus", task.status)} dot>
              {t.enums.taskStatus[task.status]}
            </Badge>
            <Badge color={colorFor("priority", task.priority)}>{t.enums.priority[task.priority]}</Badge>
            {task.request && (
              <Link
                href={`/requests/${task.request.id}`}
                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
              >
                <Inbox className="size-3.5" /> {t.tasks.fromRequest}
              </Link>
            )}
          </div>
          <h1
            className={cn(
              "text-2xl font-semibold tracking-tight text-balance",
              task.status === "DONE" && "text-muted-foreground line-through",
            )}
          >
            {task.title}
          </h1>
        </div>
        <div className="flex shrink-0 gap-2">
          <FormDialog
            title={t.tasks.logTime}
            description={task.title}
            action={logTime}
            submitLabel={t.common.save}
            trigger={
              <Button variant="outline">
                <Clock /> {t.tasks.logTime}
              </Button>
            }
          >
            <TimeFields
              poles={poles}
              projects={projectOpts}
              defaults={{ taskId: task.id, poleId: task.poleId, projectId: task.projectId }}
            />
          </FormDialog>
          {canEdit && (
            <FormDialog
              title={t.tasks.editTask}
              action={updateTask.bind(null, task.id)}
              resetOnSuccess={false}
              trigger={
                <Button variant="outline">
                  <Pencil /> {t.common.edit}
                </Button>
              }
            >
              <TaskFields
                task={{
                  title: task.title,
                  description: task.description,
                  status: task.status,
                  priority: task.priority,
                  dueDate: task.dueDate,
                  projectId: task.projectId,
                  poleId: task.poleId,
                  assigneeId: task.assigneeId,
                }}
                members={members}
                projects={projectOpts}
                poles={poles}
              />
            </FormDialog>
          )}
          {canDelete && (
            <ConfirmButton title={t.tasks.deleteTask} action={deleteTask.bind(null, task.id)} redirectTo="/tasks">
              <Button variant="outline" size="icon" aria-label={t.common.delete}>
                <Trash2 />
              </Button>
            </ConfirmButton>
          )}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          {task.description && (
            <Card>
              <CardContent className="py-5">
                <RichText text={task.description} />
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader>
              <CardTitle>
                {t.tasks.comments} <span className="font-normal text-muted-foreground">{task.comments.length}</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
              {task.comments.length === 0 && <p className="text-sm text-muted-foreground">{t.tasks.noComments}</p>}
              {task.comments.map((c) => (
                <div key={c.id} className="group flex gap-3">
                  <Avatar name={c.author?.name ?? "?"} color={c.author?.avatarColor} size="md" />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 text-sm">
                      <span className="font-medium">{c.author?.name ?? t.activity.someone}</span>
                      <span className="text-xs text-muted-foreground">{formatRelative(c.createdAt, locale)}</span>
                      {(c.authorId === user.id || user.role === "ADMIN") && (
                        <span className="ml-auto opacity-0 transition-opacity group-hover:opacity-100">
                          <RowActions
                            items={[
                              {
                                kind: "confirm",
                                label: t.common.delete,
                                icon: <Trash2 />,
                                title: t.common.confirmDelete,
                                action: deleteComment.bind(null, c.id),
                              },
                            ]}
                          />
                        </span>
                      )}
                    </div>
                    <div className="mt-1 rounded-xl rounded-tl-sm bg-muted/60 px-3.5 py-2.5">
                      <RichText text={c.body} />
                    </div>
                  </div>
                </div>
              ))}
              <div className="flex gap-3 pt-2">
                <Avatar name={user.name} color={user.avatarColor} size="md" />
                <div className="flex-1">
                  <InlineTextForm
                    action={addComment.bind(null, task.id)}
                    placeholder={t.tasks.commentPlaceholder}
                    submitLabel={t.tasks.postComment}
                    rows={2}
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardContent className="space-y-3 py-4">
              <p className="text-xs font-medium text-muted-foreground">{t.common.status}</p>
              <TaskStatusPicker id={task.id} status={task.status} disabled={!canEdit} />
            </CardContent>
          </Card>
          <Card>
            <CardContent className="py-2">
              <dl className="divide-y">
                <KeyValue label={t.common.assignee}>
                  {task.assignee ? (
                    <Link
                      href={`/members/${task.assignee.id}`}
                      className="inline-flex items-center gap-2 hover:underline"
                    >
                      <Avatar name={task.assignee.name} color={task.assignee.avatarColor} size="xs" />
                      {task.assignee.name}
                    </Link>
                  ) : (
                    <span className="text-muted-foreground">{t.common.unassigned}</span>
                  )}
                </KeyValue>
                <KeyValue label={t.common.dueDate}>
                  {task.dueDate ? <DueChip date={task.dueDate} done={task.status === "DONE"} /> : "—"}
                </KeyValue>
                <KeyValue label={t.common.project}>
                  {task.project ? (
                    <Link
                      href={`/projects/${task.project.id}`}
                      className={cn("hover:underline", colorOf(task.project.color).text)}
                    >
                      {task.project.title}
                    </Link>
                  ) : (
                    "—"
                  )}
                </KeyValue>
                <KeyValue label={t.common.pole}>
                  {task.pole ? (
                    <Link href={`/poles/${task.pole.id}`} className="hover:underline">
                      {task.pole.name}
                    </Link>
                  ) : (
                    "—"
                  )}
                </KeyValue>
                <KeyValue label={t.common.createdAt}>{formatDate(task.createdAt, locale)}</KeyValue>
              </dl>
            </CardContent>
          </Card>
          <p className="px-1 text-xs text-muted-foreground">
            {task.creator && t.tasks.createdBy(task.creator.name)}
            {task.completedAt && <> · {t.tasks.completedOn(formatRelative(task.completedAt, locale))}</>}
          </p>
        </div>
      </div>
    </div>
  );
}
