import { render, screen } from "@testing-library/react";
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

  it("shows the call links when the normal flow ends in the emergency outcome", async () => {
    // An admin-authored rule (or the API's fallback) can route the ordinary
    // question path to the emergency outcome; it must look like one.
    const user = await startQuestions();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(screen.getByRole("button", { name: t("common.next") }));
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
  });

  it("moves focus to the new step's heading on every step change", async () => {
    const user = await startQuestions();

    expect(
      screen.getByRole("heading", { name: "Возрасна група" }),
    ).toHaveFocus();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(screen.getByRole("button", { name: t("common.next") }));

    expect(
      await screen.findByRole("heading", {
        name: "Колку се изразени симптомите денес?",
      }),
    ).toHaveFocus();
  });

  it("goes back to the previous question, keeping its answer, then to the safety check", async () => {
    const user = await startQuestions();

    await user.click(screen.getByRole("radio", { name: "18–64 години" }));
    await user.click(screen.getByRole("button", { name: t("common.next") }));
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
