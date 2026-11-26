import type { Metadata } from "next";
import Link from "@/components/ui/link";
import {
  Download,
  ExternalLink,
  File,
  FileImage,
  FileSpreadsheet,
  FileText,
  FolderOpen,
  Link2,
  Pencil,
  ShieldCheck,
  Trash2,
} from "lucide-react";
import type { Prisma } from "@/generated/prisma/client";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getAllProjectOptions, getPoleOptions } from "@/lib/queries";
import { DOCUMENT_CATEGORIES } from "@/lib/constants";
import { cn, formatBytes, formatDate } from "@/lib/utils";
import { maxUploadBytes } from "@/lib/uploads";
import { deleteDocument, updateDocument } from "@/actions/documents";
import { EmptyState, PageHeader } from "@/components/ui/page";
import { Badge } from "@/components/ui/badge";
import { SearchInput } from "@/components/forms/filters";
import { RowActions, type RowAction } from "@/components/forms/row-actions";
import { UploadDialog } from "@/components/documents/upload-dialog";
import { DocumentMetaFields } from "@/components/documents/document-fields";

export const metadata: Metadata = { title: "Documents" };

function hostname(url: string) {
  try {
    return new URL(url).hostname;
  } catch {
    return url;
  }
}

function iconFor(doc: { mimeType: string | null; url: string | null }) {
  if (doc.url) return Link2;
  const m = doc.mimeType ?? "";
  if (m.startsWith("image/")) return FileImage;
  if (m.includes("sheet") || m.includes("csv") || m.includes("excel")) return FileSpreadsheet;
  if (m.includes("pdf") || m.includes("word") || m.includes("text")) return FileText;
  return File;
}

export default async function DocumentsPage(props: PageProps<"/documents">) {
  const { user, db } = await requireContext();
  const { t, locale } = await getI18n();
  const sp = await props.searchParams;
  const category =
    typeof sp.category === "string" && DOCUMENT_CATEGORIES.includes(sp.category as never) ? sp.category : "";
  const q = typeof sp.q === "string" ? sp.q : "";

  const where: Prisma.DocumentWhereInput = {};
  if (category) where.category = category;
  if (q) where.OR = [{ title: { contains: q } }, { description: { contains: q } }];

  const [docs, counts, poles, projects] = await Promise.all([
    db.document.findMany({
      where,
      orderBy: { createdAt: "desc" },
      include: {
        uploadedBy: { select: { name: true } },
        pole: { select: { id: true, name: true, color: true } },
        project: { select: { id: true, title: true } },
      },
    }),
    db.document.groupBy({ by: ["category"], _count: true }),
    getPoleOptions(db),
    getAllProjectOptions(db),
  ]);
  const projectOpts = projects.map((p) => ({ id: p.id, name: p.title }));
  const total = counts.reduce((s, c) => s + c._count, 0);
  const hasStatutes = counts.some((c) => c.category === "STATUTES");
  const maxMb = Math.round(maxUploadBytes() / 1024 / 1024);
  const chip = (value: string, label: string, count: number) => (
    <Link
      key={value || "all"}
      href={value ? `/documents?category=${value}` : "/documents"}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[13px] transition-colors",
        category === value
          ? "border-primary/50 bg-primary-soft font-medium text-primary-soft-foreground"
          : "text-muted-foreground hover:bg-hover hover:text-foreground",
      )}
    >
      {label} <span className="text-xs tabular-nums opacity-70">{count}</span>
    </Link>
  );

  return (
    <>
      <PageHeader
        title={t.documents.title}
        description={t.documents.subtitle}
        actions={
          <UploadDialog poles={poles} projects={projectOpts} maxMb={maxMb} defaultCategory={category || undefined} />
        }
      />

      {!hasStatutes && (
        <div className="mb-6 flex gap-3 rounded-xl border border-primary/20 bg-primary-soft/50 p-4 text-sm">
          <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />
          <div>
            <p className="font-medium">{t.documents.essentials}</p>
            <p className="text-muted-foreground">{t.documents.essentialsHint}</p>
            <ul className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground">
              {t.documents.essentialList.map((item) => (
                <li key={item} className="before:mr-1.5 before:content-['·']">
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}

      <div className="mb-5 flex flex-wrap items-center gap-2">
        {chip("", t.common.all, total)}
        {DOCUMENT_CATEGORIES.filter((c) => counts.some((x) => x.category === c)).map((c) =>
          chip(c, t.enums.documentCategory[c], counts.find((x) => x.category === c)?._count ?? 0),
        )}
        <SearchInput placeholder={t.common.searchPlaceholder} className="w-full sm:ml-auto sm:w-56" />
      </div>

      {docs.length === 0 ? (
        <EmptyState icon={<FolderOpen />} title={t.documents.emptyTitle} description={t.documents.emptyText} />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {docs.map((doc) => {
            const Icon = iconFor(doc);
            const href = doc.storedName ? `/api/files/${doc.id}` : (doc.url ?? "#");
            const items: RowAction[] = [];
            if (doc.storedName)
              items.push({
                kind: "link",
                label: t.documents.download,
                icon: <Download />,
                href: `/api/files/${doc.id}?download=1`,
              });
            if (can.editDocument(user, doc)) {
              items.push(
                {
                  kind: "dialog",
                  label: t.common.edit,
                  icon: <Pencil />,
                  title: t.documents.editDocument,
                  action: updateDocument.bind(null, doc.id),
                  content: (
                    <DocumentMetaFields doc={doc} poles={poles} projects={projectOpts} showUrl={!doc.storedName} />
                  ),
                },
                {
                  kind: "confirm",
                  label: t.common.delete,
                  icon: <Trash2 />,
                  title: t.documents.deleteDocument,
                  action: deleteDocument.bind(null, doc.id),
                  destructive: true,
                },
              );
            }
            return (
              <div
                key={doc.id}
                className="group flex gap-3 rounded-xl border bg-card p-4 card-shadow transition-colors hover:border-primary/40"
              >
                <a
                  href={href}
                  target="_blank"
                  rel="noreferrer noopener"
                  className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground"
                >
                  <Icon className="size-5" />
                </a>
                <div className="min-w-0 flex-1">
                  <div className="flex items-start gap-2">
                    <a href={href} target="_blank" rel="noreferrer noopener" className="min-w-0 flex-1">
                      <p className="truncate font-medium group-hover:text-primary">{doc.title}</p>
                      <p className="truncate text-xs text-muted-foreground">
                        {doc.url ? (
                          <span className="inline-flex items-center gap-1">
                            <ExternalLink className="size-3" /> {hostname(doc.url)}
                          </span>
                        ) : (
                          [doc.fileName, formatBytes(doc.sizeBytes)].filter(Boolean).join(" · ")
                        )}
                      </p>
                    </a>
                    {items.length > 0 && <RowActions items={items} />}
                  </div>
                  {doc.description && (
                    <p className="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{doc.description}</p>
                  )}
                  <div className="mt-2.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                    <Badge color="slate">{t.enums.documentCategory[doc.category]}</Badge>
                    {doc.pole && <Badge color={doc.pole.color}>{doc.pole.name}</Badge>}
                    {doc.project && (
                      <Link href={`/projects/${doc.project.id}`} className="truncate hover:underline">
                        {doc.project.title}
                      </Link>
                    )}
                    <span className="ml-auto">
                      {formatDate(doc.createdAt, locale, { day: "numeric", month: "short", year: "numeric" })}
                    </span>
                  </div>
                  {doc.uploadedBy && (
                    <p className="mt-1 text-[11px] text-muted-foreground">
                      {t.documents.uploadedBy(doc.uploadedBy.name)}
                    </p>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </>
  );
}
