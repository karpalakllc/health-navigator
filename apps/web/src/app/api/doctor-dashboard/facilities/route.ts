import { NextResponse } from "next/server";
import { relayToApi } from "@/lib/api/doctor-dashboard-proxy";
import { rejectCrossSite } from "@/lib/auth/request-guard";

/**
 * „Мој профил“: workplaces to add to a change request, found by name
 * (GET /me/doctor/facilities). The dashboard lists only the profile's own
 * workplaces, so every other one is looked up here. Same-origin only.
 */
export async function GET(request: Request) {
  const refused = rejectCrossSite(request);

  if (refused) {
    return refused;
  }

  const query = (new URL(request.url).searchParams.get("q") ?? "").trim();

  if ([...query].length < 2 || [...query].length > 100) {
    return NextResponse.json({ data: [] });
  }

  const response = await relayToApi(request, {
    method: "GET",
    path: `/me/doctor/facilities?q=${encodeURIComponent(query)}`,
  });
  response.headers.set("Cache-Control", "no-store");

  return response;
}
