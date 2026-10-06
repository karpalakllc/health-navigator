import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { API_URL } from "./support/env";
import { pharmacySlug } from "./support/fixtures";

/*
 * Seeded module flags (E2ESeeder::seedSiteSettings): pharmacies and products
 * off, as at launch; guidance and forum on.
 */
test.describe("module gating", () => {
  test("a disabled module is hidden from the nav and shows the not-available state", async ({
    page,
  }) => {
    await page.goto("/");
    const nav = page.getByRole("banner").getByRole("navigation");
    await expect(
      nav.getByRole("link", { name: mk.nav.doctors, exact: true }),
    ).toBeVisible();
    await expect(
      nav.getByRole("link", { name: mk.nav.forum, exact: true }),
    ).toBeVisible();
    await expect(
      nav.getByRole("link", { name: mk.nav.guidance, exact: true }),
    ).toBeVisible();
    await expect(
      nav.getByRole("link", { name: mk.nav.pharmacies, exact: true }),
    ).toHaveCount(0);
    await expect(
      nav.getByRole("link", { name: mk.nav.products, exact: true }),
    ).toHaveCount(0);

    await page.goto("/pharmacies");
    await expect(
      page.getByRole("heading", { level: 1, name: mk.pharmacies.title }),
    ).toBeVisible();
    const main = page.getByRole("main");
    // The badge shows in the page hero and on the placeholder card; both are
    // inside <main> since the hero joined the main landmark.
    await expect(
      main.getByText(mk.comingSoon.badge, { exact: true }).first(),
    ).toBeVisible();
    await expect(main.getByText(mk.comingSoon.title)).toBeVisible();
    // No listing behind the placeholder.
    await expect(
      page.locator(`a[href="/pharmacies/${pharmacySlug}"]`),
    ).toHaveCount(0);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
      "content",
      /noindex/,
    );
  });

  test("a disabled module's detail page renders not-found", async ({
    page,
  }) => {
    await page.goto(`/pharmacies/${pharmacySlug}`);
    await expect(
      page.getByRole("heading", { name: mk.notFound.title }),
    ).toBeVisible();
    await expect(page.getByText("Аптека Центар")).toHaveCount(0);
    await expect(
      page.locator('meta[name="robots"][content*="noindex"]').first(),
    ).toBeAttached();
  });

  test("a disabled module's detail URL answers HTTP 404", async ({
    request,
  }) => {
    /*
     * KNOWN ISSUE (reported, not fixed here): the page calls notFound(), but
     * /pharmacies/loading.tsx wraps the [slug] segment in a Suspense boundary,
     * so the response has already started streaming as 200 by then. Next marks
     * the streamed not-found page noindex (asserted above), so it stays out of
     * search results, but the status is a soft 404. Same for any unknown
     * /doctors/<slug>. Remove test.fail once the check runs before streaming.
     */
    test.fail();
    const response = await request.get(`/pharmacies/${pharmacySlug}`);
    expect(response.status()).toBe(404);
  });

  test("the API refuses the disabled module outright", async ({ request }) => {
    const response = await request.get(
      `${API_URL}/api/v1/pharmacies/${pharmacySlug}`,
      { headers: { Accept: "application/json" } },
    );
    expect(response.status()).toBe(503);
  });
});
