import { describe, expect, it } from "vitest";
import {
  citySlug,
  directionsUrl,
  distanceKm,
  formatDistance,
  isUrgentService,
  placeBySlug,
  sortByDistance,
  urgentCareHref,
  urgentOpenStatus,
} from "@/lib/urgent-care";

// Wednesday 2026-10-07, 10:30 in Skopje (UTC+2).
const WEDNESDAY_MORNING = new Date("2026-10-07T08:30:00Z");

describe("the „Каде веднаш“ link contract", () => {
  it("sends a known city to its page and keeps the service filter", () => {
    expect(urgentCareHref({})).toBe("/urgent-care");
    expect(urgentCareHref({ type: "ems" })).toBe("/urgent-care?type=ems");
    expect(urgentCareHref({ city: "Битола" })).toBe("/urgent-care/bitola");
    expect(urgentCareHref({ city: "Bitola", type: "ed" })).toBe(
      "/urgent-care/bitola?type=ed",
    );
    expect(urgentCareHref({ city: "  Скопје " })).toBe("/urgent-care/skopje");
    expect(urgentCareHref({ city: "Карпош" })).toBe("/urgent-care/karpos");
  });

  it("keeps a city outside the territorial list as a query", () => {
    expect(urgentCareHref({ city: "Нагоричане Село", type: "dental" })).toBe(
      "/urgent-care?city=%D0%9D%D0%B0%D0%B3%D0%BE%D1%80%D0%B8%D1%87%D0%B0%D0%BD%D0%B5+%D0%A1%D0%B5%D0%BB%D0%BE&type=dental",
    );
  });

  it("maps slugs back to places, and only the four service values", () => {
    expect(placeBySlug("bitola")?.name).toBe("Битола");
    expect(placeBySlug("KARPOS")?.name).toBe("Карпош");
    expect(placeBySlug("atlantis")).toBeUndefined();
    expect(citySlug("Штип")).toBe("stip");
    expect(["ed", "ems", "clinic", "dental"].every(isUrgentService)).toBe(true);
    expect(isUrgentService("pharmacy")).toBe(false);
    expect(isUrgentService(undefined)).toBe(false);
  });
});

describe("open status of the urgent service", () => {
  it("never guesses: unknown hours give nothing", () => {
    expect(
      urgentOpenStatus(
        { is_open_24h: false, emergency_hours: null },
        WEDNESDAY_MORNING,
      ),
    ).toBeNull();
  });

  it("uses 24/7 and confirmed hours", () => {
    expect(
      urgentOpenStatus(
        { is_open_24h: true, emergency_hours: null },
        WEDNESDAY_MORNING,
      ),
    ).toEqual({ state: "open24" });
    expect(
      urgentOpenStatus(
        { is_open_24h: false, emergency_hours: { "Пон–Пет": "07:00–20:00" } },
        WEDNESDAY_MORNING,
      ),
    ).toEqual({ state: "open", until: "20:00" });
  });
});

describe("distance sort (browser only)", () => {
  const skopje = { latitude: 41.9981, longitude: 21.4254 };

  it("measures great-circle distance", () => {
    const bitola = { latitude: 41.0311, longitude: 21.3343 };
    expect(distanceKm(skopje, bitola)).toBeGreaterThan(105);
    expect(distanceKm(skopje, bitola)).toBeLessThan(110);
  });

  it("puts the nearest first and places without coordinates last, in order", () => {
    const places = [
      { slug: "a", latitude: null, longitude: null },
      { slug: "far", latitude: 41.0311, longitude: 21.3343 },
      { slug: "b", latitude: null, longitude: null },
      { slug: "near", latitude: 42.0, longitude: 21.43 },
    ];

    expect(
      sortByDistance(places, skopje).map(({ place }) => place.slug),
    ).toEqual(["near", "far", "a", "b"]);
  });

  it("formats metres and kilometres", () => {
    expect(formatDistance(0.012)).toBe("50 m");
    expect(formatDistance(0.83)).toBe("850 m");
    expect(formatDistance(12.34)).toMatch(/^12[,.]3 km$/);
  });
});

describe("directions", () => {
  it("uses coordinates when known, else the name and address", () => {
    expect(
      directionsUrl({
        name: "X",
        address: null,
        city: null,
        latitude: 41,
        longitude: 21,
      }),
    ).toBe("https://www.google.com/maps/dir/?api=1&destination=41,21");
    expect(
      directionsUrl({
        name: "ЈЗУ Општа болница",
        address: "ул. 1",
        city: "Струмица",
        latitude: null,
        longitude: null,
      }),
    ).toBe(
      `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent("ЈЗУ Општа болница, ул. 1, Струмица")}`,
    );
  });
});
