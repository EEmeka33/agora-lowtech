#!/usr/bin/env node
// Starts the built app on a fresh, throw-away database for end-to-end tests.
import { execSync, spawn } from "node:child_process";
import { rmSync, mkdirSync } from "node:fs";

const port = process.argv[2] ?? "3210";
const env = {
  ...process.env,
  DATABASE_URL: "file:./data/e2e.db",
  UPLOAD_DIR: "./data/e2e-uploads",
  SMTP_URL: "",
};
rmSync("data/e2e.db", { force: true });
rmSync("data/e2e-uploads", { recursive: true, force: true });
mkdirSync("data", { recursive: true });
execSync("npx prisma migrate deploy", { stdio: "inherit", env });
const server = spawn("node", ["scripts/start.mjs"], { stdio: "inherit", env: { ...env, PORT: port } });
process.on("SIGTERM", () => server.kill("SIGTERM"));
process.on("SIGINT", () => server.kill("SIGINT"));
