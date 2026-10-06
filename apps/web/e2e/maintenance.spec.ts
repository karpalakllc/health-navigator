import { execFileSync } from "node:child_process";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { API_DIR, API_URL, apiEnv } from "./support/env";
import { doctor } from "./support/fixtures";

/*
 * Maintenance mode (site_settings.maintenance_mode, the admin toggle).
 *
 * It takes the whole site down, so this file runs in its own Playwright
 * project ("maintenance", playwright.config.ts) after every other spec has
 * finished, and switches the flag back off afterwards.
 *
 * The flag is flipped through the model (php artisan tinker) rather than the
 * admin panel: SiteSetting's saved hook flushes its cache, exactly as the
 * panel's save does, without spending the Administrator's sign-in budget.
 */
function setMaintenance(on: boolean): void {
  execFileSync(
    "php",
    [
      "artisan",
      "tinker",
      `--execute=App\\Models\\SiteSetting::current()->update(['maintenance_mode' => ${on ? "true" : "false"}]);`,
    ],
    {
      cwd: API_DIR,
      env: { ...process.env, ...apiEnv() },
      stdio: ["ignore", "ignore", "inherit"],
      timeout: 60_000,
    },
  );
}

const REQUEST_ID = /^[A-Za-z0-9-]{8,64}$/;

test.describe("maintenance mode", () => {
  test.describe.configure({ mode: "serial", timeout: 150_000 });

  test.beforeAll(() => setMaintenance(true));
  test.afterAll(() => setMaintenance(false));

  test("the API refuses content but keeps health and public settings up", async ({
    request,
  }) => {
    const headers = { Accept: "application/json", "Accept-Language": "mk" };

    const refused = await request.get(`${API_URL}/api/v1/doctors`, { headers });
    expect(refused.status()).toBe(503);
    expect((await refused.json()).code).toBe("maintenance.active");
    // Correlation ID on refusals too (AssignRequestId runs first).
    expect(refused.headers()["x-request-id"]).toMatch(REQUEST_ID);

    const health = await request.get(`${API_URL}/api/v1/health`, { headers });
    expect(health.status()).toBe(200);

    const settings = await request.get(`${API_URL}/api/v1/settings/public`, {
      headers,
    });
    expect(settings.status()).toBe(200);
    expect((await settings.json()).data.maintenance_mode).toBe(true);
  });

  test("every page shows the maintenance page instead of content", async ({
    page,
  }) => {
    // The web tier reads public settings through a 30-second data cache
    // (SETTINGS_REVALIDATE_SECONDS), served stale while it revalidates, so
    // the switch can take two requests after that window to show.
    await expect(async () => {
      await page.goto(`/doctors/${doctor.slug}`);
      await expect(
        page.getByRole("heading", { name: mk.maintenance.title }),
      ).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 100_000, intervals: [2_000, 5_000, 10_000] });

    await expect(page.getByText(doctor.name)).toHaveCount(0);

    await page.goto("/");
    await expect(
      page.getByRole("heading", { name: mk.maintenance.title }),
    ).toBeVisible();
    // No site navigation while down: the page brings only the logo.
    await expect(
      page.getByRole("link", { name: mk.nav.doctors, exact: true }),
    ).toHaveCount(0);
  });
});
