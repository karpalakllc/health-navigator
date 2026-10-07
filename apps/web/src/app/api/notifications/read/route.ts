import { rejectCrossSite } from "@/lib/auth/request-guard";
import { relayToApi } from "@/lib/api/notifications-relay";

/** Marks the member's notifications read (the list was opened). */
export async function POST(request: Request) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  return relayToApi(request, "/me/notifications/read", {
    method: "POST",
    auth: "required",
  });
}
