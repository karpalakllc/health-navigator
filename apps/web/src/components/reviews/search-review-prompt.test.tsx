import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  SearchReturnRecorder,
  SearchReviewPrompt,
  SearchVisitMarker,
} from "@/components/reviews/search-review-prompt";
import { markReviewPromptShown } from "@/lib/review-prompt-storage";
import { tFormat, t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const NAME = "д-р Ана Петровска";
const TITLE = tFormat("reviewGrowth.searchPromptTitle", { name: NAME });

/** Results → profile (opened from them) → back on the results. */
function searchThenProfile({ eligible = true, reviewed = false } = {}) {
  const results = render(<SearchVisitMarker />);
  results.unmount();
  const profile = render(
    <SearchReturnRecorder
      slug="ana"
      name={NAME}
      eligible={eligible}
      reviewed={reviewed}
    />,
  );
  profile.unmount();
}

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
});

afterEach(() => {
  localStorage.clear();
  sessionStorage.clear();
});

describe("SearchReviewPrompt", () => {
  it("asks about the doctor opened from the results, linking to the review form", async () => {
    searchThenProfile();
    render(<SearchReviewPrompt />);

    const prompt = await screen.findByRole("complementary", { name: TITLE });
    expect(prompt).toHaveTextContent(t("reviewGrowth.searchPromptLead"));
    expect(
      screen.getByRole("link", { name: t("reviewGrowth.searchPromptWrite") }),
    ).toHaveAttribute("href", "/doctors/ana#napishi-recenzija");
    // A card in the flow, never a dialog, and focus stays where it was.
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(document.body).toHaveFocus();
  });

  it("is dismissed with a labelled button and never asks about that doctor again", async () => {
    searchThenProfile();
    const user = userEvent.setup();
    const { unmount } = render(<SearchReviewPrompt />);

    const later = await screen.findByRole("button", {
      name: tFormat("reviewGrowth.searchPromptLaterLabel", { name: NAME }),
    });
    // The visible words are part of the accessible name (WCAG 2.5.3).
    expect(later).toHaveTextContent(t("reviewGrowth.searchPromptLater"));
    await user.click(later);
    expect(screen.queryByText(TITLE)).not.toBeInTheDocument();
    unmount();

    // A new session, a week later — still not this doctor.
    sessionStorage.clear();
    localStorage.setItem(
      "z360:search-prompt:v1",
      JSON.stringify({
        ...JSON.parse(localStorage.getItem("z360:search-prompt:v1") ?? "{}"),
        last: null,
      }),
    );
    searchThenProfile();
    render(<SearchReviewPrompt />);
    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(screen.queryByText(TITLE)).not.toBeInTheDocument();
  });

  it("asks at most once per session", async () => {
    searchThenProfile();
    const first = render(<SearchReviewPrompt />);
    await screen.findByText(TITLE);
    first.unmount();

    searchThenProfile();
    render(<SearchReviewPrompt />);
    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(screen.queryByText(TITLE)).not.toBeInTheDocument();
  });

  it("stays quiet for a doctor the viewer already reviewed (and remembers that)", async () => {
    searchThenProfile({ eligible: false, reviewed: true });
    render(<SearchReviewPrompt />);
    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(screen.queryByText(TITLE)).not.toBeInTheDocument();
  });

  it("stays quiet when the profile's own prompt already asked", async () => {
    markReviewPromptShown("doctor", "ana");
    searchThenProfile();
    render(<SearchReviewPrompt />);
    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(screen.queryByText(TITLE)).not.toBeInTheDocument();
  });

  it("has no serious axe violations", async () => {
    searchThenProfile();
    const { container } = render(<SearchReviewPrompt />);
    await screen.findByText(TITLE);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
