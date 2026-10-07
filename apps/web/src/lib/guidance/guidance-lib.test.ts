import { describe, expect, it } from "vitest";
import type { CatalogFlow, GuidanceOutcomeV2 } from "@/lib/api/guidance-v2";
import {
  ageBandFor,
  ageInMonths,
  pregnancyIsAsked,
} from "@/lib/api/guidance-v2";
import { directoryLinks, levelRank } from "@/lib/guidance/care";
import {
  areasWithFlows,
  flowsForAge,
  normalizeForSearch,
  searchFlows,
} from "@/lib/guidance/search";
import {
  answerLabel,
  buildSummary,
  formatSummaryDate,
  summaryText,
} from "@/lib/guidance/summary";
import {
  convertToUnit,
  formatAnswerNumber,
  parseLocaleNumber,
} from "@/lib/guidance/units";

const flow = (over: Partial<CatalogFlow>): CatalogFlow => ({
  key: "x",
  title: "X",
  body_areas: ["general"],
  search_terms: [],
  age_bands: ["adult_18_64"],
  urgency_rank: 10,
  ...over,
});

const headache = flow({
  key: "headache",
  title: "Главоболка",
  body_areas: ["head"],
  search_terms: ["глава", "мигрена", "болка во главата"],
});
const fever = flow({
  key: "fever-child",
  title: "Температура кај дете",
  body_areas: ["general"],
  search_terms: ["треска", "температура"],
  age_bands: ["child_1_4", "child_5_12"],
});

describe("symptom search (in the browser only)", () => {
  it("matches Cyrillic and Latin spellings alike", () => {
    expect(normalizeForSearch("Главоболка")).toBe("glavobolka");
    expect(searchFlows([headache, fever], "glavobolka")).toEqual([headache]);
    expect(searchFlows([headache, fever], "главобол")).toEqual([headache]);
    expect(searchFlows([headache, fever], "treska")).toEqual([fever]);
    // "sh/ch/zh" digraphs typed in Latin meet ш/ч/ж.
    expect(normalizeForSearch("shum")).toBe(normalizeForSearch("шум"));
  });

  it("finds a word inside a compound only from three letters", () => {
    expect(searchFlows([headache], "бол")).toEqual([headache]);
    expect(searchFlows([headache], "ол")).toEqual([]);
  });

  it("offers only flows meant for the visitor's age", () => {
    expect(flowsForAge([headache, fever], "child_1_4")).toEqual([fever]);
    expect(flowsForAge([headache, fever], "adult_18_64")).toEqual([headache]);
  });

  it("lists only body areas that have a flow", () => {
    expect(areasWithFlows([headache, fever])).toEqual(["head", "general"]);
  });
});

describe("age, as the API counts it", () => {
  it("derives whole months and the age band", () => {
    expect(ageInMonths(6, "weeks")).toBe(1);
    expect(ageBandFor(ageInMonths(6, "weeks"))).toBe("infant_0_3m");
    expect(ageBandFor(ageInMonths(7, "months"))).toBe("infant_3_12m");
    expect(ageBandFor(ageInMonths(65, "years"))).toBe("older_65_plus");
  });

  it("asks about pregnancy only where it can apply", () => {
    expect(
      pregnancyIsAsked({ age_value: 30, age_unit: "years", sex: "female" }),
    ).toBe(true);
    expect(
      pregnancyIsAsked({
        age_value: 30,
        age_unit: "years",
        sex: "unspecified",
      }),
    ).toBe(true);
    expect(
      pregnancyIsAsked({ age_value: 30, age_unit: "years", sex: "male" }),
    ).toBe(false);
    expect(
      pregnancyIsAsked({ age_value: 8, age_unit: "years", sex: "female" }),
    ).toBe(false);
  });
});

describe("numbers", () => {
  it("converts an alternative unit into the question's own", () => {
    expect(convertToUnit(36, "hours", "days")).toBe(1.5);
    expect(convertToUnit(2, "weeks", "days")).toBe(14);
  });

  it("reads a decimal comma and refuses text", () => {
    expect(parseLocaleNumber("38,5")).toBe(38.5);
    expect(parseLocaleNumber("боли")).toBeNaN();
    expect(formatAnswerNumber(38.5)).toBe("38.5");
  });
});

const outcome = (over: Partial<GuidanceOutcomeV2>): GuidanceOutcomeV2 => ({
  id: "o",
  level: "see_gp_this_week",
  crisis: false,
  title: "Матичен лекар",
  summary: "…",
  reasons: ["r"],
  do_now: ["d"],
  watch_for: ["w"],
  call: [],
  care: { setting: "gp", specialties: [], facility_types: [] },
  ...over,
});

describe("directory links", () => {
  it("links a known specialty with the city and verified first", () => {
    const links = directoryLinks(
      outcome({
        care: {
          setting: "specialist",
          specialties: [
            { key: "kardiologija", name: "Кардиологија", slug: "kardiologija" },
            { key: "pulmologija", name: "Пулмологија", slug: null },
          ],
          facility_types: ["hospital"],
        },
      }),
      "Битола",
      { pharmaciesOn: true },
    );

    expect(links.map((l) => l.href)).toEqual([
      "/doctors?specialty=kardiologija&city=%D0%91%D0%B8%D1%82%D0%BE%D0%BB%D0%B0&verified=1",
      "/facilities?type=hospital&city=%D0%91%D0%B8%D1%82%D0%BE%D0%BB%D0%B0",
    ]);
  });

  it("sends same-day care to facilities with an emergency department", () => {
    const links = directoryLinks(
      outcome({
        level: "urgent_same_day",
        care: { setting: "on_call", specialties: [], facility_types: [] },
      }),
      "",
      { pharmaciesOn: true },
    );

    expect(links[0].href).toBe("/facilities?has_emergency=1");
  });

  it("gives an emergency no directory links (194/112 only) and respects the pharmacy module", () => {
    expect(
      directoryLinks(outcome({ level: "emergency_now" }), "", {
        pharmaciesOn: true,
      }),
    ).toEqual([]);
    const pharmacy = outcome({
      level: "pharmacy_advice",
      care: { setting: "pharmacy", specialties: [], facility_types: [] },
    });
    expect(directoryLinks(pharmacy, "", { pharmaciesOn: false })).toEqual([]);
    expect(directoryLinks(pharmacy, "", { pharmaciesOn: true })[0].href).toBe(
      "/pharmacies",
    );
  });

  it("ranks levels from emergency down", () => {
    expect(levelRank("emergency_now")).toBeGreaterThan(
      levelRank("urgent_same_day"),
    );
    expect(levelRank("pharmacy_advice")).toBeGreaterThan(
      levelRank("self_care_with_safety_net"),
    );
  });
});

describe("summary for the doctor", () => {
  it("formats the date in Skopje time without locale data", () => {
    expect(formatSummaryDate(new Date("2026-10-07T14:49:00Z"))).toBe(
      "07.10.2026, 16:49",
    );
  });

  it("puts answers in words and the result in the text", () => {
    const summary = buildSummary({
      date: new Date("2026-10-07T14:49:00Z"),
      demo: {
        age_value: 34,
        age_unit: "years",
        sex: "female",
        pregnancy: "unsure",
        conditions: [],
      },
      symptoms: ["Кашлица"],
      answers: [
        {
          id: "cough.q",
          flowTitle: "Кашлица",
          question: "Колку дена?",
          answer: answerLabel(
            { id: "q", type: "question", kind: "number", unit: "days" },
            ["1.5"],
          ),
        },
      ],
      outcomes: [{ symptom: "Кашлица", outcome: outcome({}) }],
      redFlagStop: false,
    });
    const text = summaryText(summary);

    expect(summary.who).toBe("34 години, Женски, Не знам / можно е");
    expect(text).toContain("Колку дена? — 1,5 дена");
    expect(text).toContain(
      "Кашлица: Матичен лекар (Матичен лекар оваа недела)",
    );
    expect(text).toContain("Не е дијагноза.");
  });
});
