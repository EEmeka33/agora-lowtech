"use client";

import Link from "@/components/ui/link";
import { SearchX } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n/client";

export default function AppNotFound() {
  const t = useT();
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-24 text-center">
      <div className="flex size-12 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground">
        <SearchX className="size-5" />
      </div>
      <h1 className="text-xl font-semibold">{t.common.notFound}</h1>
      <p className="max-w-sm text-sm text-muted-foreground">{t.common.notFoundText}</p>
      <Link href="/" className={buttonVariants({ className: "mt-2" })}>
        {t.common.goHome}
      </Link>
    </div>
  );
}
