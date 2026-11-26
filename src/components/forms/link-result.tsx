"use client";

import * as React from "react";
import { Check, Copy } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n/client";

/** Shows a generated link (invitation / reset) with a copy button. */
export function LinkResultDialog({
  link,
  onClose,
  title,
  description,
}: {
  link: string | null;
  onClose: () => void;
  title?: string;
  description?: string;
}) {
  const t = useT();
  const [copied, setCopied] = React.useState(false);
  return (
    <Dialog open={!!link} onOpenChange={(o) => !o && onClose()} title={title ?? t.members.invite} size="md">
      <div className="space-y-3 px-5 py-5">
        <p className="text-sm text-muted-foreground">{description ?? t.members.inviteCreated}</p>
        <div className="flex gap-2">
          <Input readOnly value={link ?? ""} onFocus={(e) => e.currentTarget.select()} className="font-mono text-xs" />
          <Button
            variant="soft"
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(link ?? "");
                setCopied(true);
                toast.success(t.common.copied);
                setTimeout(() => setCopied(false), 1500);
              } catch {
                /* clipboard unavailable on http: the input is selectable */
              }
            }}
          >
            {copied ? <Check /> : <Copy />} {t.common.copy}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
