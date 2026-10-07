import { NextResponse } from "next/server";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";
import { UX_OVERLAY_TOKEN_HEADER } from "@/lib/ux/overlay-api";
import { isUxRoute } from "@/lib/ux/routes";
import { UX_MAX_WIDTH, UX_VIEWPORT_CLASSES } from "@/lib/ux/schema";

/**
 * The staff heatmap overlay's data (docs/ux-heatmaps.md).
 *
 * Without the overlay token this answers 403 and never calls the API; with
 * one, the API verifies its signature, expiry and the staff member's
 * permission on every request. Nothing is cached anywhere.
 */
const TOKEN = /^[A-Za-z0-9_-]{8,400}\.[A-Za-z0-9_-]{20,100}$/;
const NO_STORE = { "Cache-Control": "no-store, private" };

function refuse(status: number) {
  return NextResponse.json(
    { message: t(status === 502 ? "uxOverlay.failed" : "uxOverlay.expired") },
    { status, headers: NO_STORE },
  );
}

export async function GET(request: Request) {
  const token = request.headers.get(UX_OVERLAY_TOKEN_HEADER) ?? "";

  if (!TOKEN.test(token)) {
    return refuse(403);
  }

  const url = new URL(request.url);
  const route = url.searchParams.get("route");
  const vc = url.searchParams.get("vc");
  const wb = url.searchParams.get("wb");

  if (
    !isUxRoute(route) ||
    !(UX_VIEWPORT_CLASSES as readonly (string | null)[]).includes(vc) ||
    (wb !== null && !/^\d{1,4}$/.test(wb)) ||
    (wb !== null && Number(wb) > UX_MAX_WIDTH)
  ) {
    return refuse(422);
  }

  const query = new URLSearchParams({ route, vc: vc as string });
  if (wb !== null) query.set("wb", wb);

  try {
    const upstream = await fetch(apiUrl(`/ux/heatmap?${query.toString()}`), {
      headers: {
        Accept: "application/json",
        [UX_OVERLAY_TOKEN_HEADER]: token,
        ...forwardedForHeaders(request),
      },
      cache: "no-store",
    });
    const payload: unknown = await upstream.json().catch(() => ({}));

    return NextResponse.json(payload, {
      status: upstream.status,
      headers: NO_STORE,
    });
  } catch {
    return refuse(502);
  }
}
