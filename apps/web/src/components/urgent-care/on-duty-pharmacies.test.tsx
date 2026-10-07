import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { OnDutyPharmaciesSection } from "@/components/urgent-care/on-duty-pharmacies";
import { OnDutyTag } from "@/components/urgent-care/on-duty-tag";
import type { OnDutyPharmacy } from "@/lib/api/urgent-care";
import { seriousA11yViolations } from "../../../test/axe";

function pharmacy(overrides: Partial<OnDutyPharmacy>): OnDutyPharmacy {
  return {
    name: "Роса Вита",
    municipality: null,
    address: null,
    phone: null,
    mode: "unknown",
    hours_text: null,
    slug: null,
    latitude: null,
    longitude: null,
    ...overrides,
  };
}

describe("OnDutyPharmaciesSection", () => {
  it("lists tonight's pharmacies with their hours, a call button and the source", async () => {
    const { container } = render(
      <OnDutyPharmaciesSection
        cityName="Битола"
        data={{
          available: true,
          date: "2026-10-07",
          source_url: "https://fzo.org.mk/dezurni-apteki",
          items: [
            pharmacy({
              name: "Роса Вита",
              slug: "rosa-vita",
              mode: "hours",
              hours_text: "Од 23:00-07:00",
              phone: "047/236-468",
              address: "ул. Широк Сокак 1",
            }),
            pharmacy({
              name: "Еурофарм",
              mode: "on_call",
              hours_text: "по телефонски повик од лекарски тим",
              phone: "079-396-031, 072-250-570",
            }),
          ],
        }}
      />,
    );

    expect(
      screen.getByRole("heading", { name: "Дежурни аптеки во Битола" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Дежурство за 7 октомври 2026"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Профил: Роса Вита" }),
    ).toHaveAttribute("href", "/pharmacies/rosa-vita");
    expect(screen.getByText("Од 23:00-07:00")).toBeInTheDocument();
    expect(
      screen.getByText("По повик од дежурниот лекарски тим"),
    ).toBeInTheDocument();
    // The first of several numbers.
    expect(
      screen.getByRole("link", { name: "Јави се: Еурофарм" }),
    ).toHaveAttribute("href", "tel:079396031");
    expect(
      screen.getByRole("link", { name: "Распоред на дежурни аптеки (ФЗОМ)" }),
    ).toHaveAttribute("href", "https://fzo.org.mk/dezurni-apteki");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("asks for a city, says when the city has none, and falls back to the placeholder", () => {
    const { rerender } = render(
      <OnDutyPharmaciesSection
        cityName={null}
        data={{ available: true, date: "2026-10-07", items: null }}
      />,
    );
    expect(screen.getByText(/Изберете град погоре/)).toBeInTheDocument();

    rerender(
      <OnDutyPharmaciesSection
        cityName="Вевчани"
        data={{ available: true, date: "2026-10-07", items: [] }}
      />,
    );
    expect(
      screen.getByText(/нема дежурна аптека во Вевчани за 7 октомври 2026/),
    ).toBeInTheDocument();

    rerender(
      <OnDutyPharmaciesSection cityName="Битола" data={{ available: false }} />,
    );
    const section = screen.getByRole("region", { name: "Дежурни аптеки" });
    expect(within(section).getByText(/наскоро/)).toBeInTheDocument();
  });
});

describe("OnDutyTag", () => {
  it("says „Дежурна денес“ with the hours, and nothing off duty", () => {
    const { container, rerender } = render(
      <OnDutyTag
        duty={{ date: "2026-10-07", mode: "all_day", hours_text: "24/7" }}
      />,
    );
    expect(container).toHaveTextContent("Дежурна денес · 24 часа");

    rerender(<OnDutyTag duty={null} />);
    expect(container).toBeEmptyDOMElement();
  });
});
