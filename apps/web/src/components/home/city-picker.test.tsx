import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { CityPicker } from "@/components/home/city-picker";
import type { LocationCity } from "@/lib/api/locations";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const known: LocationCity[] = [
  { name: "Скопје", doctors_count: 6, facilities_count: 4 },
  { name: "Битола", doctors_count: 3, facilities_count: 2 },
  { name: "Прилеп", doctors_count: 2, facilities_count: 1 },
];

function renderInForm() {
  const utils = render(
    <form aria-label="probe">
      <CityPicker knownCities={known} />
    </form>,
  );
  const form = screen.getByRole("form", { name: "probe" }) as HTMLFormElement;
  return { ...utils, form, city: () => new FormData(form).get("city") };
}

function trigger() {
  return screen.getByRole("button", { name: /Град или општина/ });
}

function filter() {
  return screen.getByRole("combobox", {
    name: t("homeSearch.cityFilterLabel"),
  });
}

function activeLabel() {
  const id = filter().getAttribute("aria-activedescendant");
  const el = id ? document.getElementById(id) : null;
  return el?.getAttribute("aria-label") ?? el?.textContent ?? null;
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("CityPicker", () => {
  it("sends no city until one is picked, and names the trigger by the choice", async () => {
    const { city } = renderInForm();
    expect(city()).toBeNull();
    expect(trigger()).toHaveAccessibleName(t("homeSearch.cityTriggerNone"));
    expect(trigger()).toHaveAttribute("aria-expanded", "false");
  });

  it("opens a combobox over a tree of the bigger towns with expandable groups", async () => {
    const user = userEvent.setup();
    const { container } = renderInForm();
    await user.click(trigger());

    expect(trigger()).toHaveAttribute("aria-expanded", "true");
    expect(filter()).toHaveFocus();
    const tree = screen.getByRole("tree", {
      name: t("homeSearch.cityTreeLabel"),
    });
    expect(filter()).toHaveAttribute("aria-controls", tree.id);

    const top = within(tree)
      .getAllByRole("treeitem")
      .filter((item) => item.getAttribute("aria-level") === "1");
    expect(
      top.map((item) => item.getAttribute("aria-label") ?? item.textContent),
    ).toEqual([
      t("homeSearch.cityAny"),
      "Скопје, Скопски регион, 10 профили",
      "Битола, Пелагониски регион, 5 профили",
      "Велес, Вардарски регион",
      "Куманово, Североисточен регион",
      "Охрид, Југозападен регион",
      "Струмица, Југоисточен регион",
      "Тетово, Полошки регион",
      "Штип, Источен регион",
    ]);
    for (const group of top.slice(1)) {
      expect(group).toHaveAttribute("aria-expanded", "false");
    }
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("moves with the arrow keys, opens a town with → and picks with Enter", async () => {
    const user = userEvent.setup();
    const { city } = renderInForm();
    await user.click(trigger());

    await user.keyboard("{ArrowDown}");
    expect(activeLabel()).toContain(t("homeSearch.cityAny"));
    await user.keyboard("{ArrowDown}");
    expect(activeLabel()).toBe("Скопје, Скопски регион, 10 профили");

    // → opens Skopje; a second → steps onto its first municipality.
    await user.keyboard("{ArrowRight}");
    const skopje = screen.getByRole("treeitem", { name: /^Скопје,/ });
    expect(skopje).toHaveAttribute("aria-expanded", "true");
    const kids = within(within(skopje).getByRole("group")).getAllByRole(
      "treeitem",
    );
    expect(kids).toHaveLength(17);
    expect(kids[0]).toHaveTextContent("Аеродром");
    await user.keyboard("{ArrowRight}");
    expect(activeLabel()).toContain("Аеродром");

    // ← back to the town, ← again closes it.
    await user.keyboard("{ArrowLeft}");
    expect(activeLabel()).toMatch(/^Скопје, Скопски регион, 10 профили/);
    await user.keyboard("{ArrowLeft}");
    expect(skopje).toHaveAttribute("aria-expanded", "false");

    await user.keyboard("{End}");
    expect(activeLabel()).toBe("Штип, Источен регион");
    await user.keyboard("{Home}");
    expect(activeLabel()).toContain(t("homeSearch.cityAny"));

    await user.keyboard("{ArrowDown}{ArrowDown}{Enter}");
    expect(city()).toBe("Битола");
    expect(trigger()).toHaveAccessibleName("Град или општина: Битола");
    expect(trigger()).toHaveFocus();
    expect(screen.queryByRole("tree")).toBeNull();
  });

  it("lets touch and screen-reader users open a town with a 48px button", async () => {
    const user = userEvent.setup();
    renderInForm();
    await user.click(trigger());

    const ohrid = screen.getByRole("treeitem", { name: /^Охрид,/ });
    const toggle = within(ohrid).getByRole("button", {
      name: tFormat("homeSearch.cityToggle", { city: "Охрид" }),
    });
    expect(toggle).toHaveAttribute("aria-expanded", "false");
    expect(toggle).toHaveClass("size-12");

    await user.click(toggle);
    expect(toggle).toHaveAttribute("aria-expanded", "true");
    expect(ohrid).toHaveAttribute("aria-expanded", "true");
    expect(within(ohrid).getByRole("group")).toHaveTextContent("Дебарца");
    // Opening is not picking, and focus stays in the filter box.
    expect(trigger()).toHaveAccessibleName(t("homeSearch.cityTriggerNone"));
    expect(filter()).toHaveFocus();

    await user.click(toggle);
    expect(toggle).toHaveAttribute("aria-expanded", "false");
  });

  it("says the town fallback once per open group, not on every row", async () => {
    const user = userEvent.setup();
    renderInForm();
    await user.click(trigger());
    await user.keyboard("{ArrowDown}{ArrowDown}{ArrowRight}");

    const skopje = screen.getByRole("treeitem", { name: /^Скопје,/ });
    const notes = skopje.querySelectorAll("[data-group-note]");
    expect(notes).toHaveLength(1);
    expect(notes[0]).toHaveTextContent(
      "Општините без свои профили пребаруваат низ Скопје",
    );
    const rows = within(within(skopje).getByRole("group")).getAllByRole(
      "treeitem",
    );
    for (const row of rows) {
      expect(row).not.toHaveTextContent(/резултати за|пребаруваат низ/);
    }

    // In Pelagonia, Прилеп holds listings: its row shows its count instead.
    await user.type(filter(), "прилеп");
    expect(screen.getByRole("treeitem", { name: /Прилеп/ })).toHaveTextContent(
      "Прилеп3 профили",
    );
    // …and the group has no fallback to explain.
    expect(document.querySelector("[data-group-note]")).toBeNull();
  });

  it("filters in Cyrillic and in Latin, showing matches under their town", async () => {
    const user = userEvent.setup();
    renderInForm();
    await user.click(trigger());

    await user.type(filter(), "karpos");
    let items = screen.getAllByRole("treeitem");
    expect(
      items.map((i) => i.getAttribute("aria-label") ?? i.textContent),
    ).toEqual([
      "Скопје, Скопски регион, 10 профили, Општините без свои профили пребаруваат низ Скопје",
      "Карпош",
    ]);
    expect(items[0]).toHaveAttribute("aria-expanded", "true");

    await user.clear(filter());
    await user.type(filter(), "ресен");
    items = screen.getAllByRole("treeitem");
    expect(items[1]).toHaveTextContent("Ресен");

    await user.clear(filter());
    await user.type(filter(), "атлантида");
    expect(screen.getByRole("status")).toHaveTextContent("атлантида");
  });

  it("sends a municipality's town: Skopje's own, a listed town, or the region's main town", async () => {
    const user = userEvent.setup();
    const { city } = renderInForm();

    await user.click(trigger());
    await user.type(filter(), "Карпош{Enter}");
    expect(city()).toBe("Скопје");
    expect(trigger()).toHaveAccessibleName("Град или општина: Карпош");

    await user.click(trigger());
    await user.type(filter(), "prilep{Enter}");
    expect(city()).toBe("Прилеп");

    await user.click(trigger());
    await user.type(filter(), "Ресен");
    expect(
      screen.getByRole("treeitem", { name: /^Битола,/ }),
    ).toHaveAccessibleName(
      "Битола, Пелагониски регион, 5 профили, Општините без свои профили пребаруваат низ Битола",
    );
    await user.keyboard("{Enter}");
    expect(city()).toBe("Битола");

    // „Сите градови“ clears it.
    await user.click(trigger());
    await user.click(
      screen.getByRole("treeitem", { name: t("homeSearch.cityAny") }),
    );
    expect(city()).toBeNull();
  });

  it("closes on Escape without submitting or choosing", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn((event: Event) => event.preventDefault());
    const { form, city } = renderInForm();
    form.addEventListener("submit", onSubmit);

    await user.click(trigger());
    await user.keyboard("{ArrowDown}{Escape}");
    expect(screen.queryByRole("tree")).toBeNull();
    expect(trigger()).toHaveFocus();
    expect(city()).toBeNull();

    // Enter inside the picker never submits the search around it.
    await user.click(trigger());
    await user.keyboard("{Enter}");
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("opens as a modal bottom sheet on phones", async () => {
    vi.stubGlobal(
      "matchMedia",
      (query: string) =>
        ({
          matches: false,
          media: query,
          addEventListener() {},
          removeEventListener() {},
        }) as unknown as MediaQueryList,
    );
    const user = userEvent.setup();
    renderInForm();
    await user.click(trigger());

    const sheet = screen.getByRole("dialog", {
      name: t("homeSearch.cityDialogTitle"),
    });
    expect(sheet).toHaveAttribute("aria-modal", "true");
    expect(within(sheet).getByRole("combobox")).toHaveFocus();
    expect(within(sheet).getByRole("tree")).toBeInTheDocument();
  });
});
