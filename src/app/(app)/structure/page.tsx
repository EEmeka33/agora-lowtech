import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { Crown, Pencil, Plus, Trash2, UserPlus } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can, isAdmin } from "@/lib/permissions";
import { getMemberOptions, getPoleOptions } from "@/lib/queries";
import { BOARD_POSITIONS } from "@/lib/constants";
import { colorOf } from "@/lib/colors";
import { cn } from "@/lib/utils";
import { createResponsibility, deleteResponsibility, updateResponsibility } from "@/actions/poles";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState, PageHeader } from "@/components/ui/page";
import { FormDialog } from "@/components/forms/form-dialog";
import { RowActions } from "@/components/forms/row-actions";
import { ResponsibilityFields } from "@/components/structure/responsibility-fields";
import { PoleIcon } from "@/components/icons";

export const metadata: Metadata = { title: "Structure" };

const CORE_POSITIONS = ["PRESIDENT", "SECRETARY", "TREASURER"];

export default async function StructurePage() {
  const { user, db, association } = await requireContext();
  const { t } = await getI18n();
  const [members, poles, responsibilities, memberOptions, poleOptions] = await Promise.all([
    db.member.findMany({
      where: { status: "ACTIVE" },
      orderBy: { user: { name: "asc" } },
      include: {
        user: {
          select: {
            id: true,
            name: true,
            avatarColor: true,
            poleMemberships: { where: { pole: { associationId: association.id } } },
            _count: {
              select: {
                tasksAssigned: { where: { status: { not: "DONE" }, associationId: association.id } },
                responsibilities: { where: { associationId: association.id } },
              },
            },
          },
        },
      },
    }),
    db.pole.findMany({
      orderBy: [{ order: "asc" }, { name: "asc" }],
      include: { members: { include: { user: { select: { id: true, name: true, avatarColor: true } } } } },
    }),
    db.responsibility.findMany({
      orderBy: [{ pole: { name: "asc" } }, { title: "asc" }],
      include: {
        owner: { select: { id: true, name: true, avatarColor: true } },
        pole: { select: { id: true, name: true, color: true } },
      },
    }),
    getMemberOptions(db),
    getPoleOptions(db),
  ]);

  const users = members.map((m) => ({ ...m.user, boardPosition: m.boardPosition }));
  type Seat = { position: string; user: (typeof users)[number] | null };
  const board = BOARD_POSITIONS.flatMap((position): Seat[] => {
    const holders = users.filter((u) => u.boardPosition === position);
    if (holders.length) return holders.map((u) => ({ position, user: u }));
    return CORE_POSITIONS.includes(position) ? [{ position, user: null }] : [];
  });
  const canManage = can.manageStructure(user);

  return (
    <>
      <PageHeader title={t.structure.title} description={t.structure.subtitle} />

      {/* Org chart */}
      <Card className="mb-8 overflow-hidden">
        <CardHeader>
          <div>
            <CardTitle>{t.structure.orgChart}</CardTitle>
          </div>
        </CardHeader>
        <CardContent className="scrollbar-thin overflow-x-auto pb-8">
          <div className="mx-auto flex min-w-fit flex-col items-center">
            <div className="flex items-center gap-3 rounded-2xl bg-primary px-5 py-3 text-primary-foreground shadow-md">
              <span className="flex size-9 items-center justify-center rounded-xl bg-white/20 text-base font-bold">
                {association.name.charAt(0).toUpperCase()}
              </span>
              <div>
                <p className="leading-tight font-semibold">{association.name}</p>
                <p className="text-xs opacity-80">{association.legalForm}</p>
              </div>
            </div>

            <Connector />
            <p className="mb-3 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
              {t.structure.boardTitle}
            </p>
            <Row>
              {board.map(({ position, user: holder }, i) => (
                <Node key={`${position}-${i}`}>
                  {holder ? (
                    <Link
                      href={`/members/${holder.id}`}
                      className="flex w-40 flex-col items-center rounded-xl border bg-card p-3 text-center card-shadow transition-colors hover:border-primary/40"
                    >
                      <Avatar name={holder.name} color={holder.avatarColor} size="lg" />
                      <p className="mt-2 w-full truncate text-sm font-medium">{holder.name}</p>
                      <p className="w-full truncate text-xs text-primary">{t.enums.boardPosition[position]}</p>
                    </Link>
                  ) : (
                    <div className="flex w-40 flex-col items-center rounded-xl border border-dashed p-3 text-center">
                      <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        ?
                      </span>
                      <p className="mt-2 text-sm text-muted-foreground">{t.common.vacant}</p>
                      <p className="text-xs text-muted-foreground">{t.enums.boardPosition[position]}</p>
                      {isAdmin(user) && (
                        <Link
                          href="/members"
                          className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                          <UserPlus className="size-3" /> {t.structure.assignBoard}
                        </Link>
                      )}
                    </div>
                  )}
                </Node>
              ))}
            </Row>

            <Connector />
            <p className="mb-3 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
              {t.structure.polesTitle}
            </p>
            {poles.length === 0 ? (
              <Link
                href="/poles?new=1"
                className="rounded-xl border border-dashed px-6 py-4 text-sm text-muted-foreground hover:border-primary/50 hover:text-primary"
              >
                + {t.structure.createPole}
              </Link>
            ) : (
              <Row inset="before:left-24 before:right-24">
                {poles.map((pole) => {
                  const leads = pole.members.filter((m) => m.role === "LEAD");
                  const others = pole.members.filter((m) => m.role !== "LEAD");
                  return (
                    <Node key={pole.id}>
                      <Link
                        href={`/poles/${pole.id}`}
                        className={cn(
                          "flex w-48 flex-col rounded-xl border-t-4 border-r border-b border-l bg-card p-3 card-shadow transition-colors hover:border-primary/40",
                          colorOf(pole.color).border,
                        )}
                      >
                        <div className="flex items-center gap-2">
                          <PoleIcon icon={pole.icon} color={pole.color} size="sm" />
                          <p className="truncate text-sm font-semibold">{pole.name}</p>
                        </div>
                        <div className="mt-3 space-y-1.5">
                          {leads.map((l) => (
                            <div key={l.userId} className="flex items-center gap-2 text-xs">
                              <Avatar name={l.user.name} color={l.user.avatarColor} size="xs" />
                              <span className="truncate font-medium">{l.user.name}</span>
                              <Crown className="ml-auto size-3 shrink-0 text-amber-500" />
                            </div>
                          ))}
                          {leads.length === 0 && (
                            <p className="text-xs text-muted-foreground italic">{t.poles.noLead}</p>
                          )}
                          {others.length > 0 && (
                            <p className="text-xs text-muted-foreground">+ {t.common.countMembers(others.length)}</p>
                          )}
                        </div>
                      </Link>
                    </Node>
                  );
                })}
              </Row>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Who does what */}
      <Card className="mb-8">
        <CardHeader>
          <div>
            <CardTitle>{t.structure.responsibilities}</CardTitle>
            <CardDescription>{t.structure.responsibilitiesHint}</CardDescription>
          </div>
          {canManage && (
            <FormDialog
              title={t.structure.addResponsibility}
              action={createResponsibility}
              submitLabel={t.common.create}
              trigger={
                <Button size="sm" variant="soft">
                  <Plus /> {t.structure.addResponsibility}
                </Button>
              }
            >
              <ResponsibilityFields members={memberOptions} poles={poleOptions} />
            </FormDialog>
          )}
        </CardHeader>
        <CardContent>
          {responsibilities.length === 0 ? (
            <EmptyState compact title={t.structure.noResponsibilities} />
          ) : (
            <div className="-mx-5 overflow-x-auto">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="border-y bg-muted/40 text-left text-xs text-muted-foreground">
                    <th className="px-5 py-2 font-medium">{t.structure.responsibility}</th>
                    <th className="px-3 py-2 font-medium">{t.common.owner}</th>
                    <th className="px-3 py-2 font-medium">{t.common.pole}</th>
                    <th className="px-3 py-2 font-medium">{t.structure.frequency}</th>
                    <th className="w-12 px-3 py-2" />
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {responsibilities.map((r) => (
                    <tr key={r.id} className="hover:bg-hover/40">
                      <td className="px-5 py-3">
                        <p className="font-medium">{r.title}</p>
                        {r.description && <p className="line-clamp-1 text-xs text-muted-foreground">{r.description}</p>}
                      </td>
                      <td className="px-3 py-3">
                        {r.owner ? (
                          <Link
                            href={`/members/${r.owner.id}`}
                            className="inline-flex items-center gap-2 hover:underline"
                          >
                            <Avatar name={r.owner.name} color={r.owner.avatarColor} size="sm" />
                            {r.owner.name}
                          </Link>
                        ) : (
                          <span className="text-muted-foreground">{t.common.unassigned}</span>
                        )}
                      </td>
                      <td className="px-3 py-3">
                        {r.pole ? (
                          <Badge color={r.pole.color}>{r.pole.name}</Badge>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="px-3 py-3 text-muted-foreground">{t.enums.frequency[r.frequency]}</td>
                      <td className="px-3 py-3 text-right">
                        {canManage && (
                          <RowActions
                            items={[
                              {
                                kind: "dialog",
                                label: t.common.edit,
                                icon: <Pencil />,
                                title: t.structure.editResponsibility,
                                action: updateResponsibility.bind(null, r.id),
                                content: <ResponsibilityFields value={r} members={memberOptions} poles={poleOptions} />,
                              },
                              {
                                kind: "confirm",
                                label: t.common.delete,
                                icon: <Trash2 />,
                                title: t.common.confirmDelete,
                                action: deleteResponsibility.bind(null, r.id),
                                destructive: true,
                              },
                            ]}
                          />
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Matrix */}
      {poles.length > 0 && (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t.structure.matrix}</CardTitle>
              <CardDescription>{t.structure.matrixHint}</CardDescription>
            </div>
          </CardHeader>
          <CardContent>
            <div className="-mx-5 scrollbar-thin overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-y bg-muted/40 text-xs text-muted-foreground">
                    <th className="sticky left-0 bg-muted/40 px-5 py-2 text-left font-medium backdrop-blur">
                      {t.common.member}
                    </th>
                    {poles.map((p) => (
                      <th key={p.id} className="px-3 py-2 font-medium whitespace-nowrap">
                        <span className={cn("inline-flex items-center gap-1.5", colorOf(p.color).text)}>
                          <span className={cn("size-1.5 rounded-full", colorOf(p.color).dot)} />
                          {p.name}
                        </span>
                      </th>
                    ))}
                    <th className="px-3 py-2 font-medium whitespace-nowrap">{t.poles.openTasks}</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {users.map((u) => (
                    <tr key={u.id} className="hover:bg-hover/40">
                      <td className="sticky left-0 bg-card px-5 py-2.5">
                        <Link href={`/members/${u.id}`} className="flex items-center gap-2.5 hover:underline">
                          <Avatar name={u.name} color={u.avatarColor} size="sm" />
                          <span className="whitespace-nowrap">{u.name}</span>
                          {u.boardPosition && (
                            <span className="hidden text-xs whitespace-nowrap text-primary md:inline">
                              {t.enums.boardPosition[u.boardPosition]}
                            </span>
                          )}
                        </Link>
                      </td>
                      {poles.map((p) => {
                        const m = u.poleMemberships.find((pm) => pm.poleId === p.id);
                        return (
                          <td key={p.id} className="px-3 py-2.5 text-center">
                            {m?.role === "LEAD" ? (
                              <Crown className="mx-auto size-4 text-amber-500" aria-label={t.enums.poleRole.LEAD} />
                            ) : m ? (
                              <span
                                className={cn("mx-auto block size-2.5 rounded-full", colorOf(p.color).dot)}
                                aria-label={t.enums.poleRole.MEMBER}
                              />
                            ) : (
                              <span className="text-muted-foreground/30">·</span>
                            )}
                          </td>
                        );
                      })}
                      <td className="px-3 py-2.5 text-center text-muted-foreground tabular-nums">
                        {u._count.tasksAssigned}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}
    </>
  );
}

function Connector() {
  return <div className="h-8 w-px bg-border" />;
}

/** A row of org-chart nodes with a horizontal connector between their centers. */
function Row({ children, inset = "before:left-20 before:right-20" }: { children: React.ReactNode; inset?: string }) {
  return (
    <div
      className={cn(
        "relative flex flex-wrap justify-center gap-x-4 gap-y-6 pt-5 before:absolute before:top-0 before:h-px before:bg-border",
        inset,
      )}
    >
      {children}
    </div>
  );
}

function Node({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative before:absolute before:-top-5 before:left-1/2 before:h-5 before:w-px before:bg-border">
      {children}
    </div>
  );
}
