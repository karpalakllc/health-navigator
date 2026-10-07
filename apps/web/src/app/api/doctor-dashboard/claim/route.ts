import { NextResponse } from "next/server";
import { guardJson } from "@/lib/auth/request-guard";
import { isSlug, pathSegment } from "@/lib/api/path";
import { relayToApi } from "@/lib/api/doctor-dashboard-proxy";
import { t } from "@/i18n/t";

type ClaimPayload = {
  slug?: unknown;
  message?: unknown;
  contact?: unknown;
  /** ALTCHA payload from the form's invisible widget; the API checks it. */
  altcha?: unknown;
};

/** „Ова е мој профил“: ask staff to link this account to a doctor profile. */
export async function POST(request: Request) {
  const guarded = await guardJson<ClaimPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const { slug, message, contact, altcha } = guarded.value;

  if (!isSlug(slug)) {
    return NextResponse.json({ message: t("notFound.title") }, { status: 404 });
  }

  return relayToApi(request, {
    method: "POST",
    path: `/doctors/${pathSegment(slug)}/claim-requests`,
    body: {
      message: typeof message === "string" ? message : "",
      contact: typeof contact === "string" ? contact : "",
      altcha: typeof altcha === "string" ? altcha : null,
    },
  });
}
