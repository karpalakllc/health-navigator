import { execFileSync } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import {
  API_DIR,
  MAIL_LOG,
  apiEnv,
  assertDisposableDatabase,
} from "./support/env";

/**
 * Runs once per `playwright test`, after both servers are up: rebuild the
 * zdravje_e2e schema and seed the fixed fixtures (apps/api E2ESeeder), then
 * empty the mail outbox so a link can only come from this run.
 *
 * migrate:fresh also empties the cache table, which is where the rate limiters
 * keep their counters (CACHE_STORE=database), so every run starts with full
 * budgets.
 */
export default function globalSetup(): void {
  assertDisposableDatabase();

  execFileSync(
    "php",
    ["artisan", "migrate:fresh", "--seed", "--seeder=E2ESeeder", "--force"],
    {
      cwd: API_DIR,
      env: { ...process.env, ...apiEnv() },
      stdio: ["ignore", "ignore", "inherit"],
      timeout: 180_000,
    },
  );

  mkdirSync(path.dirname(MAIL_LOG), { recursive: true });
  writeFileSync(MAIL_LOG, "");
}
