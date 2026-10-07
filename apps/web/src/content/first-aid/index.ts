import {
  choking,
  chokingInfant,
  cprAdult,
  cprChild,
  cprInfant,
} from "@/content/first-aid/guides/breathing";
import {
  heatStroke,
  hypothermia,
  poisoning,
} from "@/content/first-aid/guides/environment";
import {
  bleeding,
  burns,
  headInjury,
  nosebleed,
  sprain,
} from "@/content/first-aid/guides/injuries";
import {
  anaphylaxis,
  fainting,
  heartAttack,
  hypoglycaemia,
  seizure,
  stroke,
} from "@/content/first-aid/guides/sudden";
import type { FirstAidGroupId, FirstAidGuide } from "@/content/first-aid/types";

/*
 * The first-aid registry and its LINK CONTRACT (docs/first-aid.md). Other
 * parts of the site — above all the guidance emergency outcomes — link here
 * only through firstAidHref()/firstAidLinksForTopic(), never with a
 * hand-written path, so an unpublished guide is never linked publicly.
 */

export const FIRST_AID_BASE = "/prva-pomos";

/** Every guide slug. Stable: a slug, once linked, is never renamed or reused. */
export const FIRST_AID_SLUGS = [
  "kpr-vozrasni",
  "kpr-dete",
  "kpr-bebe",
  "zadavuvanje",
  "zadavuvanje-bebe",
  "mozocen-udar",
  "srcev-udar",
  "epileptichen-napad",
  "anafilaksa",
  "nesvestica",
  "nizok-sheker",
  "silno-krvarenje",
  "izgorenici",
  "povreda-na-glava",
  "krvarenje-od-nos",
  "istegnuvanje",
  "truenje",
  "toploten-udar",
  "hipotermija",
] as const;

export type FirstAidSlug = (typeof FIRST_AID_SLUGS)[number];

/** Page-level anchors present on every guide page (part of the contract). */
export const FIRST_AID_ANCHORS = {
  /** „Прво повикајте 194“ / when to call. */
  call: "povikajte-194",
  /** „Како да препознаете“. */
  recognise: "prepoznavanje",
  /** The numbered steps (each step section also has its own id). */
  steps: "pomos",
  /** „Што да НЕ правите“. */
  dont: "ne-pravete",
  /** „Повикајте 194 (повторно) ако…“. */
  escalate: "koga-194",
  /** Sources and review status. */
  sources: "izvori",
} as const;

export type FirstAidAnchor =
  (typeof FIRST_AID_ANCHORS)[keyof typeof FIRST_AID_ANCHORS];

export const FIRST_AID_GUIDES: readonly FirstAidGuide[] = [
  cprAdult,
  cprChild,
  cprInfant,
  choking,
  chokingInfant,
  stroke,
  heartAttack,
  seizure,
  anaphylaxis,
  fainting,
  hypoglycaemia,
  bleeding,
  burns,
  headInjury,
  nosebleed,
  sprain,
  poisoning,
  heatStroke,
  hypothermia,
];

export const FIRST_AID_GROUPS: readonly {
  id: FirstAidGroupId;
  title: string;
}[] = [
  { id: "breathing", title: "Не дише или се задавува" },
  { id: "sudden", title: "Ненадејни симптоми" },
  { id: "injuries", title: "Повреди" },
  { id: "environment", title: "Труење, жештина и студ" },
];

/**
 * Emergency situations → the guides that help while waiting for 194, most
 * specific first. For the guidance engine's emergency outcomes (T-ENGINE):
 * pick the topic, render firstAidLinksForTopic(topic).
 */
export const FIRST_AID_TOPICS = {
  "not-breathing": ["kpr-vozrasni", "kpr-dete", "kpr-bebe"],
  choking: ["zadavuvanje", "zadavuvanje-bebe"],
  stroke: ["mozocen-udar"],
  "chest-pain": ["srcev-udar", "kpr-vozrasni"],
  seizure: ["epileptichen-napad"],
  anaphylaxis: ["anafilaksa"],
  fainting: ["nesvestica"],
  "low-blood-sugar": ["nizok-sheker"],
  bleeding: ["silno-krvarenje"],
  burn: ["izgorenici"],
  "head-injury": ["povreda-na-glava"],
  nosebleed: ["krvarenje-od-nos"],
  sprain: ["istegnuvanje"],
  poisoning: ["truenje"],
  heat: ["toploten-udar"],
  cold: ["hipotermija"],
} as const satisfies Record<string, readonly FirstAidSlug[]>;

export type FirstAidTopic = keyof typeof FIRST_AID_TOPICS;

export function getFirstAidGuide(slug: string): FirstAidGuide | undefined {
  return FIRST_AID_GUIDES.find((guide) => guide.slug === slug);
}

/** A guide is public only when flagged published AND signed off by a clinician. */
export function isFirstAidGuidePublic(guide: FirstAidGuide): boolean {
  return guide.published && guide.review.status === "reviewed";
}

export function publishedFirstAidGuides(): FirstAidGuide[] {
  return FIRST_AID_GUIDES.filter(isFirstAidGuidePublic);
}

export function hasPublishedFirstAidGuides(): boolean {
  return publishedFirstAidGuides().length > 0;
}

/** The guide's path (with an optional anchor), published or not — staff/preview use. */
export function firstAidPath(slug: FirstAidSlug, anchor?: string): string {
  return `${FIRST_AID_BASE}/${slug}${anchor ? `#${anchor}` : ""}`;
}

/**
 * The public link to a guide, or null while it is unpublished (a draft must
 * never be linked to the public: it would 404). Callers render nothing for
 * null.
 */
export function firstAidHref(
  slug: FirstAidSlug,
  anchor?: FirstAidAnchor | string,
): string | null {
  const guide = getFirstAidGuide(slug);

  return guide && isFirstAidGuidePublic(guide)
    ? firstAidPath(slug, anchor)
    : null;
}

export type FirstAidLink = { slug: FirstAidSlug; title: string; href: string };

/** Published guides for an emergency topic, in the topic's order. */
export function firstAidLinksForTopic(
  topic: FirstAidTopic,
  anchor: FirstAidAnchor = FIRST_AID_ANCHORS.steps,
): FirstAidLink[] {
  return FIRST_AID_TOPICS[topic].flatMap((slug) => {
    const href = firstAidHref(slug, anchor);
    const guide = getFirstAidGuide(slug);

    return href && guide ? [{ slug, title: guide.title, href }] : [];
  });
}
