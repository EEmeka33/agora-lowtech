import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { CalendarDays, ChevronLeft, Inbox, Crown, Pencil, Trash2, UserMinus, UserRound } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getMemberOptions, getPoleOptions, getProjectOptions, myPoleIds } from "@/lib/queries";
import { loadRequests } from "@/lib/request-queries";
import { createRequest } from "@/actions/requests";
import { RequestFields } from "@/components/requests/request-fields";
import { RequestCard } from "@/components/requests/request-card";
import { loadProjects } from "@/lib/project-queries";
import { loadTaskCards } from "@/lib/task-queries";
import { colorOf } from "@/lib/colors";
import { cn, formatDateTime, nowWall } from "@/lib/utils";
import { addPoleMember, deletePole, removePoleMember, setPoleMemberRole, updatePole } from "@/actions/poles";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormDialog } from "@/components/forms/form-dialog";
import { ConfirmButton } from "@/components/forms/confirm-dialog";
import { RowActions } from "@/components/forms/row-actions";
import { AddMemberForm } from "@/components/forms/add-member-form";
import { PoleFields } from "@/components/poles/pole-fields";
import { PoleIcon } from "@/components/icons";
import { ProjectRow } from "@/components/projects/project-card";
import { TaskTable } from "@/components/tasks/task-table";

export const metadata: Metadata = { title: "Pole" };

export default async function PolePage(props: PageProps<"/poles/[id]">) {
  const { user, db, association } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const pole = await db.pole.findUnique({
    where: { id },
    include: {
      members: {
        include: {
          user: {
            select: {
              id: true,
              name: true,
              avatarColor: true,
              memberships: { where: { associationId: association.id }, select: { title: true, boardPosition: true } },
            },
          },
        },
        orderBy: [{ role: "asc" }, { joinedAt: "asc" }],
      },
      responsibilities: { include: { owner: { select: { id: true, name: true, avatarColor: true } } } },
      events: { where: { startAt: { gte: nowWall() } }, orderBy: { startAt: "asc" }, take: 5 },
    },
  });
  if (!pole) notFound();

  const isLead = pole.members.some((m) => m.userId === user.id && m.role === "LEAD");
  const canEdit = can.managePoles(user) || isLead;
  const [projects, tasks, members, requests, allPoles, myPoles, projectOptions] = await Promise.all([
    loadProjects(db, { poleId: id, status: { notIn: ["CANCELLED"] } }),
    loadTaskCards(db, { poleId: id, status: { not: "DONE" } }, user),
    getMemberOptions(db),
    loadRequests(db, { poleId: id, status: { in: ["OPEN", "TAKEN"] } }, user, 20),
    getPoleOptions(db),
    myPoleIds(db, user.id),
    getProjectOptions(db),
  ]);
  const c = colorOf(pole.color);

  return (
    <div>
      <Link
        href="/poles"
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {t.poles.title}
      </Link>
      <div className={cn("relative mb-8 overflow-hidden rounded-2xl border bg-card p-6 card-shadow")}>
        <div
          className={cn(
            "pointer-events-none absolute -top-16 -right-16 size-56 rounded-full opacity-10 blur-2xl",
            c.bar,
          )}
        />
        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex gap-4">
            <PoleIcon icon={pole.icon} color={pole.color} size="lg" />
            <div className="min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight">{pole.name}</h1>
              {pole.description && <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{pole.description}</p>}
              {pole.mission && (
                <p className="mt-3 max-w-2xl text-sm">
                  <span className="font-medium">{t.poles.mission} · </span>
                  {pole.mission}
                </p>
              )}
            </div>
          </div>
          {canEdit && (
            <div className="flex shrink-0 gap-2">
              <FormDialog
                title={t.poles.editPole}
                action={updatePole.bind(null, pole.id)}
                resetOnSuccess={false}
                trigger={
                  <Button variant="outline">
                    <Pencil /> {t.common.edit}
                  </Button>
                }
              >
                <PoleFields
                  pole={{
                    name: pole.name,
                    description: pole.description,
                    mission: pole.mission,
                    color: pole.color,
                    icon: pole.icon,
                  }}
                />
              </FormDialog>
              {can.managePoles(user) && (
                <ConfirmButton
                  title={t.poles.deletePole}
                  description={t.poles.deleteWarning}
                  action={deletePole.bind(null, pole.id)}
                  redirectTo="/poles"
                >
                  <Button variant="outline" size="icon" aria-label={t.common.delete}>
                    <Trash2 />
                  </Button>
                </ConfirmButton>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-[15px] font-semibold">
                {t.requests.title} <span className="font-normal text-muted-foreground">{requests.length}</span>
              </h2>
              <FormDialog
                title={t.requests.askTitle(pole.name)}
                action={createRequest}
                submitLabel={t.common.create}
                trigger={
                  <Button size="sm" variant="soft">
                    <Inbox /> {t.requests.ask}
                  </Button>
                }
              >
                <RequestFields
                  poles={allPoles}
                  myPoles={allPoles.filter((p) => myPoles.includes(p.id) && p.id !== pole.id)}
                  projects={projectOptions.map((p) => ({ id: p.id, name: p.title }))}
                  defaults={{ poleId: pole.id }}
                />
              </FormDialog>
            </div>
            {requests.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                {t.requests.empty}
              </p>
            ) : (
              <div className="space-y-3">
                {requests.map((r) => (
                  <RequestCard key={r.id} request={r} compact />
                ))}
              </div>
            )}
          </section>
          <Card>
            <CardHeader>
              <CardTitle>
                {t.poles.activeProjects} <span className="font-normal text-muted-foreground">{projects.length}</span>
              </CardTitle>
              <Link href={`/projects?pole=${pole.id}&new=1`} className="text-sm text-primary hover:underline">
                + {t.projects.newProject}
              </Link>
            </CardHeader>
            <CardContent className="px-3">
              {projects.length === 0 ? (
                <p className="px-2 text-sm text-muted-foreground">{t.projects.emptyTitle}</p>
              ) : (
                projects.map((p) => <ProjectRow key={p.id} project={p} />)
              )}
            </CardContent>
          </Card>
          <div>
            <h2 className="mb-3 text-[15px] font-semibold">
              {t.poles.openTasks} <span className="font-normal text-muted-foreground">{tasks.length}</span>
            </h2>
            {tasks.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                {t.tasks.emptyTitle}
              </p>
            ) : (
              <TaskTable tasks={tasks} />
            )}
          </div>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>
                {t.poles.team} <span className="font-normal text-muted-foreground">{pole.members.length}</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {pole.members.length === 0 && <p className="text-sm text-muted-foreground">{t.poles.noMembers}</p>}
              <ul className="space-y-1">
                {pole.members.map((m) => (
                  <li key={m.userId} className="flex items-center gap-3 rounded-lg py-1.5">
                    <Avatar name={m.user.name} color={m.user.avatarColor} />
                    <Link href={`/members/${m.userId}`} className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium hover:underline">{m.user.name}</p>
                      <p className="truncate text-xs text-muted-foreground">
                        {m.user.memberships[0]?.boardPosition
                          ? t.enums.boardPosition[m.user.memberships[0].boardPosition]
                          : (m.user.memberships[0]?.title ?? "")}
                      </p>
                    </Link>
                    {m.role === "LEAD" && (
                      <Badge color={pole.color}>
                        <Crown className="size-3" /> {t.enums.poleRole.LEAD}
                      </Badge>
                    )}
                    {canEdit && (
                      <RowActions
                        items={[
                          m.role === "LEAD"
                            ? {
                                kind: "run",
                                label: t.poles.makeMember,
                                icon: <UserRound />,
                                action: setPoleMemberRole.bind(null, pole.id, m.userId, "MEMBER"),
                              }
                            : {
                                kind: "run",
                                label: t.poles.makeLead,
                                icon: <Crown />,
                                action: setPoleMemberRole.bind(null, pole.id, m.userId, "LEAD"),
                              },
                          { kind: "separator" },
                          {
                            kind: "confirm",
                            label: t.common.remove,
                            icon: <UserMinus />,
                            title: `${t.common.remove} ${m.user.name}?`,
                            description: " ",
                            confirmLabel: t.common.remove,
                            action: removePoleMember.bind(null, pole.id, m.userId),
                          },
                        ]}
                      />
                    )}
                  </li>
                ))}
              </ul>
              {canEdit &&
                (members.some((m) => !pole.members.some((pm) => pm.userId === m.id)) ? (
                  <AddMemberForm
                    action={addPoleMember.bind(null, pole.id)}
                    members={members.filter((m) => !pole.members.some((pm) => pm.userId === m.id))}
                    roleOptions={[
                      { value: "MEMBER", label: t.enums.poleRole.MEMBER },
                      { value: "LEAD", label: t.enums.poleRole.LEAD },
                    ]}
                    submitLabel={t.common.add}
                  />
                ) : (
                  <p className="text-xs text-muted-foreground">{t.poles.allMembersAdded}</p>
                ))}
            </CardContent>
          </Card>

          {pole.responsibilities.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t.structure.responsibilities}</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-3">
                  {pole.responsibilities.map((r) => (
                    <li key={r.id} className="flex items-start gap-3 text-sm">
                      {r.owner ? (
                        <Avatar name={r.owner.name} color={r.owner.avatarColor} size="sm" />
                      ) : (
                        <span className="size-6 rounded-full border border-dashed" />
                      )}
                      <div className="min-w-0">
                        <p className="font-medium">{r.title}</p>
                        <p className="text-xs text-muted-foreground">
                          {r.owner?.name ?? t.common.unassigned} · {t.enums.frequency[r.frequency]}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}

          {pole.events.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t.poles.upcomingEvents}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-1">
                {pole.events.map((e) => (
                  <Link
                    key={e.id}
                    href={`/events/${e.id}`}
                    className="flex items-center gap-2 rounded-md py-1 text-sm hover:text-primary"
                  >
                    <CalendarDays className="size-4 text-muted-foreground" />
                    <span className="flex-1 truncate">{e.title}</span>
                    <span className="text-xs text-muted-foreground">{formatDateTime(e.startAt, locale)}</span>
                  </Link>
                ))}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
