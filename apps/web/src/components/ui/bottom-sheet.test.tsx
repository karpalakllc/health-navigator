import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it, vi } from "vitest";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

function Harness() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>
        Отвори
      </button>
      <BottomSheet
        open={open}
        onClose={() => setOpen(false)}
        title="Филтри"
        footer={<button type="button">Прикажи</button>}
      >
        <label>
          Град
          <input />
        </label>
      </BottomSheet>
    </>
  );
}

async function openSheet() {
  const user = userEvent.setup();
  render(<Harness />);
  await user.click(screen.getByRole("button", { name: "Отвори" }));
  return user;
}

describe("BottomSheet", () => {
  it("is a labelled modal dialog with focus inside and the page locked", async () => {
    await openSheet();
    const dialog = screen.getByRole("dialog", { name: "Филтри" });

    expect(dialog).toHaveAttribute("aria-modal", "true");
    expect(dialog).toContainElement(document.activeElement as HTMLElement);
    expect(document.body.style.overflow).toBe("hidden");
  });

  it("keeps Tab and Shift+Tab inside, footer included", async () => {
    const user = await openSheet();
    const dialog = screen.getByRole("dialog");
    const close = within(dialog).getByRole("button", {
      name: t("search.close"),
    });
    const footer = within(dialog).getByRole("button", { name: "Прикажи" });

    expect(close).toHaveFocus();
    for (let i = 0; i < 6; i += 1) {
      await user.tab();
      expect(dialog).toContainElement(document.activeElement as HTMLElement);
    }

    footer.focus();
    await user.tab();
    expect(close).toHaveFocus();
    await user.tab({ shift: true });
    expect(footer).toHaveFocus();
  });

  it("closes on Escape and hands focus back to the opener", async () => {
    const user = await openSheet();

    await user.keyboard("{Escape}");

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Отвори" })).toHaveFocus();
    expect(document.body.style.overflow).toBe("");
  });

  it("makes the rest of the page inert while open, and gives it back", async () => {
    const user = await openSheet();
    const opener = screen.getByRole("button", { name: "Отвори", hidden: true });
    const page = opener.closest("body > *") as HTMLElement;
    const dialog = screen.getByRole("dialog");

    expect(page).toHaveAttribute("inert");
    expect(dialog.closest("[inert]")).toBeNull();

    await user.keyboard("{Escape}");

    expect(page).not.toHaveAttribute("inert");
  });

  it("pins the body (iOS-safe scroll lock) and restores the scroll position", async () => {
    const scrollTo = vi.fn();
    vi.stubGlobal("scrollTo", scrollTo);
    Object.defineProperty(window, "scrollY", {
      value: 640,
      configurable: true,
    });
    try {
      const user = await openSheet();

      expect(document.body.style.position).toBe("fixed");
      expect(document.body.style.top).toBe("-640px");

      await user.keyboard("{Escape}");

      expect(document.body.style.position).toBe("");
      expect(document.body.style.top).toBe("");
      expect(scrollTo).toHaveBeenCalledWith(
        expect.objectContaining({ top: 640 }),
      );
    } finally {
      Object.defineProperty(window, "scrollY", {
        value: 0,
        configurable: true,
      });
      vi.unstubAllGlobals();
    }
  });

  it("closes from its × button", async () => {
    const user = await openSheet();

    await user.click(screen.getByRole("button", { name: t("search.close") }));

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("has no serious accessibility violations", async () => {
    await openSheet();

    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });
});
