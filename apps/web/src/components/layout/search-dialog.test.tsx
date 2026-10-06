import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { AdvancedSearchTrigger } from "@/components/search/advanced-search-trigger";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { router } from "../../../test/next-navigation";

function renderPage() {
  return render(
    <SearchDialogProvider>
      <button type="button">Друго копче</button>
      <AdvancedSearchTrigger />
      <input aria-label="Друго поле" />
    </SearchDialogProvider>,
  );
}

function trigger() {
  return screen.getByRole("button", {
    name: new RegExp(`^${t("search.advancedButton")}`),
  });
}

function dialog() {
  return screen.queryByRole("dialog", { name: t("search.modalTitle") });
}

async function open() {
  const user = userEvent.setup();
  await user.click(trigger());
  expect(dialog()).toBeInTheDocument();

  return user;
}

describe("Advanced search dialog", () => {
  it("opens as a labelled modal dialog with focus inside it", async () => {
    renderPage();
    expect(dialog()).toBeNull();

    await open();

    expect(dialog()).toHaveAttribute("aria-modal", "true");
    expect(dialog()).toContainElement(document.activeElement as HTMLElement);
    expect(document.body.style.overflow).toBe("hidden");
  });

  it("opens from the keyboard", async () => {
    const user = userEvent.setup();
    renderPage();

    trigger().focus();
    await user.keyboard("{Enter}");
    expect(dialog()).toBeInTheDocument();
  });

  it("closes on Escape and returns focus to the button that opened it", async () => {
    renderPage();
    const user = await open();

    await user.keyboard("{Escape}");

    expect(dialog()).toBeNull();
    expect(trigger()).toHaveFocus();
    expect(document.body.style.overflow).toBe("");
  });

  it.each([
    [
      "the close button",
      () => screen.getByRole("button", { name: t("search.close") }),
    ],
    ["Cancel", () => screen.getByRole("button", { name: t("common.cancel") })],
  ])("closes with %s and returns focus", async (_, control) => {
    renderPage();
    const user = await open();

    await user.click(control());

    expect(dialog()).toBeNull();
    expect(trigger()).toHaveFocus();
  });

  it("closes on a backdrop press but not on a press inside the panel", async () => {
    renderPage();
    await open();

    fireEvent.mouseDown(dialog()!);
    expect(dialog()).toBeInTheDocument();

    fireEvent.mouseDown(dialog()!.parentElement!);
    expect(dialog()).toBeNull();
  });

  it("keeps Tab and Shift+Tab inside the dialog", async () => {
    renderPage();
    const user = await open();
    const panel = dialog()!;
    const close = screen.getByRole("button", { name: t("search.close") });
    const last = screen.getByRole("link", { name: t("search.fullPageLink") });

    expect(close).toHaveFocus();

    // Walk forward through every control; focus must never leave the panel.
    for (let i = 0; i < 12; i += 1) {
      await user.tab();
      expect(panel).toContainElement(document.activeElement as HTMLElement);
    }

    last.focus();
    await user.tab();
    expect(close).toHaveFocus();

    await user.tab({ shift: true });
    expect(last).toHaveFocus();
  });

  it("carries the typed name and city into each section link", async () => {
    renderPage();
    const user = await open();

    await user.type(screen.getByLabelText(t("search.nameLabel")), "кардио");
    await user.type(screen.getByLabelText(t("search.cityLabel")), "Битола");

    const link = screen.getByRole("link", {
      name: new RegExp(t("home.doctorsTitle")),
    });
    const href = new URL(link.getAttribute("href")!, "https://x.invalid");
    expect(href.pathname).toBe("/doctors");
    expect(href.searchParams.get("q")).toBe("кардио");
    expect(href.searchParams.get("city")).toBe("Битола");
  });

  it("sends Ctrl/Cmd+K to the search page, except while typing", async () => {
    const user = userEvent.setup();
    renderPage();

    screen.getByRole("button", { name: "Друго копче" }).focus();
    await user.keyboard("{Control>}k{/Control}");
    expect(router.push).toHaveBeenCalledWith("/search");

    router.push.mockClear();
    await user.click(screen.getByLabelText("Друго поле"));
    await user.keyboard("{Meta>}k{/Meta}");
    expect(router.push).not.toHaveBeenCalled();
  });

  it("has no serious accessibility violations when open", async () => {
    const { container } = renderPage();
    await open();

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
