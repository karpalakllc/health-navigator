import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { avatarFromForm, guardMultipart } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardMultipart(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const avatar = await avatarFromForm(guarded.value);

  if (!avatar) {
    return NextResponse.json(
      { message: t("errors.invalidAvatar") },
      { status: 422 },
    );
  }

  // Forward only the checked file, not whatever else the form carried.
  const formData = new FormData();
  formData.append("avatar", avatar, avatar.name);

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
