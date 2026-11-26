import { Suspense } from "react";
import Link from "@/components/ui/link";
import type { Metadata } from "next";
import { connection } from "next/server";
import { LinkIcon } from "lucide-react";
import { db } from "@/lib/db";
import { getI18n } from "@/lib/i18n/server";
import { sha256 } from "@/lib/tokens";
import { buttonVariants } from "@/components/ui/button";
import { acceptInvitation } from "@/actions/auth";
import { InviteForm } from "./invite-form";

export const metadata: Metadata = { title: "Invitation" };

export default function InvitePage(props: PageProps<"/invite/[token]">) {
  return (
    <Suspense>
      <Invite params={props.params} />
    </Suspense>
  );
}

async function Invite({ params }: { params: PageProps<"/invite/[token]">["params"] }) {
  await connection();
  const { t } = await getI18n();
  const { token } = await params;
  const invitation = await db.invitation.findUnique({
    where: { tokenHash: sha256(token) },
    include: { user: true, association: { select: { name: true } } },
  });
  const valid = invitation && !invitation.usedAt && invitation.expiresAt > new Date();

  if (!valid) {
    return (
      <div className="text-center">
        <div className="mx-auto mb-5 flex size-12 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
          <LinkIcon className="size-5" />
        </div>
        <h1 className="text-2xl font-semibold tracking-tight">{t.auth.invite.invalid}</h1>
        <p className="mt-2 text-muted-foreground">{t.auth.invite.invalidText}</p>
        <Link href="/login" className={buttonVariants({ className: "mt-6" })}>
          {t.auth.invite.goToLogin}
        </Link>
      </div>
    );
  }

  const reset = invitation.kind === "RESET";
  return (
    <div>
      <h1 className="text-3xl font-semibold tracking-tight">
        {reset ? t.auth.invite.resetTitle : t.auth.invite.title(invitation.association?.name ?? "Agora")}
      </h1>
      <p className="mt-2 mb-8 text-muted-foreground">{reset ? t.auth.invite.resetSubtitle : t.auth.invite.subtitle}</p>
      <InviteForm
        action={acceptInvitation.bind(null, token)}
        email={invitation.user.email}
        name={invitation.user.name}
        reset={reset}
      />
    </div>
  );
}
