import type { NextConfig } from "next";

const isDev = process.env.NODE_ENV !== "production";

// Inline scripts are needed by Next.js (hydration data) and the theme boot script.
const csp = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob:",
  "font-src 'self' data:",
  "connect-src 'self'",
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "object-src 'none'",
].join("; ");

const nextConfig: NextConfig = {
  // Cache Components + Partial Prefetching are the Next.js 16 defaults and will be
  // mandatory in Next.js 17. Every page reads the session behind a <Suspense>
  // boundary (see src/app/(app)/layout.tsx and loading.tsx).
  cacheComponents: true,
  partialPrefetching: true,
  // A self-contained server in .next/standalone, with only the files it needs:
  // what to copy to a small machine (see docs/self-hosting.md).
  output: "standalone",
  // Native modules must stay Node.js dependencies.
  serverExternalPackages: ["better-sqlite3", "@node-rs/argon2", "sharp"],
  // Allow opening the dev server from other devices on your network (phone, laptop…).
  // Add your machine's LAN IP or hostname here if needed, e.g. "192.168.1.20".
  allowedDevOrigins: ["127.0.0.1", "localhost"],
  poweredByHeader: false,
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "Content-Security-Policy", value: csp },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), interest-cohort=()" },
          { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
        ],
      },
    ];
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
