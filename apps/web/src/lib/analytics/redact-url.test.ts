import { describe, expect, it } from "vitest";
import {
  isManualPlausibleScript,
  redactPageUrl,
} from "@/lib/analytics/redact-url";

describe("redactPageUrl", () => {
  it("drops the search query a visitor typed", () => {
    expect(
      redactPageUrl(
        "https://zdravje.mk/search?q=%D0%B1%D0%BE%D0%BB%D0%BA%D0%B8",
      ),
    ).toBe("https://zdravje.mk/search");
  });

  it("drops filters and fragments", () => {
    expect(
      redactPageUrl(
        "https://zdravje.mk/doctors?city=Skopje&specialty=cardiology&page=2#reviews",
      ),
    ).toBe("https://zdravje.mk/doctors");
  });

  it("keeps the path itself", () => {
    expect(redactPageUrl("https://zdravje.mk/forum/opsto/tema-1")).toBe(
      "https://zdravje.mk/forum/opsto/tema-1",
    );
  });

  it("keeps only parameters that are explicitly allowed", () => {
    expect(
      redactPageUrl("https://zdravje.mk/doctors?page=2&q=pain&page=3", [
        "page",
      ]),
    ).toBe("https://zdravje.mk/doctors?page=2&page=3");
  });

  it("allows no parameters by default", () => {
    expect(redactPageUrl("https://zdravje.mk/?utm_source=x&ref=y")).toBe(
      "https://zdravje.mk/",
    );
  });

  it("reports nothing for an address it cannot parse", () => {
    expect(redactPageUrl("/search?q=pain")).toBe("");
  });
});

describe("isManualPlausibleScript", () => {
  it("accepts the manual builds", () => {
    expect(
      isManualPlausibleScript("https://plausible.io/js/script.manual.js"),
    ).toBe(true);
    expect(
      isManualPlausibleScript(
        "https://stats.example.com/js/script.manual.outbound-links.js",
      ),
    ).toBe(true);
  });

  it("refuses builds that report pageviews on their own", () => {
    expect(isManualPlausibleScript("https://plausible.io/js/script.js")).toBe(
      false,
    );
    expect(
      isManualPlausibleScript(
        "https://plausible.io/js/script.outbound-links.js",
      ),
    ).toBe(false);
    expect(
      isManualPlausibleScript("https://plausible.io/js/pa-abc123.js"),
    ).toBe(false);
    expect(isManualPlausibleScript("not a url")).toBe(false);
  });
});
