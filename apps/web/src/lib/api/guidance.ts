import { apiGet } from "@/lib/api/client";
import { apiUrl } from "@/lib/config";

export type GuidanceRedFlag = {
  code: string;
  label: string;
};

export type GuidanceStepOption = {
  value: string;
  label: string;
};

export type GuidanceStep = {
  key: string;
  type: "single_select" | "multi_select";
  label: string;
  required: boolean;
  options: GuidanceStepOption[];
};

export type GuidanceFlow = {
  title: string;
  intro_body: string | null;
  red_flags: GuidanceRedFlag[];
  steps: GuidanceStep[];
};

export type GuidanceOutcome = {
  outcome_code: string;
  title: string;
  body: string;
  handoffs: Array<{
    type: string;
    label?: string;
    href?: string | null;
  }>;
};

export async function fetchGuidanceFlow(): Promise<GuidanceFlow> {
  return apiGet<GuidanceFlow>("/triage/flow");
}

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    const message =
      typeof body.message === "string"
        ? body.message
        : `API request failed (${response.status})`;
    throw new Error(message);
  }

  return body.data as T;
}

export async function startGuidanceSession(
  acceptedTerms = true,
): Promise<string> {
  const response = await fetch(apiUrl("/triage/sessions"), {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ accepted_terms: acceptedTerms }),
  });

  const data = await parseJson<{ session_id: string }>(response);

  return data.session_id;
}

export async function saveGuidanceAnswers(
  sessionId: string,
  answers: Array<{ step_key: string; values: string[] }>,
): Promise<{ emergency_stopped: boolean }> {
  const response = await fetch(apiUrl(`/triage/sessions/${sessionId}/answers`), {
    method: "PUT",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ answers }),
  });

  return parseJson(response);
}

export async function completeGuidanceEmergency(
  sessionId: string,
): Promise<GuidanceOutcome> {
  const response = await fetch(apiUrl(`/triage/sessions/${sessionId}/emergency`), {
    method: "POST",
    headers: { Accept: "application/json" },
  });

  const data = await parseJson<{ outcome: GuidanceOutcome }>(response);

  return data.outcome;
}

export async function completeGuidanceSession(
  sessionId: string,
): Promise<GuidanceOutcome> {
  const response = await fetch(apiUrl(`/triage/sessions/${sessionId}/complete`), {
    method: "POST",
    headers: { Accept: "application/json" },
  });

  const data = await parseJson<{ outcome: GuidanceOutcome }>(response);

  return data.outcome;
}
