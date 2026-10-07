import { NextResponse } from "next/server";
import { readLimited, rejectCrossSite } from "@/lib/auth/request-guard";
import { relayToApi } from "@/lib/api/notifications-relay";
import { t } from "@/i18n/t";

const TOKEN = /^\d{1,18}\.[a-z_]{1,40}\.[A-Za-z0-9_-]{43}$/;

/**
 * The signed unsubscribe (G4). Two callers:
 *
 * - the mail client's RFC 8058 one-click POST to the List-Unsubscribe URL
 *   (`?token=…`, body `List-Unsubscribe=One-Click`). It comes from the
 *   provider's servers, with no Origin and no session, so there is no
 *   same-origin check: the HMAC-signed token is the authorisation, and it can
 *   only ever turn one kind of e-mail off;
 * - the /unsubscribe page's button (JSON `{token}`, same-origin).
 *
 * A GET never changes anything (link scanners prefetch URLs in e-mails).
 */
export async function POST(request: Request) {
  const url = new URL(request.url);
  let token = url.searchParams.get("token");

  if (!token) {
    const crossSite = rejectCrossSite(request);

    if (crossSite) {
      return crossSite;
    }

    const bytes = await readLimited(request, 1024);

    try {
      const parsed = bytes
        ? (JSON.parse(new TextDecoder().decode(bytes)) as { token?: unknown })
        : null;
      token = typeof parsed?.token === "string" ? parsed.token : null;
    } catch {
      token = null;
    }
  }

  if (!token || !TOKEN.test(token)) {
    return NextResponse.json(
      { message: t("notifications.unsubscribe.invalid") },
      { status: 404 },
    );
  }

  return relayToApi(request, "/notifications/unsubscribe", {
    method: "POST",
    body: { token },
    auth: "none",
  });
}
