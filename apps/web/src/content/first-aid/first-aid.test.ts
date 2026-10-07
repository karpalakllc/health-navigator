import { describe, expect, it } from "vitest";
import {
  FIRST_AID_ANCHORS,
  FIRST_AID_GROUPS,
  FIRST_AID_GUIDES,
  FIRST_AID_SLUGS,
  FIRST_AID_TOPICS,
  firstAidHref,
  firstAidLinksForTopic,
  firstAidPath,
  getFirstAidGuide,
  hasPublishedFirstAidGuides,
  isFirstAidGuidePublic,
  publishedFirstAidGuides,
} from "@/content/first-aid";
import type { FirstAidGuide } from "@/content/first-aid/types";

const ASCII_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const NON_MACEDONIAN = /[йщъыьэюяёЙЩЪЫЬЭЮЯЁ]/;

function allText(guide: FirstAidGuide): string {
  return [
    guide.title,
    guide.summary,
    guide.callNote ?? "",
    guide.untilHelp ?? "",
    ...guide.recognise,
    ...guide.dont,
    ...guide.escalate,
    ...guide.sections.flatMap((section) => [
      section.title,
      section.intro ?? "",
      ...section.steps.flatMap((step) => [step.text, step.detail ?? ""]),
    ]),
  ].join("\n");
}

describe("first-aid registry", () => {
  it("has exactly the contracted slugs, each unique and plain ASCII", () => {
    const slugs = FIRST_AID_GUIDES.map((guide) => guide.slug);

    expect(slugs).toEqual([...FIRST_AID_SLUGS]);
    expect(new Set(slugs).size).toBe(slugs.length);
    for (const slug of slugs) {
      expect(slug).toMatch(ASCII_SLUG);
    }
  });

  it("puts every guide in a known group", () => {
    const groups = new Set(FIRST_AID_GROUPS.map((group) => group.id));

    for (const guide of FIRST_AID_GUIDES) {
      expect(groups.has(guide.group), guide.slug).toBe(true);
    }
  });

  it("ships every guide as an unpublished draft awaiting clinician review", () => {
    for (const guide of FIRST_AID_GUIDES) {
      expect(guide.published, guide.slug).toBe(false);
      expect(guide.review.status, guide.slug).toBe("draft");
    }
    expect(publishedFirstAidGuides()).toEqual([]);
    expect(hasPublishedFirstAidGuides()).toBe(false);
  });

  it("gives every guide signs, steps, don'ts, escalation and dated sources", () => {
    for (const guide of FIRST_AID_GUIDES) {
      expect(guide.recognise.length, guide.slug).toBeGreaterThan(0);
      expect(guide.sections.length, guide.slug).toBeGreaterThan(0);
      for (const section of guide.sections) {
        expect(
          section.steps.length,
          `${guide.slug}#${section.id}`,
        ).toBeGreaterThan(0);
      }
      expect(guide.dont.length, guide.slug).toBeGreaterThan(0);
      expect(guide.escalate.length, guide.slug).toBeGreaterThan(0);
      expect(guide.sources.length, guide.slug).toBeGreaterThan(0);
      for (const source of guide.sources) {
        expect(source.url).toMatch(/^https:\/\//);
        expect(source.accessed).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      }
      expect(guide.summary.length, guide.slug).toBeLessThanOrEqual(160);
    }
  });

  it("keeps step-section anchors ASCII, unique and clear of the page anchors", () => {
    const pageAnchors = new Set<string>(Object.values(FIRST_AID_ANCHORS));

    for (const guide of FIRST_AID_GUIDES) {
      const ids = guide.sections.map((section) => section.id);
      expect(new Set(ids).size, guide.slug).toBe(ids.length);
      for (const id of ids) {
        expect(id).toMatch(ASCII_SLUG);
        expect(pageAnchors.has(id), `${guide.slug}#${id}`).toBe(false);
      }
    }
  });

  it("links related guides and topics only to guides that exist", () => {
    for (const guide of FIRST_AID_GUIDES) {
      for (const slug of guide.related ?? []) {
        expect(getFirstAidGuide(slug), `${guide.slug} → ${slug}`).toBeDefined();
        expect(slug).not.toBe(guide.slug);
      }
    }
    for (const slugs of Object.values(FIRST_AID_TOPICS)) {
      for (const slug of slugs) {
        expect(getFirstAidGuide(slug), slug).toBeDefined();
      }
    }
  });

  it("uses only Macedonian Cyrillic and gives no medicine doses", () => {
    for (const guide of FIRST_AID_GUIDES) {
      const text = allText(guide);
      expect(text, guide.slug).not.toMatch(NON_MACEDONIAN);
      // Doses are the dispatcher's or the prescriber's call, never ours.
      expect(text, guide.slug).not.toMatch(/\d\s*(mg|мг|ml|мл)\b/i);
    }
  });
});

describe("first-aid link contract", () => {
  it("never hands out a public link to a draft", () => {
    for (const slug of FIRST_AID_SLUGS) {
      expect(firstAidHref(slug)).toBeNull();
      expect(firstAidHref(slug, FIRST_AID_ANCHORS.steps)).toBeNull();
    }
    for (const topic of Object.keys(FIRST_AID_TOPICS)) {
      expect(
        firstAidLinksForTopic(topic as keyof typeof FIRST_AID_TOPICS),
      ).toEqual([]);
    }
  });

  it("builds stable paths with optional anchors", () => {
    expect(firstAidPath("kpr-vozrasni")).toBe("/prva-pomos/kpr-vozrasni");
    expect(firstAidPath("kpr-vozrasni", FIRST_AID_ANCHORS.steps)).toBe(
      "/prva-pomos/kpr-vozrasni#pomos",
    );
  });

  it("needs both the published flag and a clinician review to go public", () => {
    const draft = getFirstAidGuide("truenje")!;

    expect(isFirstAidGuidePublic({ ...draft, published: true })).toBe(false);
    expect(
      isFirstAidGuidePublic({ ...draft, review: { status: "reviewed" } }),
    ).toBe(false);
    expect(
      isFirstAidGuidePublic({
        ...draft,
        published: true,
        review: { status: "reviewed", reviewedOn: "2026-11-01" },
      }),
    ).toBe(true);
  });

  it("keeps the page anchors the contract documents", () => {
    expect(FIRST_AID_ANCHORS).toEqual({
      call: "povikajte-194",
      recognise: "prepoznavanje",
      steps: "pomos",
      dont: "ne-pravete",
      escalate: "koga-194",
      sources: "izvori",
    });
  });
});
