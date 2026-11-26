import { Suspense } from "react";
import Link from "@/components/ui/link";
import type { Metadata } from "next";
import { ArrowRight, Building2, KeyRound, LogOut, Plus } from "lucide-react";
import { db } from "@/lib/db";
import { requireAccount } from "@/lib/auth";
import { getI18n } from "@/lib/i18n/server";
import { newAssociationsAllowed } from "@/lib/constants";
import { logout, switchAssociation } from "@/actions/auth";
import { buttonVariants } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { JoinCodeForm } from "./join-code-form";

export const metadata: Metadata = { title: "Welcome" };

export default function WelcomePage() {
  return (
    <Suspense>
      <Welcome />
    </Suspense>
  );
}

async function Welcome() {
  const account = await requireAccount();
  const { t } = await getI18n();
  const [memberships, associationCount] = await Promise.all([
    db.member.findMany({
      where: { userId: account.id, status: "ACTIVE" },
      include: { association: true },
      orderBy: { createdAt: "asc" },
    }),
    db.association.count(),
  ]);
  const canCreate = newAssociationsAllowed() || associationCount === 0;

  return (
    <div>
      <h1 className="text-3xl font-semibold tracking-tight">{t.welcome.title(account.name.split(" ")[0])}</h1>
      <p className="mt-2 mb-8 text-muted-foreground">{t.welcome.subtitle}</p>

      {memberships.length > 0 && (
        <section className="mb-8">
          <h2 className="mb-2 text-sm font-semibold">{t.welcome.yourAssociations}</h2>
          <ul className="space-y-2">
            {memberships.map((m) => (
              <li key={m.id}>
                <form action={switchAssociation.bind(null, m.associationId)}>
                  <button
                    type="submit"
                    className="flex w-full items-center gap-3 rounded-xl border bg-card p-3 text-left transition-colors hover:border-primary/40"
                  >
                    <span className="flex size-9 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground">
                      {m.association.name.charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium">{m.association.name}</span>
                      <Badge color="slate" className="mt-0.5">
                        {t.enums.role[m.role]}
                      </Badge>
                    </span>
                    <ArrowRight className="size-4 text-muted-foreground" />
                  </button>
                </form>
              </li>
            ))}
          </ul>
        </section>
      )}

      <div className="space-y-4">
        <section className="rounded-2xl border bg-card p-5 card-shadow">
          <div className="mb-3 flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
              <Building2 className="size-[18px]" />
            </span>
            <h2 className="font-semibold">{t.welcome.createTitle}</h2>
          </div>
          {canCreate ? (
            <>
              <p className="mb-4 text-sm text-muted-foreground">{t.welcome.createText}</p>
              <Link href="/welcome/new" className={buttonVariants()}>
                <Plus /> {t.welcome.createTitle}
              </Link>
            </>
          ) : (
            <p className="text-sm text-muted-foreground">{t.welcome.creationDisabled}</p>
          )}
        </section>
        <section className="rounded-2xl border bg-card p-5 card-shadow">
          <div className="mb-3 flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
              <KeyRound className="size-[18px]" />
            </span>
            <h2 className="font-semibold">{t.welcome.joinTitle}</h2>
          </div>
          <p className="mb-4 text-sm text-muted-foreground">{t.welcome.joinText}</p>
          <JoinCodeForm />
        </section>
      </div>

      <form action={logout} className="mt-8">
        <button
          type="submit"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
          <LogOut className="size-4" /> {t.nav.logout} ({account.email})
        </button>
      </form>
    </div>
  );
}
