import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";

type ExportPayload = {
  password?: unknown;
};

/**
 * Downloads the member's data export (POST /me/export) after password step-up.
 * The bearer token stays in this server — the browser only carries the
 * httpOnly session cookie and the password in the JSON body.
 */
export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<ExportPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const password = guarded.value.password;

  let upstream: Response;

  try {
    upstream = await fetch(apiUrl("/me/export"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({
        password: typeof password === "string" ? password : "",
      }),
      cache: "no-store",
    });
  } catch {
    return NextResponse.json(
      { message: t("account.data.exportError") },
      { status: 502 },
    );
  }

  if (upstream.status === 401) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  if (!upstream.ok) {
    const payload = await upstream.json().catch(() => ({
      message: t("account.data.exportError"),
    }));

    return NextResponse.json(payload, { status: upstream.status });
  }

  if (!upstream.body) {
    return NextResponse.json(
      { message: t("account.data.exportError") },
      { status: 502 },
    );
  }

  return new Response(upstream.body, {
    status: 200,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Content-Disposition":
        upstream.headers.get("content-disposition") ??
        'attachment; filename="zdravje360.json"',
      "Cache-Control": "no-store, private",
      "X-Content-Type-Options": "nosniff",
    },
  });
}
