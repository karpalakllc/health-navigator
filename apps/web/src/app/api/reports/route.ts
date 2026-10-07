import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";
import { REPORT_REASONS, type ReportTarget } from "@/lib/api/reports";

type ReportPayload = {
  target?: Partial<ReportTarget> & { kind?: string };
  reason?: string;
  note?: string | null;
  /** ALTCHA payload from the sheet's invisible widget; the API checks it. */
  altcha?: unknown;
};

function isId(value: unknown): value is number {
  return typeof value === "number" && Number.isSafeInteger(value) && value > 0;
}

/** The API path for a report target, or null when the target is malformed. */
function reportPath(target: ReportPayload["target"]): string | null {
  if (!target) {
    return null;
  }

  if (target.kind === "review" && isId(target.id)) {
    return `/reviews/${pathSegment(target.id)}/reports`;
  }

  if (target.kind === "forum_post" && isId(target.id)) {
    return `/forum/posts/${pathSegment(target.id)}/reports`;
  }

  if (
    target.kind === "forum_topic" &&
    isSlug(target.categorySlug) &&
    isSlug(target.topicSlug)
  ) {
    return `/forum/categories/${pathSegment(target.categorySlug)}/topics/${pathSegment(target.topicSlug)}/reports`;
  }

  return null;
}

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<ReportPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;
  const path = reportPath(body.target);

  if (!path) {
    return NextResponse.json(
      { message: t("reports.invalidTarget") },
      { status: 422 },
    );
  }

  if (
    typeof body.reason !== "string" ||
    !(REPORT_REASONS as readonly string[]).includes(body.reason)
  ) {
    return NextResponse.json(
      { message: t("reports.reasonRequired") },
      { status: 422 },
    );
  }

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({
        reason: body.reason,
        note: typeof body.note === "string" ? body.note : null,
        altcha: typeof body.altcha === "string" ? body.altcha : null,
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
