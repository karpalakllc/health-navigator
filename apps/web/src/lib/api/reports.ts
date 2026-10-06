/** Report reason codes, as the API accepts them (App\Enums\ReportReason). */
export const REPORT_REASONS = [
  "spam",
  "abuse",
  "false_information",
  "personal_data",
  "other",
] as const;

export type ReportReason = (typeof REPORT_REASONS)[number];

/** The API's `max:500` on the optional note. */
export const REPORT_NOTE_MAX = 500;

/** What is being reported; posted to /api/reports. */
export type ReportTarget =
  | { kind: "review"; id: number }
  | { kind: "forum_post"; id: number }
  | { kind: "forum_topic"; categorySlug: string; topicSlug: string };
