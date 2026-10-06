/*
 * Browser-side guidance calls, imported by the client wizard. Nothing here may
 * import lib/api/client.ts (server-only); the server-rendered flow read lives
 * in lib/api/guidance-flow.ts.
 */
import { apiUrl } from "@/lib/config";
import { pathSegment } from "@/lib/api/path";

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

/**
 * A guidance session is not tied to an account. Whoever started it proves so
 * with the secret the API issued at creation, sent on every later call.
 */
export type GuidanceSessionHandle = {
  id: string;
  token: string;
};

export const GUIDANCE_TOKEN_HEADER = "X-Guidance-Token";

/** Reads a handle back from sessionStorage; anything malformed (including the
 * bare id stored before tokens existed) yields null so a fresh session starts. */
export function parseStoredGuidanceSession(
  raw: string | null,
): GuidanceSessionHandle | null {
  if (!raw) {
    return null;
  }

  try {
    const value: unknown = JSON.parse(raw);

    if (
      typeof value === "object" &&
      value !== null &&
      typeof (value as GuidanceSessionHandle).id === "string" &&
      typeof (value as GuidanceSessionHandle).token === "string" &&
      (value as GuidanceSessionHandle).id !== "" &&
      (value as GuidanceSessionHandle).token !== ""
    ) {
      return {
        id: (value as GuidanceSessionHandle).id,
        token: (value as GuidanceSessionHandle).token,
      };
    }
  } catch {
    // Not JSON: a legacy bare id.
  }

  return null;
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
): Promise<GuidanceSessionHandle> {
  const response = await fetch(apiUrl("/triage/sessions"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
    },
    body: JSON.stringify({ accepted_terms: acceptedTerms }),
  });

  const data = await parseJson<{ session_id: string; session_token: string }>(
    response,
  );

  return { id: data.session_id, token: data.session_token };
}

export async function saveGuidanceAnswers(
  session: GuidanceSessionHandle,
  answers: Array<{ step_key: string; values: string[] }>,
): Promise<{ emergency_stopped: boolean }> {
  const response = await fetch(
    apiUrl(`/triage/sessions/${pathSegment(session.id)}/answers`),
    {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        [GUIDANCE_TOKEN_HEADER]: session.token,
      },
      body: JSON.stringify({ answers }),
    },
  );

  return parseJson(response);
}

export async function completeGuidanceEmergency(
  session: GuidanceSessionHandle,
): Promise<GuidanceOutcome> {
  const response = await fetch(
    apiUrl(`/triage/sessions/${pathSegment(session.id)}/emergency`),
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Accept-Language": "mk",
        [GUIDANCE_TOKEN_HEADER]: session.token,
      },
    },
  );

  const data = await parseJson<{ outcome: GuidanceOutcome }>(response);

  return data.outcome;
}

export async function completeGuidanceSession(
  session: GuidanceSessionHandle,
): Promise<GuidanceOutcome> {
  const response = await fetch(
    apiUrl(`/triage/sessions/${pathSegment(session.id)}/complete`),
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Accept-Language": "mk",
        [GUIDANCE_TOKEN_HEADER]: session.token,
      },
    },
  );

  const data = await parseJson<{ outcome: GuidanceOutcome }>(response);

  return data.outcome;
}
