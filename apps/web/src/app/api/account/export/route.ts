import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";

/**
 * Downloads the member's data export (GET /me/export).
 *
 * The account page links here directly, so it works without JavaScript: on
 * success the API's JSON is streamed straight through as an attachment and the
 * browser stays on the page; on failure the browser is sent back to the page
 * with a reason it can show. The bearer token stays in this server — the
 * browser only ever carries the httpOnly session cookie.
 */
const DATA_PAGE = "/account/data";

/** Relative Location: the browser resolves it against the page's own origin. */
function backTo(path: string): Response {
  return new Response(null, {
    status: 303,
    headers: { Location: path, "Cache-Control": "no-store" },
  });
}

export async function GET(request: Request) {
  // A link on another site may navigate here (the lax cookie rides along on a
  // top-level GET). It learns nothing, but it must not push a download of
  // someone's health-forum history onto their device either.
  const site = request.headers.get("sec-fetch-site");

  if (site === "cross-site" || site === "same-site") {
    return backTo(DATA_PAGE);
  }

  const token = await getSessionToken();

  if (!token) {
    return backTo(`/login?redirect=${encodeURIComponent(DATA_PAGE)}`);
  }

  let upstream: Response;

  try {
    upstream = await fetch(apiUrl("/me/export"), {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      cache: "no-store",
    });
  } catch {
    return backTo(`${DATA_PAGE}?export=error`);
  }

  if (upstream.status === 401) {
    return backTo(`/login?redirect=${encodeURIComponent(DATA_PAGE)}`);
  }

  if (!upstream.ok || !upstream.body) {
    await upstream.body?.cancel().catch(() => undefined);

    return backTo(
      `${DATA_PAGE}?export=${upstream.status === 429 ? "throttled" : "error"}`,
    );
  }

  return new Response(upstream.body, {
    status: 200,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Content-Disposition":
        upstream.headers.get("content-disposition") ??
        'attachment; filename="zdravje360.json"',
      "Cache-Control": "no-store, private",
      "X-Content-Type-Options": "nosniff",
    },
  });
}
