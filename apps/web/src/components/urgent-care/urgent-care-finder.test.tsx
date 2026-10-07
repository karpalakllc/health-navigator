import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { EmergencyStrip } from "@/components/urgent-care/emergency-strip";
import { UrgentCareFinder } from "@/components/urgent-care/urgent-care-finder";
import type { UrgentCarePlace } from "@/lib/urgent-care";
import { seriousA11yViolations } from "../../../test/axe";

function place(overrides: Partial<UrgentCarePlace>): UrgentCarePlace {
  return {
    slug: "place",
    name: "Место",
    type: "clinic",
    city: "Битола",
    address: null,
    latitude: null,
    longitude: null,
    phone: null,
    emergency_phone: null,
    services: ["ems"],
    is_open_24h: false,
    emergency_hours: null,
    hours_confirmed: false,
    note: null,
    checked_at: null,
    ...overrides,
  };
}

const hospital = place({
  slug: "bolnica",
  name: "ЈЗУ Клиничка болница Битола",
  type: "hospital",
  services: ["ed"],
  is_open_24h: true,
  hours_confirmed: true,
  phone: "047 111 111",
  emergency_phone: "047 194 194",
  latitude: 41.03,
  longitude: 21.33,
});
const centre = place({
  slug: "zd",
  name: "Здравствен дом Битола",
  services: ["ems", "dental"],
  phone: "047 222 222",
  address: "ул. Прилепска бб",
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("EmergencyStrip", () => {
  it("offers 194 and 112 as tap-to-call links", async () => {
    const { container } = render(<EmergencyStrip />);

    expect(screen.getByRole("link", { name: /194/ })).toHaveAttribute(
      "href",
      "tel:194",
    );
    expect(screen.getByRole("link", { name: /112/ })).toHaveAttribute(
      "href",
      "tel:112",
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("UrgentCareFinder", () => {
  it("lists services, calls and directions, and says when hours are not confirmed", async () => {
    const { container } = render(
      <UrgentCareFinder places={[hospital, centre]} />,
    );

    const first = screen.getByRole("article", { name: hospital.name });
    expect(within(first).getByText("Итно одделение")).toBeInTheDocument();
    expect(within(first).getByText("Отворено 24 часа")).toBeInTheDocument();
    expect(
      within(first).getByRole("link", {
        name: `Итна линија: ${hospital.name}`,
      }),
    ).toHaveAttribute("href", "tel:047194194");

    const second = screen.getByRole("article", { name: centre.name });
    expect(
      within(second).getByText("Итна медицинска помош"),
    ).toBeInTheDocument();
    expect(
      within(second).getByText("Стоматолошка итна помош"),
    ).toBeInTheDocument();
    expect(
      within(second).getByText(/Работното време не е потврдено/),
    ).toBeInTheDocument();
    expect(within(second).queryByText(/Отворено/)).toBeNull();
    expect(
      within(second).getByRole("link", { name: `Насоки до ${centre.name}` }),
    ).toHaveAttribute(
      "href",
      expect.stringContaining(encodeURIComponent("ул. Прилепска бб")),
    );
    expect(
      within(second).getByRole("link", { name: `Профил: ${centre.name}` }),
    ).toHaveAttribute("href", "/facilities/zd");

    // The map shows the first place with coordinates, without a referrer.
    const map = container.querySelector("iframe");
    expect(map).toHaveAttribute("referrerpolicy", "no-referrer");
    expect(map?.getAttribute("src")).toContain("marker=41.03%2C21.33");
    expect(map).toHaveAttribute("title", `Мапа: ${hospital.name}`);

    // axe cannot reach into a jsdom iframe; the frame's title is checked above.
    map?.remove();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("sorts by distance in the browser only, without any request", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    const far = place({
      slug: "far",
      name: "Далечно",
      latitude: 42.0,
      longitude: 21.43,
    });
    const near = place({
      slug: "near",
      name: "Блиско",
      latitude: 41.04,
      longitude: 21.34,
    });
    const getCurrentPosition = vi.fn((ok: PositionCallback) =>
      ok({
        coords: { latitude: 41.031, longitude: 21.334 },
      } as GeolocationPosition),
    );
    vi.stubGlobal("navigator", {
      ...navigator,
      geolocation: { getCurrentPosition },
    });

    render(<UrgentCareFinder places={[far, centre, near]} />);
    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: "Подреди по оддалеченост" }));

    const names = screen
      .getAllByRole("article")
      .map((a) => a.getAttribute("aria-labelledby"));
    expect(names).toEqual(["urgent-near", "urgent-far", "urgent-zd"]);
    expect(
      screen.getByText("Подредено по оддалеченост од вас."),
    ).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("labels a likely emergency department as unconfirmed and offers its main phone", async () => {
    const likely = place({
      slug: "ob-strumica",
      name: "ЈЗУ Општа болница Струмица",
      type: "hospital",
      services: ["ed"],
      ed_status: "unconfirmed_likely",
      phone: "034 000 000",
    });
    const { container } = render(<UrgentCareFinder places={[likely]} />);

    const card = screen.getByRole("article", { name: likely.name });
    expect(
      within(card).getByText("Итно одделение (непотврдено)"),
    ).toBeInTheDocument();
    expect(within(card).queryByText("Итно одделение")).toBeNull();
    expect(
      within(card).getByText(/сè уште не сме потврдиле/),
    ).toBeInTheDocument();
    expect(
      within(card).getByRole("link", { name: `Јави се: ${likely.name}` }),
    ).toHaveAttribute("href", "tel:034000000");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("hides the distance sort when no place has coordinates", () => {
    render(<UrgentCareFinder places={[centre]} />);

    expect(
      screen.queryByRole("button", { name: "Подреди по оддалеченост" }),
    ).toBeNull();
    expect(screen.queryByTitle(/Мапа/)).toBeNull();
  });
});
