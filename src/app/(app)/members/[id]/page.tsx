import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { notFound } from "next/navigation";
import { CalendarDays, ChevronLeft, Crown, KeyRound, Link2, Mail, Pencil, Phone, Power, Rocket } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can, isAdmin } from "@/lib/permissions";
import { loadProjects } from "@/lib/project-queries";
import { loadTaskCards } from "@/lib/task-queries";
import { colorFor, colorOf } from "@/lib/colors";
import { cn, formatDate, formatMoney, formatRelative } from "@/lib/utils";
import { createInviteLink, setMemberStatus, updateMember } from "@/actions/members";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormDialog } from "@/components/forms/form-dialog";
import { RowActions, type RowAction } from "@/components/forms/row-actions";
import { MemberFields } from "@/components/members/member-fields";
import { memberValues } from "@/components/members/member-values";
import { PoleIcon } from "@/components/icons";
import { ProjectRow } from "@/components/projects/project-card";
import { TaskTable } from "@/components/tasks/task-table";
import { RichText } from "@/components/ui/rich-text";

export const metadata: Metadata = { title: "Member" };

export default async function MemberPage(props: PageProps<"/members/[id]">) {
  const { user, db, association } = await requireContext();
  const { t, locale } = await getI18n();
  const { id } = await props.params;
  const membership = await db.member.findUnique({
    where: { associationId_userId: { associationId: association.id, userId: id } },
    include: {
      user: {
        include: {
          poleMemberships: { where: { pole: { associationId: association.id } }, include: { pole: true } },
          responsibilities: {
            where: { associationId: association.id },
            include: { pole: { select: { name: true, color: true } } },
          },
          foundationSteps: {
            where: { associationId: association.id, status: { notIn: ["DONE", "SKIPPED"] } },
            orderBy: { order: "asc" },
          },
          dues: { where: { associationId: association.id }, orderBy: { year: "desc" } },
        },
      },
    },
  });
  if (!membership) notFound();
  const { user: account, ...memberInfo } = membership;
  const member = {
    ...account,
    memberships: account.dues,
    title: memberInfo.title,
    role: memberInfo.role,
    boardPosition: memberInfo.boardPosition,
    status: memberInfo.status,
    joinedAt: memberInfo.joinedAt,
  };

  const [projects, tasks] = await Promise.all([
    loadProjects(db, { OR: [{ leadId: id }, { members: { some: { userId: id } } }], status: { notIn: ["CANCELLED"] } }),
    loadTaskCards(db, { assigneeId: id, status: { not: "DONE" } }, user),
  ]);
  const admin = isAdmin(user);
  const canEdit = can.editProfile(user, id);
  const skills = (member.skills ?? "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);

  const adminItems: RowAction[] = admin
    ? [
        member.passwordHash
          ? {
              kind: "run",
              label: t.members.resetPassword,
              icon: <KeyRound />,
              action: createInviteLink.bind(null, id, "RESET"),
            }
          : {
              kind: "run",
              label: t.members.invite,
              icon: <Link2 />,
              action: createInviteLink.bind(null, id, "INVITE"),
            },
        ...(member.id !== user.id
          ? [
              member.status === "ACTIVE"
                ? ({
                    kind: "run",
                    label: t.members.deactivate,
                    icon: <Power />,
                    action: setMemberStatus.bind(null, id, "INACTIVE"),
                  } as const)
                : ({
                    kind: "run",
                    label: t.members.reactivate,
                    icon: <Power />,
                    action: setMemberStatus.bind(null, id, "ACTIVE"),
                  } as const),
            ]
          : []),
      ]
    : [];

  return (
    <div>
      <Link
        href="/members"
        className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="size-4" /> {t.members.title}
      </Link>

      <Card className="mb-6 overflow-hidden">
        <div
          className={cn("h-24 opacity-90", colorOf(member.avatarColor).bar)}
          style={{ maskImage: "linear-gradient(to bottom, black, transparent)" }}
        />
        <CardContent className="-mt-12 flex flex-col gap-5 pb-6 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
            <Avatar
              name={member.name}
              color={member.avatarColor}
              size="xl"
              className="size-20 text-2xl ring-4 ring-card"
            />
            <div>
              <h1 className="text-2xl font-semibold tracking-tight">{member.name}</h1>
              {member.title && <p className="text-muted-foreground">{member.title}</p>}
              <div className="mt-2 flex flex-wrap gap-1.5">
                {member.boardPosition && <Badge color="indigo">{t.enums.boardPosition[member.boardPosition]}</Badge>}
                <Badge color={colorFor("role", member.role)}>{t.enums.role[member.role]}</Badge>
                {member.status !== "ACTIVE" && <Badge color="rose">{t.members.statusLabel.INACTIVE}</Badge>}
              </div>
            </div>
          </div>
          <div className="flex gap-2">
            {canEdit && (
              <FormDialog
                title={t.members.editMember}
                action={updateMember.bind(null, id)}
                size="lg"
                resetOnSuccess={false}
                trigger={
                  <Button variant="outline">
                    <Pencil /> {t.common.edit}
                  </Button>
                }
              >
                <MemberFields member={memberValues(member)} adminFields={admin} />
              </FormDialog>
            )}
            {adminItems.length > 0 && <RowActions items={adminItems} />}
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-[340px_1fr]">
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{t.members.contact}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5 text-sm">
              <a href={`mailto:${member.email}`} className="flex items-center gap-2.5 hover:text-primary">
                <Mail className="size-4 text-muted-foreground" /> <span className="truncate">{member.email}</span>
              </a>
              {member.phone && (
                <a href={`tel:${member.phone}`} className="flex items-center gap-2.5 hover:text-primary">
                  <Phone className="size-4 text-muted-foreground" /> {member.phone}
                </a>
              )}
              <p className="flex items-center gap-2.5 text-muted-foreground">
                <CalendarDays className="size-4" /> {t.members.joinedAt} {formatDate(member.joinedAt, locale)}
              </p>
              {admin && (
                <p className="text-xs text-muted-foreground">
                  {t.members.lastLogin}:{" "}
                  {member.lastLoginAt ? formatRelative(member.lastLoginAt, locale) : t.members.neverLoggedIn}
                </p>
              )}
            </CardContent>
          </Card>

          {(member.bio || skills.length > 0) && (
            <Card>
              <CardHeader>
                <CardTitle>{t.members.bio}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {member.bio && <RichText text={member.bio} />}
                {skills.length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {skills.map((s) => (
                      <Badge key={s} color="slate">
                        {s}
                      </Badge>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader>
              <CardTitle>{t.common.poles}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {member.poleMemberships.length === 0 && (
                <p className="text-sm text-muted-foreground">{t.members.noPoles}</p>
              )}
              {member.poleMemberships.map((pm) => (
                <Link
                  key={pm.poleId}
                  href={`/poles/${pm.poleId}`}
                  className="flex items-center gap-3 rounded-lg p-1 hover:bg-hover/60"
                >
                  <PoleIcon icon={pm.pole.icon} color={pm.pole.color} size="sm" />
                  <span className="flex-1 text-sm font-medium">{pm.pole.name}</span>
                  {pm.role === "LEAD" && (
                    <Badge color="amber">
                      <Crown className="size-3" /> {t.enums.poleRole.LEAD}
                    </Badge>
                  )}
                </Link>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t.members.duesHistory}</CardTitle>
            </CardHeader>
            <CardContent>
              {member.memberships.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t.members.noDues}</p>
              ) : (
                <ul className="divide-y text-sm">
                  {member.memberships.map((ms) => (
                    <li key={ms.id} className="flex items-center justify-between py-2">
                      <span className="font-medium">{ms.year}</span>
                      <span className="text-muted-foreground">{formatDate(ms.paidAt, locale)}</span>
                      <span className="tabular-nums">{formatMoney(ms.amountCents, association.currency, locale)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <div>
            <h2 className="mb-3 text-[15px] font-semibold">
              {t.members.assignedTasks} <span className="font-normal text-muted-foreground">{tasks.length}</span>
            </h2>
            {tasks.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                {t.members.noTasks}
              </p>
            ) : (
              <TaskTable tasks={tasks} />
            )}
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t.common.projects}</CardTitle>
            </CardHeader>
            <CardContent className="px-3">
              {projects.length === 0 ? (
                <p className="px-2 text-sm text-muted-foreground">{t.members.noProjects}</p>
              ) : (
                projects.map((p) => <ProjectRow key={p.id} project={p} />)
              )}
            </CardContent>
          </Card>

          {member.responsibilities.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t.structure.responsibilities}</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-2.5 text-sm">
                  {member.responsibilities.map((r) => (
                    <li key={r.id} className="flex items-center gap-2">
                      <span className="size-1.5 rounded-full bg-primary" />
                      <span className="flex-1 font-medium">{r.title}</span>
                      {r.pole && <Badge color={r.pole.color}>{r.pole.name}</Badge>}
                      <span className="text-xs text-muted-foreground">{t.enums.frequency[r.frequency]}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}

          {member.foundationSteps.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t.members.foundingSteps}</CardTitle>
                <Link href="/foundation" className="text-sm text-primary hover:underline">
                  {t.common.viewAll}
                </Link>
              </CardHeader>
              <CardContent>
                <ul className="space-y-2 text-sm">
                  {member.foundationSteps.map((s) => (
                    <li key={s.id} className="flex items-center gap-2">
                      <Rocket className="size-4 text-muted-foreground" />
                      <span className="flex-1">{s.title}</span>
                      <Badge color={colorFor("stepStatus", s.status)}>{t.enums.stepStatus[s.status]}</Badge>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
