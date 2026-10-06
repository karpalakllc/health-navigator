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

describe("SiteNav", () => {
  it.each(["/", "/doctors", "/doctors/ana-petrovska"])(
    "marks only the current section on %s",
    (pathname) => {
      setPathname(pathname);
      render(<SiteNav modules={modules} />);

      // Форум was coloured like the active item on every page.
      expect(link(t("nav.forum"))).not.toHaveAttribute("aria-current");
      expect(link(t("nav.forum")).className).not.toMatch(/text-primary/);

      const doctors = link(t("nav.doctors"));
      if (pathname.startsWith("/doctors")) {
        expect(doctors).toHaveAttribute("aria-current", "page");
        expect(doctors.className).toMatch(/text-primary/);
      } else {
        expect(doctors).not.toHaveAttribute("aria-current");
      }
    },
  );

  it("marks Форум when on the forum", () => {
    setPathname("/forum/srce");
    render(<SiteNav modules={modules} />);

    expect(link(t("nav.forum"))).toHaveAttribute("aria-current", "page");
  });
});
