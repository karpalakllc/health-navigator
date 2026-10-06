import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const webTierRequestHeaders = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api/client-ip", () => ({ webTierRequestHeaders }));

import { fetchHomeHighlights } from "@/lib/api/home";

const fetchMock = vi.fn();

beforeEach(() => {
  process.env.NEXT_PUBLIC_API_URL = "http://api.test";
  webTierRequestHeaders.mockResolvedValue({ "X-Web-Tier-Auth": "s" });
  fetchMock.mockResolvedValue(
    Response.json({
      data: { specialties: [], cities: [], recent_reviews: [] },
    }),
  );
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
  webTierRequestHeaders.mockReset();
});

describe("fetchHomeHighlights", () => {
  it("is shared for a minute, like the directory lists, without visitor data", async () => {
    await fetchHomeHighlights();

    const [url, init] = fetchMock.mock.calls.at(-1)!;
    expect(url).toBe("http://api.test/api/v1/home/highlights");
    expect(init.next).toEqual({ revalidate: 60 });
    expect(new Headers(init.headers).get("Authorization")).toBeNull();
    expect(webTierRequestHeaders).toHaveBeenLastCalledWith({
      forwardVisitor: false,
    });
  });
});
