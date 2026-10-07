import { describe, expect, it } from "vitest";
import {
  MAX_ENTRIES,
  REVIEW_PROMPT_KEY,
  RETENTION_DAYS,
  canShowReviewPrompt,
  markReviewPromptShown,
  profileHash,
} from "@/lib/review-prompt-storage";

function memoryStorage(initial: Record<string, string> = {}) {
  const data = new Map(Object.entries(initial));

  return {
    data,
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => {
      data.set(key, value);
    },
    removeItem: (key: string) => {
      data.delete(key);
    },
  };
}

const DAY = 24 * 60 * 60 * 1000;
const NOW = Date.UTC(2026, 9, 7);

describe("review prompt storage", () => {
  it("shows once per profile, then remembers it", () => {
    const storage = memoryStorage();

    expect(canShowReviewPrompt("doctor", "ana", storage, NOW)).toBe(true);
    markReviewPromptShown("doctor", "ana", storage, NOW);

    expect(canShowReviewPrompt("doctor", "ana", storage, NOW)).toBe(false);
    expect(canShowReviewPrompt("facility", "ana", storage, NOW)).toBe(true);
  });

  it("stores a short hash and a day, never the profile's slug or name", () => {
    const storage = memoryStorage();

    // Checking writes nothing that stays.
    canShowReviewPrompt("doctor", "d-r-ana-petrovska", storage, NOW);
    expect([...storage.data.keys()]).toEqual([]);

    markReviewPromptShown("doctor", "d-r-ana-petrovska", storage, NOW);

    const raw = storage.data.get(REVIEW_PROMPT_KEY) ?? "";
    expect(raw).not.toContain("ana");
    expect(JSON.parse(raw)).toEqual({
      [profileHash("doctor", "d-r-ana-petrovska")]: Math.floor(NOW / DAY),
    });
  });

  it("forgets entries after the retention window", () => {
    const storage = memoryStorage();

    markReviewPromptShown("doctor", "ana", storage, NOW);

    expect(
      canShowReviewPrompt(
        "doctor",
        "ana",
        storage,
        NOW + (RETENTION_DAYS + 1) * DAY,
      ),
    ).toBe(true);
  });

  it("keeps at most the newest entries", () => {
    const storage = memoryStorage();

    for (let index = 0; index <= MAX_ENTRIES; index++) {
      markReviewPromptShown(
        "doctor",
        `d-${index}`,
        storage,
        NOW + index * 1000,
      );
    }

    const kept = JSON.parse(storage.data.get(REVIEW_PROMPT_KEY) ?? "{}");
    expect(Object.keys(kept)).toHaveLength(MAX_ENTRIES);
  });

  it("never shows when storage is missing, broken or full", () => {
    expect(canShowReviewPrompt("doctor", "ana", null, NOW)).toBe(false);

    const full = {
      getItem: () => null,
      setItem: () => {
        throw new Error("QuotaExceededError");
      },
      removeItem: () => {},
    };
    expect(canShowReviewPrompt("doctor", "ana", full, NOW)).toBe(false);

    const garbage = memoryStorage({ [REVIEW_PROMPT_KEY]: "{not json" });
    expect(canShowReviewPrompt("doctor", "ana", garbage, NOW)).toBe(true);
  });

  it("ignores tampered values", () => {
    const storage = memoryStorage({
      [REVIEW_PROMPT_KEY]: JSON.stringify({
        [profileHash("doctor", "ana")]: "yesterday",
        "<script>": 1,
      }),
    });

    expect(canShowReviewPrompt("doctor", "ana", storage, NOW)).toBe(true);
  });
});
