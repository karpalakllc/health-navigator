import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { isSlug, pathSegment } from "@/lib/api/path";
import { ApiRequestError as ServerApiRequestError } from "@/lib/api/server";
import { fetchProduct } from "@/lib/api/products";
import { fetchForumTopics } from "@/lib/api/forum";

/**
 * Route params arrive decoded, so `/doctors/..%2Fme` handed the fetchers `../me`.
 * Interpolated raw, that became `/api/v1/doctors/../me` and the server fetched
 * `/api/v1/me` with the visitor's own token.
 */
describe("pathSegment", () => {
  it("leaves an ordinary slug alone", () => {
    expect(pathSegment("dr-ana-petrovska")).toBe("dr-ana-petrovska");
    expect(pathSegment(42)).toBe("42");
  });

  it("encodes separators so a value stays one segment", () => {
    expect(pathSegment("../me")).toBe("..%2Fme");
    expect(pathSegment("a/b")).toBe("a%2Fb");
    expect(pathSegment("a?b#c")).toBe("a%3Fb%23c");
    expect(pathSegment("%2e%2e")).toBe("%252e%252e");
  });

  it("refuses dot segments, which no encoding protects", () => {
    expect(() => pathSegment(".")).toThrow();
    expect(() => pathSegment("..")).toThrow();
    expect(() => pathSegment("")).toThrow();
  });

  it("refuses them as a 404 the detail pages turn into notFound()", () => {
    for (const value of [".", "..", ""]) {
      try {
        pathSegment(value);
        expect.unreachable();
      } catch (error) {
        // server.ts re-exports this class; the pages check against it.
        expect(error).toBeInstanceOf(ServerApiRequestError);
        expect((error as ServerApiRequestError).status).toBe(404);
      }
    }
  });

  it("keeps the resolved URL inside the resource it names", () => {
    const url = new URL(
      `https://api.test/api/v1/doctors/${pathSegment("../me")}`,
    );
    expect(url.pathname).toBe("/api/v1/doctors/..%2Fme");
  });
});

describe("isSlug", () => {
  it("accepts slugs the API mints and Cyrillic ones an admin might type", () => {
    expect(isSlug("opsta-bolnica-1")).toBe(true);
    expect(isSlug("кардиологија")).toBe(true);
    expect(isSlug("under_score")).toBe(true);
  });

  it("rejects anything that is not a single plain segment", () => {
    for (const value of [
      "",
      ".",
      "..",
      "../me",
      "a/b",
      "a%2Fb",
      "a b",
      "a?x=1",
      "x".repeat(256),
      undefined,
      null,
      42,
      ["a"],
    ]) {
      expect(isSlug(value)).toBe(false);
    }
  });
});

describe("API fetchers", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
    fetchMock.mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), { status: 200 }),
    );
    vi.stubGlobal("fetch", fetchMock);
  });

  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    fetchMock.mockReset();
  });

  it("does not let a slug climb out of its resource", async () => {
    await fetchProduct("../me");
    const requested = new URL(String(fetchMock.mock.calls[0][0]));
    expect(requested.pathname).toBe("/api/v1/products/..%2Fme");
  });

  it("encodes a forum category before the query string is appended", async () => {
    await fetchForumTopics("../../me?x=", { page: 2 });
    const requested = new URL(String(fetchMock.mock.calls[0][0]));
    expect(requested.pathname).toBe(
      "/api/v1/forum/categories/..%2F..%2Fme%3Fx%3D/topics",
    );
    expect(requested.search).toBe("?page=2");
  });
});
