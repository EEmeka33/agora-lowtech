import "server-only";

/** Longest side kept for photos: sharp on any screen, and enough to print an A5 page. */
const MAX_SIDE = 1600;
/** Below this size a file is not worth processing. */
const MIN_BYTES = 300 * 1024;

/**
 * Reduces an uploaded photo: at most 1600 px on its longest side, re-encoded as JPEG (quality 80), or as PNG
 * when it has transparency. The orientation is applied and the metadata (GPS position, camera…) removed.
 * Returns null to keep the original file: not a photo, already small, not smaller after processing, or the
 * image library (sharp, installed with Next.js) is missing.
 */
export async function shrinkImage(input: Buffer, mimeType: string) {
  if (!/^image\/(jpeg|png|webp)$/.test(mimeType) || input.length < MIN_BYTES) return null;
  try {
    const { default: sharp } = await import("sharp");
    const image = sharp(input, { failOn: "none" }).rotate();
    const { hasAlpha } = await image.metadata();
    const resized = image.resize({ width: MAX_SIDE, height: MAX_SIDE, fit: "inside", withoutEnlargement: true });
    const png = mimeType === "image/png" && hasAlpha;
    const buffer = png
      ? await resized.png({ compressionLevel: 9 }).toBuffer()
      : await resized.jpeg({ quality: 80, mozjpeg: true }).toBuffer();
    if (buffer.length > input.length * 0.9) return null;
    return { buffer, mimeType: png ? "image/png" : "image/jpeg", ext: png ? ".png" : ".jpg" };
  } catch {
    return null;
  }
}
