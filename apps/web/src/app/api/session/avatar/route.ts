import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { t } from "@/i18n/t";

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const formData = await request.formData();

  const response = await fetch(apiUrl("/me/avatar"), {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: formData,
  });

  const body = await response
    .json()
    .catch(() => ({ message: t("errors.uploadFailed") }));

  return NextResponse.json(body, { status: response.status });
}
