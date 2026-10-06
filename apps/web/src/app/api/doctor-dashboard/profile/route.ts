import { guardJson } from "@/lib/auth/request-guard";
import { pick, relayToApi } from "@/lib/api/doctor-dashboard-proxy";

/** What PATCH /me/doctor saves at once; nothing else is forwarded. */
const PRACTICE_FIELDS = [
  "bio",
  "phone",
  "email",
  "consultation_fee_note",
  "accepts_new_patients",
  "office_hours",
  "language_ids",
  "clinical_interest_ids",
  "procedure_ids",
] as const;

/** „Мој профил“: save the practice details (PATCH /me/doctor). */
export async function PATCH(request: Request) {
  const guarded = await guardJson<Record<string, unknown>>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  return relayToApi(request, {
    method: "PATCH",
    path: "/me/doctor",
    body: pick(guarded.value, PRACTICE_FIELDS),
  });
}
