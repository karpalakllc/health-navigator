import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { setPathname } from "../../../test/next-navigation";

const modules = {
  public_guidance: true,
  public_products: false,
  public_pharmacies: true,
  public_forum: true,
};

function menuButton() {
  return screen.getByRole("button", { name: t("nav.menu") });
}

function drawer() {
  return screen.queryByRole("dialog", { name: t("nav.menu") });
}

async function openDrawer() {
  const user = userEvent.setup();
  setPathname("/doctors");
  render(<SiteHeaderBar isLoggedIn={false} modules={modules} />);
  await user.click(menuButton());
  expect(drawer()).toBeInTheDocument();
  return user;
}

describe("Mobile nav drawer", () => {
  it("opens as a labelled modal with focus inside and the page locked", async () => {
    await openDrawer();

    expect(menuButton()).toHaveAttribute("aria-expanded", "true");
    expect(drawer()).toHaveAttribute("aria-modal", "true");
    expect(drawer()).toContainElement(document.activeElement as HTMLElement);
    expect(document.body.style.overflow).toBe("hidden");
  });

  it("lists enabled sections, search and sign-in", async () => {
    await openDrawer();
    const panel = within(drawer()!);

    expect(panel.getByRole("link", { name: t("nav.doctors") })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(panel.queryByRole("link", { name: t("nav.products") })).toBeNull();
    expect(panel.getByRole("link", { name: t("nav.search") })).toBeVisible();
    expect(
      panel.getByRole("link", { name: t("nav.login") }).getAttribute("href"),
    ).toMatch(/^\/login\?redirect=/);
  });

  it("keeps Tab and Shift+Tab inside the drawer", async () => {
    const user = await openDrawer();
    const panel = drawer()!;
    const close = within(panel).getByRole("button", {
      name: t("search.close"),
    });
    const login = within(panel).getByRole("link", { name: t("nav.login") });

    expect(close).toHaveFocus();

    for (let i = 0; i < 15; i += 1) {
      await user.tab();
      expect(panel).toContainElement(document.activeElement as HTMLElement);
    }

    login.focus();
    await user.tab();
    expect(close).toHaveFocus();
    await user.tab({ shift: true });
    expect(login).toHaveFocus();
  });

  it("closes on Escape and returns focus to the menu button", async () => {
    const user = await openDrawer();

    await user.keyboard("{Escape}");

    expect(drawer()).toBeNull();
    expect(menuButton()).toHaveFocus();
    expect(menuButton()).toHaveAttribute("aria-expanded", "false");
    expect(document.body.style.overflow).toBe("");
  });

  it("closes with the close button and returns focus", async () => {
    const user = await openDrawer();

    await user.click(screen.getByRole("button", { name: t("search.close") }));

    expect(drawer()).toBeNull();
    expect(menuButton()).toHaveFocus();
  });

  it("has no serious accessibility violations when open", async () => {
    await openDrawer();

    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });
});

describe("Header bar", () => {
  it("has no emergency call pill (194/112 live in guidance and the footer)", () => {
    setPathname("/");
    render(<SiteHeaderBar isLoggedIn={false} modules={modules} />);

    expect(
      document.querySelector('a[href="tel:194"], a[href="tel:112"]'),
    ).toBeNull();
    expect(screen.queryByText("Итно 194")).toBeNull();
  });

  it("has a labelled GET search form to /search", () => {
    setPathname("/");
    render(<SiteHeaderBar isLoggedIn={false} modules={modules} />);

    const form = screen.getByRole("search");
    expect(form).toHaveAttribute("action", "/search");
    expect(
      within(form).getByRole("searchbox", { name: t("nav.searchWhat") }),
    ).toHaveAttribute("name", "q");
    expect(
      within(form).getByRole("textbox", { name: t("nav.searchWhere") }),
    ).toHaveAttribute("name", "city");
    expect(
      within(form).getByRole("button", { name: t("nav.searchSubmit") }),
    ).toHaveAttribute("type", "submit");
  });
});
