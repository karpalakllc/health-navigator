export type ApiEnvelope<T> = {
  data: T;
};

export type PaginatedEnvelope<T> = {
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    viewer_review?: ViewerReview | null;
    /** Review lists only: approved reviews per star, for the histogram. */
    rating_counts?: ReviewRatingCounts;
    /** Review lists only: per-aspect averages (null below 3 ratings). */
    aspects?: ReviewAspectSummary[];
    /** Review lists only: 12-month trend, or null below 5 reviews. */
    trend?: ReviewTrendPeriod[] | null;
  };
};

/** Public reason a published review or reply was later removed. */
export type RemovalCategory =
  | "spam"
  | "abuse"
  | "false_information"
  | "personal_data"
  | "illegal"
  | "other";

/**
 * A review or forum reply that was published and later removed: it stays in
 * its list as a placeholder with only the date and the public reason.
 */
export type RemovedItem = {
  id: number;
  removed: true;
  removed_at: string | null;
  removal_category: RemovalCategory | null;
};

export function isRemovedItem(item: object): item is RemovedItem {
  return "removed" in item && item.removed === true;
}

/** A row of a profile's review list: a review or a removal placeholder. */
export type ReviewListItem = PublicReview | RemovedItem;

export type ReviewAspectSummary = {
  key: string;
  count: number;
  /** Withheld (null) below 3 approved ratings. */
  average: number | null;
};

/** One three-month period of the rating trend (dates are YYYY-MM-DD). */
export type ReviewTrendPeriod = {
  start: string;
  end: string;
  count: number;
  average: number | null;
};

/** Approved reviews per star („1“…„5“), whatever the list's filter or page. */
export type ReviewRatingCounts = Record<"1" | "2" | "3" | "4" | "5", number>;

export type ViewerReview = {
  id: number;
  rating: number;
  body: string | null;
  status: "pending" | "approved" | "rejected";
  /** Why it was refused; only on the author's own rejected review. */
  rejection_note?: string | null;
  /** Taken down after publication: a placeholder, never resent. */
  removed?: boolean;
  /** Refused before publication and not yet resent: one more try allowed. */
  can_resubmit?: boolean;
  /** The aspect ratings it carried, by code. */
  aspects?: Record<string, number>;
  created_at: string | null;
  published_at: string | null;
};

export type ApiHealthData = {
  status: string;
};

export type Specialty = {
  slug: string;
  name: string;
  description: string | null;
  doctors_count: number;
};

export type DepartmentListItem = {
  slug: string;
  name: string;
};

export type ForumTopicSearchItem = {
  slug: string;
  title: string;
  author_name: string;
  replies_count: number;
  last_post_at: string | null;
  published_at: string | null;
  category: {
    slug: string;
    name: string;
  };
};

export type UnifiedSearchResult = {
  doctors: PaginatedEnvelope<DoctorListItem>;
  facilities: PaginatedEnvelope<FacilityListItem>;
  pharmacies: PaginatedEnvelope<PharmacyListItem>;
  products: PaginatedEnvelope<ProductListItem>;
  forum_topics: PaginatedEnvelope<ForumTopicSearchItem>;
  /** Specialties whose name matches: shortcuts to /doctors?specialty=. */
  specialties?: Array<Pick<Specialty, "slug" | "name" | "doctors_count">>;
  grand_total: number;
};

/**
 * Whether a profile's data is confirmed by an official source or our team.
 * `basis` / `basis_label` are null while unverified; the evidence behind a
 * decision is never in the API.
 */
export type Verification = {
  status: "verified" | "unverified";
  basis:
    | "official_registers"
    | "licence_and_website"
    | "website_and_register"
    | "staff"
    | "owner_claim"
    | null;
  basis_label: string | null;
};

export type DoctorListItem = {
  slug: string;
  full_name: string;
  title: string | null;
  subspecialty: string | null;
  city: string | null;
  avatar_url: string | null;
  years_experience: number | null;
  accepts_new_patients: boolean;
  is_featured: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  is_sponsored: boolean;
  primary_specialty: {
    slug: string;
    name: string;
  } | null;
  /** All published specialties, primary first. */
  specialties?: Array<{ slug: string; name: string }>;
  primary_facility: {
    name: string;
    city: string | null;
  } | null;
  review_summary: ReviewSummary;
  /**
   * Not in the list payload yet: when the API adds it, cards get a
   * tap-to-call „Јави се“ (and an open-now line from office_hours).
   */
  phone?: string | null;
  office_hours?: Record<string, string> | unknown[];
};

export type ReviewSummary = {
  count: number;
  average_rating: number | null;
};

export type PublicReview = {
  id: number;
  rating: number;
  body: string | null;
  author_name: string;
  /** W8-C: the author's reviewer level (1–5), or null for none to show. */
  author_level?: number | null;
  published_at: string | null;
  /** The reviewed profile's official reply (plain text), entered by staff. */
  response?: ReviewResponse | null;
  /** „Корисно“ votes. */
  helpful_count?: number;
  /** Optional aspect sub-ratings by code (communication, waiting_time…). */
  aspects?: Record<string, number>;
  /** Present only on signed-in requests. */
  viewer?: { has_voted_helpful: boolean };
};

export type ReviewResponse = {
  body: string;
  responder_name: string | null;
  responded_at: string | null;
  /** `doctor`: written by the linked doctor („Одговор од лекарот“); `staff`: entered on the profile's behalf. */
  source?: "staff" | "doctor";
};

export type DoctorDetail = {
  slug: string;
  full_name: string;
  title: string | null;
  subspecialty: string | null;
  bio: string | null;
  years_experience: number | null;
  education: string | null;
  languages: string[];
  clinical_interests: string[];
  procedures: string[];
  consultation_fee_note: string | null;
  avatar_url: string | null;
  office_hours: Record<string, string>;
  accepts_new_patients: boolean;
  is_featured: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  is_sponsored: boolean;
  city: string | null;
  phone: string | null;
  email: string | null;
  specialties: {
    slug: string;
    name: string;
    is_primary: boolean;
  }[];
  facilities: {
    slug: string;
    name: string;
    type: FacilityType | "pharmacy";
    city: string | null;
    is_primary: boolean;
  }[];
  review_summary: ReviewSummary;
  /**
   * The Лекарска комора list shows a licence valid today. Status only: the
   * licence number and expiry date are never in the payload. False also when
   * we simply do not know (dentists, profiles without a match).
   */
  has_valid_licence: boolean;
};

export type FacilityType = "clinic" | "hospital" | "laboratory";

export type FacilityListItem = {
  slug: string;
  name: string;
  type: FacilityType;
  city: string | null;
  /** The logo (null in most demo data: the initial is shown instead). */
  avatar_url: string | null;
  /** Cover photo, WebP ≤1600×900; optional until every API serves it. */
  cover_url?: string | null;
  has_emergency_services: boolean;
  is_featured: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  departments_count: number;
  review_summary: ReviewSummary;
  /**
   * Not in the list payload yet: when the API adds it, cards get a
   * tap-to-call „Јави се“ (and an open-now line from office_hours).
   */
  phone?: string | null;
  office_hours?: Record<string, string> | unknown[];
};

export type FacilityDetail = {
  slug: string;
  name: string;
  type: FacilityType;
  description: string | null;
  city: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  has_emergency_services: boolean;
  departments: string[];
  phone: string | null;
  email: string | null;
  website: string | null;
  avatar_url: string | null;
  cover_url?: string | null;
  is_featured?: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  office_hours: Record<string, string>;
  doctors: {
    slug: string;
    full_name: string;
    title: string | null;
    is_primary: boolean;
  }[];
  review_summary: ReviewSummary;
};

export type PharmacyListItem = {
  slug: string;
  name: string;
  city: string | null;
  /** The logo, else the initial. */
  avatar_url: string | null;
  /** Cover photo, WebP ≤1600×900. */
  cover_url?: string | null;
  is_featured?: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  review_summary: ReviewSummary;
  /**
   * Not in the list payload yet: when the API adds it, cards get a
   * tap-to-call „Јави се“ (and an open-now line from office_hours).
   */
  phone?: string | null;
  office_hours?: Record<string, string> | unknown[];
};

export type PharmacyDetail = {
  slug: string;
  name: string;
  description: string | null;
  city: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  avatar_url: string | null;
  cover_url?: string | null;
  is_featured?: boolean;
  /** „Верификуван“ / „Неверификуван“ (list and detail payloads). */
  verification?: Verification;
  office_hours: Record<string, string>;
  /** „Дежурна денес“ from ФЗОМ's schedule; null/absent when not on duty. */
  on_duty_today?: {
    date: string;
    mode: "all_day" | "hours" | "on_call" | "unknown";
    hours_text: string | null;
  } | null;
  review_summary: ReviewSummary;
};

export type PharmacyShelfProduct = {
  slug: string;
  name: string;
  category: string | null;
  price: number;
  currency: string;
  price_updated_at: string | null;
};

export type ProductListItem = {
  slug: string;
  name: string;
  category: string | null;
  from_price: number | null;
  offer_count: number;
};

export type ProductOffer = {
  pharmacy: {
    slug: string;
    name: string;
    city: string | null;
  };
  price: number;
  currency: string;
  price_updated_at: string | null;
};

export type ProductDetail = {
  slug: string;
  name: string;
  description: string | null;
  category: string | null;
  offers: ProductOffer[];
  offers_total: number;
  offers_truncated: boolean;
};
