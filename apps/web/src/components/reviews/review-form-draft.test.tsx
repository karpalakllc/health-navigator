import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { ReviewForm } from "@/components/reviews/review-form";
import { CONSENT_KEY } from "@/lib/consent/consent";
import {
  DRAFT_KEY,
  SHARED_LANDING_KEY,
  forgetSharedLanding,
  readReviewDraft,
  rememberSharedLanding,
  saveReviewDraft,
} from "@/lib/review-growth";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const SLUG = "ana-petrovska";

function consent(statistics: boolean) {
  localStorage.setItem(
    CONSENT_KEY,
    JSON.stringify({
      statistics,
      decidedAt: new Date().toISOString(),
      version: 1,
    }),
  );
}

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
  forgetSharedLanding();
});

afterEach(() => {
  localStorage.clear();
  sessionStorage.clear();
});

describe("ReviewForm — signed out, draft kept across sign-in", () => {
  it("keeps what was filled in and goes to sign-in, returning to the form", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    render(
      <ReviewForm kind="doctor" slug={SLUG} guest profileName="д-р Ана" />,
    );

    // The step indicator says sign-in comes last.
    expect(
      screen.getByRole("list", { name: t("reviewFlow.stepsLabel") }),
    ).toHaveTextContent(t("reviewGrowth.stepLogin"));

    await user.click(screen.getByRole("radio", { name: "4 ѕвезди од 5" }));
    await user.type(
      screen.getByLabelText(t("reviews.body")),
      "  Внимателна и точна.  ",
    );
    await user.click(
      screen.getByRole("button", { name: t("reviewGrowth.guestSubmit") }),
    );

    // Nothing is sent before sign-in.
    expect(fetch).not.toHaveBeenCalled();
    expect(router.push).toHaveBeenCalledWith(
      `/login?redirect=${encodeURIComponent(`/doctors/${SLUG}#napishi-recenzija`)}`,
    );
    expect(readReviewDraft("doctor", SLUG)).toMatchObject({
      rating: 4,
      body: "Внимателна и точна.",
      name: "д-р Ана",
      path: `/doctors/${SLUG}`,
    });
  });

  it("restores the draft after sign-in, says so, and deletes it once sent", async () => {
    saveReviewDraft({
      kind: "doctor",
      slug: SLUG,
      name: "д-р Ана",
      path: `/doctors/${SLUG}`,
      rating: 4,
      body: "Внимателна и точна.",
      aspects: {},
    });
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    render(<ReviewForm kind="doctor" slug={SLUG} />);

    expect(
      await screen.findByText(t("reviewGrowth.draftRestored")),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("radio", { name: "4 ѕвезди од 5" }),
    ).toHaveAttribute("aria-checked", "true");
    expect(screen.getByLabelText(t("reviews.body"))).toHaveValue(
      "Внимателна и точна.",
    );

    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));

    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));
    expect(requestBody(fetch)).toMatchObject({
      rating: 4,
      body: "Внимателна и точна.",
    });
    await waitFor(() => expect(localStorage.getItem(DRAFT_KEY)).toBeNull());
  });

  it("does not restore a draft written for another profile", async () => {
    saveReviewDraft({
      kind: "doctor",
      slug: "someone-else",
      name: null,
      path: "/doctors/someone-else",
      rating: 2,
      body: "",
      aspects: {},
    });
    render(<ReviewForm kind="doctor" slug={SLUG} />);

    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(
      screen.queryByText(t("reviewGrowth.draftRestored")),
    ).not.toBeInTheDocument();
    for (const radio of screen.getAllByRole("radio")) {
      expect(radio).toHaveAttribute("aria-checked", "false");
    }
  });

  it("validates a too-short comment inline, in Macedonian, before sending", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    render(<ReviewForm kind="doctor" slug={SLUG} />);

    await user.click(screen.getByRole("radio", { name: "5 ѕвезди од 5" }));
    const body = screen.getByLabelText(t("reviews.body"));
    await user.type(body, "Добро");
    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));

    expect(fetch).not.toHaveBeenCalled();
    expect(body).toHaveAttribute("aria-invalid", "true");
    expect(body).toHaveAccessibleDescription(
      expect.stringContaining(t("reviewGrowth.bodyTooShort")),
    );
    expect(body).toHaveFocus();
    expect(body).toHaveAttribute("maxlength", "2000");
  });

  it("has no serious axe violations as a guest", async () => {
    const { container } = render(
      <ReviewForm kind="doctor" slug={SLUG} guest profileName="д-р Ана" />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("ReviewForm — the shared-link mark", () => {
  async function sendFiveStars() {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    render(<ReviewForm kind="doctor" slug={SLUG} />);
    await user.click(screen.getByRole("radio", { name: "5 ѕвезди од 5" }));
    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));
    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));

    return requestBody(fetch) as Record<string, unknown>;
  }

  it("is sent with statistics consent", async () => {
    consent(true);
    rememberSharedLanding("doctor", SLUG);

    expect(await sendFiveStars()).toMatchObject({ via_shared_link: true });
    expect(sessionStorage.getItem(SHARED_LANDING_KEY)).toBeNull();
  });

  it("is neither kept nor sent without it", async () => {
    consent(false);
    rememberSharedLanding("doctor", SLUG);

    expect(sessionStorage.getItem(SHARED_LANDING_KEY)).toBeNull();
    expect(await sendFiveStars()).not.toHaveProperty("via_shared_link");
  });

  it("is not sent for a profile the link was not for", async () => {
    consent(true);
    rememberSharedLanding("doctor", "someone-else");

    expect(await sendFiveStars()).not.toHaveProperty("via_shared_link");
  });
});
