import { Suspense } from "react";
import Link from "@/components/ui/link";
import type { Metadata } from "next";
import { connection } from "next/server";
import { getI18n } from "@/lib/i18n/server";
import { mailEnabled } from "@/lib/mail";
import { ForgotForm } from "./forgot-form";

export const metadata: Metadata = { title: "Forgot password" };

export default function ForgotPage() {
  return (
    <Suspense>
      <Forgot />
    </Suspense>
  );
}

async function Forgot() {
  await connection();
  const { t } = await getI18n();
  return (
    <div>
      <h1 className="text-3xl font-semibold tracking-tight">{t.account.forgot.title}</h1>
      <p className="mt-2 mb-8 text-muted-foreground">{t.account.forgot.subtitle}</p>
      {mailEnabled() ? (
        <ForgotForm />
      ) : (
        <p className="rounded-lg border bg-card p-4 text-sm">{t.account.forgot.noMail}</p>
      )}
      <p className="mt-8 text-sm">
        <Link href="/login" className="font-medium text-primary hover:underline">
          ← {t.account.signIn}
        </Link>
      </p>
    </div>
  );
}
