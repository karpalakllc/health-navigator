import type { ForumPost, ForumTopicDetail } from "@/lib/api/forum";
import type {
  DoctorDetail,
  FacilityType,
  PharmacyDetail,
  ReviewSummary,
} from "@/lib/api/types";

/**
 * schema.org payloads for facility and pharmacy pages, rendered through
 * components/seo/json-ld.tsx.
 *
 * Same rule as the doctor page: only facts the database holds. An empty field is
 * left out rather than sent as null, and a rating is only claimed when there are
 * published reviews behind it.
 */

type PlaceFacts = Pick<
  PharmacyDetail,
  | "name"
  | "description"
  | "city"
  | "address"
  | "latitude"
  | "longitude"
  | "phone"
  | "email"
  | "website"
  | "review_summary"
>;

const SCHEMA_TYPE_BY_FACILITY: Record<FacilityType, string> = {
  hospital: "Hospital",
  clinic: "MedicalClinic",
  laboratory: "DiagnosticLab",
};

export function facilitySchemaType(type: FacilityType): string {
  return SCHEMA_TYPE_BY_FACILITY[type] ?? "MedicalOrganization";
}

function aggregateRating(summary: ReviewSummary | undefined) {
  if (!summary || summary.count < 1 || summary.average_rating === null) {
    return {};
  }

  return {
    aggregateRating: {
      "@type": "AggregateRating",
      ratingValue: summary.average_rating,
      reviewCount: summary.count,
      bestRating: 5,
      worstRating: 1,
    },
  };
}

export function placeJsonLd(
  schemaType: string,
  place: PlaceFacts,
  url: string,
): Record<string, unknown> {
  const hasAddress = Boolean(place.address || place.city);
  const hasGeo = place.latitude !== null && place.longitude !== null;

  return {
    "@context": "https://schema.org",
    "@type": schemaType,
    name: place.name,
    url,
    ...(place.description ? { description: place.description } : {}),
    ...(hasAddress
      ? {
          address: {
            "@type": "PostalAddress",
            ...(place.address ? { streetAddress: place.address } : {}),
            ...(place.city ? { addressLocality: place.city } : {}),
            addressCountry: "MK",
          },
        }
      : {}),
    ...(hasGeo
      ? {
          geo: {
            "@type": "GeoCoordinates",
            latitude: place.latitude,
            longitude: place.longitude,
          },
        }
      : {}),
    ...(place.phone ? { telephone: place.phone } : {}),
    ...(place.email ? { email: place.email } : {}),
    ...(place.website ? { sameAs: place.website } : {}),
    ...aggregateRating(place.review_summary),
  };
}

/**
 * A doctor profile as schema.org Physician (a LocalBusiness and
 * MedicalOrganization subtype).
 *
 * Only what the profile shows: no credentials or affiliations we cannot
 * substantiate. Specialties go in `knowsAbout` (text) rather than
 * `medicalSpecialty`, whose range is schema.org's English MedicalSpecialty
 * enumeration — a Macedonian specialty name is not one of its values.
 *
 * aggregateRating is allowed here: Google's self-serving rule excludes stars
 * only when "the entity that's being reviewed controls the reviews about
 * itself". Doctors cannot add, edit or remove reviews on this site, and the
 * rating is shown on the page (docs/seo.md).
 */
export function physicianJsonLd(
  doctor: Pick<
    DoctorDetail,
    | "full_name"
    | "avatar_url"
    | "city"
    | "phone"
    | "specialties"
    | "procedures"
    | "languages"
    | "facilities"
    | "accepts_new_patients"
    | "review_summary"
  >,
  url: string,
  facilityUrl: (facility: DoctorDetail["facilities"][number]) => string,
): Record<string, unknown> {
  const specialties = [...doctor.specialties]
    .sort((a, b) => Number(b.is_primary) - Number(a.is_primary))
    .map((specialty) => specialty.name);
  const hospitals = doctor.facilities.filter(
    (facility) => facility.type === "hospital",
  );

  return {
    "@context": "https://schema.org",
    "@type": "Physician",
    name: doctor.full_name,
    url,
    ...(doctor.avatar_url ? { image: doctor.avatar_url } : {}),
    ...(specialties.length > 0 ? { knowsAbout: specialties } : {}),
    ...(doctor.city
      ? {
          address: {
            "@type": "PostalAddress",
            addressLocality: doctor.city,
            addressCountry: "MK",
          },
        }
      : {}),
    ...(doctor.phone ? { telephone: doctor.phone } : {}),
    ...(doctor.languages.length > 0 ? { knowsLanguage: doctor.languages } : {}),
    ...(doctor.procedures.length > 0
      ? {
          availableService: doctor.procedures.map((name) => ({
            "@type": "MedicalProcedure",
            name,
          })),
        }
      : {}),
    ...(hospitals.length > 0
      ? {
          hospitalAffiliation: hospitals.map((facility) => ({
            "@type": "Hospital",
            name: facility.name,
            url: facilityUrl(facility),
          })),
        }
      : {}),
    isAcceptingNewPatients: doctor.accepts_new_patients,
    ...aggregateRating(doctor.review_summary),
  };
}

export type BreadcrumbItem = { name: string; url?: string };

/**
 * BreadcrumbList: Google wants at least two items; the last may omit `item`
 * (it then stands for the page itself).
 */
export function breadcrumbJsonLd(
  items: BreadcrumbItem[],
): Record<string, unknown> | null {
  if (items.length < 2) {
    return null;
  }

  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      ...(item.url ? { item: item.url } : {}),
    })),
  };
}

function forumAuthor(name: string) {
  // Members have no public profile pages, so author.url (recommended, not
  // required) is left out rather than pointing somewhere invented.
  return { "@type": "Person", name };
}

function replyCounter(count: number) {
  return {
    "@type": "InteractionCounter",
    interactionType: "https://schema.org/CommentAction",
    userInteractionCount: count,
  };
}

type CommentSource = Pick<
  ForumPost,
  "id" | "body" | "author_name" | "published_at"
>;

/**
 * A reply taken down by moderation, kept in the thread as a placeholder
 * ({ id, removed: true, … }). Its author and text are gone, so it is not a
 * Comment. Structural on purpose: the list may hold either shape.
 */
type RemovedPlaceholder = { id: number; removed: boolean };

function isRemoved(post: object): post is RemovedPlaceholder {
  return "removed" in post && (post as { removed?: unknown }).removed === true;
}

/**
 * A forum topic as Google's DiscussionForumPosting, replies as Comment.
 *
 * Not QAPage: topics here are experiences and discussions („Искуства со…“),
 * not a single question with answers. `url` is always the first page, as
 * Google asks of multi-page threads; `comment` holds the replies rendered on
 * this page only. Required on both levels: author.name, datePublished, text.
 * An item without a publication date is skipped rather than given a made-up
 * one (approved content always has one).
 */
export function forumTopicJsonLd(input: {
  topic: Pick<
    ForumTopicDetail,
    | "title"
    | "body"
    | "author_name"
    | "published_at"
    | "last_post_at"
    | "replies_count"
    | "tags"
  >;
  /** Replies on this page; removed-reply placeholders are skipped. */
  posts: readonly (CommentSource | RemovedPlaceholder)[];
  url: string;
  category: { name: string; url: string };
}): Record<string, unknown> | null {
  const { topic, posts, url, category } = input;

  if (!topic.published_at) {
    return null;
  }

  const comments = posts
    .filter((post): post is CommentSource => !isRemoved(post))
    .filter((post) => post.published_at)
    .map((post) => ({
      "@type": "Comment",
      url: `${url}#post-${post.id}`,
      author: forumAuthor(post.author_name),
      datePublished: post.published_at,
      text: post.body,
    }));
  const tags = topic.tags ?? [];

  return {
    "@context": "https://schema.org",
    "@type": "DiscussionForumPosting",
    mainEntityOfPage: url,
    url,
    headline: topic.title,
    text: topic.body,
    author: forumAuthor(topic.author_name),
    datePublished: topic.published_at,
    ...(topic.last_post_at && topic.last_post_at !== topic.published_at
      ? { dateModified: topic.last_post_at }
      : {}),
    inLanguage: "mk",
    ...(tags.length > 0
      ? { keywords: tags.map((tag) => tag.name).join(", ") }
      : {}),
    isPartOf: {
      "@type": "WebPage",
      name: category.name,
      url: category.url,
    },
    interactionStatistic: replyCounter(topic.replies_count),
    ...(comments.length > 0 ? { comment: comments } : {}),
  };
}

/** The home page's WebSite entity: gives Google the site name to show. */
export function websiteJsonLd(
  name: string,
  url: string,
  description: string,
): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name,
    url,
    description,
    inLanguage: "mk",
  };
}
