"use client";

import * as React from "react";
import { Plus } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { FormDialog } from "@/components/forms/form-dialog";
import { LinkResultDialog } from "@/components/forms/link-result";
import { Button } from "@/components/ui/button";
import { createMember } from "@/actions/members";
import { useT } from "@/lib/i18n/client";
import { MemberFields } from "./member-fields";

export function NewMemberDialog({
  poles,
  defaultOpen,
}: {
  poles: { id: string; name: string }[];
  defaultOpen?: boolean;
}) {
  const t = useT();
  const [link, setLink] = React.useState<string | null>(null);
  return (
    <>
      <FormDialog
        title={t.members.newMember}
        action={createMember}
        submitLabel={t.common.create}
        size="lg"
        defaultOpen={defaultOpen}
        successToast={false}
        onSuccess={(state) =>
          state.data?.link ? setLink(state.data.link) : toast.success(state.message ?? t.common.created)
        }
        trigger={
          <Button>
            <Plus /> {t.members.newMember}
          </Button>
        }
      >
        <MemberFields adminFields poles={poles} isNew />
      </FormDialog>
      <LinkResultDialog link={link} onClose={() => setLink(null)} />
    </>
  );
}
