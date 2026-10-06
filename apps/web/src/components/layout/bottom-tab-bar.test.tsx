import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { BottomTabBar } from "@/components/layout/bottom-tab-bar";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { setPathname } from "../../../test/next-navigation";

const allOn = {
  public_guidance: true,
  public_products: true,
  public_pharmacies: true,
  public_forum: true,
};

function bar() {
  return screen.getByRole("navigation", { name: t("nav.primary") });
}

function tab(name: string) {
  return within(bar()).getByRole("link", { name });
}

function current() {
  return within(bar())
    .getAllByRole("link")
    .filter((link) => link.getAttribute("aria-current") === "page")
    .map((link) => link.textContent);
}

describe("BottomTabBar", () => {
  it("shows the five tabs with Барај in the centre", () => {
    setPathname("/");
    render(<BottomTabBar isLoggedIn={false} modules={allOn} />);

    expect(
      within(bar())
        .getAllByRole("link")
        .map((link) => link.textContent),
    ).toEqual([
      t("nav.home"),
      t("nav.tabGuidance"),
      t("nav.tabSearch"),
      t("nav.forum"),
      t("nav.tabProfile"),
    ]);
  });

  it.each([
    ["/", "nav.home"],
    ["/guidance", "nav.tabGuidance"],
    ["/doctors/ana-petrovska", "nav.tabSearch"],
    ["/search", "nav.tabSearch"],
    ["/pharmacies", "nav.tabSearch"],
    ["/forum/srce/tema", "nav.forum"],
    ["/account/reviews", "nav.tabProfile"],
    ["/login", "nav.tabProfile"],
  ] as const)("marks exactly one tab current on %s", (pathname, key) => {
    setPathname(pathname);
    render(<BottomTabBar isLoggedIn={false} modules={allOn} />);

    expect(current()).toEqual([t(key)]);
  });

  it("marks nothing on pages outside the five destinations", () => {
    setPathname("/about");
    render(<BottomTabBar isLoggedIn={false} modules={allOn} />);

    expect(current()).toEqual([]);
  });

  it("sends Профил to sign-in (returning here) or to the account", () => {
    setPathname("/forum");
    const { unmount } = render(
      <BottomTabBar isLoggedIn={false} modules={allOn} />,
    );
    expect(tab(t("nav.tabProfile")).getAttribute("href")).toMatch(
      /^\/login\?redirect=/,
    );
    unmount();

    render(<BottomTabBar isLoggedIn modules={allOn} />);
    expect(tab(t("nav.tabProfile"))).toHaveAttribute("href", "/account");
  });

  it("leaves out switched-off modules", () => {
    setPathname("/");
    render(
      <BottomTabBar
        isLoggedIn={false}
        modules={{ ...allOn, public_guidance: false, public_forum: false }}
      />,
    );

    expect(
      within(bar()).queryByRole("link", { name: t("nav.tabGuidance") }),
    ).toBeNull();
    expect(
      within(bar()).queryByRole("link", { name: t("nav.forum") }),
    ).toBeNull();
    expect(within(bar()).getAllByRole("link")).toHaveLength(3);
  });

  it("has no serious accessibility violations", async () => {
    setPathname("/forum");
    const { container } = render(
      <BottomTabBar isLoggedIn={false} modules={allOn} />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
