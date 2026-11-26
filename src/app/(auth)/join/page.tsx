import { Suspense } from "react";
import Link from "@/components/ui/link";
import { redirect } from "next/navigation";
import type { Metadata } from "next";
import { KeyRound } from "lucide-react";
import { getAccount } from "@/lib/auth";
import { getI18n } from "@/lib/i18n/server";
import { findUsableCode } from "@/lib/join-codes";
import { normalizeJoinCode } from "@/lib/tokens";
import { JoinCodeForm } from "../welcome/join-code-form";

export const metadata: Metadata = { title: "Join" };

export default function JoinPage(props: PageProps<"/join">) {
  return (
    <Suspense>
      <Join searchParams={props.searchParams} />
    </Suspense>
  );
}

async function Join({ searchParams }: { searchParams: PageProps<"/join">["searchParams"] }) {
  const sp = await searchParams;
  const code = typeof sp.code === "string" ? normalizeJoinCode(sp.code) : "";
  const account = await getAccount();
  if (!account) redirect(code ? `/signup?code=${code}` : "/signup");
  const { t } = await getI18n();
  const jc = code ? await findUsableCode(code) : null;

  return (
    <div>
      <div className="mb-5 flex size-12 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground">
        <KeyRound className="size-5" />
      </div>
      {jc ? (
        <>
          <h1 className="text-3xl font-semibold tracking-tight">{t.join.title(jc.association.name)}</h1>
          <p className="mt-2 mb-8 text-muted-foreground">{t.join.subtitle(t.enums.role[jc.role])}</p>
        </>
      ) : (
        <>
          <h1 className="text-2xl font-semibold tracking-tight">{t.welcome.joinTitle}</h1>
          <p className="mt-2 mb-8 text-muted-foreground">{code ? t.join.invalid : t.welcome.joinText}</p>
        </>
      )}
      <JoinCodeForm code={jc ? code : undefined} />
      <Link href="/welcome" className="mt-8 inline-block text-sm font-medium text-primary hover:underline">
        ← {t.welcome.back}
      </Link>
    </div>
  );
}
