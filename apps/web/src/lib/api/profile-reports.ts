/**
 * „Пријави профил“ (App\Enums\ProfileReportReason, W7-C). Posted to
 * /api/profile-reports, which relays to the API.
 */

export type ProfileReportSubject = "doctor" | "facility" | "pharmacy";

/** Reason codes in the order the sheet lists them. */
export const PROFILE_REPORT_REASONS = [
  "fake_profile",
  "wrong_person",
  "no_longer_here",
  "inappropriate_content",
  "other",
] as const;

export type ProfileReportReason = (typeof PROFILE_REPORT_REASONS)[number];

/** The API's `max:500` on the optional note. */
export const PROFILE_REPORT_NOTE_MAX = 500;

/** The hidden field people never fill; the API stores nothing when it is. */
export const PROFILE_REPORT_HONEYPOT = "website";

const API_SEGMENT: Record<ProfileReportSubject, string> = {
  doctor: "doctors",
  facility: "facilities",
  pharmacy: "pharmacies",
};

export function isProfileReportSubject(
  value: unknown,
): value is ProfileReportSubject {
  return value === "doctor" || value === "facility" || value === "pharmacy";
}

export function isProfileReportReason(
  value: unknown,
): value is ProfileReportReason {
  return (PROFILE_REPORT_REASONS as readonly unknown[]).includes(value);
}

/** The API path (under /v1) for a subject's reports. */
export function profileReportApiPath(
  subject: ProfileReportSubject,
  encodedSlug: string,
): string {
  return `/${API_SEGMENT[subject]}/${encodedSlug}/profile-reports`;
}
