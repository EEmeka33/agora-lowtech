import { randomBytes } from "node:crypto";
import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";
import { NextResponse, type NextRequest } from "next/server";
import { getContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { DOCUMENT_CATEGORIES } from "@/lib/constants";
import { maxUploadBytes, uploadDir } from "@/lib/uploads";
import { shrinkImage } from "@/lib/images";
import { logActivity } from "@/lib/activity";
import { isSameOrigin } from "@/lib/api";

/** Creates a document from an uploaded file or a link (multipart/form-data). */
export async function POST(request: NextRequest) {
  const { t } = await getI18n();
  const ctx = await getContext();
  if (!ctx || !isSameOrigin(request))
    return NextResponse.json({ ok: false, error: t.common.notAllowed }, { status: 403 });
  const { db, user, association } = ctx;

  const fd = await request.formData();
  const str = (k: string) => {
    const v = fd.get(k);
    return typeof v === "string" ? v.trim() : "";
  };
  const title = str("title");
  const url = str("url");
  const file = fd.get("file");
  const hasFile = file instanceof File && file.size > 0;

  const fieldErrors: Record<string, string> = {};
  if (!title) fieldErrors.title = t.validation.required;
  if (!hasFile && !url) fieldErrors.file = t.documents.fileOrLinkRequired;
  if (hasFile && file.size > maxUploadBytes()) fieldErrors.file = t.documents.tooLarge(maxUploadBytes() / 1024 / 1024);
  if (url && !/^https?:\/\//i.test(url)) fieldErrors.url = t.validation.required;
  const poleId = str("poleId") || null;
  const projectId = str("projectId") || null;
  if (poleId && !(await db.pole.count({ where: { id: poleId } }))) fieldErrors.poleId = t.common.notAllowed;
  if (projectId && !(await db.project.count({ where: { id: projectId } }))) fieldErrors.projectId = t.common.notAllowed;
  if (Object.keys(fieldErrors).length) {
    return NextResponse.json({ ok: false, error: t.validation.required, fieldErrors }, { status: 400 });
  }

  let stored: { storedName: string; fileName: string; mimeType: string; sizeBytes: number } | null = null;
  if (hasFile) {
    let buffer = Buffer.from(await file.arrayBuffer());
    let mimeType = file.type || "application/octet-stream";
    let fileName = file.name;
    let ext = path
      .extname(file.name)
      .replace(/[^a-zA-Z0-9.]/g, "")
      .slice(0, 10);
    // Photos straight from a phone (4 to 12 MB) are reduced to what a screen shows.
    const smaller = await shrinkImage(buffer, mimeType);
    if (smaller) {
      ({ buffer, mimeType } = smaller);
      if (!(smaller.ext === ".jpg" ? /^\.jpe?g$/i : /^\.png$/i).test(ext)) {
        fileName = path.basename(file.name, path.extname(file.name)) + smaller.ext;
        ext = smaller.ext;
      }
    }
    const storedName = `${Date.now()}-${randomBytes(12).toString("hex")}${ext}`;
    await mkdir(uploadDir(), { recursive: true });
    await writeFile(path.join(uploadDir(), storedName), buffer);
    stored = { storedName, fileName: fileName.slice(0, 200), mimeType, sizeBytes: buffer.length };
  }

  const category = DOCUMENT_CATEGORIES.includes(str("category") as never) ? str("category") : "OTHER";
  const doc = await db.document.create({
    data: {
      associationId: association.id,
      title,
      description: str("description") || null,
      category,
      url: stored ? null : url,
      poleId,
      projectId,
      uploadedById: user.id,
      ...stored,
    },
  });
  await logActivity(ctx, {
    actorId: user.id,
    action: "uploaded",
    entityType: "document",
    entityId: doc.id,
    entityLabel: doc.title,
  });
  return NextResponse.json({ ok: true, id: doc.id, message: t.common.created });
}
