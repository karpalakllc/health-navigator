import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";

type ModerationPayload = {
  categorySlug?: string;
  topicSlug?: string;
  is_pinned?: boolean;
  is_locked?: boolean;
};

export async function PATCH(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: t("errors.unauthenticated") }, { status: 401 });
  }

  const body = (await request.json()) as ModerationPayload;

  if (!body.categorySlug || !body.topicSlug) {
    return NextResponse.json({ message: t("errors.topicRequired") }, { status: 422 });
  }

  const response = await fetch(
    apiUrl(
      `/forum/categories/${body.categorySlug}/topics/${body.topicSlug}/moderation`,
    ),
    {
      method: "PATCH",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      "Accept-Language": "mk",
      },
      body: JSON.stringify({
        is_pinned: body.is_pinned,
        is_locked: body.is_locked,
      }),
    },
  );

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
