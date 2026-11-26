"use client";

import * as React from "react";
import { Ban, Copy, KeyRound, Plus } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { createJoinCode, revokeJoinCode } from "@/actions/associations";
import { FormDialog } from "@/components/forms/form-dialog";
import { FormGrid, SelectField, TextField } from "@/components/forms/fields";
import { LinkResultDialog } from "@/components/forms/link-result";
import { RowActions } from "@/components/forms/row-actions";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useLocale, useT } from "@/lib/i18n/client";
import { ROLES } from "@/lib/constants";
import { formatInstant } from "@/lib/utils";

type Code = {
  id: string;
  code: string;
  label: string | null;
  role: string;
  pole: string | null;
  uses: number;
  maxUses: number;
  expiresAt: Date | null;
  revokedAt: Date | null;
};

export function JoinCodesPanel({
  codes,
  poles,
  canGiveRoles,
}: {
  codes: Code[];
  poles: { id: string; name: string }[];
  canGiveRoles: boolean;
}) {
  const t = useT();
  const locale = useLocale();
  const [link, setLink] = React.useState<string | null>(null);
  const now = new Date();

  const state = (c: Code) =>
    c.revokedAt
      ? { label: t.codes.revoked, color: "slate" }
      : c.expiresAt && new Date(c.expiresAt) < now
        ? { label: t.codes.expired, color: "slate" }
        : c.uses >= c.maxUses
          ? { label: t.codes.exhausted, color: "amber" }
          : { label: t.codes.active, color: "emerald" };

  const copy = async (code: string) => {
    const url = `${window.location.origin}/join?code=${code}`;
    try {
      await navigator.clipboard.writeText(url);
      toast.success(t.common.copied);
    } catch {
      setLink(url);
    }
  };

  return (
    <Card className="mt-8">
      <CardHeader>
        <div>
          <CardTitle className="flex items-center gap-2">
            <KeyRound className="size-4 text-primary" /> {t.codes.title}
          </CardTitle>
          <CardDescription>{t.codes.subtitle}</CardDescription>
        </div>
        <FormDialog
          title={t.codes.new}
          action={createJoinCode}
          submitLabel={t.codes.create}
          successToast={false}
          onSuccess={(s) => setLink(s.data?.link ?? null)}
          trigger={
            <Button size="sm" variant="soft">
              <Plus /> {t.codes.new}
            </Button>
          }
        >
          <TextField name="label" label={t.codes.label} placeholder={t.codes.labelPlaceholder} optional />
          <FormGrid>
            <TextField
              name="maxUses"
              type="number"
              min={1}
              max={500}
              defaultValue={10}
              label={t.codes.maxUses}
              required
            />
            <SelectField
              name="expires"
              label={t.codes.expires}
              defaultValue="30"
              options={["7", "30", "90", "never"].map((v) => ({ value: v, label: t.codes.expiresIn[v] }))}
            />
            <SelectField
              name="role"
              label={t.codes.role}
              defaultValue="MEMBER"
              disabled={!canGiveRoles}
              options={(canGiveRoles ? ROLES : (["MEMBER"] as const)).map((r) => ({
                value: r,
                label: t.enums.role[r],
              }))}
            />
            <SelectField
              name="poleId"
              label={t.codes.pole}
              placeholder={t.common.none}
              options={poles.map((p) => ({ value: p.id, label: p.name }))}
            />
          </FormGrid>
        </FormDialog>
      </CardHeader>
      <CardContent>
        {codes.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t.codes.none}</p>
        ) : (
          <ul className="divide-y">
            {codes.map((c) => {
              const st = state(c);
              const usable = st.label === t.codes.active;
              return (
                <li key={c.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                  <code
                    className={`rounded-md bg-muted px-2 py-1 font-mono text-sm tracking-wider ${usable ? "" : "text-muted-foreground line-through"}`}
                  >
                    {c.code}
                  </code>
                  <div className="min-w-0 flex-1 text-sm">
                    <p className="truncate font-medium">{c.label ?? t.enums.role[c.role]}</p>
                    <p className="text-xs text-muted-foreground">
                      {t.codes.uses(c.uses, c.maxUses)} · {t.enums.role[c.role]}
                      {c.pole && ` · ${c.pole}`}
                      {c.expiresAt && ` · ${t.codes.expiresOn(formatInstant(c.expiresAt, locale))}`}
                    </p>
                  </div>
                  <Badge color={st.color} dot>
                    {st.label}
                  </Badge>
                  {usable && (
                    <>
                      <Button size="xs" variant="outline" onClick={() => copy(c.code)}>
                        <Copy /> {t.codes.copyLink}
                      </Button>
                      <RowActions
                        items={[
                          {
                            kind: "confirm",
                            label: t.codes.revoke,
                            icon: <Ban />,
                            title: `${t.codes.revoke} ${c.code}?`,
                            confirmLabel: t.codes.revoke,
                            action: revokeJoinCode.bind(null, c.id),
                            destructive: true,
                          },
                        ]}
                      />
                    </>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>
      <LinkResultDialog
        link={link}
        onClose={() => setLink(null)}
        title={t.codes.title}
        description={t.codes.subtitle}
      />
    </Card>
  );
}
