import { Suspense } from "react";
import Link from "@/components/ui/link";
import { redirect } from "next/navigation";
import type { Metadata } from "next";
import { db } from "@/lib/db";
import { getAccount } from "@/lib/auth";
import { getI18n } from "@/lib/i18n/server";
import { signupMode } from "@/lib/constants";
import { LoginForm } from "./login-form";

export const metadata: Metadata = { title: "Sign in" };

export default function LoginPage(props: PageProps<"/login">) {
  return (
    <Suspense>
      <Login searchParams={props.searchParams} />
    </Suspense>
  );
}

async function Login({ searchParams }: { searchParams: PageProps<"/login">["searchParams"] }) {
  if (await getAccount()) redirect("/");
  const { t } = await getI18n();
  if ((await db.user.count()) === 0) redirect("/signup");
  const { next } = await searchParams;

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-semibold tracking-tight">{t.auth.welcomeBack}</h1>
        <p className="mt-2 text-muted-foreground">{t.auth.signInSubtitle}</p>
      </div>
      <LoginForm next={typeof next === "string" ? next : ""} />
      <div className="mt-8 space-y-2 text-sm text-muted-foreground">
        <p>
          <Link href="/forgot" className="font-medium text-primary hover:underline">
            {t.account.forgotPassword}
          </Link>
        </p>
        {signupMode() !== "closed" ? (
          <p>
            {t.account.noAccountYet}{" "}
            <Link href="/signup" className="font-medium text-primary hover:underline">
              {t.account.createAccount}
            </Link>
          </p>
        ) : (
          <p>{t.auth.noAccount}</p>
        )}
      </div>
    </div>
  );
}
