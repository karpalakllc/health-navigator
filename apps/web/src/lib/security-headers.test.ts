import { describe, expect, it } from "vitest";
import { HSTS_VALUE, securityHeaders } from "./security-headers";

const hsts = (siteUrl: string | undefined) =>
  securityHeaders(siteUrl).find(
    (header) => header.key === "Strict-Transport-Security",
  )?.value;

describe("securityHeaders", () => {
  it("sends HSTS for an https site: one year, subdomains, no preload", () => {
    expect(hsts("https://zdravje360.mk")).toBe(
      "max-age=31536000; includeSubDomains",
    );
    expect(HSTS_VALUE).not.toContain("preload");
  });

  it("omits HSTS for a plain-http or missing origin", () => {
    expect(hsts("http://127.0.0.1:3000")).toBeUndefined();
    expect(hsts(undefined)).toBeUndefined();
    expect(hsts("not a url")).toBeUndefined();
  });

  it("keeps the baseline headers and never sets a static CSP", () => {
    const keys = securityHeaders("https://zdravje360.mk").map((h) => h.key);

    expect(keys).toEqual(
      expect.arrayContaining([
        "X-Frame-Options",
        "X-Content-Type-Options",
        "Referrer-Policy",
        "Permissions-Policy",
      ]),
    );
    expect(keys).not.toContain("Content-Security-Policy");
  });
});
