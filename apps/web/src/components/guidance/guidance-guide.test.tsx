import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { GuidanceGuide } from "@/components/guidance/guidance-guide";
import type {
  GuidanceCatalog,
  GuidanceOutcomeV2,
  GuidanceState,
} from "@/lib/api/guidance-v2";
import { t } from "@/i18n/t";

// The guide's network calls are replaced: these tests cover what it renders,
// in which order, and that the emergency path never waits on the API.
const api = vi.hoisted(() => ({
  startSession: vi.fn(),
  fetchState: vi.fn(),
  saveDemographics: vi.fn(),
  chooseSymptoms: vi.fn(),
  answerScreen: vi.fn(),
  answerQuestion: vi.fn(),
  emergencyShortcut: vi.fn(),
  noMatch: vi.fn(),
}));

vi.mock("@/lib/api/guidance-v2", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api/guidance-v2")>()),
  ...api,
}));

// Feedback and drop-off counters are recorded, not sent.
const feedback = vi.hoisted(() => ({
  recordFunnelStep: vi.fn(),
  sendFeedback: vi.fn(),
}));

vi.mock("@/lib/feedback", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/feedback")>()),
  ...feedback,
}));

const catalog: GuidanceCatalog = {
  max_symptoms: 3,
  flows: [
    {
      key: "headache",
      title: "Главоболка",
      body_areas: ["head"],
      search_terms: ["глава", "мигрена", "болка"],
      age_bands: ["adult_18_64"],
      urgency_rank: 50,
    },
    {
      key: "cough",
      title: "Кашлица",
      body_areas: ["chest"],
      search_terms: ["кашлам", "искашлување", "гради"],
      age_bands: ["adult_18_64"],
      urgency_rank: 40,
    },
    {
      key: "rash",
      title: "Осип",
      body_areas: ["skin"],
      search_terms: ["дамки", "кожа", "чешање"],
      age_bands: ["adult_18_64"],
      urgency_rank: 30,
    },
    {
      key: "back",
      title: "Болка во грбот",
      body_areas: ["back"],
      search_terms: ["грб", "крста", "болка"],
      age_bands: ["adult_18_64"],
      urgency_rank: 20,
    },
    {
      key: "infant-fever",
      title: "Температура кај бебе",
      body_areas: ["general"],
      search_terms: ["бебе", "треска", "температура"],
      age_bands: ["infant_0_3m"],
      urgency_rank: 90,
    },
  ],
};

const emergencyOutcome: GuidanceOutcomeV2 = {
  id: "emergency_now",
  level: "emergency_now",
  crisis: false,
  title: "Веднаш јавете се на 194",
  summary: "Според вашите одговори, ова може да е итна состојба.",
  reasons: ["Означивте знак."],
  do_now: ["Следете ги упатствата на диспечерот."],
  watch_for: [],
  call: [
    { number: "194", label: "Итна медицинска помош" },
    { number: "112", label: "Единствен број" },
  ],
  care: {
    setting: "emergency_department",
    specialties: [],
    facility_types: [],
  },
};

const gpOutcome: GuidanceOutcomeV2 = {
  id: "o_gp",
  level: "see_gp_this_week",
  crisis: false,
  title: "Закажете преглед кај матичниот лекар",
  summary: "Добро е да ве погледне лекар.",
  reasons: ["Главоболката трае повеќе од една недела."],
  do_now: ["Закажете преглед оваа недела."],
  watch_for: ["Ако се појави вкочанет врат, веднаш јавете се на 194."],
  call: [],
  care: {
    setting: "gp",
    specialties: [
      { key: "opsta-medicina", name: "Општа медицина", slug: null },
    ],
    facility_types: [],
  },
};

const S = "s1";
const symptomsState: GuidanceState = {
  session_id: S,
  stage: "symptoms",
  age_band: "adult_18_64",
};
const screenState: GuidanceState = {
  session_id: S,
  stage: "screen",
  flows: [{ key: "headache", title: "Главоболка" }],
  screen: [
    {
      code: "global.chest_pain",
      label: "Силна болка во градите",
      help: null,
      group: null,
    },
    {
      code: "global.self_harm",
      label: "Мисли за самоповредување или самоубиство",
      help: null,
      group: null,
    },
    {
      code: "headache.worst_ever",
      label: "Најсилната главоболка во животот",
      help: null,
      group: "headache",
    },
  ],
};
const q1: GuidanceState = {
  session_id: S,
  stage: "question",
  flows: [{ key: "headache", title: "Главоболка" }],
  flow: { key: "headache", title: "Главоболка", position: 0 },
  node: {
    id: "q_days",
    type: "question",
    kind: "number",
    text: "Колку дена ве боли главата?",
    unit: "days",
    alt_units: ["hours"],
    min: 0,
    max: 365,
  },
  progress: { answered: 0, remaining_max: 2 },
  path: [],
};
const q2: GuidanceState = {
  ...q1,
  node: {
    id: "q_fever",
    type: "question",
    kind: "yes_no",
    text: "Дали имате температура?",
  },
  progress: { answered: 1, remaining_max: 1 },
} as GuidanceState;
const result = (
  outcome: GuidanceOutcomeV2,
  reason: "answers" | "red_flag" | "shortcut" = "answers",
): GuidanceState => ({
  session_id: S,
  stage: "result",
  emergency_stopped: outcome.level === "emergency_now",
  level: outcome.level,
  reason,
  flows: [{ key: "headache", title: "Главоболка" }],
  outcomes: [
    {
      flow:
        reason === "answers" ? { key: "headache", title: "Главоболка" } : null,
      outcome,
    },
  ],
});

beforeEach(() => {
  vi.resetAllMocks();
  feedback.sendFeedback.mockResolvedValue(undefined);
  window.sessionStorage.clear();
  api.startSession.mockResolvedValue({
    handle: { id: S, token: "tok" },
    state: { session_id: S, stage: "demographics" },
  });
  api.saveDemographics.mockResolvedValue(symptomsState);
  api.chooseSymptoms.mockResolvedValue(screenState);
  api.answerScreen.mockResolvedValue(q1);
  api.emergencyShortcut.mockResolvedValue(result(emergencyOutcome, "shortcut"));
});

function callLinks() {
  return [
    screen.getByRole("link", { name: t("guidance.call194") }),
    screen.getByRole("link", { name: t("guidance.call112") }),
  ];
}

async function start() {
  const user = userEvent.setup();
  render(<GuidanceGuide catalog={catalog} />);
  await user.click(screen.getByRole("checkbox"));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: t("guidance.forWhomTitle") });

  return user;
}

async function toSymptoms(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByRole("textbox", { name: /Возраст/ }), "40");
  await user.click(screen.getByRole("radio", { name: t("guidance.sexMale") }));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: t("guidance.symptomsTitle") });
}

async function toQuestion(user: ReturnType<typeof userEvent.setup>) {
  await toSymptoms(user);
  await user.click(screen.getByRole("checkbox", { name: "Главоболка" }));
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
  await user.click(
    screen.getByRole("button", { name: t("guidance.continue") }),
  );
  await screen.findByRole("heading", { name: "Колку дена ве боли главата?" });
}

describe("GuidanceGuide emergency path", () => {
  it("shows the 194/112 tel links at once, before the API answers", async () => {
    api.emergencyShortcut.mockReturnValue(new Promise(() => {}));
    const user = userEvent.setup();
    render(<GuidanceGuide catalog={catalog} />);

    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    expect(
      await screen.findByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toHaveFocus();
    const [l194, l112] = callLinks();
    expect(l194).toHaveAttribute("href", "tel:194");
    expect(l112).toHaveAttribute("href", "tel:112");
  });

  it("keeps the call links when no session can be started", async () => {
    api.startSession.mockRejectedValue(new TypeError("Failed to fetch"));
    const user = userEvent.setup();
    render(<GuidanceGuide catalog={catalog} />);

    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    expect(await screen.findByText("Failed to fetch")).toBeInTheDocument();
    expect(callLinks()).toHaveLength(2);
  });

  it("ends in the emergency outcome with neutral copy after the urgent-help button", async () => {
    const user = await start();
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    expect(
      await screen.findByRole("heading", { name: emergencyOutcome.title }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("guidance.emergencyShortcutBody")),
    ).toBeInTheDocument();
    expect(screen.queryByText(emergencyOutcome.summary)).toBeNull();
    expect(callLinks()).toHaveLength(2);
  });

  it("goes straight to the emergency screen when a red flag is ticked", async () => {
    api.answerScreen.mockResolvedValue(result(emergencyOutcome, "red_flag"));
    const user = await start();
    await toSymptoms(user);
    await user.click(screen.getByRole("checkbox", { name: "Главоболка" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });

    // Grouped: the global screen, then the symptom's own flags.
    expect(
      screen.getByRole("group", { name: t("guidance.screenGroupGeneral") }),
    ).toBeInTheDocument();
    await user.click(
      screen.getByRole("checkbox", {
        name: "Најсилната главоболка во животот",
      }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      await screen.findByRole("heading", { name: emergencyOutcome.title }),
    ).toBeInTheDocument();
    expect(api.answerScreen).toHaveBeenCalledWith({ id: S, token: "tok" }, [
      "headache.worst_ever",
    ]);
    expect(
      screen.getByText("Следете ги упатствата на диспечерот."),
    ).toBeInTheDocument();
    expect(callLinks()).toHaveLength(2);
    expect(screen.queryByRole("button", { name: t("common.back") })).toBeNull();
  });

  it("shows the crisis wording with 194/112 when the API fails after self-harm is ticked", async () => {
    api.answerScreen.mockRejectedValue(new TypeError("Failed to fetch"));
    const user = await start();
    await toSymptoms(user);
    await user.click(screen.getByRole("checkbox", { name: "Главоболка" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
    await user.click(
      screen.getByRole("checkbox", {
        name: "Мисли за самоповредување или самоубиство",
      }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      await screen.findByRole("heading", {
        name: t("guidance.emergencyCrisisTitle"),
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("guidance.emergencyCrisisBody")),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toBeNull();
    const [l194, l112] = callLinks();
    expect(l194).toHaveAttribute("href", "tel:194");
    expect(l112).toHaveAttribute("href", "tel:112");
  });

  it("keeps the generic emergency card when another red flag is ticked and the API fails", async () => {
    api.answerScreen.mockRejectedValue(new TypeError("Failed to fetch"));
    const user = await start();
    await toSymptoms(user);
    await user.click(screen.getByRole("checkbox", { name: "Главоболка" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
    await user.click(
      screen.getByRole("checkbox", { name: "Силна болка во градите" }),
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      await screen.findByRole("heading", {
        name: t("guidance.emergencyInterimTitle"),
      }),
    ).toBeInTheDocument();
  });

  it("keeps the emergency screen when an earlier answer lands after the shortcut", async () => {
    let finishScreen: (s: GuidanceState) => void = () => {};
    api.answerScreen.mockReturnValue(
      new Promise((resolve) => (finishScreen = resolve)),
    );
    const user = await start();
    await toSymptoms(user);
    await user.click(screen.getByRole("checkbox", { name: "Главоболка" }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: t("guidance.safetyCheck") });
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    // Saving: the urgent-help button stays enabled.
    const urgent = screen.getByRole("button", {
      name: t("guidance.emergencyNow"),
    });
    expect(urgent).toBeEnabled();
    await user.click(urgent);
    await screen.findByRole("heading", { name: emergencyOutcome.title });

    await act(async () => finishScreen(q1));

    expect(
      screen.queryByRole("heading", { name: "Колку дена ве боли главата?" }),
    ).toBeNull();
    expect(
      screen.getByRole("heading", { name: emergencyOutcome.title }),
    ).toBeInTheDocument();
  });
});

describe("GuidanceGuide intro", () => {
  it("states the emergency message once: the 194/112 line and the urgent-help button, no reminder card", () => {
    render(<GuidanceGuide catalog={catalog} />);

    expect(
      document.querySelectorAll("[data-guidance-compact-emergency]"),
    ).toHaveLength(1);
    expect(
      screen.getAllByRole("button", { name: t("guidance.emergencyNow") }),
    ).toHaveLength(1);
    expect(document.querySelector("#guidance-red-flags-reminder")).toBeNull();
    expect(screen.queryByText(t("guidance.emergencyDelay"))).toBeNull();
    expect(
      screen.getByText(t("guidance.notDiagnosisBody")),
    ).toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "194" })).toHaveLength(1);
  });
});

describe("GuidanceGuide steps", () => {
  it("carries the compact 194/112 tel line and the urgent-help button on every step", async () => {
    const user = await start();
    const check = () => {
      const line = document.querySelector(
        "[data-guidance-compact-emergency]",
      ) as HTMLElement;
      expect(within(line).getByRole("link", { name: "194" })).toHaveAttribute(
        "href",
        "tel:194",
      );
      expect(within(line).getByRole("link", { name: "112" })).toHaveAttribute(
        "href",
        "tel:112",
      );
      expect(
        screen.getByRole("button", { name: t("guidance.emergencyNow") }),
      ).toBeInTheDocument();
    };

    check();
    await toQuestion(user);
    check();
  });

  it("asks about pregnancy only where it can apply", async () => {
    const user = await start();
    await user.type(screen.getByRole("textbox", { name: /Возраст/ }), "30");
    await user.click(
      screen.getByRole("radio", { name: t("guidance.sexMale") }),
    );
    expect(
      screen.queryByRole("group", { name: t("guidance.pregnancyLabel") }),
    ).toBeNull();

    await user.click(
      screen.getByRole("radio", { name: t("guidance.sexFemale") }),
    );
    expect(
      screen.getByRole("group", { name: t("guidance.pregnancyLabel") }),
    ).toBeInTheDocument();
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    expect(
      screen.getByText(t("guidance.pregnancyRequired")),
    ).toBeInTheDocument();
    expect(api.saveDemographics).not.toHaveBeenCalled();
  });

  it("finds symptoms by Latin search, only for the visitor's age, at most three", async () => {
    const user = await start();
    await toSymptoms(user);

    expect(
      screen.queryByRole("checkbox", { name: "Температура кај бебе" }),
    ).toBeNull();

    await user.type(
      screen.getByRole("searchbox", { name: t("guidance.searchLabel") }),
      "kaslam",
    );
    expect(
      screen.getByRole("checkbox", { name: "Кашлица" }),
    ).toBeInTheDocument();
    expect(screen.queryByRole("checkbox", { name: "Главоболка" })).toBeNull();

    await user.clear(
      screen.getByRole("searchbox", { name: t("guidance.searchLabel") }),
    );
    for (const name of ["Главоболка", "Кашлица", "Осип"]) {
      await user.click(screen.getByRole("checkbox", { name }));
    }
    expect(
      screen.getByRole("checkbox", { name: "Болка во грбот" }),
    ).toBeDisabled();
  });

  it("filters by body area through the labelled list", async () => {
    const user = await start();
    await toSymptoms(user);

    await user.click(
      screen.getByRole("button", { name: t("guidance.areaSkin") }),
    );

    expect(screen.getByRole("checkbox", { name: "Осип" })).toBeInTheDocument();
    expect(screen.queryByRole("checkbox", { name: "Главоболка" })).toBeNull();
  });

  it("converts an alternative unit, goes back keeping the answer, and ends with reasons, safety-net and a summary", async () => {
    api.answerQuestion
      .mockResolvedValueOnce(q2)
      .mockResolvedValueOnce(q2)
      .mockResolvedValueOnce(result(gpOutcome));
    const user = await start();
    await toQuestion(user);

    await user.type(
      screen.getByRole("textbox", { name: t("guidance.numberLabel") }),
      "36",
    );
    await user.selectOptions(
      screen.getByRole("combobox", { name: t("guidance.numberUnitLabel") }),
      "hours",
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    expect(api.answerQuestion).toHaveBeenLastCalledWith(
      { id: S, token: "tok" },
      "headache",
      "q_days",
      ["1.5"],
    );

    await screen.findByRole("heading", { name: "Дали имате температура?" });
    await user.click(screen.getByRole("button", { name: t("common.back") }));
    expect(
      await screen.findByRole("heading", {
        name: "Колку дена ве боли главата?",
      }),
    ).toHaveFocus();
    expect(
      screen.getByRole("textbox", { name: t("guidance.numberLabel") }),
    ).toHaveValue("1,5");

    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: "Дали имате температура?" });
    await user.click(screen.getByRole("radio", { name: t("guidance.no") }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      await screen.findByRole("heading", { name: gpOutcome.title }),
    ).toBeInTheDocument();
    expect(screen.getByText(gpOutcome.reasons[0])).toBeInTheDocument();
    expect(screen.getByText(gpOutcome.watch_for[0])).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("guidance.linkDoctors") }),
    ).toHaveAttribute("href", "/doctors");

    const summary = screen.getByRole("region", {
      name: t("guidance.summaryTitle"),
    });
    expect(
      within(summary).getByText(/Колку дена ве боли главата\?/),
    ).toHaveTextContent("1,5 дена");
    expect(
      within(summary).getByText(/Дали имате температура\?/),
    ).toHaveTextContent("Не");
    expect(within(summary).getByText("40 години, Машки")).toBeInTheDocument();
  });

  it("refuses a number outside the question's range without calling the API", async () => {
    const user = await start();
    await toQuestion(user);

    await user.type(
      screen.getByRole("textbox", { name: t("guidance.numberLabel") }),
      "400",
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );

    expect(
      screen.getByText(/Внесете број од 0 до 365 дена/),
    ).toBeInTheDocument();
    expect(api.answerQuestion).not.toHaveBeenCalled();
  });
});

describe("GuidanceGuide next steps", () => {
  const sameDayOutcome: GuidanceOutcomeV2 = {
    ...gpOutcome,
    id: "o_urgent",
    level: "urgent_same_day",
    title: "Побарајте преглед денес",
    care: { setting: "on_call", specialties: [], facility_types: [] },
  };

  it("sends an emergency to „Каде веднаш“ emergency departments and asks if it helped", async () => {
    window.sessionStorage.setItem("guidance_city", "Битола");
    const user = await start();
    await user.click(
      screen.getByRole("button", { name: t("guidance.emergencyNow") }),
    );

    expect(
      await screen.findByRole("link", {
        name: t("guidance.linkUrgentCareEmergency"),
      }),
    ).toHaveAttribute("href", "/urgent-care/bitola?type=ed");
    // Every first-aid guide is still a draft: none is linked.
    expect(screen.queryByText(t("guidance.firstAidTitle"))).toBeNull();

    await user.click(screen.getByRole("button", { name: t("feedback.yes") }));
    expect(feedback.sendFeedback).toHaveBeenCalledWith({
      kind: "vote",
      item: "guidance:global:outcome:emergency_now",
      helpful: true,
    });
  });

  it("puts „Каде веднаш“ first on a same-day result, for the typed city", async () => {
    api.answerQuestion.mockResolvedValueOnce(result(sameDayOutcome));
    const user = await start();
    await toQuestion(user);
    await user.type(
      screen.getByRole("textbox", { name: t("guidance.numberLabel") }),
      "2",
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: sameDayOutcome.title });

    await user.type(
      screen.getByRole("textbox", { name: t("guidance.cityLabel") }),
      "Струмица",
    );
    expect(
      screen.getByRole("link", { name: t("guidance.linkUrgentCare") }),
    ).toHaveAttribute("href", "/urgent-care/strumica");
    expect(
      screen.getByRole("region", { name: t("feedback.question") }),
    ).toBeInTheDocument();
  });

  it("counts each step reached once, and the outcome, as anonymous drop-off", async () => {
    api.answerQuestion
      .mockResolvedValueOnce(q2)
      .mockResolvedValueOnce(result(gpOutcome));
    const user = await start();
    await toQuestion(user);

    expect(feedback.recordFunnelStep).toHaveBeenCalledWith(
      "guidance:headache",
      "start",
      0,
    );
    expect(feedback.recordFunnelStep).toHaveBeenCalledWith(
      "guidance:headache",
      "q_days",
      1,
    );

    await user.type(
      screen.getByRole("textbox", { name: t("guidance.numberLabel") }),
      "2",
    );
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: "Дали имате температура?" });
    await user.click(screen.getByRole("radio", { name: t("guidance.no") }));
    await user.click(
      screen.getByRole("button", { name: t("guidance.continue") }),
    );
    await screen.findByRole("heading", { name: gpOutcome.title });

    expect(feedback.recordFunnelStep).toHaveBeenCalledWith(
      "guidance:headache",
      "outcome:see_gp_this_week",
      3,
    );
    expect(
      feedback.recordFunnelStep.mock.calls.filter(
        ([, step]) => step === "start",
      ),
    ).toHaveLength(1);
  });
});
