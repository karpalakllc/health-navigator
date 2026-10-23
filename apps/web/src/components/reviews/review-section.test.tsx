import { render, screen, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { PaginatedEnvelope, PublicReview } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

const fetchDoctorReviews = vi.fn();

vi.mock("@/lib/auth/session", () => ({
  getSessionToken: async () => null,
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), refresh: vi.fn() }),
}));

vi.mock("@/lib/api/reviews", () => ({
  fetchDoctorReviews: (...args: unknown[]) => fetchDoctorReviews(...args),
  fetchFacilityReviews: vi.fn(),
  fetchPharmacyReviews: vi.fn(),
}));

const settings = {
  ...(await import("@/lib/api/public-settings")).publicSettingsDefaults,
};

vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => settings,
}));

const { ReviewSection, ratingDistribution } =
  await import("@/components/reviews/review-section");

function envelope(
  total: number,
  data: Partial<PublicReview>[] = [],
  ratingCounts?: PaginatedEnvelope<PublicReview>["meta"]["rating_counts"],
): PaginatedEnvelope<PublicReview> {
  return {
    data: data as PublicReview[],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total,
      rating_counts: ratingCounts,
    },
  };
}

describe("ReviewSection", () => {
  beforeEach(() => {
    fetchDoctorReviews.mockReset();
  });

  it("makes one request and draws the histogram from meta.rating_counts", async () => {
    fetchDoctorReviews.mockResolvedValue(
      envelope(40, [], { "1": 2, "2": 0, "3": 3, "4": 10, "5": 25 }),
    );

    render(
      await ReviewSection({
        kind: "doctor",
        slug: "ana",
        summary: { count: 40, average_rating: 4.4 },
        searchParams: { review_rating: "5" },
      }),
    );

    // No more five `?rating=N&per_page=1` look-ups.
    expect(fetchDoctorReviews).toHaveBeenCalledTimes(1);
    expect(fetchDoctorReviews.mock.calls[0][1]).toMatchObject({ rating: 5 });

    const bars = screen.getByRole("list", { name: t("reviews.distribution") });
    expect(
      within(bars).getByText(
        tFormat("reviews.distributionRow", { stars: 5, count: 25 }),
      ),
    ).toBeInTheDocument();
    expect(
      within(bars).getByText(
        tFormat("reviews.distributionRowOne", { stars: 1, count: 2 }),
      ),
    ).toBeInTheDocument();
  });

  it("leaves the bars out when the API sends no counts", async () => {
    fetchDoctorReviews.mockResolvedValue(envelope(3, []));

    render(
      await ReviewSection({
        kind: "doctor",
        slug: "ana",
        summary: { count: 3, average_rating: 4 },
      }),
    );

    expect(fetchDoctorReviews).toHaveBeenCalledTimes(1);
    expect(
      screen.queryByRole("list", { name: t("reviews.distribution") }),
    ).not.toBeInTheDocument();
  });
});

describe("ReviewSection — review invitations", () => {
  beforeEach(() => {
    fetchDoctorReviews.mockReset();
    fetchDoctorReviews.mockResolvedValue(envelope(0, []));
    settings.reviews_share_link = true;
  });

  async function renderDoctor() {
    render(
      await ReviewSection({
        kind: "doctor",
        slug: "ana",
        profileName: "д-р Ана",
        summary: { count: 0, average_rating: null },
      }),
    );
  }

  it("offers „Препорачајте рецензија“ on a doctor profile while the switch is on", async () => {
    await renderDoctor();

    expect(
      screen.getByRole("button", { name: t("reviewGrowth.shareButton") }),
    ).toBeInTheDocument();
    // The form is there for a signed-out visitor too.
    expect(
      screen.getByRole("button", { name: t("reviewGrowth.guestSubmit") }),
    ).toBeInTheDocument();
  });

  it("leaves it out entirely when switched off (server-rendered, no flash)", async () => {
    settings.reviews_share_link = false;
    await renderDoctor();

    expect(
      screen.queryByRole("button", { name: t("reviewGrowth.shareButton") }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByText(
        tFormat("reviewGrowth.shareLead", { name: "д-р Ана" }),
      ),
    ).not.toBeInTheDocument();
  });
});

describe("ratingDistribution", () => {
  it("maps the string-keyed counts onto stars", () => {
    expect(
      ratingDistribution({ "1": 1, "2": 2, "3": 3, "4": 4, "5": 5 }),
    ).toEqual({ 1: 1, 2: 2, 3: 3, 4: 4, 5: 5 });
    expect(ratingDistribution(undefined)).toBeNull();
  });
});
