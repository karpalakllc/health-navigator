import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { avatarFromForm, guardMultipart } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

/** „Мој профил“: the doctor's photo (POST /me/doctor/avatar). */
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

  const upstream = await readUpstream(
    fetch(apiUrl("/me/doctor/avatar"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: formData,
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}
