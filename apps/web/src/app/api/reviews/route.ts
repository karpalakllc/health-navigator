import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";

type ReviewPayload = {
  kind?: "doctor" | "facility";
  slug?: string;
  rating?: number;
  body?: string | null;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = (await request.json()) as ReviewPayload;

  if (body.kind !== "doctor" && body.kind !== "facility") {
    return NextResponse.json(
      { message: "Invalid review target.", errors: { kind: ["Required."] } },
      { status: 422 },
    );
  }

  if (!body.slug) {
    return NextResponse.json(
      { message: "Invalid review target.", errors: { slug: ["Required."] } },
      { status: 422 },
    );
  }

  const path =
    body.kind === "doctor"
      ? `/doctors/${body.slug}/reviews`
      : `/facilities/${body.slug}/reviews`;

  const response = await fetch(apiUrl(path), {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      rating: body.rating,
      body: body.body,
    }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
