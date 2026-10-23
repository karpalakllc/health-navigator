// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  DRAFT_TTL_MS,
  SEARCH_ORIGIN_TTL_MS,
  SEARCH_PROMPT_INTERVAL_DAYS,
  isSharedReviewLanding,
  markSearchPromptShown,
  markSearchVisit,
  noteProfileFromSearch,
  readAnyReviewDraft,
  reviewShareUrl,
  saveReviewDraft,
  searchPromptCandidate,
  showStickyReviewCta,
  skipSearchPrompt,
} from "@/lib/review-growth";
import {
  publicSettingsDefaults,
  publicSettingsUnavailable,
} from "@/lib/api/public-settings";

const DAY = 24 * 60 * 60 * 1000;
const T0 = Date.UTC(2026, 9, 23, 10, 0, 0);
const always = () => true;

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe("reviewShareUrl", () => {
  it("is the profile URL with the fixed campaign tags and the review anchor — the same for everyone", () => {
    const url = reviewShareUrl("https://zdravje360.mk/doctors/ana-petrovska");

    expect(url).toBe(
      "https://zdravje360.mk/doctors/ana-petrovska?utm_source=share&utm_medium=referral&utm_campaign=napishi-recenzija#napishi-recenzija",
    );
    // No sender, token or counter of any kind.
    expect(new URL(url).searchParams.size).toBe(3);
    expect(reviewShareUrl("https://zdravje360.mk/doctors/ana-petrovska")).toBe(
      url,
    );
  });

  it("recognises a landing from it by its campaign tag", () => {
    expect(
      isSharedReviewLanding("?utm_source=share&utm_campaign=napishi-recenzija"),
    ).toBe(true);
    expect(isSharedReviewLanding("?utm_campaign=lansiranje")).toBe(false);
    expect(isSharedReviewLanding("")).toBe(false);
  });
});

describe("search prompt frequency caps", () => {
  function cameBackFrom(slug: string, at = T0) {
    markSearchVisit(at - 60_000);
    noteProfileFromSearch(slug, `д-р ${slug}`, at - 30_000);
  }

  it("asks about the doctor opened from the results", () => {
    cameBackFrom("ana");

    expect(searchPromptCandidate(always, T0)).toEqual({
      slug: "ana",
      name: "д-р ana",
    });
  });

  it("does not ask about a profile that was not opened from the results", () => {
    noteProfileFromSearch("ana", "д-р Ана", T0);

    expect(searchPromptCandidate(always, T0)).toBeNull();
  });

  it("forgets a search older than half an hour", () => {
    markSearchVisit(T0 - SEARCH_ORIGIN_TTL_MS - 1);
    noteProfileFromSearch("ana", "д-р Ана", T0);

    expect(searchPromptCandidate(always, T0)).toBeNull();
  });

  it("asks at most once per session", () => {
    cameBackFrom("ana");
    markSearchPromptShown(T0);

    // A different doctor later in the same session: still quiet, even past the week.
    localStorage.clear();
    cameBackFrom("boris", T0 + 8 * DAY);
    expect(searchPromptCandidate(always, T0 + 8 * DAY)).toBeNull();
  });

  it("asks at most once a week per device", () => {
    cameBackFrom("ana");
    markSearchPromptShown(T0);
    sessionStorage.clear(); // a new session

    cameBackFrom("boris", T0 + 2 * DAY);
    expect(searchPromptCandidate(always, T0 + 2 * DAY)).toBeNull();

    const later = T0 + SEARCH_PROMPT_INTERVAL_DAYS * DAY + 1;
    cameBackFrom("boris", later);
    expect(searchPromptCandidate(always, later)?.slug).toBe("boris");
  });

  it("never asks again about a doctor after „Не сега“ or a review", () => {
    skipSearchPrompt("doctor", "ana");
    cameBackFrom("ana");

    expect(searchPromptCandidate(always, T0)).toBeNull();
  });

  it("stays quiet when the profile's own prompt already asked", () => {
    cameBackFrom("ana");

    expect(searchPromptCandidate(() => false, T0)).toBeNull();
  });

  it("stays quiet when storage is unavailable", () => {
    cameBackFrom("ana");
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new DOMException("QuotaExceededError");
    });

    expect(searchPromptCandidate(always, T0)).toBeNull();
  });
});

describe("review draft", () => {
  const draft = {
    kind: "doctor" as const,
    slug: "ana",
    name: "д-р Ана",
    path: "/doctors/ana",
    rating: 5,
    body: "Одлична.",
    aspects: { communication: 5, bogus: 9 } as Record<string, number>,
  };

  it("lasts a day and drops anything malformed", () => {
    saveReviewDraft(draft, T0);

    expect(readAnyReviewDraft(T0 + 1000)).toMatchObject({
      rating: 5,
      aspects: { communication: 5 },
    });
    expect(readAnyReviewDraft(T0 + DRAFT_TTL_MS + 1)).toBeNull();
    // Expired drafts are deleted, not left behind.
    expect(localStorage.length).toBe(0);
  });

  it("never returns an off-site path", () => {
    saveReviewDraft({ ...draft, path: "//evil.example" }, T0);

    expect(readAnyReviewDraft(T0)).toBeNull();
  });
});

describe("sticky „Напишете рецензија“ visibility", () => {
  const visitor = {
    canWrite: true,
    ownProfile: false,
    mustChooseUsername: false,
  };

  it("shows only for a profile without a published review, to someone who can write one", () => {
    expect(
      showStickyReviewCta({
        enabled: true,
        publishedReviews: 0,
        viewer: visitor,
      }),
    ).toBe(true);
    expect(
      showStickyReviewCta({
        enabled: true,
        publishedReviews: 1,
        viewer: visitor,
      }),
    ).toBe(false);
    expect(
      showStickyReviewCta({
        enabled: false,
        publishedReviews: 0,
        viewer: visitor,
      }),
    ).toBe(false);
    expect(
      showStickyReviewCta({
        enabled: true,
        publishedReviews: 0,
        viewer: { ...visitor, canWrite: false },
      }),
    ).toBe(false);
    expect(
      showStickyReviewCta({
        enabled: true,
        publishedReviews: 0,
        viewer: { ...visitor, ownProfile: true },
      }),
    ).toBe(false);
    expect(
      showStickyReviewCta({
        enabled: true,
        publishedReviews: 0,
        viewer: { ...visitor, mustChooseUsername: true },
      }),
    ).toBe(false);
    expect(
      showStickyReviewCta({ enabled: true, publishedReviews: 0, viewer: null }),
    ).toBe(false);
  });
});

describe("settings", () => {
  it("default the invitations on, and off when the settings cannot be read", () => {
    for (const flag of [
      "reviews_prompt_on_profile",
      "reviews_prompt_after_search",
      "reviews_share_link",
      "reviews_mobile_sticky_cta",
    ] as const) {
      expect(publicSettingsDefaults[flag]).toBe(true);
      expect(publicSettingsUnavailable[flag]).toBe(false);
    }
  });
});
