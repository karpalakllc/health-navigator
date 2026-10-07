import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { ConsentBanner } from "@/components/layout/consent-banner";
import { ConsentSettingsButton } from "@/components/layout/consent-settings-button";
import {
  CONSENT_KEY,
  CONSENT_VERSION,
  readConsent,
  saveConsent,
  statisticsAllowed,
} from "@/lib/consent/consent";
import { t } from "@/i18n/t";
import { setPathname } from "../../../test/next-navigation";

beforeEach(() => {
  window.localStorage.removeItem(CONSENT_KEY);
  setPathname("/doctors");
});

afterEach(() => {
  window.localStorage.removeItem(CONSENT_KEY);
  document.body.style.overflow = "";
});

describe("ConsentBanner layer 1", () => {
  it("asks on a first visit, as a non-modal bottom dialog with equal accept and reject", () => {
    render(<ConsentBanner />);

    const banner = screen.getByRole("dialog", { name: t("consent.title") });
    expect(banner).toHaveAttribute("aria-modal", "false");
    const accept = within(banner).getByRole("button", {
      name: t("consent.acceptAll"),
    });
    const reject = within(banner).getByRole("button", {
      name: t("consent.decline"),
    });
    expect(accept.className).toBe(reject.className);
    expect(
      within(banner).getByRole("button", { name: t("consent.customize") }),
    ).toBeInTheDocument();
    expect(within(banner).getByRole("link")).toHaveAttribute(
      "href",
      "/privacy",
    );
    // No decision, no statistics.
    expect(statisticsAllowed()).toBe(false);
  });

  it("stores an accept and closes", async () => {
    const user = userEvent.setup();
    render(<ConsentBanner />);

    await user.click(
      screen.getByRole("button", { name: t("consent.acceptAll") }),
    );

    expect(screen.queryByRole("dialog")).toBeNull();
    expect(readConsent()).toMatchObject({
      statistics: true,
      version: CONSENT_VERSION,
    });
    expect(statisticsAllowed()).toBe(true);
  });

  it("stores a decline", async () => {
    const user = userEvent.setup();
    render(<ConsentBanner />);

    await user.click(
      screen.getByRole("button", { name: t("consent.decline") }),
    );

    expect(readConsent()?.statistics).toBe(false);
    expect(statisticsAllowed()).toBe(false);
  });

  it("stays away once decided, on /unsubscribe, and asks again for a new version", () => {
    saveConsent(false);
    const { unmount } = render(<ConsentBanner />);
    expect(screen.queryByRole("dialog")).toBeNull();
    unmount();

    window.localStorage.setItem(
      CONSENT_KEY,
      JSON.stringify({
        statistics: true,
        decidedAt: "2026-01-01T00:00:00Z",
        version: CONSENT_VERSION - 1,
      }),
    );
    setPathname("/unsubscribe");
    const second = render(<ConsentBanner />);
    expect(screen.queryByRole("dialog")).toBeNull();
    second.unmount();

    setPathname("/doctors");
    render(<ConsentBanner />);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(statisticsAllowed()).toBe(false);
  });
});

describe("ConsentBanner layer 2", () => {
  it("opens from Прилагоди as a modal with Statistics off, and Necessary on and disabled", async () => {
    const user = userEvent.setup();
    render(<ConsentBanner />);

    await user.click(
      screen.getByRole("button", { name: t("consent.customize") }),
    );

    const modal = screen.getByRole("dialog", { name: t("consent.modalTitle") });
    expect(modal).toHaveAttribute("aria-modal", "true");
    expect(modal).toHaveFocus();
    const necessary = within(modal).getByRole("switch", {
      name: new RegExp(t("consent.necessaryTitle")),
    });
    const statistics = within(modal).getByRole("switch", {
      name: new RegExp(t("consent.statisticsTitle")),
    });
    expect(necessary).toBeChecked();
    expect(necessary).toBeDisabled();
    expect(statistics).not.toBeChecked();

    await user.click(statistics);
    await user.click(
      within(modal).getByRole("button", { name: t("consent.save") }),
    );
    expect(readConsent()?.statistics).toBe(true);
    expect(screen.queryByRole("dialog")).toBeNull();
  });

  it("closes on Escape back to the banner and returns focus to the button", async () => {
    const user = userEvent.setup();
    render(<ConsentBanner />);
    const customize = screen.getByRole("button", {
      name: t("consent.customize"),
    });

    await user.click(customize);
    await user.keyboard("{Escape}");

    expect(
      screen.queryByRole("dialog", { name: t("consent.modalTitle") }),
    ).toBeNull();
    expect(
      screen.getByRole("dialog", { name: t("consent.title") }),
    ).toBeInTheDocument();
    expect(customize).toHaveFocus();
    expect(readConsent()).toBeNull();
  });

  it("keeps Tab inside the modal", async () => {
    const user = userEvent.setup();
    render(<ConsentBanner />);
    await user.click(
      screen.getByRole("button", { name: t("consent.customize") }),
    );
    const modal = screen.getByRole("dialog", { name: t("consent.modalTitle") });
    const last = within(modal).getByRole("button", {
      name: t("consent.declineAll"),
    });

    last.focus();
    await user.tab();
    expect(modal.contains(document.activeElement)).toBe(true);
    expect(document.activeElement).not.toBe(last);
    await user.tab({ shift: true });
    await user.tab({ shift: true });
    expect(modal.contains(document.activeElement)).toBe(true);
  });

  it("opens straight from the footer link, shows the stored choice, and withdraws", async () => {
    saveConsent(true);
    const user = userEvent.setup();
    render(
      <>
        <ConsentSettingsButton />
        <ConsentBanner />
      </>,
    );
    expect(screen.queryByRole("dialog")).toBeNull();

    const link = screen.getByRole("button", {
      name: t("footer.cookieSettings"),
    });
    await user.click(link);

    const modal = screen.getByRole("dialog", { name: t("consent.modalTitle") });
    expect(
      within(modal).getByRole("switch", {
        name: new RegExp(t("consent.statisticsTitle")),
      }),
    ).toBeChecked();

    await user.click(
      within(modal).getByRole("button", { name: t("consent.declineAll") }),
    );

    // Withdrawn at once, without a reload.
    expect(statisticsAllowed()).toBe(false);
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(link).toHaveFocus();
  });

  it("keeps working when storage is blocked", async () => {
    const user = userEvent.setup();
    const original = Storage.prototype.setItem;
    Storage.prototype.setItem = () => {
      throw new Error("blocked");
    };

    try {
      render(<ConsentBanner />);
      await user.click(
        screen.getByRole("button", { name: t("consent.decline") }),
      );
      expect(screen.queryByRole("dialog")).toBeNull();
      await act(async () => {});
    } finally {
      Storage.prototype.setItem = original;
    }
  });
});
