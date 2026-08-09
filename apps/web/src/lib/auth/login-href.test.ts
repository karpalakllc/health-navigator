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
    expect(loginHref("/forum/a/b?page=2")).toBe("/login?redirect=%2Fforum%2Fa%2Fb%3Fpage%3D2");
  });

  it("drops unsafe redirect targets rather than encoding them", () => {
    expect(loginHref("//evil.example")).toBe("/login");
    expect(loginHref("https://evil.example")).toBe("/login");
    expect(loginHref("evil.example")).toBe("/login");
  });

  it("does not build a login loop", () => {
    expect(loginHref("/login")).toBe("/login");
    expect(loginHref("/login?redirect=%2F")).toBe("/login");
  });
});
