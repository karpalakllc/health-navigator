/*
 * „Прва помош“ guides: the shape every guide file fills in. Text only — the
 * pages under src/app/prva-pomos render it, and docs/first-aid.md is the link
 * contract other parts of the site (guidance emergency outcomes) rely on.
 */

/** Situation groups on the index page, in display order (see GROUPS). */
export type FirstAidGroupId =
  "breathing" | "sudden" | "injuries" | "environment";

/** Which inline SVG (src/components/first-aid/illustrations.tsx) a step or guide shows. */
export type FirstAidIllustrationId =
  | "cpr-adult"
  | "cpr-child"
  | "cpr-infant"
  | "recovery-position"
  | "choking-back-blows"
  | "choking-abdominal"
  | "choking-infant"
  | "stroke"
  | "heart"
  | "seizure"
  | "anaphylaxis"
  | "bleeding"
  | "burn"
  | "head"
  | "poison"
  | "faint"
  | "hypo"
  | "heat"
  | "cold"
  | "nose"
  | "sprain";

export type FirstAidStep = {
  /** The instruction itself: one short imperative sentence. */
  text: string;
  /** Optional second line: how, or why, in plain words. */
  detail?: string;
  illustration?: FirstAidIllustrationId;
};

/** A numbered block of steps; guides with variants (with/without breaths) have several. */
export type FirstAidStepSection = {
  /** Stable ASCII anchor (part of the link contract, never rename). */
  id: string;
  title: string;
  /** One line before the list, e.g. who this variant is for. */
  intro?: string;
  steps: FirstAidStep[];
};

export type FirstAidSource = {
  publisher: string;
  title: string;
  url: string;
  /** ISO date the page was read for this guide. */
  accessed: string;
  /** The source's own „last reviewed“ date, when it shows one. */
  sourceReviewed?: string;
};

/**
 * How prominent the call to 194 is:
 * - "first": „Прво повикајте 194“ above the steps (life-threatening);
 * - "if-signs": the call box lists when to call instead (e.g. nosebleed).
 */
export type FirstAidCallMode = "first" | "if-signs";

export type FirstAidReview = {
  /** Draft until a clinician signs it off (docs/first-aid-review-checklist.md). */
  status: "draft" | "reviewed";
  /** Filled in at sign-off; name/registration optional. */
  reviewer?: string;
  reviewedOn?: string;
  note?: string;
};

export type FirstAidGuide = {
  /** Stable ASCII URL slug: /prva-pomos/{slug}. Never rename once linked. */
  slug: string;
  title: string;
  /** Index card line and meta description (≤ 160 characters). */
  summary: string;
  group: FirstAidGroupId;
  /** Hero illustration on the index card and at the top of the guide. */
  illustration: FirstAidIllustrationId;
  call: FirstAidCallMode;
  /** One line under „Прво повикајте 194“, e.g. ask for a defibrillator. */
  callNote?: string;
  /** „Како да препознаете“: the signs, as short bullet lines. */
  recognise: string[];
  sections: FirstAidStepSection[];
  /** „Што да НЕ правите“. */
  dont: string[];
  /** „Повикајте 194 (или повторно) ако…“: escalation signs. */
  escalate: string[];
  /** „Продолжете додека…“: when to stop. */
  untilHelp?: string;
  sources: FirstAidSource[];
  /** Other guides worth a link at the end (slugs). */
  related?: string[];
  /**
   * Public only when true. Stays false until a clinician has signed the guide
   * off (review.status === "reviewed"); staff can preview drafts meanwhile.
   */
  published: boolean;
  review: FirstAidReview;
  /** ISO date the text last changed. */
  updated: string;
};
