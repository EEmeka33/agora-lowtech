#!/usr/bin/env node
/**
 * Starts the production server in its small form.
 *
 *   npm run build && npm start
 *
 * - `.next/standalone` holds only the files the server needs: 85 MB, instead of
 *   the 1.1 GB of node_modules used to build it. That folder (with `.next/static`
 *   and `public` copied in, done here) is what you copy to a small machine.
 * - The JavaScript heap is capped (AGORA_MAX_MEMORY_MB, 192 by default): under load,
 *   the server stays around 325 MB of memory instead of growing past 900 MB. The garbage
 *   collector works more often: each page costs 20 to 50 % more processor time
 *   (about 25 ms instead of 18 ms on our test machine). Raise it on a big server.
 */
import { spawn } from "node:child_process";
import { cpSync, existsSync } from "node:fs";
import path from "node:path";

const root = process.cwd();
const standalone = path.join(root, ".next", "standalone");
if (!existsSync(path.join(standalone, "server.js"))) {
  console.error("No production build found: run `npm run build` first.");
  process.exit(1);
}

// Settings from .env (variables already set in the environment win).
if (existsSync(".env")) process.loadEnvFile(".env");

// The standalone server runs from its own folder: make the data paths absolute.
const absolute = (p) => path.resolve(root, p);
const db = process.env.DATABASE_URL ?? "file:./data/agora.db";
process.env.DATABASE_URL = db.startsWith("file:") ? `file:${absolute(db.slice(5))}` : db;
process.env.UPLOAD_DIR = absolute(process.env.UPLOAD_DIR ?? "./data/uploads");
process.env.HOSTNAME = process.env.LISTEN_HOST ?? "0.0.0.0";

// The standalone server serves .next/static and public/ once they sit next to it.
cpSync(path.join(root, ".next", "static"), path.join(standalone, ".next", "static"), { recursive: true });
if (existsSync("public")) cpSync("public", path.join(standalone, "public"), { recursive: true });

// On the command line, this cap wins over one in NODE_OPTIONS (some hosts set a large one for every process).
const memory = Number(process.env.AGORA_MAX_MEMORY_MB) || 192;
const server = spawn(process.execPath, [`--max-old-space-size=${memory}`, path.join(standalone, "server.js")], {
  stdio: "inherit",
  env: process.env,
});
for (const signal of ["SIGINT", "SIGTERM"]) process.on(signal, () => server.kill(signal));
server.on("exit", (code) => process.exit(code ?? 0));
