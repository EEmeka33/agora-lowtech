import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end tests run against a production build with a throw-away database.
 *   npm run build && npm run test:e2e
 * (first time only: npx playwright install chromium)
 */
const PORT = 3210;

export default defineConfig({
  testDir: "tests/e2e",
  timeout: 60_000,
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: {
    baseURL: `http://localhost:${PORT}`,
    trace: "off",
    ...devices["Desktop Chrome"],
  },
  projects: [
    { name: "story", testMatch: "smoke.spec.ts" },
    // Measured on the association created by the story.
    { name: "budget", testMatch: "budget.spec.ts", dependencies: ["story"] },
  ],
  webServer: {
    command: `node scripts/e2e-server.mjs ${PORT}`,
    url: `http://localhost:${PORT}/api/health`,
    reuseExistingServer: false,
    timeout: 120_000,
  },
});
