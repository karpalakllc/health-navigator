import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";

type ModerationPayload = {
  categorySlug?: string;
  topicSlug?: string;
  is_pinned?: boolean;
  is_locked?: boolean;
};

export async function PATCH(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<ModerationPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  if (!isSlug(body.categorySlug) || !isSlug(body.topicSlug)) {
    return NextResponse.json(
      { message: t("errors.topicRequired") },
      { status: 422 },
    );
  }

  const upstream = await readUpstream(
    fetch(
      apiUrl(
        `/forum/categories/${pathSegment(body.categorySlug)}/topics/${pathSegment(body.topicSlug)}/moderation`,
      ),
      {
        method: "PATCH",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
          Accept: "application/json",
          "Accept-Language": "mk",
          ...forwardedForHeaders(request),
        },
        body: JSON.stringify({
          is_pinned: body.is_pinned,
          is_locked: body.is_locked,
        }),
      },
    ),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
