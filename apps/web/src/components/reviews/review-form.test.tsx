import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ReviewForm } from "@/components/reviews/review-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

function renderForm() {
  return render(<ReviewForm kind="doctor" slug="d-r-ana-petrovska" />);
}

function submitButton() {
  return screen.getByRole("button", { name: t("reviews.submit") });
}

describe("ReviewForm", () => {
  it("has no rating selected by default", () => {
    renderForm();

    for (const radio of screen.getAllByRole("radio")) {
      expect(radio).toHaveAttribute("aria-checked", "false");
    }
  });

  it("refuses to submit without a rating and says why", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    renderForm();

    await userEvent.setup().click(submitButton());

    expect(fetch).not.toHaveBeenCalled();
    expect(screen.getByRole("alert")).toHaveTextContent(
      t("reviews.ratingRequired"),
    );
    const group = screen.getByRole("radiogroup");
    expect(group).toHaveAttribute("aria-invalid", "true");
    expect(group).toHaveAccessibleDescription(t("reviews.ratingRequired"));
  });

  it("clears the rating error once a star is chosen", async () => {
    mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    renderForm();

    await user.click(submitButton());
    await user.click(screen.getByRole("radio", { name: "4 ѕвезди од 5" }));

    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    expect(screen.getByRole("radiogroup")).not.toHaveAttribute("aria-invalid");
  });

  it("submits the rating with a trimmed body and announces moderation", async () => {
    const fetch = mockFetch({
      status: 201,
      body: { data: { status: "pending" } },
    });
    const user = userEvent.setup();
    renderForm();
    const region = screen.getByRole("status");

    await user.click(screen.getByRole("radio", { name: "2 ѕвезди од 5" }));
    await user.type(
      screen.getByLabelText(t("reviews.body")),
      "  Љубезна докторка.  ",
    );
    await user.click(submitButton());

    expect(await screen.findByText(t("reviews.submitSuccess"))).toBe(region);
    expect(requestBody(fetch)).toEqual({
      kind: "doctor",
      slug: "d-r-ana-petrovska",
      rating: 2,
      body: "Љубезна докторка.",
    });
    expect(router.refresh).toHaveBeenCalled();
    // The form resets — no rating carried over to a second review.
    expect(screen.getByLabelText(t("reviews.body"))).toHaveValue("");
    for (const radio of screen.getAllByRole("radio")) {
      expect(radio).toHaveAttribute("aria-checked", "false");
    }
  });

  it("sends a blank body as null", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    renderForm();

    await user.click(screen.getByRole("radio", { name: "5 ѕвезди од 5" }));
    await user.type(screen.getByLabelText(t("reviews.body")), "   ");
    await user.click(submitButton());

    await waitFor(() => expect(fetch).toHaveBeenCalled());
    expect(requestBody(fetch).body).toBeNull();
  });

  it.each([
    [
      422,
      {
        message: "Веќе оставивте рецензија.",
        errors: { review: ["Веќе имате рецензија за овој профил."] },
      },
      "Веќе имате рецензија за овој профил.",
    ],
    [429, { message: "Премногу барања." }, "Премногу барања."],
    [500, {}, t("reviews.submitError")],
  ])("shows a %i failure as an alert", async (status, body, text) => {
    mockFetch({ status, body });
    const user = userEvent.setup();
    renderForm();

    await user.click(screen.getByRole("radio", { name: "3 ѕвезди од 5" }));
    await user.click(submitButton());

    expect(await screen.findByRole("alert")).toHaveTextContent(text);
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
    // The chosen rating survives a failed submit.
    expect(
      screen.getByRole("radio", { name: "3 ѕвезди од 5" }),
    ).toHaveAttribute("aria-checked", "true");
  });

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    const user = userEvent.setup();
    renderForm();

    await user.click(screen.getByRole("radio", { name: "1 ѕвезда од 5" }));
    await user.click(submitButton());

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("reviews.submitErrorRetry"),
    );
  });

  it("has no serious accessibility violations, including the rating error", async () => {
    const { container } = renderForm();
    expect(await seriousA11yViolations(container)).toEqual([]);

    await userEvent.setup().click(submitButton());
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
