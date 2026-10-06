import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";

/**
 * One place for the E2E stack's addresses and environment, shared by
 * playwright.config.ts (the two webServer processes) and global-setup.ts (the
 * database reset), so the API that seeds and the API that serves can never
 * disagree about which database or which URLs they use.
 *
 * Dedicated ports (not 8000/3000) so the suite can run beside a dev stack.
 * 127.0.0.1 rather than localhost throughout: the web tier compares the Origin
 * header against NEXT_PUBLIC_SITE_URL literally, and the API signs verification
 * links for APP_URL, so the browser has to use exactly these hosts.
 */
export const API_PORT = Number(process.env.E2E_API_PORT ?? 8010);
export const WEB_PORT = Number(process.env.E2E_WEB_PORT ?? 3010);
export const API_URL = `http://127.0.0.1:${API_PORT}`;
export const WEB_URL = `http://127.0.0.1:${WEB_PORT}`;

export const WEB_DIR = path.resolve(__dirname, "..", "..");
export const API_DIR = path.resolve(WEB_DIR, "..", "api");

/**
 * Playwright's results and HTML report go to <repo>/.e2e-output rather than
 * under apps/web, where Prettier and ESLint would walk into them (Prettier
 * only reads the top-level ignore files). The directory ignores itself, so
 * nothing in it shows up in git either.
 */
export const OUTPUT_DIR = path.resolve(WEB_DIR, "..", "..", ".e2e-output");

export function prepareOutputDir(): string {
  mkdirSync(OUTPUT_DIR, { recursive: true });
  writeFileSync(path.join(OUTPUT_DIR, ".gitignore"), "*\n");
  return OUTPUT_DIR;
}

/** Where the log mailer writes; read back by support/mail.ts. */
export const MAIL_LOG = path.join(API_DIR, "storage", "logs", "e2e-mail.log");

/**
 * E2E-only values. Neither is a secret: the key encrypts nothing that outlives
 * the run, and the web-tier secret only has to match between the two processes.
 */
const E2E_APP_KEY = "base64:eQd3Br6HGeSGdAzH8rf/3VQRczPcIGSBQ9KGhjQm/qw=";
const E2E_WEB_TIER_SECRET =
  "d47f9df30a5c7d8b78dd9b7eb986b22f53e9ef7e5a2e3f4fdb6527b2264e2845";

/**
 * Everything the API reads that could differ between a developer's
 * apps/api/.env and the E2E stack is pinned here. Process environment outranks
 * .env (Laravel loads it immutably), so a local .env pointing at another
 * database, a real mailer or Meilisearch cannot leak into the run.
 */
export function apiEnv(): Record<string, string> {
  return {
    // `local` is a non-deployed environment (DeploymentEnvironment), which the
    // E2ESeeder requires. Unlike `testing` it does not swap in SiteSetting's
    // test-suite defaults; the seeder sets every module flag explicitly anyway.
    APP_ENV: "local",
    APP_NAME: "Zdravje360",
    APP_DEBUG: "true",
    APP_KEY: E2E_APP_KEY,
    // Verification links are signed for this host, so it must be the address
    // the browser follows them on.
    APP_URL: API_URL,
    FRONTEND_URL: WEB_URL,
    WEB_PUBLIC_URL: WEB_URL,
    APP_LOCALE: "en",
    // A config:cache left behind in a dev checkout would make the API ignore
    // every value below; point the cache paths somewhere that never exists.
    APP_CONFIG_CACHE: path.join(
      API_DIR,
      "bootstrap",
      "cache",
      "e2e-config-unused.php",
    ),
    APP_ROUTES_CACHE: path.join(
      API_DIR,
      "bootstrap",
      "cache",
      "e2e-routes-unused.php",
    ),

    DB_CONNECTION: "pgsql",
    DB_HOST: process.env.E2E_DB_HOST ?? "127.0.0.1",
    DB_PORT: process.env.E2E_DB_PORT ?? "5432",
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? "zdravje_e2e",
    DB_USERNAME: process.env.E2E_DB_USERNAME ?? "zdravje",
    DB_PASSWORD: process.env.E2E_DB_PASSWORD ?? "secret",
    DB_URL: "",

    // Database-backed cache, so rate-limiter counters live in a table that
    // migrate:fresh empties: each run starts with every limit's full budget.
    CACHE_STORE: "database",
    SESSION_DRIVER: "database",
    // Production queues on redis. Sync here only so a verification or reset
    // mail is in the log by the time the request that caused it returns.
    QUEUE_CONNECTION: "sync",
    MAIL_MAILER: "log",
    MAIL_LOG_CHANNEL: "e2e-mail",
    LOG_CHANNEL: "single",
    LOG_STACK: "single",
    BROADCAST_CONNECTION: "log",
    FILESYSTEM_DISK: "local",
    MEDIA_DISK: "public",
    SCOUT_DRIVER: "null",
    SENTRY_LARAVEL_DSN: "",

    CORS_ALLOWED_ORIGINS: WEB_URL,
    WEB_TIER_SECRET: E2E_WEB_TIER_SECRET,
    // Several workers: the Next server and the browser call the API at once.
    PHP_CLI_SERVER_WORKERS: "8",
  };
}

/**
 * The web tier: a production build (`next build` + `next start`), not the dev
 * server. NEXT_PUBLIC_* are inlined at build time, so these must be set for the
 * build as well as for `next start`.
 */
export function webEnv(): Record<string, string> {
  return {
    NEXT_PUBLIC_API_URL: API_URL,
    NEXT_PUBLIC_SITE_URL: WEB_URL,
    WEB_TIER_SECRET: E2E_WEB_TIER_SECRET,
    NEXT_TELEMETRY_DISABLED: "1",
    NEXT_PUBLIC_SENTRY_DSN: "",
    NEXT_PUBLIC_PLAUSIBLE_DOMAIN: "",
  };
}
