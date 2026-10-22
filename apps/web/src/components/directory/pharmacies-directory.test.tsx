import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  PharmaciesDirectory,
  type PharmaciesFilterValues,
} from "@/components/directory/pharmacies-directory";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { router } from "../../../test/next-navigation";

const none: PharmaciesFilterValues = {
  q: "",
  city: "",
  verified: "",
  on_duty: "",
};

function renderList(applied: Partial<PharmaciesFilterValues> = {}) {
  return render(
    <PharmaciesDirectory applied={{ ...none, ...applied }} total={3}>
      <p>резултати</p>
    </PharmaciesDirectory>,
  );
}

async function openSheet(applied: Partial<PharmaciesFilterValues> = {}) {
  const user = userEvent.setup();
  renderList(applied);
  await user.click(screen.getByRole("button", { name: /^Филтри/ }));
  return { user, sheet: screen.getByRole("dialog", { name: "Филтри" }) };
}

describe("Pharmacies filters: „Само дежурни денес“", () => {
  beforeEach(() => {
    vi.stubGlobal("requestAnimationFrame", (cb: FrameRequestCallback) =>
      setTimeout(() => cb(0), 0),
    );
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("applies on_duty=1 in a shareable URL, keeping the city", async () => {
    const { user, sheet } = await openSheet({ city: "Битола" });

    const group = within(sheet).getByRole("group", {
      name: t("pharmacies.onDutyLegend"),
    });
    await user.click(
      within(group).getByRole("switch", { name: t("pharmacies.onDutyFilter") }),
    );

    expect(router.push).toHaveBeenCalledWith(
      `/pharmacies?${new URLSearchParams({ city: "Битола", on_duty: "1" })}`,
      { scroll: false },
    );
  });

  it("shows an applied toggle as on, and as a removable filter", async () => {
    const { sheet } = await openSheet({ on_duty: "1" });

    expect(
      within(sheet).getByRole("switch", { name: t("pharmacies.onDutyFilter") }),
    ).toBeChecked();
    expect(
      screen.getAllByRole("link", {
        name: tFormat("ui.removeFilter", {
          label: t("pharmacies.onDutyFilter"),
        }),
      }).length,
    ).toBeGreaterThan(0);
  });

  it("has no serious accessibility violations while open", async () => {
    await openSheet({ city: "Битола", on_duty: "1" });

    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });
});
