import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { t } from "@/i18n/t";

type ReviewPayload = {
  kind?: "doctor" | "facility";
  slug?: string;
  rating?: number;
  body?: string | null;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const body = (await request.json()) as ReviewPayload;

  if (body.kind !== "doctor" && body.kind !== "facility") {
    return NextResponse.json(
      { message: t("errors.invalidReviewTarget") },
      { status: 422 },
    );
  }

  if (!body.slug) {
    return NextResponse.json(
      { message: t("errors.invalidReviewTarget") },
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
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: JSON.stringify({
      rating: body.rating,
      body: body.body,
    }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
