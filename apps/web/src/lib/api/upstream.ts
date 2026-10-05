import { NextResponse } from "next/server";
import { t } from "@/i18n/t";

/**
 * The route handlers under src/app/api/** relay the Laravel API's JSON. When
 * something in between answers instead — a proxy's HTML 502/503 page, a
 * gateway timeout, a refused connection — `response.json()` (or the fetch
 * itself) threw, and the visitor got a bare 500 with no message the forms
 * could show. This turns every such case into a 502 with a localized message.
 */
export type UpstreamResult =
  | {
      ok: true;
      response: Response;
      // The API's envelope; each handler reads the fields it needs, exactly as
      // it did from `response.json()`.
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      payload: any;
    }
  | { ok: false; error: NextResponse };

export function upstreamUnavailable(): NextResponse {
  return NextResponse.json(
    { message: t("errors.upstreamUnavailable") },
    { status: 502 },
  );
}

export async function readUpstream(
  pending: Response | Promise<Response>,
): Promise<UpstreamResult> {
  let response: Response;

  try {
    response = await pending;
  } catch {
    return { ok: false, error: upstreamUnavailable() };
  }

  try {
    return { ok: true, response, payload: await response.json() };
  } catch {
    return { ok: false, error: upstreamUnavailable() };
  }
}
