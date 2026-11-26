"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { Loader2, Upload } from "lucide-react";
import { toast } from "@/components/ui/toast";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n/client";
import { DocumentMetaFields } from "./document-fields";

type Opt = { id: string; name: string };

/** Uploads go through a route handler (no server-action body size limit). */
export function UploadDialog({
  poles,
  projects,
  maxMb,
  defaultCategory,
}: {
  poles: Opt[];
  projects: Opt[];
  maxMb: number;
  defaultCategory?: string;
}) {
  const t = useT();
  const router = useRouter();
  const [open, setOpen] = React.useState(false);
  const [pending, setPending] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string>>({});
  const [fileName, setFileName] = React.useState("");
  const titleRef = React.useRef<HTMLDivElement>(null);

  const onSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const file = fd.get("file");
    if (file instanceof File && file.size > maxMb * 1024 * 1024) {
      setErrors({ file: t.documents.tooLarge(maxMb) });
      return;
    }
    setPending(true);
    setErrors({});
    try {
      const res = await fetch("/api/documents", { method: "POST", body: fd });
      const data = (await res.json()) as {
        ok: boolean;
        error?: string;
        message?: string;
        fieldErrors?: Record<string, string>;
      };
      if (!data.ok) {
        setErrors(data.fieldErrors ?? {});
        if (!data.fieldErrors) toast.error(data.error ?? t.common.genericError);
        return;
      }
      toast.success(data.message ?? t.common.created);
      setOpen(false);
      setFileName("");
      router.refresh();
    } catch {
      toast.error(t.common.genericError);
    } finally {
      setPending(false);
    }
  };

  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Upload /> {t.documents.newDocument}
      </Button>
      <Dialog open={open} onOpenChange={setOpen} title={t.documents.newDocument}>
        <form onSubmit={onSubmit} key={open ? "open" : "closed"}>
          <div className="space-y-4 px-5 py-5">
            <Field label={t.documents.file} error={errors.file}>
              <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-6 text-center text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary-soft/30">
                <Upload className="size-5" />
                <span className="max-w-full truncate font-medium text-foreground">
                  {fileName || `${t.documents.file} (max ${maxMb} MB)`}
                </span>
                <input
                  type="file"
                  name="file"
                  className="sr-only"
                  onChange={(e) => {
                    const f = e.target.files?.[0];
                    setFileName(f?.name ?? "");
                    const title = titleRef.current?.querySelector<HTMLInputElement>('input[name="title"]');
                    if (f && title && !title.value) title.value = f.name.replace(/\.[^.]+$/, "");
                  }}
                />
              </label>
            </Field>
            <Field label={t.documents.orLink} error={errors.url}>
              <Input name="url" type="url" placeholder={t.documents.urlPlaceholder} />
            </Field>
            <div ref={titleRef} className="space-y-4">
              <DocumentMetaFields poles={poles} projects={projects} doc={{ category: defaultCategory }} />
              {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
            </div>
          </div>
          <div className="flex justify-end gap-2 border-t bg-muted/40 px-5 py-3">
            <Button variant="ghost" onClick={() => setOpen(false)}>
              {t.common.cancel}
            </Button>
            <Button type="submit" disabled={pending}>
              {pending && <Loader2 className="animate-spin" />}
              {t.common.add}
            </Button>
          </div>
        </form>
      </Dialog>
    </>
  );
}
