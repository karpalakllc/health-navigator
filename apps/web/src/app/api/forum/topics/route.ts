import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";

type TopicPayload = {
  categorySlug?: string;
  title?: string;
  body?: string;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = (await request.json()) as TopicPayload;

  if (!body.categorySlug) {
    return NextResponse.json(
      { message: "Category is required." },
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
      },
      body: JSON.stringify({
        title: body.title,
        body: body.body,
      }),
    },
  );

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
