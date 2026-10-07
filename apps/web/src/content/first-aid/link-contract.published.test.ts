import { describe, expect, it, vi } from "vitest";

// One guide signed off and published, as it will be after clinician review.
vi.mock("@/content/first-aid/guides/environment", async (importOriginal) => {
  const actual =
    await importOriginal<
      typeof import("@/content/first-aid/guides/environment")
    >();
  return {
    ...actual,
    poisoning: {
      ...actual.poisoning,
      published: true,
      review: { status: "reviewed", reviewedOn: "2026-11-01" },
    },
  };
});

import {
  firstAidHref,
  firstAidLinksForTopic,
  hasPublishedFirstAidGuides,
  publishedFirstAidGuides,
} from "@/content/first-aid";

describe("first-aid link contract once a guide is published", () => {
  it("links the published guide, with the steps anchor by default", () => {
    expect(firstAidHref("truenje")).toBe("/prva-pomos/truenje");
    expect(firstAidLinksForTopic("poisoning")).toEqual([
      {
        slug: "truenje",
        title: "Труење",
        href: "/prva-pomos/truenje#pomos",
      },
    ]);
  });

  it("still withholds every other (draft) guide", () => {
    expect(firstAidHref("kpr-vozrasni")).toBeNull();
    expect(firstAidLinksForTopic("not-breathing")).toEqual([]);
    expect(publishedFirstAidGuides().map((guide) => guide.slug)).toEqual([
      "truenje",
    ]);
    expect(hasPublishedFirstAidGuides()).toBe(true);
  });
});
