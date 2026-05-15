import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";

type PostPayload = {
  categorySlug?: string;
  topicSlug?: string;
  body?: string;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = (await request.json()) as PostPayload;

  if (!body.categorySlug || !body.topicSlug) {
    return NextResponse.json(
      { message: "Topic is required." },
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
      },
      body: JSON.stringify({ body: body.body }),
    },
  );

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
