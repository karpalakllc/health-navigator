import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { FeaturedMark } from "@/components/directory/cover-media";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { FeaturedTag } from "@/components/ui/tag";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const featuredInfo = t("disclosure.featuredInfo");
const sponsoredInfo = t("disclosure.sponsoredInfo");

function featuredButton() {
  return screen.getByRole("button", { name: t("disclosure.featuredWhy") });
}

describe("DisclosureBadge (featured / sponsored toggletip)", () => {
  it.each([
    ["FeaturedMark", <FeaturedMark key="m" />],
    ["FeaturedTag", <FeaturedTag key="t" />],
  ])("%s keeps its label and starts closed", (_name, badge) => {
    render(badge);

    expect(screen.getByText(t("ui.featured"))).toBeInTheDocument();
    expect(featuredButton()).toHaveAttribute("aria-expanded", "false");
    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();
  });

  it("takes a card's absolute placement instead of its own relative one", () => {
    // Facility and pharmacy cards overlay the mark on the cover photo. Both
    // `relative` and `absolute` on one element leave it in the flow
    // (`.relative` comes later in the stylesheet), under the photo.
    const { container, rerender } = render(
      <FeaturedMark className="absolute left-3 top-3 shadow-card" />,
    );
    const root = container.firstElementChild as HTMLElement;
    expect(root).toHaveClass("absolute", "left-3", "top-3");
    expect(root).not.toHaveClass("relative");

    // Placed nowhere in particular, it stays the anchor of its panel.
    rerender(<FeaturedMark />);
    expect(container.firstElementChild).toHaveClass("relative");
  });

  it("opens on click and closes on a second click", async () => {
    const user = userEvent.setup();
    render(<FeaturedMark />);

    await user.click(featuredButton());
    expect(featuredButton()).toHaveAttribute("aria-expanded", "true");
    expect(screen.getByRole("status")).toHaveTextContent(featuredInfo);
    expect(featuredButton()).toHaveAttribute(
      "aria-controls",
      screen.getByRole("status").id,
    );

    await user.click(featuredButton());
    expect(featuredButton()).toHaveAttribute("aria-expanded", "false");
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });

  it("opens with Enter and Space from the keyboard", async () => {
    const user = userEvent.setup();
    render(<SponsoredBadge />);
    const button = screen.getByRole("button", {
      name: t("disclosure.sponsoredWhy"),
    });

    await user.tab();
    expect(button).toHaveFocus();
    await user.keyboard("{Enter}");
    expect(screen.getByText(sponsoredInfo)).toBeInTheDocument();
    await user.keyboard(" ");
    expect(screen.queryByText(sponsoredInfo)).not.toBeInTheDocument();
  });

  it("closes on Escape and keeps focus on the button", async () => {
    const user = userEvent.setup();
    render(<FeaturedTag />);

    await user.click(featuredButton());
    await user.keyboard("{Escape}");

    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();
    expect(featuredButton()).toHaveAttribute("aria-expanded", "false");
    expect(featuredButton()).toHaveFocus();
  });

  it("closes on a press outside, not on one inside", async () => {
    const user = userEvent.setup();
    render(
      <>
        <FeaturedMark />
        <p>надвор</p>
      </>,
    );

    await user.click(featuredButton());
    await user.click(screen.getByText(featuredInfo));
    expect(screen.getByText(featuredInfo)).toBeInTheDocument();

    await user.click(screen.getByText("надвор"));
    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();
  });

  it("closes when Tab moves focus out of the badge", async () => {
    const user = userEvent.setup();
    render(
      <>
        <FeaturedMark />
        <a href="#next">следно</a>
      </>,
    );

    await user.tab();
    await user.keyboard("{Enter}");
    expect(screen.getByText(featuredInfo)).toBeInTheDocument();

    // The explanation's own „Повеќе“ link is inside the badge: it stays open.
    await user.tab();
    expect(screen.getByRole("link", { name: "Повеќе" })).toHaveFocus();
    expect(screen.getByText(featuredInfo)).toBeInTheDocument();

    await user.tab();
    expect(screen.getByRole("link", { name: "следно" })).toHaveFocus();
    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();
    expect(featuredButton()).toHaveAttribute("aria-expanded", "false");
  });

  it("links „Повеќе“ to how results are ordered on the transparency page", async () => {
    const user = userEvent.setup();
    render(<FeaturedMark />);

    await user.click(featuredButton());

    expect(screen.getByRole("link", { name: "Повеќе" })).toHaveAttribute(
      "href",
      "/transparency#redosled",
    );
  });

  it("gives the small „i“ button a 44px touch target", () => {
    render(<FeaturedMark />);
    const classes = featuredButton().className.split(/\s+/);

    // The visible circle stays small; an invisible, centred ::after carries the hit area.
    expect(classes).toContain("relative");
    expect(classes).toContain("after:size-11");
    expect(classes).toContain("after:absolute");
  });

  it("shows on mouse hover without pinning, and not on a touch 'hover'", () => {
    render(<FeaturedMark />);
    const root = featuredButton().closest("span.relative")!;

    fireEvent.pointerEnter(root, { pointerType: "touch" });
    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();

    fireEvent.pointerEnter(root, { pointerType: "mouse" });
    expect(screen.getByText(featuredInfo)).toBeInTheDocument();
    expect(featuredButton()).toHaveAttribute("aria-expanded", "false");

    fireEvent.pointerLeave(root, { pointerType: "mouse" });
    expect(screen.queryByText(featuredInfo)).not.toBeInTheDocument();
  });

  it("does not move the layout: the explanation is absolutely positioned", async () => {
    const user = userEvent.setup();
    render(<SponsoredBadge />);

    await user.click(
      screen.getByRole("button", { name: t("disclosure.sponsoredWhy") }),
    );

    expect(screen.getByRole("status").className.split(/\s+/)).toContain(
      "absolute",
    );
  });

  it("has no serious axe violations, closed or open", async () => {
    const user = userEvent.setup();
    const { container } = render(
      <>
        <FeaturedMark />
        <SponsoredBadge />
      </>,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
    await user.click(featuredButton());
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
