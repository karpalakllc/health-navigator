/*
 * Session plumbing shared by the browser-side guidance calls
 * (lib/api/guidance-v2.ts). Nothing here may import lib/api/client.ts
 * (server-only).
 */

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

/** A non-2xx answer from the guidance API, with its status. */
export class GuidanceApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
    this.name = "GuidanceApiError";
  }
}

/**
 * The stored handle no longer names a usable session: unknown or expired
 * (404), or already completed / not accepting this call (422). Starting a new
 * session is the only way forward.
 */
export function isStaleGuidanceSession(error: unknown): boolean {
  return (
    error instanceof GuidanceApiError &&
    (error.status === 404 || error.status === 422)
  );
}
