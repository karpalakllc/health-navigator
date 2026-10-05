import { describe, expect, it } from "vitest";
import { loginHref, safeRedirectTarget } from "@/lib/auth/login-href";

/**
 * These two functions are the app's only open-redirect guards — safeRedirectTarget
 * decides where a user lands straight after authenticating — and they had no test
 * coverage at all. The cases below are the ones an attacker would actually try.
 */
describe("safeRedirectTarget", () => {
  it("keeps a plain internal path", () => {
    expect(safeRedirectTarget("/account/reviews")).toBe("/account/reviews");
  });

  it("rejects protocol-relative URLs that would leave the site", () => {
    expect(safeRedirectTarget("//evil.example")).toBe("/");
    expect(safeRedirectTarget("//evil.example/path")).toBe("/");
  });

  it("rejects absolute URLs", () => {
    expect(safeRedirectTarget("https://evil.example")).toBe("/");
    expect(safeRedirectTarget("http://evil.example")).toBe("/");
  });

  it("rejects paths that do not start with a slash", () => {
    expect(safeRedirectTarget("evil.example")).toBe("/");
    expect(safeRedirectTarget("javascript:alert(1)")).toBe("/");
  });

  it("falls back for empty, whitespace and nullish input", () => {
    expect(safeRedirectTarget(null)).toBe("/");
    expect(safeRedirectTarget(undefined)).toBe("/");
    expect(safeRedirectTarget("")).toBe("/");
    expect(safeRedirectTarget("   ")).toBe("/");
  });

  it("honours a caller-supplied fallback", () => {
    expect(safeRedirectTarget(null, "/forum")).toBe("/forum");
    expect(safeRedirectTarget("//evil.example", "/forum")).toBe("/forum");
  });

  it("rejects backslash variants that browsers read as a second slash", () => {
    expect(safeRedirectTarget("/\\evil.example")).toBe("/");
    expect(safeRedirectTarget("/\\/evil.example")).toBe("/");
    expect(safeRedirectTarget("/\\\\evil.example")).toBe("/");
    expect(safeRedirectTarget("\\\\evil.example")).toBe("/");
  });

  it("rejects tabs and newlines that browsers strip before navigating", () => {
    expect(safeRedirectTarget("/\t/evil.example")).toBe("/");
    expect(safeRedirectTarget("/\n/evil.example")).toBe("/");
    expect(safeRedirectTarget("/\r\n/evil.example")).toBe("/");
    expect(safeRedirectTarget("/\u0000/evil.example")).toBe("/");
  });

  it("rejects paths that normalise to a protocol-relative URL", () => {
    expect(safeRedirectTarget("/.//evil.example")).toBe("/");
    expect(safeRedirectTarget("/a/..//evil.example")).toBe("/");
  });

  it("keeps percent-encoded slashes as a harmless internal path", () => {
    // Encoded, these are just odd path characters — not a host. Whatever comes
    // back must still resolve on our own origin.
    for (const candidate of ["/%5Cevil.example", "/%2F%2Fevil.example"]) {
      const target = safeRedirectTarget(candidate);
      expect(new URL(target, "https://site.test").origin).toBe(
        "https://site.test",
      );
      expect(target.startsWith("//")).toBe(false);
    }
  });

  it("rejects script and data URLs", () => {
    expect(safeRedirectTarget("javascript:alert(1)")).toBe("/");
    expect(safeRedirectTarget("JaVaScRiPt:alert(1)")).toBe("/");
    expect(safeRedirectTarget("data:text/html,<script>alert(1)</script>")).toBe(
      "/",
    );
  });

  it("keeps the query string and fragment of an internal path", () => {
    expect(safeRedirectTarget("/forum/a/b?page=2#post-3")).toBe(
      "/forum/a/b?page=2#post-3",
    );
  });

  it("trims surrounding whitespace before validating", () => {
    expect(safeRedirectTarget("  /doctors  ")).toBe("/doctors");
    expect(safeRedirectTarget("  //evil.example  ")).toBe("/");
  });
});

describe("loginHref", () => {
  it("returns the bare login path with no redirect", () => {
    expect(loginHref()).toBe("/login");
    expect(loginHref(null)).toBe("/login");
    expect(loginHref("")).toBe("/login");
  });

  it("encodes a safe internal redirect", () => {
    expect(loginHref("/account")).toBe("/login?redirect=%2Faccount");
    expect(loginHref("/forum/a/b?page=2")).toBe(
      "/login?redirect=%2Fforum%2Fa%2Fb%3Fpage%3D2",
    );
  });

  it("drops unsafe redirect targets rather than encoding them", () => {
    expect(loginHref("//evil.example")).toBe("/login");
    expect(loginHref("https://evil.example")).toBe("/login");
    expect(loginHref("evil.example")).toBe("/login");
  });

  it("drops backslash and control-character redirect targets", () => {
    expect(loginHref("/\\evil.example")).toBe("/login");
    expect(loginHref("/\\/evil.example")).toBe("/login");
    expect(loginHref("/\t/evil.example")).toBe("/login");
    expect(loginHref("/\n/evil.example")).toBe("/login");
    expect(loginHref("javascript:alert(1)")).toBe("/login");
    expect(loginHref("data:text/html,x")).toBe("/login");
  });

  it("does not build a login loop", () => {
    expect(loginHref("/login")).toBe("/login");
    expect(loginHref("/login?redirect=%2F")).toBe("/login");
  });
});
