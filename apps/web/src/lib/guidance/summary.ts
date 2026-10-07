import type {
  Demographics,
  GuidanceNode,
  GuidanceOutcomeV2,
  NumberUnit,
  OutcomeLevel,
} from "@/lib/api/guidance-v2";
import { t, type MessageKey } from "@/i18n/t";

/*
 * The summary a visitor can print, save as PDF or share with their doctor.
 * Built only in the browser from what this tab showed and answered; nothing
 * of it is sent to or kept on a server.
 */

export type AnsweredQuestion = {
  /** `${flowKey}.${nodeId}` */
  id: string;
  flowTitle: string;
  question: string;
  answer: string;
};

export type GuidanceSummary = {
  date: string;
  who: string;
  symptoms: string[];
  redFlags: string;
  answers: AnsweredQuestion[];
  result: Array<{ symptom: string | null; level: string; title: string }>;
  doNow: string[];
  watchFor: string[];
};

export const LEVEL_KEYS: Record<OutcomeLevel, MessageKey> = {
  emergency_now: "guidance.levelEmergency",
  urgent_same_day: "guidance.levelUrgent",
  see_doctor_24_48h: "guidance.levelDoctor",
  see_gp_this_week: "guidance.levelGp",
  pharmacy_advice: "guidance.levelPharmacy",
  self_care_with_safety_net: "guidance.levelSelfCare",
};

export const UNIT_KEYS: Record<NumberUnit, MessageKey> = {
  celsius: "guidance.unitCelsius",
  minutes: "guidance.unitMinutes",
  hours: "guidance.unitHours",
  days: "guidance.unitDays",
  weeks: "guidance.unitWeeks",
  months: "guidance.unitMonths",
  years: "guidance.unitYears",
  mmhg: "guidance.unitMmhg",
  mmol_l: "guidance.unitMmol",
  bpm: "guidance.unitBpm",
  kg: "guidance.unitKg",
  count: "guidance.unitCount",
};

/** An answer in words, as the visitor saw the choices. */
export function answerLabel(node: GuidanceNode, values: string[]): string {
  if (node.type === "info") {
    return "—";
  }

  if (values.length === 0) {
    return t("guidance.skipped");
  }

  switch (node.kind) {
    case "yes_no":
      return values[0] === "yes"
        ? t("guidance.yes")
        : values[0] === "no"
          ? t("guidance.no")
          : t("guidance.unsure");
    case "number":
      return values[0] === "unknown"
        ? t("guidance.unsure")
        : `${values[0].replace(".", ",")} ${node.unit ? t(UNIT_KEYS[node.unit]) : ""}`.trim();
    case "scale":
      return `${values[0]} / 10`;
    default:
      return values
        .map((v) => node.options?.find((o) => o.value === v)?.label ?? v)
        .join("; ");
  }
}

export function whoLabel(demo: Demographics): string {
  const unit =
    demo.age_unit === "years"
      ? t("guidance.ageUnitYears")
      : demo.age_unit === "months"
        ? t("guidance.ageUnitMonths")
        : t("guidance.ageUnitWeeks");
  const sex =
    demo.sex === "female"
      ? t("guidance.sexFemale")
      : demo.sex === "male"
        ? t("guidance.sexMale")
        : t("guidance.sexUnspecified");
  const parts = [`${demo.age_value} ${unit}`, sex];

  if (demo.pregnancy === "pregnant")
    parts.push(t("guidance.pregnancyPregnant"));
  if (demo.pregnancy === "postpartum")
    parts.push(t("guidance.pregnancyPostpartum"));
  if (demo.pregnancy === "unsure") parts.push(t("guidance.pregnancyUnsure"));

  return parts.join(", ");
}

export function buildSummary({
  date,
  demo,
  symptoms,
  answers,
  outcomes,
  redFlagStop,
}: {
  date: Date;
  demo: Demographics | null;
  symptoms: string[];
  answers: AnsweredQuestion[];
  outcomes: Array<{ symptom: string | null; outcome: GuidanceOutcomeV2 }>;
  redFlagStop: boolean;
}): GuidanceSummary {
  const primary = outcomes[0]?.outcome;

  return {
    date: formatSummaryDate(date),
    who: demo ? whoLabel(demo) : "—",
    symptoms,
    redFlags: redFlagStop
      ? t("guidance.summaryRedFlagsYes")
      : t("guidance.summaryRedFlagsNone"),
    answers,
    result: outcomes.map(({ symptom, outcome }) => ({
      symptom,
      level: t(LEVEL_KEYS[outcome.level]),
      title: outcome.title,
    })),
    doNow: primary?.do_now ?? [],
    watchFor: primary?.watch_for ?? [],
  };
}

/**
 * „07.10.2026, 16:49“ in Skopje time. Built from parts: browsers without
 * Macedonian locale data would otherwise fall back to English month names.
 */
export function formatSummaryDate(date: Date): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
      timeZone: "Europe/Skopje",
    })
      .formatToParts(date)
      .map((p) => [p.type, p.value]),
  );

  return `${parts.day}.${parts.month}.${parts.year}, ${parts.hour}:${parts.minute}`;
}

/** Plain text for „Сподели“ / „Копирај“. */
export function summaryText(summary: GuidanceSummary): string {
  const lines = [
    t("guidance.summaryTitle"),
    `${t("guidance.summaryDate")}: ${summary.date}`,
    `${t("guidance.summaryWho")}: ${summary.who}`,
  ];

  if (summary.symptoms.length > 0) {
    lines.push(
      `${t("guidance.summarySymptoms")}: ${summary.symptoms.join(", ")}`,
    );
  }

  lines.push(`${t("guidance.summaryRedFlags")}: ${summary.redFlags}`);

  if (summary.answers.length > 0) {
    lines.push("", `${t("guidance.summaryAnswers")}:`);

    for (const a of summary.answers) {
      lines.push(`- ${a.question} — ${a.answer}`);
    }
  }

  lines.push("", `${t("guidance.summaryResult")}:`);

  for (const r of summary.result) {
    lines.push(`- ${r.symptom ? `${r.symptom}: ` : ""}${r.title} (${r.level})`);
  }

  if (summary.doNow.length > 0) {
    lines.push(
      "",
      `${t("guidance.doNowTitle")}:`,
      ...summary.doNow.map((d) => `- ${d}`),
    );
  }

  if (summary.watchFor.length > 0) {
    lines.push(
      "",
      `${t("guidance.watchTitle")}:`,
      ...summary.watchFor.map((d) => `- ${d}`),
    );
  }

  lines.push("", t("guidance.summaryDisclaimer"));

  return lines.join("\n");
}
