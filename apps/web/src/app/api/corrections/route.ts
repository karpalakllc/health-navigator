import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { readUpstream } from "@/lib/api/upstream";
import { guardJson } from "@/lib/auth/request-guard";
import {
  CORRECTION_HONEYPOT,
  isCorrectionSubject,
} from "@/lib/api/corrections";
import { t } from "@/i18n/t";

type CorrectionPayload = {
  subject?: unknown;
  slug?: unknown;
  type?: unknown;
  field?: unknown;
  message?: unknown;
  contact?: unknown;
  [CORRECTION_HONEYPOT]?: unknown;
};

function text(value: unknown): string | null {
  return typeof value === "string" ? value : null;
}

/**
 * „Пријави грешка во профилот“ / „Барање за приговор / отстранување“: open to
 * everyone. The session token rides along when there is one, so the API can
 * record the account; without one the request is anonymous. The visitor's
 * address is forwarded only for the API's rate limit, which never stores it.
 */
export async function POST(request: Request) {
  const guarded = await guardJson<CorrectionPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  if (!isCorrectionSubject(body.subject) || !isSlug(body.slug)) {
    return NextResponse.json({ message: t("notFound.title") }, { status: 404 });
  }

  const token = await getSessionToken();
  const path =
    body.subject === "doctor"
      ? `/doctors/${pathSegment(body.slug)}/corrections`
      : `/facilities/${pathSegment(body.slug)}/corrections`;

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
      method: "POST",
      headers: {
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({
        type: body.type === "objection" ? "objection" : "correction",
        field: text(body.field),
        message: text(body.message) ?? "",
        contact: text(body.contact),
        [CORRECTION_HONEYPOT]: text(body[CORRECTION_HONEYPOT]),
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}
