import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  DoctorsDirectory,
  type DoctorsFilterValues,
} from "@/components/directory/doctors-directory";
import type { Specialty } from "@/lib/api/types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { router } from "../../../test/next-navigation";

const specialties: Specialty[] = [
  {
    slug: "kardiologija",
    name: "Кардиологија",
    description: null,
    doctors_count: 3,
  },
  {
    slug: "pedijatrija",
    name: "Педијатрија",
    description: null,
    doctors_count: 2,
  },
];

const none: DoctorsFilterValues = {
  q: "",
  specialty: "",
  city: "",
  min_reviews: "",
  sort: "",
};

function renderList(applied: Partial<DoctorsFilterValues> = {}, total = 12) {
  return render(
    <DoctorsDirectory
      specialties={specialties}
      applied={{ ...none, ...applied }}
      total={total}
    >
      <p>резултати</p>
    </DoctorsDirectory>,
  );
}

function filtersButton() {
  return screen.getByRole("button", { name: /^Филтри/ });
}

async function openSheet(applied: Partial<DoctorsFilterValues> = {}) {
  const user = userEvent.setup();
  renderList(applied);
  await user.click(filtersButton());
  return { user, sheet: screen.getByRole("dialog", { name: "Филтри" }) };
}

describe("Doctors filter sheet", () => {
  beforeEach(() => {
    // jsdom has no layout: let requestAnimationFrame run synchronously-ish.
    vi.stubGlobal("requestAnimationFrame", (cb: FrameRequestCallback) =>
      setTimeout(() => cb(0), 0),
    );
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("counts the active filters on its button (the query is the strip's)", () => {
    renderList({ specialty: "kardiologija", city: "Скопје", q: "Ана" });

    expect(filtersButton()).toHaveTextContent("Филтри (2)");
    expect(filtersButton()).toHaveAttribute("aria-haspopup", "dialog");
  });

  it("opens as a modal dialog with focus inside", async () => {
    const { sheet } = await openSheet();

    expect(sheet).toHaveAttribute("aria-modal", "true");
    expect(sheet).toContainElement(document.activeElement as HTMLElement);
    expect(filtersButton()).toHaveAttribute("aria-expanded", "true");
  });

  it("traps Tab inside the sheet", async () => {
    const { user, sheet } = await openSheet();

    for (let i = 0; i < 20; i += 1) {
      await user.tab();
      expect(sheet).toContainElement(document.activeElement as HTMLElement);
    }
  });

  it("closes on Escape and returns focus to „Филтри“", async () => {
    const { user } = await openSheet();

    await user.keyboard("{Escape}");

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(filtersButton()).toHaveFocus();
  });

  it("names the result count on its sticky button, pluralised", async () => {
    const { sheet } = await openSheet();

    expect(
      within(sheet).getByRole("button", { name: "Прикажи 12 резултати" }),
    ).toBeInTheDocument();
  });

  it("uses the singular for one result", async () => {
    const user = userEvent.setup();
    renderList({}, 1);
    await user.click(filtersButton());

    expect(
      screen.getByRole("button", { name: "Прикажи 1 резултат" }),
    ).toBeInTheDocument();
  });

  it("applies a choice at once as a new history entry, without scrolling", async () => {
    const { user, sheet } = await openSheet();

    await user.click(within(sheet).getByRole("radio", { name: "Педијатрија" }));

    // push, so the browser's Back undoes the filter.
    expect(router.push).toHaveBeenCalledWith("/doctors?specialty=pedijatrija", {
      scroll: false,
    });
    expect(router.replace).not.toHaveBeenCalled();
  });

  it("only stages a choice moved with the arrow keys; Enter applies it", async () => {
    const { user, sheet } = await openSheet();
    const all = within(sheet).getByRole("radio", {
      name: t("doctors.allSpecialties"),
    });

    all.focus();
    await user.keyboard("{ArrowRight}");
    await user.keyboard("{ArrowRight}");

    expect(
      within(sheet).getByRole("radio", { name: "Педијатрија" }),
    ).toBeChecked();
    expect(router.push).not.toHaveBeenCalled();
    expect(router.replace).not.toHaveBeenCalled();

    await user.keyboard("{Enter}");

    expect(router.push).toHaveBeenCalledTimes(1);
    expect(router.push).toHaveBeenCalledWith("/doctors?specialty=pedijatrija", {
      scroll: false,
    });
  });

  it("applies a staged arrow-key choice when focus leaves the group", async () => {
    const { user, sheet } = await openSheet();

    within(sheet)
      .getByRole("radio", { name: t("doctors.allSpecialties") })
      .focus();
    await user.keyboard("{ArrowRight}");
    expect(router.push).not.toHaveBeenCalled();

    await user.tab();

    expect(router.push).toHaveBeenCalledWith(
      "/doctors?specialty=kardiologija",
      { scroll: false },
    );
  });

  it("waits for a pause in typing before applying the city", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    try {
      const user = userEvent.setup({
        advanceTimers: vi.advanceTimersByTime,
      });
      renderList();
      await user.click(filtersButton());
      await user.type(
        within(screen.getByRole("dialog")).getByRole("searchbox", {
          name: "Град",
        }),
        "Битола",
      );

      expect(router.replace).not.toHaveBeenCalled();
      await act(() => vi.advanceTimersByTimeAsync(700));
      expect(router.replace).toHaveBeenCalledTimes(1);
      expect(router.replace).toHaveBeenCalledWith(
        `/doctors?city=${encodeURIComponent("Битола")}`,
        { scroll: false },
      );
      // Typing replaces the entry; it never stacks one per pause.
      expect(router.push).not.toHaveBeenCalled();
    } finally {
      vi.useRealTimers();
    }
  });

  it("drops a pending typing debounce when a link is followed", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    try {
      const user = userEvent.setup({
        advanceTimers: vi.advanceTimersByTime,
      });
      render(
        <DoctorsDirectory specialties={specialties} applied={none} total={3}>
          {/* A plain anchor stands in for a result card's <Link> (which
              renders one); the click reaches the document the same way. */}
          {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
          <a href="/doctors/ana">д-р Ана</a>
        </DoctorsDirectory>,
      );
      await user.type(
        screen.getAllByRole("searchbox", { name: t("doctors.queryLabel") })[0],
        "Ан",
      );
      const link = screen.getByRole("link", { name: "д-р Ана" });
      link.addEventListener("click", (event) => event.preventDefault());
      await user.click(link);
      await act(() => vi.advanceTimersByTimeAsync(700));

      expect(router.replace).not.toHaveBeenCalled();
      expect(router.push).not.toHaveBeenCalled();
    } finally {
      vi.useRealTimers();
    }
  });

  it("„Исчисти“ resets the sheet's filters but keeps the query", async () => {
    const { user, sheet } = await openSheet({
      q: "Ана",
      specialty: "kardiologija",
      min_reviews: "1",
    });

    await user.click(within(sheet).getByRole("button", { name: "Исчисти" }));

    expect(router.push).toHaveBeenCalledWith(
      `/doctors?q=${encodeURIComponent("Ана")}`,
      { scroll: false },
    );
  });

  it("„Прикажи N резултати“ closes the sheet and lands on the results", async () => {
    const { user, sheet } = await openSheet();

    await user.click(
      within(sheet).getByRole("button", { name: "Прикажи 12 резултати" }),
    );
    await act(() => new Promise((resolve) => setTimeout(resolve, 5)));

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(
      screen.getByRole("heading", { level: 1, name: t("doctors.title") }),
    ).toHaveFocus();
  });

  it("has no serious accessibility violations while open", async () => {
    await openSheet({ specialty: "kardiologija" });

    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });
});

describe("Doctors active filters", () => {
  it("can each be removed, without JS, by a named link", () => {
    renderList({ specialty: "kardiologija", city: "Скопје" });

    expect(
      screen.getByRole("link", { name: "Отстрани филтер: Кардиологија" }),
    ).toHaveAttribute("href", `/doctors?city=${encodeURIComponent("Скопје")}`);
    expect(
      screen.getByRole("link", { name: "Отстрани филтер: Скопје" }),
    ).toHaveAttribute("href", "/doctors?specialty=kardiologija");
  });

  it("removing one (with JS) pushes the new URL and moves focus to the results heading", async () => {
    const user = userEvent.setup();
    renderList({ specialty: "kardiologija", city: "Скопје" });

    await user.click(
      screen.getByRole("link", { name: "Отстрани филтер: Скопје" }),
    );

    expect(router.push).toHaveBeenCalledWith(
      "/doctors?specialty=kardiologija",
      { scroll: false },
    );
    expect(
      screen.getByRole("heading", { level: 1, name: t("doctors.title") }),
    ).toHaveFocus();
  });
});

describe("Doctors search landmark", () => {
  it("is named, so it is told apart from the header's site search", () => {
    renderList();

    expect(
      screen.getByRole("search", { name: t("directory.searchLabel") }),
    ).toBeInTheDocument();
  });
});
