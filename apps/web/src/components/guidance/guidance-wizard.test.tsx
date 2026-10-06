import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { GuidanceWizard } from "@/components/guidance/guidance-wizard";
import type { GuidanceFlow, GuidanceOutcome } from "@/lib/api/guidance";
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
