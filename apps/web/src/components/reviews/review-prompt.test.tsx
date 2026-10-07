import { act, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  PROMPT_DWELL_MS,
  ReviewPrompt,
} from "@/components/reviews/review-prompt";
import { REVIEW_PROMPT_KEY } from "@/lib/review-prompt-storage";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";

const QUESTION = "Дали сте биле кај д-р Ана Петровска?";

function renderPrompt(props: Partial<Parameters<typeof ReviewPrompt>[0]> = {}) {
  return render(
    <ReviewPrompt
      kind="doctor"
      slug="ana"
      name="д-р Ана Петровска"
      isLoggedIn={false}
      basePath="/doctors/ana"
      {...props}
    />,
  );
}

/** Where the prompt's slot sits relative to the top of the viewport. */
function slotAt(top: number) {
  vi.spyOn(HTMLElement.prototype, "getBoundingClientRect").mockReturnValue({
    top,
  } as DOMRect);
}

function dwell() {
  act(() => {
    vi.advanceTimersByTime(PROMPT_DWELL_MS);
  });
}

describe("ReviewPrompt", () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("appears after a short dwell, below the fold, and only once per profile", () => {
    slotAt(5000);
    const { unmount } = renderPrompt();

    expect(screen.queryByText(QUESTION)).not.toBeInTheDocument();
    dwell();
    expect(screen.getByText(QUESTION)).toBeInTheDocument();
    expect(window.localStorage.getItem(REVIEW_PROMPT_KEY)).not.toContain("ana");

    unmount();
    renderPrompt();
    dwell();
    expect(screen.queryByText(QUESTION)).not.toBeInTheDocument();
  });

  it("counts as shown only once the card comes on screen", () => {
    const observed: { callback: IntersectionObserverCallback }[] = [];
    vi.stubGlobal(
      "IntersectionObserver",
      class {
        constructor(public callback: IntersectionObserverCallback) {
          observed.push(this);
        }
        observe() {}
        disconnect() {}
      },
    );
    slotAt(5000);
    const { unmount } = renderPrompt();
    dwell();

    // Revealed below the fold, but the visitor has not scrolled to it.
    expect(screen.getByText(QUESTION)).toBeInTheDocument();
    expect(window.localStorage.getItem(REVIEW_PROMPT_KEY)).toBeNull();

    act(() => {
      observed[observed.length - 1].callback(
        [{ isIntersecting: true } as IntersectionObserverEntry],
        {} as IntersectionObserver,
      );
    });
    expect(window.localStorage.getItem(REVIEW_PROMPT_KEY)).not.toBeNull();

    unmount();
    renderPrompt();
    dwell();
    expect(screen.queryByText(QUESTION)).not.toBeInTheDocument();
  });

  it("never appears where the visitor is reading (no layout shift)", () => {
    slotAt(100);
    renderPrompt();

    dwell();

    expect(screen.queryByText(QUESTION)).not.toBeInTheDocument();
    expect(window.localStorage.getItem(REVIEW_PROMPT_KEY)).toBeNull();
  });

  it("is not a dialog, and can be dismissed", async () => {
    slotAt(5000);
    renderPrompt();
    dwell();

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    await userEvent
      .setup({ advanceTimers: vi.advanceTimersByTime })
      .click(screen.getByRole("button", { name: t("reviewFlow.promptLater") }));

    expect(screen.queryByText(QUESTION)).not.toBeInTheDocument();
  });

  it("sends a guest to sign in and back to the form, without a reminder box", () => {
    slotAt(5000);
    renderPrompt();
    dwell();

    expect(
      screen.getByRole("link", { name: t("reviewFlow.promptRate") }),
    ).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/doctors/ana#review-form")}`,
    );
    expect(
      screen.queryByLabelText(t("reviewFlow.promptRemind")),
    ).not.toBeInTheDocument();
  });

  it("words places differently", () => {
    slotAt(5000);
    renderPrompt({ kind: "facility", name: "Клиника Здравје" });
    dwell();

    expect(
      screen.getByText("Дали сте биле во Клиника Здравје?"),
    ).toBeInTheDocument();
  });

  it("lets a member ask for one reminder and take it back", async () => {
    slotAt(5000);
    const fetch = mockFetch(
      {
        status: 201,
        body: { data: { id: 7, remind_at: "2026-10-21T10:00:00+02:00" } },
      },
      { status: 200, body: { data: { deleted: true } } },
    );
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    const { container } = renderPrompt({ isLoggedIn: true });
    dwell();

    await user.click(screen.getByLabelText(t("reviewFlow.promptRemind")));

    expect(fetch.mock.calls[0][0]).toBe("/api/review-reminders");
    expect(requestBody(fetch)).toEqual({ kind: "doctor", slug: "ana" });
    expect(await screen.findByRole("status")).toHaveTextContent(
      "Ќе ве потсетиме на 21 октомври 2026.",
    );
    expect(await seriousA11yViolations(container)).toEqual([]);

    await user.click(screen.getByLabelText(t("reviewFlow.promptRemind")));
    expect(fetch.mock.calls[1][0]).toBe("/api/review-reminders/7");
    expect(await screen.findByRole("status")).toHaveTextContent(
      t("reviewFlow.promptReminderRemoved"),
    );
  });
});
