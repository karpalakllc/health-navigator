import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { AccountSubNav } from "@/components/account/account-sub-nav";
import { ModerationStatusTag } from "@/components/account/moderation-status-tag";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("AccountSubNav", () => {
  it("marks only the current section as the current page", async () => {
    const { container } = render(<AccountSubNav current="reviews" />);

    const nav = screen.getByRole("navigation", {
      name: t("account.subNavAria"),
    });
    const links = within(nav).getAllByRole("link");
    expect(links.map((link) => link.getAttribute("href"))).toEqual([
      "/account",
      "/account/reviews",
      "/account/forum",
    ]);
    expect(
      within(nav).getByRole("link", { name: t("nav.myReviews") }),
    ).toHaveAttribute("aria-current", "page");
    expect(
      links.filter((link) => link.hasAttribute("aria-current")),
    ).toHaveLength(1);
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("ModerationStatusTag", () => {
  it.each([
    ["pending", t("account.statusPending")],
    ["approved", t("account.statusApproved")],
    ["rejected", t("account.statusRejected")],
  ])("labels %s in words", (status, label) => {
    render(<ModerationStatusTag status={status} />);

    expect(screen.getByText(label)).toBeInTheDocument();
  });

  it("never colours a rejection red", () => {
    const { container } = render(<ModerationStatusTag status="rejected" />);

    expect(container.innerHTML).not.toMatch(/emergency|destructive|red-/);
  });

  it("shows an unknown status as it came", () => {
    render(<ModerationStatusTag status="flagged" />);

    expect(screen.getByText("flagged")).toBeInTheDocument();
  });
});
