import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { SiteNav } from "@/components/layout/site-nav";
import { t } from "@/i18n/t";
import { setPathname } from "../../../test/next-navigation";

const modules = {
  public_guidance: true,
  public_products: true,
  public_pharmacies: true,
  public_forum: true,
};

function link(name: string) {
  return screen.getByRole("link", { name });
}

/** The active link is drawn 600-weight with a 3px ink underline bar. */
function looksActive(element: HTMLElement): boolean {
  return (
    element.className.includes("font-semibold") &&
    element.querySelector("[data-active-indicator]") !== null
  );
}

describe("SiteNav", () => {
  it.each(["/", "/doctors", "/doctors/ana-petrovska"])(
    "marks only the current section on %s",
    (pathname) => {
      setPathname(pathname);
      render(<SiteNav modules={modules} />);

      // Форум was coloured like the active item on every page.
      expect(link(t("nav.forum"))).not.toHaveAttribute("aria-current");
      expect(looksActive(link(t("nav.forum")))).toBe(false);

      const doctors = link(t("nav.doctors"));
      if (pathname.startsWith("/doctors")) {
        expect(doctors).toHaveAttribute("aria-current", "page");
        expect(looksActive(doctors)).toBe(true);
      } else {
        expect(doctors).not.toHaveAttribute("aria-current");
        expect(looksActive(doctors)).toBe(false);
      }
    },
  );

  it("marks Форум when on the forum", () => {
    setPathname("/forum/srce");
    render(<SiteNav modules={modules} />);

    expect(link(t("nav.forum"))).toHaveAttribute("aria-current", "page");
  });

  it("is a named navigation landmark without switched-off modules", () => {
    setPathname("/");
    render(
      <SiteNav
        modules={{
          ...modules,
          public_pharmacies: false,
          public_products: false,
        }}
      />,
    );

    const nav = screen.getByRole("navigation", { name: t("nav.sections") });
    expect(nav).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: t("nav.pharmacies") }),
    ).toBeNull();
    expect(screen.queryByRole("link", { name: t("nav.products") })).toBeNull();
    expect(link(t("nav.guidance"))).toBeInTheDocument();
  });
});
