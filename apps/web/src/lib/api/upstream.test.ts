import { describe, expect, it } from "vitest";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

describe("readUpstream", () => {
  it("hands back the API's status and JSON body", async () => {
    const result = await readUpstream(
      new Response(JSON.stringify({ message: "Неточна лозинка." }), {
        status: 422,
      }),
    );

    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.response.status).toBe(422);
      expect(result.payload).toEqual({ message: "Неточна лозинка." });
    }
  });

  it.each([
    ["an HTML error page", new Response("<html>502</html>", { status: 502 })],
    ["an empty body", new Response(null, { status: 504 })],
    ["truncated JSON", new Response('{"data":', { status: 200 })],
  ])("answers %s with a localized 502", async (_, upstream) => {
    const result = await readUpstream(upstream);

    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.error.status).toBe(502);
      expect(await result.error.json()).toEqual({
        message: t("errors.upstreamUnavailable"),
      });
    }
  });

  it("answers a refused connection with a localized 502", async () => {
    const result = await readUpstream(
      Promise.reject(new TypeError("fetch failed")),
    );

    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.error.status).toBe(502);
    }
  });
});
