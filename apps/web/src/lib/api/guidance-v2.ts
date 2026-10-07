/*
 * Browser-side calls of symptom guidance v2 (docs/triage-flows.md). Nothing
 * here may import lib/api/client.ts (server-only); the server-rendered
 * catalogue read lives in lib/api/guidance-flow.ts.
 *
 * Sessions are anonymous: the API issues a secret at creation, kept in this
 * tab's sessionStorage and sent on every later call. Answers are option
 * codes and numbers only — never free text.
 */
import { apiUrl } from "@/lib/config";
import { pathSegment } from "@/lib/api/path";
import {
  GUIDANCE_TOKEN_HEADER,
  GuidanceApiError,
  type GuidanceSessionHandle,
} from "@/lib/api/guidance";

export type AgeBand =
  | "infant_0_3m"
  | "infant_3_12m"
  | "child_1_4"
  | "child_5_12"
  | "teen_13_17"
  | "adult_18_64"
  | "older_65_plus";

export type BodyArea =
  | "head"
  | "eyes"
  | "ears"
  | "mouth"
  | "throat"
  | "chest"
  | "abdomen"
  | "pelvis"
  | "back"
  | "arms"
  | "legs"
  | "skin"
  | "general"
  | "mind";

export type OutcomeLevel =
  | "emergency_now"
  | "urgent_same_day"
  | "see_doctor_24_48h"
  | "see_gp_this_week"
  | "pharmacy_advice"
  | "self_care_with_safety_net";

export type CatalogFlow = {
  key: string;
  title: string;
  body_areas: BodyArea[];
  search_terms: string[];
  age_bands: AgeBand[];
  urgency_rank: number;
};

export type GuidanceCatalog = {
  flows: CatalogFlow[];
  max_symptoms: number;
};

export type QuestionOption = {
  value: string;
  label: string;
  help?: string;
  exclusive?: boolean;
};

export type TimeUnit =
  "minutes" | "hours" | "days" | "weeks" | "months" | "years";

export type NumberUnit =
  TimeUnit | "celsius" | "mmhg" | "mmol_l" | "bpm" | "kg" | "count";

export type GuidanceNode = {
  id: string;
  type: "question" | "info";
  kind?: "single" | "multi" | "yes_no" | "number" | "scale";
  text?: string;
  title?: string;
  help?: string;
  options?: QuestionOption[];
  unit?: NumberUnit;
  alt_units?: TimeUnit[];
  min?: number;
  max?: number;
  step?: number;
  allow_unknown?: boolean;
  allow_unsure?: boolean;
  optional?: boolean;
  min_label?: string;
  max_label?: string;
};

export type ScreenItem = {
  code: string;
  label: string;
  help: string | null;
  /** null = the global screen; else the flow key it belongs to. */
  group: string | null;
};

export type FlowRef = { key: string; title: string };

export type CareSpecialty = { key: string; name: string; slug: string | null };

export type GuidanceOutcomeV2 = {
  id: string;
  level: OutcomeLevel;
  crisis: boolean;
  title: string;
  summary: string;
  reasons: string[];
  do_now: string[];
  watch_for: string[];
  call: Array<{ number: string; label: string }>;
  care: {
    setting: string;
    specialties: CareSpecialty[];
    facility_types: string[];
  };
};

export type GuidanceState =
  | { session_id: string; stage: "demographics" }
  | { session_id: string; stage: "symptoms"; age_band: AgeBand }
  | {
      session_id: string;
      stage: "screen";
      flows: FlowRef[];
      screen: ScreenItem[];
    }
  | {
      session_id: string;
      stage: "question";
      flows: FlowRef[];
      flow: FlowRef & { position: number };
      node: GuidanceNode;
      progress: { answered: number; remaining_max: number };
      path: Array<{ node: GuidanceNode; values: string[] }>;
    }
  | {
      session_id: string;
      stage: "result";
      emergency_stopped: boolean;
      level: OutcomeLevel;
      reason: "answers" | "red_flag" | "shortcut" | "fallback";
      flows: FlowRef[];
      outcomes: Array<{ flow: FlowRef | null; outcome: GuidanceOutcomeV2 }>;
    };

export type Demographics = {
  age_value: number;
  age_unit: "years" | "months" | "weeks";
  sex: "female" | "male" | "unspecified";
  pregnancy: "pregnant" | "postpartum" | "not_pregnant" | "unsure" | null;
  conditions: string[];
};

/** Whether the pregnancy question applies (mirrors Demographics::pregnancyIsAsked). */
export function pregnancyIsAsked(demo: {
  age_value: number;
  age_unit: Demographics["age_unit"];
  sex: Demographics["sex"];
}): boolean {
  const months = ageInMonths(demo.age_value, demo.age_unit);
  const years = Math.floor(months / 12);

  return demo.sex !== "male" && years >= 10 && years <= 55;
}

/** Whole months, as the API counts them (Demographics::fromInput). */
export function ageInMonths(
  value: number,
  unit: Demographics["age_unit"],
): number {
  if (unit === "years") {
    return Math.floor(value * 12);
  }

  if (unit === "months") {
    return Math.floor(value);
  }

  return Math.floor((value * 7) / 30.4375);
}

/** The age band, as the API derives it (Demographics::ageBand). */
export function ageBandFor(months: number): AgeBand {
  const years = Math.floor(months / 12);

  if (months < 3) return "infant_0_3m";
  if (months < 12) return "infant_3_12m";
  if (years <= 4) return "child_1_4";
  if (years <= 12) return "child_5_12";
  if (years <= 17) return "teen_13_17";
  if (years <= 64) return "adult_18_64";

  return "older_65_plus";
}

async function parse<T>(response: Response): Promise<T> {
  const body = ((await response.json().catch(() => null)) ?? {}) as {
    message?: unknown;
    data?: unknown;
  };

  if (!response.ok) {
    throw new GuidanceApiError(
      typeof body.message === "string"
        ? body.message
        : `API request failed (${response.status})`,
      response.status,
    );
  }

  return body.data as T;
}

function headers(session?: GuidanceSessionHandle): HeadersInit {
  return {
    "Content-Type": "application/json",
    Accept: "application/json",
    "Accept-Language": "mk",
    ...(session ? { [GUIDANCE_TOKEN_HEADER]: session.token } : {}),
  };
}

function sessionUrl(session: GuidanceSessionHandle, suffix = ""): string {
  return apiUrl(`/triage/v2/sessions/${pathSegment(session.id)}${suffix}`);
}

export async function startSession(): Promise<{
  handle: GuidanceSessionHandle;
  state: GuidanceState;
}> {
  const data = await parse<{
    session_id: string;
    session_token: string;
    state: GuidanceState;
  }>(
    await fetch(apiUrl("/triage/v2/sessions"), {
      method: "POST",
      headers: headers(),
      body: JSON.stringify({ accepted_terms: true }),
    }),
  );

  return {
    handle: { id: data.session_id, token: data.session_token },
    state: data.state,
  };
}

export async function fetchState(
  session: GuidanceSessionHandle,
): Promise<GuidanceState> {
  return parse(await fetch(sessionUrl(session), { headers: headers(session) }));
}

async function put(
  session: GuidanceSessionHandle,
  suffix: string,
  body: unknown,
): Promise<GuidanceState> {
  return parse(
    await fetch(sessionUrl(session, suffix), {
      method: "PUT",
      headers: headers(session),
      body: JSON.stringify(body),
    }),
  );
}

export function saveDemographics(
  session: GuidanceSessionHandle,
  demo: Demographics,
): Promise<GuidanceState> {
  return put(session, "/demographics", demo);
}

export function chooseSymptoms(
  session: GuidanceSessionHandle,
  flows: string[],
): Promise<GuidanceState> {
  return put(session, "/symptoms", { flows });
}

export function answerScreen(
  session: GuidanceSessionHandle,
  redFlags: string[],
): Promise<GuidanceState> {
  return put(session, "/screen", { red_flags: redFlags });
}

export function answerQuestion(
  session: GuidanceSessionHandle,
  flow: string,
  node: string,
  values: string[],
): Promise<GuidanceState> {
  return put(session, "/answer", { flow, node, values });
}

export async function emergencyShortcut(
  session: GuidanceSessionHandle,
): Promise<GuidanceState> {
  return parse(
    await fetch(sessionUrl(session, "/emergency"), {
      method: "POST",
      headers: headers(session),
    }),
  );
}

export async function noMatch(
  session: GuidanceSessionHandle,
  bodyArea: BodyArea | null,
): Promise<{ suggested: string[] }> {
  return parse(
    await fetch(sessionUrl(session, "/no-match"), {
      method: "POST",
      headers: headers(session),
      body: JSON.stringify({ body_area: bodyArea }),
    }),
  );
}
