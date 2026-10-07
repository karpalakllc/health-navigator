import { NextResponse } from "next/server";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { relayToApi } from "@/lib/api/notifications-relay";
import { t } from "@/i18n/t";

/** Cancels one reminder; another member's id deletes nothing (API). */
export async function DELETE(
  request: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const { id } = await params;

  if (!/^\d{1,18}$/.test(id)) {
    return NextResponse.json(
      { message: t("notifications.reminderError") },
      { status: 404 },
    );
  }

  return relayToApi(request, `/me/review-reminders/${id}`, {
    method: "DELETE",
    auth: "required",
  });
}
