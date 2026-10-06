import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";

type TopicPayload = {
  categorySlug?: string;
  title?: string;
  body?: string;
  accepted_community_rules?: boolean;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<TopicPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  if (!isSlug(body.categorySlug)) {
    return NextResponse.json(
      { message: t("errors.categoryRequired") },
      { status: 422 },
    );
  }

  const upstream = await readUpstream(
    fetch(
      apiUrl(`/forum/categories/${pathSegment(body.categorySlug)}/topics`),
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
          Accept: "application/json",
          "Accept-Language": "mk",
          ...forwardedForHeaders(request),
        },
        body: JSON.stringify({
          title: body.title,
          body: body.body,
          accepted_community_rules: body.accepted_community_rules ?? false,
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
