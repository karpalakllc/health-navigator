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
  };
};

/** Approved reviews per star („1“…„5“), whatever the list's filter or page. */
export type ReviewRatingCounts = Record<"1" | "2" | "3" | "4" | "5", number>;

export type ViewerReview = {
  id: number;
  rating: number;
  body: string | null;
  status: "pending" | "approved" | "rejected";
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
  grand_total: number;
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
  published_at: string | null;
  /** The reviewed profile's official reply (plain text), entered by staff. */
  response?: ReviewResponse | null;
  /** „Корисно“ votes. */
  helpful_count?: number;
  /** Present only on signed-in requests. */
  viewer?: { has_voted_helpful: boolean };
};

export type ReviewResponse = {
  body: string;
  responder_name: string | null;
  responded_at: string | null;
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
  office_hours: Record<string, string>;
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
