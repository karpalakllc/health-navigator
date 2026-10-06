import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import {
  HERO_SPECIALTY_CHIPS,
  HeroSpecialtyChips,
  type HeroSpecialty,
} from "@/components/home/hero-specialty-chips";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const names = [
  "Кардиологија",
  "Педијатрија",
  "Дерматологија",
  "Ортопедија",
  "Гинекологија",
  "Неврологија",
  "Офталмологија",
  "Интерна медицина",
  "Оториноларингологија",
  "Стоматологија",
  "Психијатрија",
];
const all: HeroSpecialty[] = names.map((name, i) => ({
  slug: `s-${i}`,
  name,
  doctors_count: 20 - i,
}));

describe("HeroSpecialtyChips", () => {
  it("shows the top eight as chips, then „Сите специјалности“", async () => {
    const { container } = render(<HeroSpecialtyChips top={all} all={all} />);

    const row = screen.getByRole("list", { name: t("home.quickLinksAria") });
    const links = within(row).getAllByRole("link");
    expect(links).toHaveLength(HERO_SPECIALTY_CHIPS + 1);
    expect(links[0]).toHaveAccessibleName("Кардиологија");
    expect(links[0]).toHaveAttribute("href", "/doctors?specialty=s-0");
    const more = links.at(-1)!;
    expect(more).toHaveAccessibleName(t("homeSearch.allSpecialties"));
    // Without JavaScript it is a plain link to the doctor directory.
    expect(more).toHaveAttribute("href", "/doctors");
    expect(more).toHaveAttribute("aria-haspopup", "dialog");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("„Сите“ opens every specialty in a sheet, filterable in either script", async () => {
    const user = userEvent.setup();
    render(<HeroSpecialtyChips top={all.slice(0, 5)} all={all} />);

    await user.click(
      screen.getByRole("link", { name: t("homeSearch.allSpecialties") }),
    );
    const sheet = screen.getByRole("dialog", {
      name: t("homeSearch.specialtiesTitle"),
    });
    const filter = within(sheet).getByRole("searchbox", {
      name: t("homeSearch.specialtiesFilter"),
    });
    expect(filter).toHaveFocus();
    expect(within(sheet).getAllByRole("link")).toHaveLength(all.length);
    expect(
      within(sheet).getByRole("link", { name: /Психијатрија/ }),
    ).toHaveAttribute("href", "/doctors?specialty=s-10");

    await user.type(filter, "kardio");
    expect(
      within(sheet)
        .getAllByRole("link")
        .map((l) => l.textContent),
    ).toEqual(["Кардиологија20 лекари"]);

    await user.clear(filter);
    await user.type(filter, "зззз");
    expect(within(sheet).getByRole("status")).toHaveTextContent("зззз");

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).toBeNull();
  });

  it("offers no „Сите“ when the chips already show every specialty", () => {
    render(<HeroSpecialtyChips top={all.slice(0, 3)} all={all.slice(0, 3)} />);
    expect(
      screen.queryByRole("link", { name: t("homeSearch.allSpecialties") }),
    ).toBeNull();
  });
});
