import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { CheckSquare, FolderKanban, Layers, Plus, Users } from "lucide-react";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getMemberOptions } from "@/lib/queries";
import { createPole } from "@/actions/poles";
import { EmptyState, PageHeader } from "@/components/ui/page";
import { Button } from "@/components/ui/button";
import { AvatarStack } from "@/components/ui/avatar";
import { FormDialog } from "@/components/forms/form-dialog";
import { PoleFields } from "@/components/poles/pole-fields";
import { PoleIcon } from "@/components/icons";
import { colorOf } from "@/lib/colors";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "Poles" };

export default async function PolesPage(props: PageProps<"/poles">) {
  const { user, db } = await requireContext();
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const [poles, members] = await Promise.all([
    db.pole.findMany({
      orderBy: [{ order: "asc" }, { name: "asc" }],
      include: {
        members: {
          include: { user: { select: { id: true, name: true, avatarColor: true } } },
          orderBy: { role: "asc" },
        },
        projects: { where: { status: { notIn: ["COMPLETED", "CANCELLED"] } }, select: { id: true } },
        tasks: { where: { status: { not: "DONE" } }, select: { id: true } },
      },
    }),
    getMemberOptions(db),
  ]);

  const newPole = (autoOpen: boolean) =>
    can.managePoles(user) && (
      <FormDialog
        title={t.poles.newPole}
        action={createPole}
        submitLabel={t.common.create}
        defaultOpen={autoOpen && sp.new === "1"}
        trigger={
          <Button>
            <Plus /> {t.poles.newPole}
          </Button>
        }
      >
        <PoleFields members={members} />
      </FormDialog>
    );

  return (
    <>
      <PageHeader title={t.poles.title} description={t.poles.subtitle} actions={newPole(true)} />
      {poles.length === 0 ? (
        <EmptyState
          icon={<Layers />}
          title={t.poles.emptyTitle}
          description={t.poles.emptyText}
          action={newPole(false)}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {poles.map((pole) => {
            const leads = pole.members.filter((m) => m.role === "LEAD");
            return (
              <Link
                key={pole.id}
                href={`/poles/${pole.id}`}
                className="group relative flex flex-col overflow-hidden rounded-xl border bg-card p-5 card-shadow transition-[border-color,transform,box-shadow] hover:-translate-y-0.5 hover:border-primary/40 hover:pop-shadow"
              >
                <div
                  className={cn(
                    "pointer-events-none absolute -top-10 -right-10 size-32 rounded-full opacity-[0.08] blur-xl",
                    colorOf(pole.color).bar,
                  )}
                />
                <div className="flex items-start gap-3">
                  <PoleIcon icon={pole.icon} color={pole.color} size="lg" />
                  <div className="min-w-0">
                    <h3 className="font-semibold tracking-tight group-hover:text-primary">{pole.name}</h3>
                    <p className="text-xs text-muted-foreground">
                      {leads.length ? `${t.common.leads}: ${leads.map((l) => l.user.name).join(", ")}` : t.poles.noLead}
                    </p>
                  </div>
                </div>
                {(pole.mission || pole.description) && (
                  <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">{pole.mission ?? pole.description}</p>
                )}
                <div className="mt-auto flex items-center gap-4 pt-5 text-xs text-muted-foreground">
                  <span className="inline-flex items-center gap-1">
                    <Users className="size-3.5" /> {pole.members.length}
                  </span>
                  <span className="inline-flex items-center gap-1">
                    <FolderKanban className="size-3.5" /> {pole.projects.length}
                  </span>
                  <span className="inline-flex items-center gap-1">
                    <CheckSquare className="size-3.5" /> {pole.tasks.length}
                  </span>
                  <span className="ml-auto">
                    <AvatarStack people={pole.members.map((m) => m.user)} max={4} />
                  </span>
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </>
  );
}
