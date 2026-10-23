import { act, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  ProfileCallBar,
  ProfileContactList,
  type ContactInfo,
} from "@/components/directory/profile-contact";
import { REVIEW_CTA_ID } from "@/components/reviews/review-paths";
import { ReviewSummaryBlock } from "@/components/reviews/review-summary";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

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

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("ProfileCallBar", () => {
  it("adds „Напишете рецензија“ beside the call when asked, with directions as a named icon", async () => {
    const { container } = render(
      <ProfileCallBar
        info={info}
        reviewHref="/doctors/ana#napishi-recenzija"
      />,
    );
    const bar = screen.getByRole("region", {
      name: t("reviewGrowth.stickyLabel"),
    });

    expect(
      within(bar).getByRole("link", { name: t("reviews.write") }),
    ).toHaveAttribute("href", "/doctors/ana#napishi-recenzija");
    const call = within(bar).getByRole("link", { name: t("directory.call") });
    expect(call).toHaveAttribute("href", "tel:023124567");
    const directions = within(bar).getByRole("link", {
      name: t("directory.directions"),
    });
    // Icon-only so three fit a phone: the names come from aria-label.
    for (const link of [call, directions]) {
      expect(link).toHaveTextContent("");
    }
    expect(directions).toHaveAttribute("target", "_blank");
    // Phones only, like the call bar itself.
    expect(bar.className).toContain("lg:hidden");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("shows the review action alone when there is nothing to call or find", () => {
    render(
      <ProfileCallBar
        info={{ ...info, phone: null, directionsHref: null }}
        reviewHref="/doctors/ana#napishi-recenzija"
      />,
    );

    expect(screen.getAllByRole("link")).toHaveLength(1);
    expect(
      screen.getByRole("link", { name: t("reviews.write") }),
    ).toBeInTheDocument();
  });

  it("hides the lone sticky review action while the inline „Напишете рецензија“ is on screen", () => {
    type Callback = (
      entries: Array<{ isIntersecting: boolean; target: Element }>,
    ) => void;
    const observed = new Map<Element, Callback>();
    vi.stubGlobal(
      "IntersectionObserver",
      class {
        constructor(private callback: Callback) {}
        observe(element: Element) {
          observed.set(element, this.callback);
        }
        disconnect() {}
      },
    );
    render(
      <>
        <ReviewSummaryBlock
          summary={{ count: 0, average_rating: null }}
          kind="doctor"
          slug="ana"
        />
        <ProfileCallBar
          info={{ ...info, phone: null, directionsHref: null }}
          reviewHref="/doctors/ana#napishi-recenzija"
        />
      </>,
    );
    const bar = screen.getByRole("region", {
      name: t("reviewGrowth.stickyLabel"),
    });
    const inline = document.getElementById(REVIEW_CTA_ID)!;
    expect(
      within(inline).getByRole("link", { name: t("reviews.write") }),
    ).toBeInTheDocument();

    expect(bar).not.toHaveAttribute("data-hidden");
    act(() =>
      observed.get(inline)?.([{ isIntersecting: true, target: inline }]),
    );
    expect(bar).toHaveAttribute("data-hidden", "true");
    act(() =>
      observed.get(inline)?.([{ isIntersecting: false, target: inline }]),
    );
    expect(bar).not.toHaveAttribute("data-hidden");
  });

  it("has no review action unless asked", () => {
    render(<ProfileCallBar info={info} />);

    expect(
      screen.queryByRole("link", { name: t("reviews.write") }),
    ).not.toBeInTheDocument();
  });

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

  it("leaves out the whole card when there is nothing to contact", () => {
    const { container } = render(
      <ProfileContactList
        info={{
          ...info,
          phone: null,
          email: null,
          place: null,
          directionsHref: null,
          hours: null,
          hoursAnchor: undefined,
        }}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });
});
