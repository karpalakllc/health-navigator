/**
 * Correlation ID shared by a page render and every API call it makes.
 *
 * proxy.ts assigns one per page request (keeping a well-formed ID an edge
 * already put on the request) and passes it on as a request header; the
 * server-side API helpers (lib/api/client-ip.ts) forward it as X-Request-Id,
 * and the API logs every record under it and returns it on the response
 * (apps/api AssignRequestId). A support report quoting the ID finds both
 * tiers' log lines.
 *
 * The pattern matches the API's: letters, digits and hyphens, 8–64
 * characters. Anything else is caller input headed for log lines and is
 * dropped rather than forwarded. The ID only correlates; nothing trusts it.
 */
export const REQUEST_ID_HEADER = "x-request-id";

const REQUEST_ID_PATTERN = /^[A-Za-z0-9-]{8,64}$/;

export function validRequestId(
  value: string | null | undefined,
): string | undefined {
  return value && REQUEST_ID_PATTERN.test(value) ? value : undefined;
}

/** The incoming request's ID when well-formed, otherwise a fresh UUID. */
export function requestIdFor(incoming: Headers): string {
  return validRequestId(incoming.get(REQUEST_ID_HEADER)) ?? crypto.randomUUID();
}
