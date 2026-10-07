import { NextResponse } from "next/server";
import { guardJson } from "@/lib/auth/request-guard";
import { isSlug } from "@/lib/api/path";
import { relayToApi } from "@/lib/api/notifications-relay";
import { t } from "@/i18n/t";

/** „Потсети ме за 2 недели“ on a profile: the member's explicit request. */
export async function POST(request: Request) {
  const guarded = await guardJson<{ kind?: unknown; slug?: unknown }>(
    request,
    2 * 1024,
  );

  if (!guarded.ok) {
    return guarded.response;
  }

  const { kind, slug } = guarded.value;

  if (
    (kind !== "doctor" && kind !== "facility" && kind !== "pharmacy") ||
    !isSlug(slug)
  ) {
    return NextResponse.json(
      { message: t("reviewFlow.promptReminderError") },
      { status: 422 },
    );
  }

  return relayToApi(request, "/me/review-reminders", {
    method: "POST",
    body: { kind, slug },
    auth: "required",
  });
}
