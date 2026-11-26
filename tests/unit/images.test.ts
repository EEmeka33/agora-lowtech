import sharp from "sharp";
import { describe, expect, it } from "vitest";
import { shrinkImage } from "@/lib/images";

/** A noisy picture compresses badly, like a real photo. */
async function photo(width: number, height: number, format: "jpeg" | "png") {
  const pixels = Buffer.alloc(width * height * 3);
  let seed = 42;
  for (let i = 0; i < pixels.length; i++) {
    seed = (seed * 1103515245 + 12345) >>> 0;
    pixels[i] = 96 + (seed >>> 26) + ((i / 3 / width) % 64);
  }
  const image = sharp(pixels, { raw: { width, height, channels: 3 } });
  return format === "jpeg" ? image.jpeg({ quality: 95 }).toBuffer() : image.png().toBuffer();
}

describe("shrinkImage", () => {
  it("reduces a big photo to 1600 px and makes it lighter", async () => {
    const input = await photo(4000, 3000, "jpeg");
    const out = await shrinkImage(input, "image/jpeg");
    expect(out).not.toBeNull();
    const meta = await sharp(out!.buffer).metadata();
    expect(Math.max(meta.width!, meta.height!)).toBe(1600);
    expect(out!.buffer.length).toBeLessThan(input.length / 3);
    expect(out!.mimeType).toBe("image/jpeg");
  });

  it("turns an opaque PNG photo into a JPEG", async () => {
    const out = await shrinkImage(await photo(2400, 1800, "png"), "image/png");
    expect(out?.mimeType).toBe("image/jpeg");
    expect(out?.ext).toBe(".jpg");
  });

  it("keeps small files and other kinds of files as they are", async () => {
    expect(await shrinkImage(await photo(400, 300, "jpeg"), "image/jpeg")).toBeNull();
    expect(await shrinkImage(Buffer.alloc(2_000_000), "application/pdf")).toBeNull();
  });
});
