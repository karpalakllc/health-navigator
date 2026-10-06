import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { GuidanceWizard } from "@/components/guidance/guidance-wizard";
import {
  GuidanceApiError,
  type GuidanceFlow,
  type GuidanceOutcome,
} from "@/lib/api/guidance";
import { t } from "@/i18n/t";

// The wizard's network calls are replaced so these tests cover only what it
// renders and where it moves focus between steps.
const api = vi.hoisted(() => ({
  startGuidanceSession: vi.fn<() => Promise<{ id: string; token: string }>>(),
  saveGuidanceAnswers: vi.fn<() => Promise<void>>(),
  completeGuidanceSession: vi.fn<() => Promise<unknown>>(),
  completeGuidanceEmergency: vi.fn<() => Promise<unknown>>(),
}));

// Only the network calls: the session-handle parsing stays real.
vi.mock("@/lib/api/guidance", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api/guidance")>()),
  ...api,
}));

const flow: GuidanceFlow = {
  title: "Општи насоки за симптоми",
  intro_body: null,
  red_flags: [
    { code: "chest_pain", label: "Силна болка или притисок во градите" },
  ],
  steps: [
    {
      key: "age_band",
      type: "single_select",
      label: "Возрасна група",
      required: true,
      options: [
        { value: "under_18", label: "Помлади од 18 години" },
        { value: "18_64", label: "18–64 години" },
      ],
    },
    {
      key: "severity",
      type: "single_select",
      label: "Колку се изразени симптомите денес?",
      required: true,
      options: [{ value: "mild", label: "Благи" }],
    },
  ],
};

const emergency: GuidanceOutcome = {
  outcome_code: "emergency",
  title: "Веднаш побарајте итна помош",
  body: "Според вашите одговори, треба веднаш да ја повикате службата за итна помош.",
  handoffs: [{ type: "emergency", label: "Броеви за итни случаи" }],
};

beforeEach(() => {
  vi.resetAllMocks();
  window.sessionStorage.clear();
  api.startGuidanceSession.mockResolvedValue({
    id: "session-1",
    token: "token-1",
  });
  api.saveGuidanceAnswers.mockResolvedValue(undefined);
  api.completeGuidanceEmergency.mockResolvedValue(emergency);
  api.completeGuidanceSession.mockResolvedValue(emergency);
});

async function startQuestions() {
  const user = userEvent.setup();
  render(<GuidanceWizard flow={flow} />);
  await user.click(screen.getByRole("checkbox"));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: "Возрасна група" });

  return user;
}

describe("GuidanceWizard", () => {
  it("offers tap-to-call 194 and 112 links on the emergency outcome", async () => {
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);

    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    const heading = await screen.findByRole("heading", {
      name: emergency.title,
    });
    expect(heading).toHaveFocus();
    expect(
      screen.getByRole("link", { name: "Повикај 194 (Брза помош)" }),
    ).toHaveAttribute("href", "tel:194");
    expect(
      screen.getByRole("link", {
        name: "112 — единствен број за итни случаи",
      }),
    ).toHaveAttribute("href", "tel:112");
  });

  it("uses neutral copy, not „Според вашите одговори…“, after the urgent-help button", async () => {
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);

    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    await screen.findByRole("heading", { name: emergency.title });
    expect(
      screen.getByText(t("guidance.emergencyShortcutBody")),
    ).toBeInTheDocument();
    expect(screen.queryByText(emergency.body)).toBeNull();
  });

  it("keeps the urgent-help button compact on question steps, full width on the intro", async () => {
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);
    const intro = screen.getByRole("button", {
      name: t("guidance.emergencyNow"),
    });
    expect(intro).toHaveClass("w-full", "btn-lg");

    await user.click(screen.getByRole("checkbox"));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });

    const step = screen.getByRole("button", {
      name: t("guidance.emergencyNow"),
    });
    expect(step).toHaveClass("btn-md", "bg-emergency");
    expect(step).not.toHaveClass("w-full");
  });

  it("shows the call links when the normal flow ends in the emergency outcome", async () => {
    // An admin-authored rule (or the API's fallback) can route the ordinary
    // question path to the emergency outcome; it must look like one.
    const user = await startQuestions();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", {
      name: "Колку се изразени симптомите денес?",
    });
    await user.click(screen.getByRole("radio", { name: "Благи" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.seeGuidance") }),
    );

    await screen.findByRole("heading", { name: emergency.title });
    expect(
      screen.getByRole("link", { name: "Повикај 194 (Брза помош)" }),
    ).toHaveAttribute("href", "tel:194");
    // Reached through answers, so the outcome's own wording stays.
    expect(screen.getByText(emergency.body)).toBeInTheDocument();
  });

  it("moves focus to the new step's heading on every step change", async () => {
    const user = await startQuestions();

    expect(
      screen.getByRole("heading", { name: "Возрасна група" }),
    ).toHaveFocus();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      await screen.findByRole("heading", {
        name: "Колку се изразени симптомите денес?",
      }),
    ).toHaveFocus();
  });

  it("goes back to the previous question, keeping its answer, then to the safety check", async () => {
    const user = await startQuestions();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", {
      name: "Колку се изразени симптомите денес?",
    });

    await user.click(screen.getByRole("button", { name: t("common.back") }));
    expect(
      await screen.findByRole("heading", { name: "Возрасна група" }),
    ).toHaveFocus();
    expect(screen.getByRole("radio", { name: "18–64 години" })).toBeChecked();

    await user.click(screen.getByRole("button", { name: t("common.back") }));
    expect(
      await screen.findByRole("heading", { name: t("guidance.safetyCheck") }),
    ).toBeInTheDocument();
  });

  it("names answer options by their label and submits their value, not 'on'", async () => {
    await startQuestions();

    const option = screen.getByRole("radio", { name: "Помлади од 18 години" });
    expect(option).toHaveAttribute("value", "under_18");
    expect(option).toHaveAttribute("id");
    expect(
      document.querySelector(`label[for="${option.id}"]`) ??
        option.closest("label"),
    ).toHaveTextContent("Помлади од 18 години");
  });
});

/**
 * Asking for emergency help must always end at tap-to-call 194/112, whatever
 * the API does: the session cap (10/hour per address, shared on public wifi)
 * answers 429, a handle kept in sessionStorage can be stale, the network can
 * be down. These used to leave only an error message on the screen.
 */
describe("GuidanceWizard emergency path when the API fails", () => {
  function callLinks() {
    return [
      screen.getByRole("link", { name: t("guidance.call194") }),
      screen.getByRole("link", { name: t("guidance.call112") }),
    ];
  }

  async function chooseEmergency() {
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    return user;
  }

  it("shows the call links at once, before the API answers", async () => {
    api.completeGuidanceEmergency.mockReturnValue(new Promise(() => {}));

    await chooseEmergency();

    const [call194, call112] = callLinks();
    expect(call194).toHaveAttribute("href", "tel:194");
    expect(call112).toHaveAttribute("href", "tel:112");
    expect(
      screen.getByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toHaveFocus();
  });

  it("keeps the call links when no session can be started (rate limited)", async () => {
    api.startGuidanceSession.mockRejectedValue(
      new GuidanceApiError("Премногу барања.", 429),
    );

    await chooseEmergency();

    expect(await screen.findByText("Премногу барања.")).toBeInTheDocument();
    expect(callLinks()).toHaveLength(2);
    expect(api.startGuidanceSession).toHaveBeenCalledTimes(1);
  });

  it("keeps the call links when the network is down", async () => {
    api.completeGuidanceEmergency.mockRejectedValue(
      new TypeError("Failed to fetch"),
    );

    await chooseEmergency();

    expect(await screen.findByText("Failed to fetch")).toBeInTheDocument();
    expect(callLinks()).toHaveLength(2);
  });

  it.each([404, 422])(
    "drops a stale stored handle (%i) and retries once with a new session",
    async (status) => {
      window.sessionStorage.setItem(
        "guidance_session",
        JSON.stringify({ id: "stale", token: "old" }),
      );
      api.completeGuidanceEmergency
        .mockRejectedValueOnce(new GuidanceApiError("Not found.", status))
        .mockResolvedValueOnce(emergency);

      await chooseEmergency();

      expect(
        await screen.findByRole("heading", { name: emergency.title }),
      ).toHaveFocus();
      expect(api.completeGuidanceEmergency).toHaveBeenNthCalledWith(1, {
        id: "stale",
        token: "old",
      });
      expect(api.startGuidanceSession).toHaveBeenCalledTimes(1);
      expect(api.completeGuidanceEmergency).toHaveBeenNthCalledWith(2, {
        id: "session-1",
        token: "token-1",
      });
      expect(callLinks()).toHaveLength(2);
      expect(window.sessionStorage.getItem("guidance_session")).toBeNull();
    },
  );

  it("does not retry other errors", async () => {
    api.completeGuidanceEmergency.mockRejectedValue(
      new GuidanceApiError("Server error.", 500),
    );

    await chooseEmergency();

    expect(await screen.findByText("Server error.")).toBeInTheDocument();
    expect(api.completeGuidanceEmergency).toHaveBeenCalledTimes(1);
    expect(callLinks()).toHaveLength(2);
  });

  it("gives up after one retry on a second stale answer, keeping the links", async () => {
    api.completeGuidanceEmergency.mockRejectedValue(
      new GuidanceApiError("Not found.", 404),
    );

    await chooseEmergency();

    expect(await screen.findByText("Not found.")).toBeInTheDocument();
    expect(api.completeGuidanceEmergency).toHaveBeenCalledTimes(2);
    expect(callLinks()).toHaveLength(2);
  });

  it("shows the call links when a red flag is ticked even if saving fails", async () => {
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);
    await user.click(screen.getByRole("checkbox"));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
    api.saveGuidanceAnswers.mockRejectedValue(
      new GuidanceApiError("Server error.", 500),
    );

    await user.click(
      screen.getByRole("checkbox", { name: flow.red_flags[0].label }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(await screen.findByText("Server error.")).toBeInTheDocument();
    expect(callLinks()).toHaveLength(2);
  });
});

/** A promise the test settles by hand, to hold a request open. */
function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}

const generalInformation: GuidanceOutcome = {
  outcome_code: "general_information",
  title: "Општи информации",
  body: "Следете ги симптомите.",
  handoffs: [
    { type: "doctors", label: "Прегледајте лекари", href: "/doctors" },
    { type: "facilities", label: "Прегледајте установи", href: "/facilities" },
    { type: "emergency", label: "Броеви за итни случаи" },
  ],
};

async function reachLastQuestion(pharmaciesOn?: boolean) {
  const user = userEvent.setup();
  render(<GuidanceWizard flow={flow} pharmaciesOn={pharmaciesOn} />);
  await user.click(screen.getByRole("checkbox"));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: "Возрасна група" });
  await user.click(screen.getByRole("radio", { name: "18–64 години" }));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", {
    name: "Колку се изразени симптомите денес?",
  });
  await user.click(screen.getByRole("radio", { name: "Благи" }));

  return user;
}

/**
 * „Потребна ми е итна помош“ must work at any moment, including while an
 * ordinary answer is still saving (a slow connection is exactly when someone
 * may feel worse). Whatever that save does afterwards must not take the
 * emergency screen away.
 */
describe("GuidanceWizard emergency shortcut while a save is pending", () => {
  it("stays enabled during the save, and a late normal outcome does not replace the emergency screen", async () => {
    const user = await reachLastQuestion();
    const normal = deferred<unknown>();
    api.completeGuidanceSession.mockReturnValue(normal.promise);
    api.completeGuidanceEmergency.mockReturnValue(new Promise(() => {}));

    await user.click(
      screen.getByRole("button", { name: t("guidance.seeGuidance") }),
    );
    const shortcut = screen.getByRole("button", {
      name: t("guidance.emergencyNow"),
    });
    expect(shortcut).toBeEnabled();
    await user.click(shortcut);

    expect(
      screen.getByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toHaveFocus();

    await act(async () => normal.resolve(generalInformation));

    expect(
      screen.queryByRole("heading", { name: generalInformation.title }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("guidance.call194") }),
    ).toHaveAttribute("href", "tel:194");
    // The emergency save is still running and still says so.
    expect(screen.getByRole("status")).toHaveTextContent(t("guidance.saving"));
  });

  it("keeps the emergency outcome when the earlier save fails afterwards", async () => {
    const user = await reachLastQuestion();
    const normal = deferred<unknown>();
    api.completeGuidanceSession.mockReturnValue(normal.promise);

    await user.click(
      screen.getByRole("button", { name: t("guidance.seeGuidance") }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );
    await screen.findByRole("heading", { name: emergency.title });

    await act(async () =>
      normal.reject(new GuidanceApiError("Server error.", 500)),
    );

    expect(screen.queryByText("Server error.")).not.toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: emergency.title }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("guidance.call194") }),
    ).toHaveAttribute("href", "tel:194");
  });

  it("does not move on to the next question when the save lands after the shortcut", async () => {
    const user = await startQuestions();
    const save = deferred<void>();
    api.saveGuidanceAnswers.mockReturnValue(save.promise);
    api.completeGuidanceEmergency.mockReturnValue(new Promise(() => {}));

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );
    await act(async () => save.resolve());

    expect(
      screen.queryByRole("heading", {
        name: "Колку се изразени симптомите денес?",
      }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toBeInTheDocument();
  });
});

describe("GuidanceWizard outcome care ladder", () => {
  async function finishWith(outcome: GuidanceOutcome, pharmaciesOn = true) {
    api.completeGuidanceSession.mockResolvedValue(outcome);
    const user = await reachLastQuestion(pharmaciesOn);
    await user.click(
      screen.getByRole("button", { name: t("guidance.seeGuidance") }),
    );
    expect(
      await screen.findByRole("heading", { name: outcome.title }),
    ).toHaveFocus();

    return user;
  }

  function ladder() {
    return screen.getByRole("list", { name: t("guidance.ladderTitle") });
  }

  function currentRungs() {
    return within(ladder())
      .getAllByRole("listitem")
      .filter((rung) => rung.getAttribute("aria-current") === "step");
  }

  it("lists the four care levels in order, from home care to emergency", async () => {
    await finishWith(generalInformation);

    expect(ladder().tagName).toBe("OL");
    expect(
      within(ladder())
        .getAllByRole("listitem")
        .map((rung) => within(rung).getByRole("heading").textContent),
    ).toEqual([
      `1. ${t("guidance.ladderHomeTitle")}`,
      `2. ${t("guidance.ladderPharmacyTitle")}`,
      `3. ${t("guidance.ladderGpTitle")}`,
      `4. ${t("guidance.ladderEmergencyTitle")}`,
    ]);
  });

  it("marks only the outcome's level as the current step, in words as well as colour", async () => {
    await finishWith({ ...generalInformation, outcome_code: "seek_care_soon" });

    const current = currentRungs();
    expect(current).toHaveLength(1);
    expect(current[0]).toHaveTextContent(t("guidance.ladderGpTitle"));
    expect(current[0]).toHaveTextContent(t("guidance.ladderYourResult"));
    expect(screen.getAllByText(t("guidance.ladderYourResult"))).toHaveLength(1);
  });

  it("marks home care for the general-information outcome", async () => {
    await finishWith(generalInformation);

    expect(currentRungs()).toHaveLength(1);
    expect(currentRungs()[0]).toHaveTextContent(t("guidance.ladderHomeTitle"));
  });

  it("marks no level for an outcome code it does not know", async () => {
    await finishWith({ ...generalInformation, outcome_code: "custom_admin" });

    expect(currentRungs()).toHaveLength(0);
    expect(
      screen.queryByText(t("guidance.ladderYourResult")),
    ).not.toBeInTheDocument();
  });

  it("links the levels into the directory and to 194/112", async () => {
    await finishWith(generalInformation);

    expect(
      screen.getByRole("link", { name: t("guidance.ladderPharmacyLink") }),
    ).toHaveAttribute("href", "/pharmacies");
    expect(
      screen.getByRole("link", { name: t("guidance.ladderGpLink") }),
    ).toHaveAttribute("href", "/doctors");
    expect(screen.getByRole("link", { name: "194" })).toHaveAttribute(
      "href",
      "tel:194",
    );
    expect(screen.getByRole("link", { name: "112" })).toHaveAttribute(
      "href",
      "tel:112",
    );
    // The doctors handoff repeats the ladder and is dropped; the rest stay.
    expect(
      screen.queryByRole("link", { name: "Прегледајте лекари" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Прегледајте установи" }),
    ).toHaveAttribute("href", "/facilities");
  });

  it("does not link to pharmacies when that directory is switched off", async () => {
    await finishWith(generalInformation, false);

    expect(
      screen.queryByRole("link", { name: t("guidance.ladderPharmacyLink") }),
    ).not.toBeInTheDocument();
    expect(
      within(ladder()).getByText(t("guidance.ladderPharmacyTitle")),
    ).toBeInTheDocument();
  });

  it("starts a new check from the beginning, with a new session", async () => {
    const user = await finishWith(generalInformation);

    await user.click(
      screen.getByRole("button", { name: t("guidance.restart") }),
    );

    expect(
      screen.getByRole("heading", { level: 1, name: t("guidance.title") }),
    ).toHaveFocus();
    expect(screen.getByRole("checkbox")).not.toBeChecked();

    await user.click(screen.getByRole("checkbox"));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
    expect(api.startGuidanceSession).toHaveBeenCalledTimes(2);
  });
});

/*
 * Продолжи starts a session; pressing the emergency shortcut before that
 * request returns used to start a second one, and the first, landing late,
 * wrote its handle back into storage the emergency path had just cleared.
 */
describe("GuidanceWizard session start shared between handlers", () => {
  it("uses one session for Продолжи and the emergency shortcut, and leaves storage cleared", async () => {
    const first = deferred<{ id: string; token: string }>();
    api.startGuidanceSession
      .mockReturnValueOnce(first.promise)
      .mockResolvedValueOnce({ id: "session-2", token: "token-2" });
    const user = userEvent.setup();
    render(<GuidanceWizard flow={flow} />);

    await user.click(screen.getByRole("checkbox"));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );
    await screen.findByRole("heading", {
      name: t("guidance.emergencyInterimTitle"),
    });
    await act(async () => first.resolve({ id: "session-1", token: "token-1" }));
    await screen.findByRole("heading", { name: emergency.title });

    expect(api.startGuidanceSession).toHaveBeenCalledTimes(1);
    expect(api.completeGuidanceEmergency).toHaveBeenCalledWith({
      id: "session-1",
      token: "token-1",
    });
    expect(window.sessionStorage.getItem("guidance_session")).toBeNull();
  });
});
