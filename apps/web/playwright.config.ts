import { defineConfig, devices } from "@playwright/test";
import {
  API_PORT,
  API_URL,
  API_DIR,
  WEB_PORT,
  WEB_URL,
  apiEnv,
  prepareOutputDir,
  webEnv,
} from "./e2e/support/env";
import { consentState } from "./e2e/support/consent";

/**
 * Browser suite against the real stack: Laravel on PostgreSQL (database
 * zdravje_e2e) and a production build of this app. See README "Running E2E
 * locally". global-setup.ts resets and seeds the database once per run.
 */
const CI = Boolean(process.env.CI);

/** Reuse an existing .next build (E2E_SKIP_BUILD=1) when iterating on specs. */
const buildStep = process.env.E2E_SKIP_BUILD === "1" ? "" : "npm run build && ";

/**
 * Attach to servers already listening on the E2E ports only when asked
 * (E2E_REUSE=1), never in CI: an unrelated process on :8010 or :3010 — another
 * checkout, a stale run with different settings — would otherwise be tested
 * silently. Without it Playwright refuses to start when a port is taken.
 */
const reuseExistingServer = !CI && process.env.E2E_REUSE === "1";

/** <repo>/.e2e-output: results/ (traces, screenshots) and report/ (HTML). */
const output = prepareOutputDir();

export default defineConfig({
  testDir: "./e2e",
  outputDir: `${output}/results`,
  globalSetup: "./e2e/global-setup.ts",
  // Specs share one database, but every one of them works on its own accounts
  // and records, so files may run side by side.
  fullyParallel: false,
  workers: CI ? 2 : 3,
  forbidOnly: CI,
  retries: CI ? 1 : 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: CI
    ? [
        ["github"],
        ["html", { outputFolder: `${output}/report`, open: "never" }],
      ]
    : [["list"], ["html", { outputFolder: `${output}/report`, open: "never" }]],
  use: {
    baseURL: WEB_URL,
    locale: "mk-MK",
    // Cookie banner already answered (statistics off); see e2e/support/consent.ts.
    storageState: consentState(false),
    trace: "on-first-retry",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
      testIgnore: /maintenance\.spec\.ts/,
    },
    {
      // Maintenance mode takes the whole site down, so it runs alone, after
      // every other spec (and is skipped if any of them failed).
      name: "maintenance",
      use: { ...devices["Desktop Chrome"] },
      testMatch: /maintenance\.spec\.ts/,
      dependencies: ["chromium"],
    },
  ],
  webServer: [
    {
      name: "api",
      command: `php artisan serve --host=127.0.0.1 --port=${API_PORT} --no-reload`,
      cwd: API_DIR,
      // Static files: the database is only migrated by global-setup.ts, which
      // Playwright runs after the servers are up, so anything that queries it
      // (the health endpoint, any page) would fail on a first run.
      url: `${API_URL}/robots.txt`,
      env: apiEnv(),
      reuseExistingServer,
      timeout: 60_000,
      stdout: "ignore",
      stderr: "pipe",
    },
    {
      name: "web",
      command: `${buildStep}npm run start -- --hostname 127.0.0.1 --port ${WEB_PORT}`,
      url: `${WEB_URL}/next.svg`,
      env: webEnv(),
      reuseExistingServer,
      // Includes `next build` unless E2E_SKIP_BUILD=1.
      timeout: 300_000,
      stdout: "ignore",
      stderr: "pipe",
    },
  ],
});
