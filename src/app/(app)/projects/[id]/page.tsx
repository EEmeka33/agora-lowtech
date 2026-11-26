import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { CalendarDays, ChevronLeft, FileText, Pencil, Plus, Trash2, UserMinus, Wallet } from "lucide-react";
import { requireContext, type CurrentUser } from "@/lib/context";
import type { TenantDb } from "@/lib/tenant";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getMemberOptions, getPoleOptions, getProjectOptions, poleLeadIds } from "@/lib/queries";
import { loadTaskCards } from "@/lib/task-queries";
import { projectHealth, projectProgress } from "@/lib/progress";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, daysBetween, formatDate, formatMoney, formatRelative, nowWall, pct } from "@/lib/utils";
import {
  addProjectMember,
  deleteProject,
  postProjectUpdate,
  removeProjectMember,
  updateProject,
} from "@/actions/projects";
import { createTask } from "@/actions/tasks";
import { createTransaction } from "@/actions/finance";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress, ProgressRing } from "@/components/ui/progress";
import { EmptyState, TabLinks } from "@/components/ui/page";
import { RichText } from "@/components/ui/rich-text";
import { FormDialog } from "@/components/forms/form-dialog";
import { ConfirmButton } from "@/components/forms/confirm-dialog";
import { RowActions } from "@/components/forms/row-actions";
import { AddMemberForm } from "@/components/forms/add-member-form";
import { ProjectFields } from "@/components/projects/project-fields";
import { HealthBadge } from "@/components/projects/project-card";
import { Milestones } from "@/components/projects/milestones";
import { ProjectUpdateForm } from "@/components/projects/update-form";
import { TaskBoard } from "@/components/tasks/task-board";
import { TaskFields } from "@/components/tasks/task-fields";
import { TransactionFields } from "@/components/finance/transaction-fields";

export const metadata: Metadata = { title: "Project" };

const TABS = ["overview", "tasks", "updates", "team", "budget"] as const;

export default async function ProjectPage(props: PageProps<"/projects/[id]">) {
  const { user, db, association } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const sp = await props.searchParams;
  const tab = TABS.includes(sp.tab as never) ? (sp.tab as (typeof TABS)[number]) : "overview";

  const project = await db.project.findUnique({
    where: { id },
    include: {
      pole: { select: { id: true, name: true, color: true, icon: true } },
      lead: { select: { id: true, name: true, avatarColor: true } },
      members: {
        include: { user: { select: { id: true, name: true, avatarColor: true } } },
        orderBy: { joinedAt: "asc" },
      },
      milestones: { orderBy: [{ order: "asc" }, { createdAt: "asc" }] },
      tasks: { select: { status: true } },
      updates: { orderBy: { createdAt: "desc" }, include: { author: { select: { name: true, avatarColor: true } } } },
      transactions: { orderBy: { date: "desc" } },
      events: { where: { startAt: { gte: nowWall() } }, orderBy: { startAt: "asc" }, take: 5 },
      documents: { orderBy: { createdAt: "desc" }, take: 8 },
    },
  });
  if (!project) notFound();

  const canEdit = can.editProject(user, { leadId: project.leadId, poleLeadIds: await poleLeadIds(db, project.poleId) });
  const isTeam = project.members.some((m) => m.userId === user.id);
  const [members, poles, projects] = await Promise.all([
    getMemberOptions(db),
    getPoleOptions(db),
    getProjectOptions(db),
  ]);
  const progress = projectProgress(project);
  const health = projectHealth({ ...project, progress });
  const doneTasks = project.tasks.filter((x) => x.status === "DONE").length;
  const spent = project.transactions.filter((x) => x.type === "EXPENSE").reduce((s, x) => s + x.amountCents, 0);
  const income = project.transactions.filter((x) => x.type === "INCOME").reduce((s, x) => s + x.amountCents, 0);
  const money = (c: number | null) => formatMoney(c, association.currency, locale);
  const daysLeft = project.dueDate ? daysBetween(nowWall(), project.dueDate) : null;
  const tabHref = (k: string) => (k === "overview" ? `/projects/${id}` : `/projects/${id}?tab=${k}`);
  const c = colorOf(project.color);

  return (
    <div>
      <Link
        href="/projects"
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {t.projects.title}
      </Link>

      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex min-w-0 gap-4">
          <span className={cn("mt-1 hidden h-12 w-1.5 shrink-0 rounded-full sm:block", c.bar)} />
          <div className="min-w-0">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <Badge color={colorFor("projectStatus", project.status)}>{t.enums.projectStatus[project.status]}</Badge>
              <Badge color={colorFor("priority", project.priority)}>{t.enums.priority[project.priority]}</Badge>
              <HealthBadge health={health} />
            </div>
            <h1 className="text-2xl font-semibold tracking-tight text-balance sm:text-[28px]">{project.title}</h1>
            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
              {project.pole && (
                <Link
                  href={`/poles/${project.pole.id}`}
                  className={cn("font-medium hover:underline", colorOf(project.pole.color).text)}
                >
                  {project.pole.name}
                </Link>
              )}
              {project.lead && (
                <Link
                  href={`/members/${project.lead.id}`}
                  className="inline-flex items-center gap-1.5 hover:text-foreground"
                >
                  <Avatar name={project.lead.name} color={project.lead.avatarColor} size="xs" />
                  {t.common.lead}: {project.lead.name}
                </Link>
              )}
            </div>
          </div>
        </div>
        {canEdit && (
          <div className="flex shrink-0 gap-2">
            <FormDialog
              title={t.projects.editProject}
              action={updateProject.bind(null, project.id)}
              size="lg"
              resetOnSuccess={false}
              trigger={
                <Button variant="outline">
                  <Pencil /> {t.common.edit}
                </Button>
              }
            >
              <ProjectFields
                project={{
                  title: project.title,
                  description: project.description,
                  objectives: project.objectives,
                  status: project.status,
                  priority: project.priority,
                  progressMode: project.progressMode,
                  manualProgress: project.manualProgress,
                  startDate: project.startDate,
                  dueDate: project.dueDate,
                  budgetCents: project.budgetCents,
                  color: project.color,
                  poleId: project.poleId,
                  leadId: project.leadId,
                }}
                members={members}
                poles={poles}
                currency={association.currency}
              />
            </FormDialog>
            <ConfirmButton
              title={t.projects.deleteProject}
              description={t.projects.deleteWarning}
              action={deleteProject.bind(null, project.id)}
              redirectTo="/projects"
            >
              <Button variant="outline" size="icon" aria-label={t.common.delete}>
                <Trash2 />
              </Button>
            </ConfirmButton>
          </div>
        )}
      </div>

      {/* Summary */}
      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="flex items-center gap-4 p-4">
          <ProgressRing value={progress} size={58} />
          <div>
            <p className="text-[13px] text-muted-foreground">{t.common.progress}</p>
            <p className="font-semibold">{t.projects.tasksDone(doneTasks, project.tasks.length)}</p>
          </div>
        </Card>
        <Card className="p-4">
          <p className="text-[13px] text-muted-foreground">{t.common.dueDate}</p>
          <p className="mt-1 font-semibold">{project.dueDate ? formatDate(project.dueDate, locale) : "—"}</p>
          <p
            className={cn(
              "text-xs",
              daysLeft !== null && daysLeft < 0 && project.status !== "COMPLETED"
                ? "text-destructive"
                : "text-muted-foreground",
            )}
          >
            {project.startDate && `${formatDate(project.startDate, locale, { day: "numeric", month: "short" })} → `}
            {daysLeft !== null && project.status !== "COMPLETED"
              ? daysLeft < 0
                ? t.common.overdueBy(-daysLeft)
                : t.common.dueIn(daysLeft)
              : ""}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[13px] text-muted-foreground">{t.common.budget}</p>
          {project.budgetCents ? (
            <>
              <p className="mt-1 font-semibold">
                {money(spent)} <span className="font-normal text-muted-foreground">/ {money(project.budgetCents)}</span>
              </p>
              <Progress
                value={pct(spent, project.budgetCents)}
                color={spent > project.budgetCents ? "rose" : "emerald"}
                size="sm"
                className="mt-2"
              />
            </>
          ) : (
            <p className="mt-1 text-sm text-muted-foreground">{t.projects.noBudget}</p>
          )}
        </Card>
        <Card className="p-4">
          <p className="text-[13px] text-muted-foreground">{t.projects.team}</p>
          <div className="mt-2 flex -space-x-1.5">
            {project.members.slice(0, 6).map((m) => (
              <Avatar
                key={m.userId}
                name={m.user.name}
                color={m.user.avatarColor}
                size="md"
                className="ring-2 ring-card"
              />
            ))}
            {project.members.length === 0 && <span className="text-sm text-muted-foreground">{t.projects.noTeam}</span>}
          </div>
        </Card>
      </div>

      <TabLinks
        items={TABS.map((k) => ({
          href: tabHref(k),
          label: t.projects.tabs[k],
          active: tab === k,
          count:
            k === "tasks"
              ? project.tasks.length
              : k === "updates"
                ? project.updates.length
                : k === "team"
                  ? project.members.length
                  : undefined,
        }))}
      />

      {tab === "overview" && (
        <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>{t.common.description}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-5">
                {project.description ? (
                  <RichText text={project.description} />
                ) : (
                  <p className="text-sm text-muted-foreground">—</p>
                )}
                {project.objectives && (
                  <div className="rounded-xl bg-primary-soft/60 p-4">
                    <p className="mb-1 text-xs font-semibold tracking-wide text-primary-soft-foreground uppercase">
                      {t.projects.objectives}
                    </p>
                    <RichText text={project.objectives} />
                  </div>
                )}
              </CardContent>
            </Card>
            {project.updates[0] && (
              <Card>
                <CardHeader>
                  <CardTitle>{t.projects.updates}</CardTitle>
                  <Link href={tabHref("updates")} className="text-sm text-primary hover:underline">
                    {t.common.viewAll}
                  </Link>
                </CardHeader>
                <CardContent>
                  <UpdateItem update={project.updates[0]} t={t} locale={locale} />
                </CardContent>
              </Card>
            )}
          </div>
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>{t.projects.milestones}</CardTitle>
              </CardHeader>
              <CardContent>
                <Milestones
                  projectId={project.id}
                  canEdit={canEdit}
                  milestones={project.milestones.map((m) => ({
                    id: m.id,
                    title: m.title,
                    dueDate: m.dueDate,
                    done: m.done,
                  }))}
                />
              </CardContent>
            </Card>
            {(project.events.length > 0 || project.documents.length > 0) && (
              <Card>
                <CardContent className="space-y-4 py-4">
                  {project.events.length > 0 && (
                    <div>
                      <p className="mb-2 text-xs font-semibold text-muted-foreground uppercase">{t.projects.events}</p>
                      {project.events.map((e) => (
                        <Link
                          key={e.id}
                          href={`/events/${e.id}`}
                          className="flex items-center gap-2 rounded-md py-1 text-sm hover:text-primary"
                        >
                          <CalendarDays className="size-4 text-muted-foreground" />
                          <span className="flex-1 truncate">{e.title}</span>
                          <span className="text-xs text-muted-foreground">
                            {formatDate(e.startAt, locale, { day: "numeric", month: "short" })}
                          </span>
                        </Link>
                      ))}
                    </div>
                  )}
                  {project.documents.length > 0 && (
                    <div>
                      <p className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                        {t.projects.documents}
                      </p>
                      {project.documents.map((d) => (
                        <a
                          key={d.id}
                          href={d.storedName ? `/api/files/${d.id}` : (d.url ?? "#")}
                          target="_blank"
                          rel="noreferrer"
                          className="flex items-center gap-2 rounded-md py-1 text-sm hover:text-primary"
                        >
                          <FileText className="size-4 text-muted-foreground" />
                          <span className="truncate">{d.title}</span>
                        </a>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      )}

      {tab === "tasks" && (
        <TasksTab
          projectId={project.id}
          poleId={project.poleId}
          user={user}
          members={members}
          projects={
            projects.some((p) => p.id === project.id)
              ? projects
              : [...projects, { id: project.id, title: project.title }]
          }
          poles={poles}
          t={t}
          db={db}
        />
      )}

      {tab === "updates" && (
        <div className="mx-auto max-w-3xl space-y-6">
          {(canEdit || isTeam) && (
            <Card>
              <CardContent className="py-4">
                <ProjectUpdateForm action={postProjectUpdate.bind(null, project.id)} progress={progress} />
              </CardContent>
            </Card>
          )}
          {project.updates.length === 0 ? (
            <EmptyState compact title={t.projects.noUpdates} />
          ) : (
            <ol className="relative space-y-4 border-l pl-6">
              {project.updates.map((u) => (
                <li key={u.id} className="relative">
                  <span
                    className={cn(
                      "absolute top-4 -left-[31px] size-3 rounded-full ring-4 ring-background",
                      colorOf(colorFor("health", u.health)).dot,
                    )}
                  />
                  <Card>
                    <CardContent className="py-4">
                      <UpdateItem update={u} t={t} locale={locale} />
                    </CardContent>
                  </Card>
                </li>
              ))}
            </ol>
          )}
        </div>
      )}

      {tab === "team" && (
        <Card className="mx-auto max-w-3xl">
          <CardContent className="space-y-4 py-5">
            {canEdit && (
              <AddMemberForm
                action={addProjectMember.bind(null, project.id)}
                members={members.filter((m) => !project.members.some((pm) => pm.userId === m.id))}
                rolePlaceholder={t.projects.teamRolePlaceholder}
                submitLabel={t.projects.addToTeam}
              />
            )}
            {project.members.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t.projects.noTeam}</p>
            ) : (
              <ul className="divide-y">
                {project.members.map((m) => (
                  <li key={m.userId} className="flex items-center gap-3 py-3">
                    <Avatar name={m.user.name} color={m.user.avatarColor} />
                    <Link href={`/members/${m.userId}`} className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium hover:underline">{m.user.name}</p>
                      <p className="truncate text-xs text-muted-foreground">{m.role ?? t.common.member}</p>
                    </Link>
                    {m.userId === project.leadId && <Badge color="indigo">{t.common.lead}</Badge>}
                    {canEdit && m.userId !== project.leadId && (
                      <RowActions
                        items={[
                          {
                            kind: "confirm",
                            label: t.common.remove,
                            icon: <UserMinus />,
                            title: `${t.common.remove} ${m.user.name}?`,
                            description: " ",
                            confirmLabel: t.common.remove,
                            action: removeProjectMember.bind(null, project.id, m.userId),
                          },
                        ]}
                      />
                    )}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      )}

      {tab === "budget" && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-3">
            <Card className="p-4">
              <p className="text-[13px] text-muted-foreground">{t.common.budget}</p>
              <p className="mt-1 text-xl font-semibold tabular-nums">
                {project.budgetCents ? money(project.budgetCents) : "—"}
              </p>
            </Card>
            <Card className="p-4">
              <p className="text-[13px] text-muted-foreground">{t.common.spent}</p>
              <p
                className={cn(
                  "mt-1 text-xl font-semibold tabular-nums",
                  project.budgetCents && spent > project.budgetCents && "text-destructive",
                )}
              >
                {money(spent)}
              </p>
              {project.budgetCents ? (
                <Progress
                  value={pct(spent, project.budgetCents)}
                  color={spent > project.budgetCents ? "rose" : "emerald"}
                  size="sm"
                  className="mt-2"
                />
              ) : null}
            </Card>
            <Card className="p-4">
              <p className="text-[13px] text-muted-foreground">{t.finance.income}</p>
              <p className="mt-1 text-xl font-semibold text-success tabular-nums">{money(income)}</p>
            </Card>
          </div>
          <Card>
            <CardHeader>
              <CardTitle>{t.projects.linkedTransactions}</CardTitle>
              {can.manageFinance(user) && (
                <FormDialog
                  title={t.finance.newTransaction}
                  action={createTransaction}
                  submitLabel={t.common.create}
                  trigger={
                    <Button size="sm" variant="soft">
                      <Plus /> {t.finance.newTransaction}
                    </Button>
                  }
                >
                  <TransactionFields
                    tx={{ projectId: project.id, poleId: project.poleId }}
                    projects={projects.map((p) => ({ id: p.id, name: p.title }))}
                    poles={poles}
                    currency={association.currency}
                  />
                </FormDialog>
              )}
            </CardHeader>
            <CardContent>
              {project.transactions.length === 0 ? (
                <EmptyState compact icon={<Wallet />} title={t.projects.noTransactions} />
              ) : (
                <ul className="divide-y">
                  {project.transactions.map((tx) => (
                    <li key={tx.id} className="flex items-center gap-3 py-2.5 text-sm">
                      <span className="w-20 shrink-0 text-xs text-muted-foreground">
                        {formatDate(tx.date, locale, { day: "numeric", month: "short" })}
                      </span>
                      <span className="min-w-0 flex-1 truncate">{tx.label}</span>
                      <Badge color="slate" className="hidden sm:inline-flex">
                        {t.enums.category[tx.category] ?? tx.category}
                      </Badge>
                      <span
                        className={cn(
                          "w-24 text-right font-medium tabular-nums",
                          tx.type === "INCOME" ? "text-success" : "",
                        )}
                      >
                        {tx.type === "INCOME" ? "+" : "−"}
                        {money(tx.amountCents)}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

function UpdateItem({
  update,
  t,
  locale,
}: {
  update: {
    body: string;
    health: string;
    progress: number | null;
    createdAt: Date;
    author: { name: string; avatarColor: string } | null;
  };
  t: Awaited<ReturnType<typeof getI18n>>["t"];
  locale: string;
}) {
  return (
    <div>
      <div className="mb-2 flex flex-wrap items-center gap-2 text-sm">
        <Avatar name={update.author?.name ?? "?"} color={update.author?.avatarColor} size="sm" />
        <span className="font-medium">{update.author?.name ?? t.activity.someone}</span>
        <span className="text-xs text-muted-foreground">{formatRelative(update.createdAt, locale)}</span>
        <span className="ml-auto flex items-center gap-2">
          {update.progress !== null && (
            <span className="text-xs font-medium text-muted-foreground tabular-nums">{update.progress}%</span>
          )}
          <Badge color={colorFor("health", update.health)} dot>
            {t.enums.health[update.health]}
          </Badge>
        </span>
      </div>
      <RichText text={update.body} />
    </div>
  );
}

async function TasksTab({
  projectId,
  poleId,
  user,
  members,
  projects,
  poles,
  t,
  db,
}: {
  projectId: string;
  poleId: string | null;
  user: CurrentUser;
  db: TenantDb;
  members: { id: string; name: string }[];
  projects: { id: string; title: string }[];
  poles: { id: string; name: string }[];
  t: Awaited<ReturnType<typeof getI18n>>["t"];
}) {
  const tasks = await loadTaskCards(db, { projectId }, user);
  return (
    <div>
      <div className="mb-4 flex justify-end">
        <FormDialog
          title={t.tasks.newTask}
          action={createTask}
          submitLabel={t.common.create}
          trigger={
            <Button size="sm">
              <Plus /> {t.tasks.newTask}
            </Button>
          }
        >
          <TaskFields
            task={{ projectId, poleId }}
            members={members}
            projects={projects.map((p) => ({ id: p.id, name: p.title }))}
            poles={poles}
          />
        </FormDialog>
      </div>
      <TaskBoard tasks={tasks} />
    </div>
  );
}
