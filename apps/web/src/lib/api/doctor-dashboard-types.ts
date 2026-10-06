/**
 * „Мој профил“ payload types and limits, without any server-only import, so
 * client components can use them (doctor-dashboard.ts holds the fetchers).
 */
/** A specialty or workplace link as the dashboard shows and edits it. */
export type DoctorLink = { id: number; name: string; is_primary: boolean };

export type DashboardOption = { id: number; name: string };

/** The profile as its linked doctor edits it (GET /me/doctor `doctor`). */
export type ManagedDoctor = {
  slug: string;
  is_published: boolean;
  full_name: string;
  title: string | null;
  subspecialty: string | null;
  education: string | null;
  years_experience: number | null;
  city: string | null;
  bio: string | null;
  phone: string | null;
  email: string | null;
  consultation_fee_note: string | null;
  accepts_new_patients: boolean;
  office_hours: Record<string, string>;
  avatar_url: string | null;
  specialties: DoctorLink[];
  facilities: DoctorLink[];
  language_ids: number[];
  clinical_interest_ids: number[];
  procedure_ids: number[];
  linked_at: string | null;
};

/** Sensitive fields a change request can carry, as the API names them. */
export type SensitiveField =
  | "full_name"
  | "title"
  | "subspecialty"
  | "education"
  | "years_experience"
  | "city"
  | "specialties"
  | "facilities";

export type ChangeValue = string | number | null | DoctorLink[];

export type DoctorChangeRequest = {
  id: number;
  status: "pending" | "approved" | "rejected" | "withdrawn";
  changes: Partial<
    Record<SensitiveField, { old: ChangeValue; new: ChangeValue }>
  >;
  message: string | null;
  rejection_reason: string | null;
  created_at: string | null;
  reviewed_at: string | null;
};

export type DoctorDashboard = {
  doctor: ManagedDoctor;
  pending_change_request: DoctorChangeRequest | null;
  recent_change_requests: DoctorChangeRequest[];
  stats: {
    review_count: number;
    average_rating: number | null;
    unanswered_reviews: number;
    pending_replies: number;
  };
  settings: { replies_require_moderation: boolean };
  options: {
    specialties: DashboardOption[];
    languages: DashboardOption[];
    clinical_interests: DashboardOption[];
    procedures: DashboardOption[];
    facilities: (DashboardOption & { city: string | null })[];
    days: string[];
  };
};

export type DoctorReplyState = {
  body: string;
  source: "staff" | "doctor";
  status: "pending" | "approved" | "rejected" | null;
  responded_at: string | null;
  rejection_note: string | null;
};

/** A published review of the doctor's own profile (public author name only). */
export type DashboardReview = {
  id: number;
  rating: number;
  body: string | null;
  author_name: string;
  published_at: string | null;
  helpful_count: number;
  reply: DoctorReplyState | null;
  can_reply: boolean;
};

export type ReviewFilter = "all" | "unanswered";

/** The longest reply the API accepts (Review::RESPONSE_MAX_LENGTH). */
export const REPLY_MAX_LENGTH = 2000;
