import { Suspense } from "react";
import Link from "@/components/ui/link";
import type { Metadata } from "next";
import { db } from "@/lib/db";
import { requireAccount } from "@/lib/auth";
import { getI18n } from "@/lib/i18n/server";
import { newAssociationsAllowed } from "@/lib/constants";
import { AssociationWizard } from "./association-wizard";

export const metadata: Metadata = { title: "New association" };

export default function NewAssociationPage() {
  return (
    <Suspense>
      <NewAssociation />
    </Suspense>
  );
}

async function NewAssociation() {
  await requireAccount();
  const { locale, t } = await getI18n();
  const allowed = newAssociationsAllowed() || (await db.association.count()) === 0;
  if (!allowed) {
    return (
      <div>
        <p className="rounded-lg border bg-card p-4 text-sm">{t.welcome.creationDisabled}</p>
        <Link href="/welcome" className="mt-6 inline-block text-sm font-medium text-primary hover:underline">
          ← {t.welcome.back}
        </Link>
      </div>
    );
  }
  return <AssociationWizard defaultLocale={locale} />;
}
