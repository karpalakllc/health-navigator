import { NextResponse } from "next/server";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { isPositiveId, relayToApi } from "@/lib/api/doctor-dashboard-proxy";
import { t } from "@/i18n/t";

/** „Мој профил“: withdraw the pending change request. */
export async function DELETE(
  request: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const { id } = await params;

  if (!isPositiveId(id)) {
    return NextResponse.json({ message: t("notFound.title") }, { status: 404 });
  }

  return relayToApi(request, {
    method: "DELETE",
    path: `/me/doctor/change-requests/${id}`,
  });
}
