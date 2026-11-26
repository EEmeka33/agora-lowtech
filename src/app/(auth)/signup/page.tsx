import { Suspense } from "react";
import Link from "@/components/ui/link";
import { redirect } from "next/navigation";
import type { Metadata } from "next";
import { db } from "@/lib/db";
import { getAccount } from "@/lib/auth";
import { getI18n } from "@/lib/i18n/server";
import { signupMode } from "@/lib/constants";
import { normalizeJoinCode } from "@/lib/tokens";
import { SignupForm } from "./signup-form";

export const metadata: Metadata = { title: "Create an account" };

export default function SignupPage(props: PageProps<"/signup">) {
  return (
    <Suspense>
      <Signup searchParams={props.searchParams} />
    </Suspense>
  );
}

async function Signup({ searchParams }: { searchParams: PageProps<"/signup">["searchParams"] }) {
  const sp = await searchParams;
  const code = typeof sp.code === "string" ? normalizeJoinCode(sp.code) : "";
  if (await getAccount()) redirect(code ? `/join?code=${code}` : "/welcome");
  const { t } = await getI18n();
  const isFirst = (await db.user.count()) === 0;
  const mode = signupMode();

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-semibold tracking-tight">{t.account.signup.title}</h1>
        <p className="mt-2 text-muted-foreground">{t.account.signup.subtitle}</p>
      </div>
      {mode === "closed" && !isFirst ? (
        <p className="rounded-lg border bg-card p-4 text-sm">{t.account.signup.closed}</p>
      ) : (
        <SignupForm defaultCode={code} codeRequired={mode === "invite" && !isFirst} isFirst={isFirst} />
      )}
      <p className="mt-8 text-sm text-muted-foreground">
        {t.account.haveAccount}{" "}
        <Link
          href={code ? `/login?next=${encodeURIComponent(`/join?code=${code}`)}` : "/login"}
          className="font-medium text-primary hover:underline"
        >
          {t.account.signIn}
        </Link>
      </p>
    </div>
  );
}
