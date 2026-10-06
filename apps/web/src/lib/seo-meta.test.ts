import { describe, expect, it } from "vitest";
import {
  forumTagMeta,
  forumTopicMeta,
  isIndexableTag,
  pageMetadata,
  plainExcerpt,
  profileMeta,
  siteVerification,
} from "@/lib/metadata";
import { latinSearchVariant, toLatin } from "@/lib/transliterate";

describe("transliteration", () => {
  it("spells Cyrillic the way people type it without diacritics", () => {
    expect(toLatin("Операција за проширени вени")).toBe(
      "operacija za prosireni veni",
    );
    expect(toLatin("ѓ ж ѕ ј љ њ ќ ц ч џ ш")).toBe("g z dz j lj nj k c c dz s");
  });

  it("offers a Latin variant only for Cyrillic titles, without punctuation", () => {
    expect(latinSearchVariant("Операција за проширени вени?")).toBe(
      "operacija za prosireni veni",
    );
    expect(latinSearchVariant("Laser za veni")).toBeNull();
    expect(latinSearchVariant("д-р Елена Димитрова")).toBe(
      "dr elena dimitrova",
    );
    expect(latinSearchVariant("бајпас-операција")).toBe("bajpas operacija");
  });
});

describe("forumTopicMeta", () => {
  const body =
    "Кој има искуство со операција на проширени вени во Скопје? Ме интересира болница, лекар, ризици и цена, и колку трае опоравувањето после интервенцијата.";

  it("adds the Latin spelling of a Cyrillic title once, in brackets", () => {
    const { description } = forumTopicMeta({
      title: "Операција за проширени вени",
      body,
    });

    expect(description.endsWith(" (operacija za prosireni veni)")).toBe(true);
    expect(description.match(/operacija za prosireni veni/g)).toHaveLength(1);
    expect(description.length).toBeLessThanOrEqual(160);
    expect(description.startsWith("Кој има искуство")).toBe(true);
  });

  it("adds nothing for a Latin title", () => {
    expect(
      forumTopicMeta({ title: "Laser za veni", body }).description,
    ).not.toContain("(");
  });

  it("appends the first keyword to the title only when the title lacks it", () => {
    const tags = [
      { name: "проширени вени", latin: "prosireni veni" },
      { name: "операција", latin: "operacija" },
    ];

    expect(
      forumTopicMeta({ title: "Операција за проширени вени", body, tags })
        .title,
    ).toBe("Операција за проширени вени");
    expect(
      forumTopicMeta({ title: "Искуство во Скопје", body, tags }).title,
    ).toBe("Искуство во Скопје – проширени вени");
  });
});

describe("profileMeta", () => {
  it("says what and where in the title and adds the Latin name", () => {
    const meta = profileMeta({
      name: "Елена Димитрова",
      kind: "Кардиологија",
      city: "Скопје",
      text: "Кардиолог со дваесет години искуство.",
      fallback: "Лекар",
    });

    expect(meta.title).toBe("Елена Димитрова – Кардиологија, Скопје");
    expect(meta.description).toBe(
      "Кардиологија, Скопје. Кардиолог со дваесет години искуство. (elena dimitrova)",
    );
  });

  it("falls back when there is nothing to say", () => {
    expect(profileMeta({ name: "Ana", fallback: "Лекар" })).toEqual({
      title: "Ana",
      description: "Лекар",
    });
  });
});

describe("tag pages", () => {
  it("are indexable from three topics", () => {
    expect(isIndexableTag(2)).toBe(false);
    expect(isIndexableTag(3)).toBe(true);
    expect(isIndexableTag(undefined)).toBe(false);
  });

  it("name the tag and its Latin spelling", () => {
    const meta = forumTagMeta({
      name: "проширени вени",
      latin: "prosireni veni",
    });

    expect(meta.title).toBe("Теми за „проширени вени“");
    expect(meta.description).toContain("(prosireni veni)");
  });

  it("thin tag pages are noindex but followed", () => {
    expect(
      pageMetadata("x", undefined, { noIndex: true, follow: true }).robots,
    ).toEqual({ index: false, follow: true });
  });
});

describe("plainExcerpt", () => {
  it("cuts at a word and marks the cut", () => {
    expect(plainExcerpt("едно  два\nтри четири", 12)).toBe("едно два…");
    expect(plainExcerpt("кратко", 12)).toBe("кратко");
  });
});

describe("siteVerification", () => {
  it("emits Google and Bing tokens from env, nothing when unset", () => {
    expect(siteVerification({})).toBeUndefined();
    expect(
      siteVerification({
        GOOGLE_SITE_VERIFICATION: " g-token ",
        BING_SITE_VERIFICATION: "b-token",
      }),
    ).toEqual({ google: "g-token", other: { "msvalidate.01": "b-token" } });
  });
});
