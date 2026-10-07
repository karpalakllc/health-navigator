import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { readUpstream } from "@/lib/api/upstream";
import { guardJson } from "@/lib/auth/request-guard";
import {
  PROFILE_REPORT_HONEYPOT,
  isProfileReportReason,
  isProfileReportSubject,
  profileReportApiPath,
} from "@/lib/api/profile-reports";
import { t } from "@/i18n/t";

type ProfileReportPayload = {
  subject?: unknown;
  slug?: unknown;
  reason?: unknown;
  note?: unknown;
  altcha?: unknown;
  [PROFILE_REPORT_HONEYPOT]?: unknown;
};

function text(value: unknown): string | null {
  return typeof value === "string" ? value : null;
}

/**
 * „Пријави профил“: open to everyone. The session token rides along when
 * there is one, so the API can keep a member to one open report per profile;
 * the visitor's address is forwarded for the API's limits and its once-a-day
 * guest rule, which keep only a keyed hash of it. The ALTCHA payload and the
 * honeypot are relayed untouched for the API to judge.
 */
export async function POST(request: Request) {
  const guarded = await guardJson<ProfileReportPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;
  const { subject, slug } = body;

  if (!isProfileReportSubject(subject) || !isSlug(slug)) {
    return NextResponse.json({ message: t("notFound.title") }, { status: 404 });
  }

  if (!isProfileReportReason(body.reason)) {
    return NextResponse.json(
      {
        message: t("profileReports.reasonRequired"),
        errors: { reason: [t("profileReports.reasonRequired")] },
      },
      { status: 422 },
    );
  }

  const token = await getSessionToken();

  const send = (bearer: string | null) =>
    readUpstream(
      fetch(apiUrl(profileReportApiPath(subject, pathSegment(slug))), {
        method: "POST",
        headers: {
          ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}),
          "Content-Type": "application/json",
          Accept: "application/json",
          "Accept-Language": "mk",
          ...forwardedForHeaders(request),
        },
        body: JSON.stringify({
          reason: body.reason,
          note: text(body.note),
          altcha: text(body.altcha),
          [PROFILE_REPORT_HONEYPOT]: text(body[PROFILE_REPORT_HONEYPOT]),
        }),
      }),
    );

  let upstream = await send(token ?? null);

  // An expired session cookie is refused even on optional-auth routes; the
  // report still counts without it.
  if (token && upstream.ok && upstream.response.status === 401) {
    upstream = await send(null);
  }

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}
