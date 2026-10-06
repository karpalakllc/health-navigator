import { beforeEach, describe, expect, it, vi } from "vitest";
import type { PaginatedEnvelope, PublicReview } from "@/lib/api/types";

const fetchDoctorReviews = vi.fn();

vi.mock("@/lib/auth/session", () => ({
  getSessionToken: async () => null,
}));

vi.mock("@/lib/api/reviews", () => ({
  fetchDoctorReviews: (...args: unknown[]) => fetchDoctorReviews(...args),
  fetchFacilityReviews: vi.fn(),
  fetchPharmacyReviews: vi.fn(),
}));

const { ReviewSection } = await import("@/components/reviews/review-section");

function envelope(
  total: number,
  data: Partial<PublicReview>[] = [],
): PaginatedEnvelope<PublicReview> {
  return {
    data: data as PublicReview[],
    meta: { current_page: 1, last_page: 1, per_page: 15, total },
  } as PaginatedEnvelope<PublicReview>;
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe("ReviewSection", () => {
  beforeEach(() => {
    fetchDoctorReviews.mockReset();
  });

  it("asks for the rating totals alongside the review list, not after it", async () => {
    let resolveList: (value: PaginatedEnvelope<PublicReview>) => void = () =>
      undefined;
    fetchDoctorReviews.mockImplementation(
      (_slug: string, params: { rating?: number }) =>
        params.rating
          ? Promise.resolve(envelope(params.rating))
          : new Promise((resolve) => {
              resolveList = resolve;
            }),
    );

    const pending = ReviewSection({
      kind: "doctor",
      slug: "ana",
      summary: { count: 40, average_rating: 4.2 },
    });
    await flush();

    // The list is still loading, yet all five rating requests are out.
    expect(fetchDoctorReviews).toHaveBeenCalledTimes(6);
    expect(
      fetchDoctorReviews.mock.calls.map(([, params]) => params.rating),
    ).toEqual([undefined, 5, 4, 3, 2, 1]);

    resolveList(envelope(40));
    await expect(pending).resolves.toBeTruthy();
  });

  it("counts the first page itself when it holds every review", async () => {
    fetchDoctorReviews.mockResolvedValue(
      envelope(2, [{ rating: 5 }, { rating: 4 }]),
    );

    await ReviewSection({
      kind: "doctor",
      slug: "ana",
      summary: { count: 2, average_rating: 4.5 },
    });

    expect(fetchDoctorReviews).toHaveBeenCalledTimes(1);
  });
});
