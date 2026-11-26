# syntax=docker/dockerfile:1
# ─── Agora — production image ──────────────────────────────────────────────
#   docker compose up -d        → http://localhost:3000
# Data (SQLite database + uploads) lives in the /app/data volume.

FROM node:22-bookworm-slim AS base
ENV NEXT_TELEMETRY_DISABLED=1 \
    DATABASE_URL=file:/app/data/agora.db
WORKDIR /app

# 1. Dependencies. better-sqlite3 ships prebuilt binaries; build tools are only
#    installed if they are missing for your platform.
FROM base AS deps
COPY package.json package-lock.json prisma.config.ts ./
COPY prisma ./prisma
RUN npm ci || ( \
      apt-get update && apt-get install -y --no-install-recommends python3 make g++ \
      && rm -rf /var/lib/apt/lists/* && npm ci )

# 2. Build
FROM base AS build
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npx prisma generate && npm run build && npm prune --omit=dev

# 3. Runtime
FROM base AS runner
ENV NODE_ENV=production \
    PORT=3000 \
    HOSTNAME=0.0.0.0 \
    UPLOAD_DIR=/app/data/uploads
COPY --from=build --chown=node:node /app ./
RUN mkdir -p /app/data/uploads && chown -R node:node /app/data
USER node
VOLUME ["/app/data"]
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:3000/api/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
ENTRYPOINT ["sh", "scripts/docker-entrypoint.sh"]
# The small standalone server, with a memory cap (see scripts/start.mjs).
CMD ["node", "scripts/start.mjs"]
