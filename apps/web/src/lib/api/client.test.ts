import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiGet, registerServerHeaders } from "@/lib/api/client";

/**
 * Server-rendered GETs used to reach the API with no visitor identity, so all
 * anonymous SSR shared one bucket per web server: one busy client emptied it
 * for everybody.
 */
const fetchMock = vi.fn();

beforeEach(() => {
  process.env.NEXT_PUBLIC_API_URL = "http://api.test";
  fetchMock.mockResolvedValue(
    new Response(JSON.stringify({ data: { ok: true } }), { status: 200 }),
  );
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function sentHeaders(): Record<string, string> {
  return fetchMock.mock.calls[0][1].headers as Record<string, string>;
}

describe("apiGet on the server", () => {
  it("identifies the visitor with the web tier's headers", async () => {
    const provider = vi.fn().mockResolvedValue({
      "X-Web-Tier-Auth": "secret",
      "X-Client-IP": "203.0.113.7",
    });
    registerServerHeaders(provider);

    await apiGet("/settings/public");

    expect(provider).toHaveBeenCalledWith({ forwardVisitor: true });
    expect(sentHeaders()).toMatchObject({
      "Accept-Language": "mk",
      "X-Web-Tier-Auth": "secret",
      "X-Client-IP": "203.0.113.7",
    });
  });

  it("does not forward a visitor on a cached fetch shared by everyone", async () => {
    const provider = vi.fn().mockResolvedValue({ "X-Web-Tier-Auth": "s" });
    registerServerHeaders(provider);

    await apiGet("/settings/public", { revalidate: 3600 });

    expect(provider).toHaveBeenCalledWith({ forwardVisitor: false });
  });
});
