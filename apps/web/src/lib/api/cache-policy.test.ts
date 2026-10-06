import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const webTierRequestHeaders = vi.hoisted(() => vi.fn());
const getSessionToken = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api/client-ip", () => ({ webTierRequestHeaders }));
vi.mock("@/lib/auth/session", () => ({ getSessionToken }));

import { directoryCache } from "@/lib/api/client";
import { fetchDepartments } from "@/lib/api/departments";
import { fetchDoctor, fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { fetchForumCategories, fetchForumTopicPage } from "@/lib/api/forum";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchProducts } from "@/lib/api/products";
import { apiGetServer } from "@/lib/api/server";
import { fetchSpecialties } from "@/lib/api/specialties";

/**
 * Which fetches may use Next's data cache. Taxonomies are shared and change
 * rarely (5 minutes); anonymous directory lists without free text tolerate a
 * minute. Anything carrying the visitor's bearer token must never be cached:
 * a cached entry is served to every visitor.
 */
const fetchMock = vi.fn();

beforeEach(() => {
  process.env.NEXT_PUBLIC_API_URL = "http://api.test";
  webTierRequestHeaders.mockResolvedValue({ "X-Web-Tier-Auth": "s" });
  getSessionToken.mockResolvedValue("member-token");
  fetchMock.mockImplementation(async (url: string) => {
    if (url.endsWith("/specialties")) {
      return Response.json({ data: [{ slug: "kardiologija" }] });
    }
    if (url.endsWith("/languages")) {
      return Response.json({ data: [{ slug: "angliski" }] });
    }
    if (url.endsWith("/departments")) {
      return Response.json({ data: [{ slug: "urgenten" }] });
    }

    return new Response(
      JSON.stringify({
        data: [],
        meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
      }),
      { status: 200 },
    );
  });
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
  webTierRequestHeaders.mockReset();
  getSessionToken.mockReset();
});

function lastInit(): RequestInit & { next?: { revalidate?: number } } {
  return fetchMock.mock.calls.at(-1)?.[1];
}

function authorization(init: RequestInit): string | null {
  return new Headers(init.headers).get("Authorization");
}

describe("taxonomy fetches", () => {
  it.each([
    ["specialties", fetchSpecialties],
    ["departments", fetchDepartments],
    ["forum categories", () => fetchForumCategories()],
  ])("revalidates %s every five minutes", async (_name, load) => {
    await load();

    expect(lastInit().next).toEqual({ revalidate: 300 });
    expect(lastInit().cache).toBeUndefined();
    expect(authorization(lastInit())).toBeNull();
  });

  it("keeps an explicit window, such as the sitemap's", async () => {
    await fetchForumCategories({ revalidate: 3600 });

    expect(lastInit().next).toEqual({ revalidate: 3600 });
  });
});

describe("directory list fetches", () => {
  it.each([
    ["doctors", () => fetchDoctors({ specialty: "kardiologija", page: 2 })],
    ["doctors by rating", () => fetchDoctors({ sort: "rating" })],
    ["doctors by language", () => fetchDoctors({ language: "angliski" })],
    ["facilities", () => fetchFacilities({ department: "urgenten" })],
    [
      "emergency hospitals",
      () => fetchFacilities({ type: "hospital", has_emergency: true }),
    ],
    ["pharmacies", () => fetchPharmacies({ page: 3 })],
    ["products", () => fetchProducts({ page: 2 })],
  ])("caches %s without free text for a minute", async (_name, load) => {
    await load();

    expect(lastInit().next).toEqual({ revalidate: 60 });
    expect(authorization(lastInit())).toBeNull();
    expect(webTierRequestHeaders).toHaveBeenLastCalledWith({
      forwardVisitor: false,
    });
  });

  it.each([
    ["an unknown specialty", () => fetchDoctors({ specialty: "x-1" })],
    ["an unknown language", () => fetchDoctors({ language: "klingonski" })],
    ["a deep page", () => fetchDoctors({ page: 6 })],
    ["a huge page", () => fetchDoctors({ page: 1e9 })],
    [
      "an unknown sort",
      () => fetchDoctors({ sort: "price" as unknown as "name" }),
    ],
    ["an unknown department", () => fetchFacilities({ department: "nope" })],
    ["an unknown facility type", () => fetchFacilities({ type: "spa" })],
    ["a non-boolean flag", () => fetchFacilities({ has_emergency: "maybe" })],
    ["a pharmacy's products", () => fetchProducts({ pharmacy: "zegin" })],
  ])("sends %s per-visitor instead of caching it", async (_name, load) => {
    await load();

    expect(lastInit().cache).toBe("no-store");
    expect(lastInit().next).toBeUndefined();
    expect(webTierRequestHeaders).toHaveBeenLastCalledWith({
      forwardVisitor: true,
    });
  });

  it("does not cache a specialty when the taxonomy cannot be read", async () => {
    fetchMock.mockImplementationOnce(
      async () => new Response("", { status: 503 }),
    );

    await fetchDoctors({ specialty: "kardiologija" });

    expect(lastInit().cache).toBe("no-store");
  });

  it.each([
    ["doctors by name", () => fetchDoctors({ q: "ана" })],
    ["doctors by city", () => fetchDoctors({ city: "Скопје" })],
    ["facilities by name", () => fetchFacilities({ q: "медика" })],
    ["pharmacies by city", () => fetchPharmacies({ city: "skopje" })],
    ["products by name", () => fetchProducts({ q: "витамин" })],
    ["products by category", () => fetchProducts({ category: "Козметика" })],
  ])("does not cache %s", async (_name, load) => {
    await load();

    expect(lastInit().cache).toBe("no-store");
    expect(lastInit().next).toBeUndefined();
  });

  it("treats blank free text as absent", () => {
    expect(directoryCache({ q: "  ", city: "" })).toEqual({ revalidate: 60 });
    expect(directoryCache({ q: "x" })).toEqual({});
  });

  it.each([
    ["doctors", () => fetchDoctors({ q: " ", city: "\u3000" })],
    ["facilities", () => fetchFacilities({ q: "\t" })],
    ["pharmacies", () => fetchPharmacies({ city: "  " })],
    ["products", () => fetchProducts({ q: " " })],
  ])(
    "drops whitespace-only %s filters instead of minting a cache key per variant",
    async (_name, load) => {
      await load();

      const url = String(fetchMock.mock.calls.at(-1)?.[0]);
      expect(url).not.toMatch(/[?&](q|city)=/);
      expect(lastInit().next).toEqual({ revalidate: 60 });
    },
  );

  it("does not cache products by pharmacy", () => {
    expect(directoryCache({ pharmacy: "zegin" })).toEqual({});
  });
});

describe("token-bearing fetches", () => {
  it("are never cached, even for a public path", async () => {
    fetchMock.mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), { status: 200 }),
    );

    await apiGetServer("/specialties");

    expect(authorization(lastInit())).toBe("Bearer member-token");
    expect(lastInit().cache).toBe("no-store");
    expect(lastInit().next).toBeUndefined();
  });

  it.each([
    ["doctor profile", () => fetchDoctor("ana")],
    ["forum topic", () => fetchForumTopicPage("general", "zdravo")],
  ])("%s stays no-store", async (_name, load) => {
    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          data: { topic: {}, posts: [], related_topics: [] },
          meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
        }),
        { status: 200 },
      ),
    );

    await load().catch(() => undefined);

    expect(authorization(lastInit())).toBe("Bearer member-token");
    expect(lastInit().cache).toBe("no-store");
    expect(lastInit().next).toBeUndefined();
  });
});
