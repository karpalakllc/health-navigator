import { guardJson } from "@/lib/auth/request-guard";
import { pick, relayToApi } from "@/lib/api/doctor-dashboard-proxy";

/** The sensitive fields staff check before they change (and a note). */
const REQUEST_FIELDS = [
  "full_name",
  "title",
  "subspecialty",
  "education",
  "years_experience",
  "city",
  "specialty_ids",
  "primary_specialty_id",
  "facility_ids",
  "primary_facility_id",
  "message",
] as const;

/** „Мој профил“: ask staff to change sensitive fields. */
export async function POST(request: Request) {
  const guarded = await guardJson<Record<string, unknown>>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  return relayToApi(request, {
    method: "POST",
    path: "/me/doctor/change-requests",
    body: pick(guarded.value, REQUEST_FIELDS),
  });
}
