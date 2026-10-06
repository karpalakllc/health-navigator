/**
 * „Пријави грешка во профилот“ and the listed doctor's objection or removal
 * request (App\Enums\ProfileCorrectionField / ProfileCorrectionType). Posted
 * to /api/corrections, which relays to the API.
 */

export type CorrectionSubject = "doctor" | "facility";

export type CorrectionType = "correction" | "objection";

/** Field codes per profile kind, in the order the form lists them. */
export const CORRECTION_FIELDS = {
  doctor: [
    "name",
    "title",
    "specialty",
    "workplace",
    "address",
    "contact",
    "office_hours",
    "photo",
    "description",
    "no_longer_practising",
    "other",
  ],
  facility: [
    "name",
    "address",
    "contact",
    "office_hours",
    "photo",
    "description",
    "doctors",
    "departments",
    "closed",
    "other",
  ],
} as const satisfies Record<CorrectionSubject, readonly string[]>;

export type CorrectionField =
  (typeof CORRECTION_FIELDS)[CorrectionSubject][number];

/** The API's `max:1000` on the message. */
export const CORRECTION_MESSAGE_MAX = 1000;

/** The API's `min:10` on the message. */
export const CORRECTION_MESSAGE_MIN = 10;

/** The API's `max:255` on the contact. */
export const CORRECTION_CONTACT_MAX = 255;

/** The hidden field people never fill; the API drops a request that has it. */
export const CORRECTION_HONEYPOT = "website";

export function isCorrectionSubject(
  value: unknown,
): value is CorrectionSubject {
  return value === "doctor" || value === "facility";
}

export function isCorrectionField(
  subject: CorrectionSubject,
  value: unknown,
): value is CorrectionField {
  return (CORRECTION_FIELDS[subject] as readonly unknown[]).includes(value);
}

/** Public profile path the form pages go back to. */
export function correctionProfilePath(
  subject: CorrectionSubject,
  slug: string,
): string {
  return `/${subject === "doctor" ? "doctors" : "facilities"}/${encodeURIComponent(slug)}`;
}
