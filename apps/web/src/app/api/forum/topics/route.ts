import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";

type TopicPayload = {
  categorySlug?: string;
  title?: string;
  body?: string;
  accepted_community_rules?: boolean;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: t("errors.unauthenticated") }, { status: 401 });
  }

  const body = (await request.json()) as TopicPayload;

  if (!body.categorySlug) {
    return NextResponse.json(
      { message: t("errors.categoryRequired") },
      { status: 422 },
    );
  }

  const response = await fetch(
    apiUrl(`/forum/categories/${body.categorySlug}/topics`),
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      "Accept-Language": "mk",
      },
      body: JSON.stringify({
        title: body.title,
        body: body.body,
        accepted_community_rules: body.accepted_community_rules ?? false,
      }),
    },
  );

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
