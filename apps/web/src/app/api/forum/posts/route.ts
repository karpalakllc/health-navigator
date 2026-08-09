import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";

type PostPayload = {
  categorySlug?: string;
  topicSlug?: string;
  body?: string;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: t("errors.unauthenticated") }, { status: 401 });
  }

  const body = (await request.json()) as PostPayload;

  if (!body.categorySlug || !body.topicSlug) {
    return NextResponse.json(
      { message: t("errors.topicRequired") },
      { status: 422 },
    );
  }

  const response = await fetch(
    apiUrl(
      `/forum/categories/${body.categorySlug}/topics/${body.topicSlug}/posts`,
    ),
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      "Accept-Language": "mk",
      },
      body: JSON.stringify({ body: body.body }),
    },
  );

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
