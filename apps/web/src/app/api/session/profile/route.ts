import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

type ProfilePayload = {
  display_name?: unknown;
};

/** Changes the signed-in member's public display name (PATCH /me/profile). */
export async function PATCH(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<ProfilePayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  // Forward only the one editable field, not whatever else the body carried.
  const upstream = await readUpstream(
    fetch(apiUrl("/me/profile"), {
      method: "PATCH",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({ display_name: guarded.value.display_name }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
