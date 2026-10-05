import { describe, expect, it } from "vitest";
import { NextRequest } from "next/server";
import { GET } from "@/app/reset-password/route";
import {
  decodeResetCookie,
  encodeResetCookie,
  RESET_COOKIE,
  RESET_FORM_PATH,
} from "@/lib/auth/reset-token";

/**
 * /reset-password?token=…&email=… used to render the form, so Plausible (which
 * reports location.href) and Sentry saw the live token. It must now answer with
 * a bare redirect to a URL that carries nothing.
 */
describe("GET /reset-password", () => {
  const link =
    "https://zdravje.test/reset-password?token=secret-token&email=ana%40example.mk";

  it("redirects to the form at a URL without the token or address", async () => {
    const response = GET(new NextRequest(link));

    expect(response.status).toBe(303);
    expect(response.headers.get("location")).toBe(RESET_FORM_PATH);
    expect(response.headers.get("location")).not.toContain("secret-token");
    expect(await response.text()).toBe("");
  });

  it("hands the token over in an httpOnly cookie scoped to the form", () => {
    const response = GET(new NextRequest(link));
    const cookie = response.cookies.get(RESET_COOKIE);

    expect(decodeResetCookie(cookie?.value)).toEqual({
      token: "secret-token",
      email: "ana@example.mk",
    });
    expect(cookie?.httpOnly).toBe(true);
    expect(cookie?.path).toBe(RESET_FORM_PATH);
    expect(response.headers.get("cache-control")).toBe("no-store");
  });

  it("sets no cookie when the link is incomplete", () => {
    const response = GET(
      new NextRequest("https://zdravje.test/reset-password?token=only"),
    );

    expect(response.status).toBe(303);
    expect(response.cookies.get(RESET_COOKIE)).toBeUndefined();
  });
});

describe("decodeResetCookie", () => {
  it("round-trips", () => {
    const credentials = { token: "t", email: "a@b.mk" };
    expect(decodeResetCookie(encodeResetCookie(credentials))).toEqual(
      credentials,
    );
  });

  it("treats a missing, malformed or partial cookie as absent", () => {
    expect(decodeResetCookie(undefined)).toBeNull();
    expect(decodeResetCookie("{")).toBeNull();
    expect(decodeResetCookie('{"token":"t"}')).toBeNull();
    expect(decodeResetCookie('{"token":"","email":"a@b.mk"}')).toBeNull();
    expect(decodeResetCookie('{"token":1,"email":"a@b.mk"}')).toBeNull();
  });
});
