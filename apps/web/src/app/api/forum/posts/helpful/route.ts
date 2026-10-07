import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { pathSegment } from "@/lib/api/path";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";

type HelpfulPayload = { id?: unknown };

/** W8-C: „Корисно“ on a forum reply — PUT marks it, DELETE takes the mark back. */
async function forward(request: Request, method: "PUT" | "DELETE") {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<HelpfulPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const { id } = guarded.value;

  if (typeof id !== "number" || !Number.isSafeInteger(id) || id <= 0) {
    return NextResponse.json(
      { message: t("reviews.helpfulError") },
      { status: 422 },
    );
  }

  const upstream = await readUpstream(
    fetch(apiUrl(`/forum/posts/${pathSegment(id)}/helpful`), {
      method,
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}

export async function PUT(request: Request) {
  return forward(request, "PUT");
}

export async function DELETE(request: Request) {
  return forward(request, "DELETE");
}
