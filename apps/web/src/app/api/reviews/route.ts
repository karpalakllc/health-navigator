import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { isSlug, pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";
import { aspectsForUpstream } from "@/lib/review-integrity";

type ReviewPayload = {
  kind?: "doctor" | "facility" | "pharmacy";
  slug?: string;
  rating?: number;
  body?: string | null;
  /** Optional aspect sub-ratings, {code: 1–5}; the API validates them. */
  aspects?: unknown;
};

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<ReviewPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  if (
    body.kind !== "doctor" &&
    body.kind !== "facility" &&
    body.kind !== "pharmacy"
  ) {
    return NextResponse.json(
      { message: t("errors.invalidReviewTarget") },
      { status: 422 },
    );
  }

  if (!isSlug(body.slug)) {
    return NextResponse.json(
      { message: t("errors.invalidReviewTarget") },
      { status: 422 },
    );
  }

  const path =
    body.kind === "doctor"
      ? `/doctors/${pathSegment(body.slug)}/reviews`
      : body.kind === "pharmacy"
        ? `/pharmacies/${pathSegment(body.slug)}/reviews`
        : `/facilities/${pathSegment(body.slug)}/reviews`;

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
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
        aspects: aspectsForUpstream(body.aspects),
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
