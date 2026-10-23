import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

type TopicPayload = {
  categorySlug?: unknown;
  topicSlug?: unknown;
  postId?: unknown;
};

/**
 * Forwards a signed-in member's action on one topic („Следи ја темата“,
 * „Најкорисен одговор“) to the API: same-origin JSON only, slugs checked,
 * the session token added here so the browser never holds it.
 */
export async function forwardTopicAction(
  request: Request,
  method: "PUT" | "DELETE",
  action: "follow" | "accepted-answer",
): Promise<NextResponse> {
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

  const { categorySlug, topicSlug, postId } = guarded.value;

  if (!isSlug(categorySlug) || !isSlug(topicSlug)) {
    return NextResponse.json(
      { message: t("errors.topicRequired") },
      { status: 422 },
    );
  }

  let body: string | undefined;

  if (action === "accepted-answer" && method === "PUT") {
    if (
      typeof postId !== "number" ||
      !Number.isSafeInteger(postId) ||
      postId <= 0
    ) {
      return NextResponse.json(
        { message: t("forum.acceptError") },
        { status: 422 },
      );
    }
    body = JSON.stringify({ post_id: postId });
  }

  const upstream = await readUpstream(
    fetch(
      apiUrl(
        `/forum/categories/${pathSegment(categorySlug)}/topics/${pathSegment(topicSlug)}/${action}`,
      ),
      {
        method,
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
          "Accept-Language": "mk",
          ...(body ? { "Content-Type": "application/json" } : {}),
          ...forwardedForHeaders(request),
        },
        body,
      },
    ),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
