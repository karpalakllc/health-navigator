import { describe, expect, it } from "vitest";
import {
  isManualPlausibleScript,
  isSafeCampaignValue,
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

  it("keeps the four campaign tags by default, and nothing else", () => {
    expect(
      redactPageUrl(
        "https://zdravje.mk/forum?q=boli&utm_source=viber&utm_medium=social&utm_campaign=esen-2026&utm_content=kopce_1&ref=y&fbclid=abc",
      ),
    ).toBe(
      "https://zdravje.mk/forum?utm_source=viber&utm_medium=social&utm_campaign=esen-2026&utm_content=kopce_1",
    );
  });

  it("leaves out utm_term, which is meant for search keywords", () => {
    expect(
      redactPageUrl(
        "https://zdravje.mk/?utm_source=google&utm_term=%D0%B3%D0%BB%D0%B0%D0%B2%D0%BE%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0",
      ),
    ).toBe("https://zdravje.mk/?utm_source=google");
  });

  it("drops a campaign tag that is not a short slug", () => {
    expect(
      redactPageUrl(
        "https://zdravje.mk/?utm_source=ana%40mail.mk&utm_medium=email&utm_campaign=me+boli+glava&utm_content=070123456",
      ),
    ).toBe("https://zdravje.mk/?utm_medium=email");
  });

  it("reports nothing for an address it cannot parse", () => {
    expect(redactPageUrl("/search?q=pain")).toBe("");
  });
});

describe("isSafeCampaignValue", () => {
  it("accepts short slugs in either script", () => {
    for (const value of [
      "viber",
      "Newsletter",
      "esen-2026",
      "a_b.c",
      "есен",
      "v2-10",
    ]) {
      expect(isSafeCampaignValue(value)).toBe(true);
    }
  });

  it("refuses free text, addresses, numbers and long values", () => {
    for (const value of [
      "",
      "me boli glava",
      "ana@mail.mk",
      "+38970123456",
      "070123456",
      "070-123-456",
      "070.123.456",
      "07_01_23_45",
      "id-12-34-56",
      "https://x.mk/a",
      "a".repeat(65),
    ]) {
      expect(isSafeCampaignValue(value)).toBe(false);
    }
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
