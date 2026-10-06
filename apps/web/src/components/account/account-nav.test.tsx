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
      "/account/devices",
      "/account/data",
    ]);
    expect(
      within(nav).getByRole("link", { name: t("nav.myReviews") }),
    ).toHaveAttribute("aria-current", "page");
    expect(
      links.filter((link) => link.hasAttribute("aria-current")),
    ).toHaveLength(1);
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("offers „Мој профил“ only to an account that manages a doctor profile", async () => {
    const { container, rerender } = render(<AccountSubNav current="doctor" />);

    expect(
      screen.queryByRole("link", { name: t("doctorDashboard.navLabel") }),
    ).toBeNull();

    rerender(<AccountSubNav current="doctor" showDoctor />);

    const link = screen.getByRole("link", {
      name: t("doctorDashboard.navLabel"),
    });
    expect(link).toHaveAttribute("href", "/account/doctor");
    expect(link).toHaveAttribute("aria-current", "page");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("leaves room for the focus ring inside the scrolling row", () => {
    render(<AccountSubNav current="reviews" />);

    // A scroller clips its overflow: the 2px ring 2px off each pill needs
    // vertical padding, cancelled by a negative margin so nothing moves.
    const row = screen.getByRole("list").className.split(/\s+/);
    expect(row).toEqual(expect.arrayContaining(["py-2.5", "-my-2.5"]));
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
