import { NextResponse } from "next/server";
import { guardJson, rejectCrossSite } from "@/lib/auth/request-guard";
import { isPositiveId, relayToApi } from "@/lib/api/doctor-dashboard-proxy";
import { t } from "@/i18n/t";

type Params = { params: Promise<{ id: string }> };

function notFound() {
  return NextResponse.json({ message: t("notFound.title") }, { status: 404 });
}

/** „Мој профил“: write or replace the doctor's reply to one review. */
export async function PUT(request: Request, { params }: Params) {
  const guarded = await guardJson<{ body?: unknown }>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const { id } = await params;

  if (!isPositiveId(id)) {
    return notFound();
  }

  return relayToApi(request, {
    method: "PUT",
    path: `/me/doctor/reviews/${id}/reply`,
    body: {
      body: typeof guarded.value.body === "string" ? guarded.value.body : "",
    },
  });
}

/** „Мој профил“: remove the doctor's own reply. */
export async function DELETE(request: Request, { params }: Params) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const { id } = await params;

  if (!isPositiveId(id)) {
    return notFound();
  }

  return relayToApi(request, {
    method: "DELETE",
    path: `/me/doctor/reviews/${id}/reply`,
  });
}
