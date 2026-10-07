import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  ProfileCallBar,
  ProfileContactList,
  type ContactInfo,
} from "@/components/directory/profile-contact";
import { t } from "@/i18n/t";

// Tuesday 10:00 in Skopje.
const now = new Date("2026-10-06T10:00:00+02:00");

const info: ContactInfo = {
  name: "д-р Ана Петровска",
  phone: "02 312 4567",
  email: "kontakt@example.test",
  place: {
    title: "Клиника Ана",
    sub: "Скопје",
    href: "/facilities/klinika-ana",
  },
  directionsHref: "https://www.openstreetmap.org/search?query=Клиника",
  hours: { "Пон–Пет": "08:00–14:00" },
  hoursAnchor: "hours",
  now,
};

describe("ProfileCallBar", () => {
  it("pins „Јави се“ (a tel: link) and „Насоки“ in a named region", () => {
    render(<ProfileCallBar info={info} />);
    const bar = screen.getByRole("region", {
      name: t("directory.quickContact"),
    });

    expect(
      within(bar).getByRole("link", { name: t("directory.call") }),
    ).toHaveAttribute("href", "tel:023124567");
    const directions = within(bar).getByRole("link", {
      name: t("directory.directions"),
    });
    expect(directions).toHaveAttribute("href", info.directionsHref);
    expect(directions).toHaveAttribute("target", "_blank");
  });

  it("sits above the bottom tab bar, not at the very bottom", () => {
    render(<ProfileCallBar info={info} />);

    expect(
      screen.getByRole("region", { name: t("directory.quickContact") })
        .className,
    ).toContain("bottom-[calc(var(--tabbar-space)+var(--consent-h,0px))]");
  });

  it("is left out when there is nothing to call or find", () => {
    render(
      <ProfileCallBar info={{ ...info, phone: null, directionsHref: null }} />,
    );

    expect(screen.queryByRole("region")).not.toBeInTheDocument();
  });

  it("keeps directions when only the number is missing", () => {
    render(<ProfileCallBar info={{ ...info, phone: null }} />);

    expect(
      screen.queryByRole("link", { name: t("directory.call") }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("directory.directions") }),
    ).toBeInTheDocument();
  });
});

describe("ProfileContactList", () => {
  it("puts the number first as a tel: link, then place, hours and e-mail", () => {
    render(<ProfileContactList info={info} />);
    const links = screen.getAllByRole("link");

    expect(links[0]).toHaveAttribute("href", "tel:023124567");
    expect(links[0]).toHaveTextContent("02 312 4567");
    expect(links[1]).toHaveAttribute("href", info.directionsHref);
    expect(links[2]).toHaveAttribute("href", "#hours");
    expect(links[2]).toHaveTextContent("Отворено до 14:00");
    expect(links[3]).toHaveAttribute("href", "mailto:kontakt@example.test");
  });
});
