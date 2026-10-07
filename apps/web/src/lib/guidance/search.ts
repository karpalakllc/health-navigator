import type { AgeBand, BodyArea, CatalogFlow } from "@/lib/api/guidance-v2";
import { toLatin } from "@/lib/transliterate";

/*
 * Symptom search runs entirely in the browser over the published catalogue:
 * what someone types about their symptoms never reaches a server.
 *
 * Matching is done in diacritic-free Latin, so „главоболка“, "glavobolka" and
 * "glavobolka" typed with "sh"/"ch"/"zh" digraphs all meet.
 */
export function normalizeForSearch(text: string): string {
  return toLatin(text.normalize("NFC"))
    .replace(/dzh/g, "dz")
    .replace(/sh/g, "s")
    .replace(/ch/g, "c")
    .replace(/zh/g, "z")
    .replace(/kj/g, "k")
    .replace(/gj/g, "g")
    .replace(/[^\p{L}\p{N}]+/gu, " ")
    .trim();
}

/** Flows meant for this age band. */
export function flowsForAge(
  flows: CatalogFlow[],
  band: AgeBand | null,
): CatalogFlow[] {
  return band === null
    ? flows
    : flows.filter((f) => f.age_bands.includes(band));
}

/**
 * Flows matching the query: every query word must start a word of the title
 * or of a search term (so "glav bol" finds „главоболка“ via the term
 * „главоболка“ and „болка во главата“). Title matches rank first.
 */
export function searchFlows(
  flows: CatalogFlow[],
  query: string,
): CatalogFlow[] {
  const words = normalizeForSearch(query).split(" ").filter(Boolean);

  if (words.length === 0) {
    return flows;
  }

  const scored: Array<{ flow: CatalogFlow; score: number }> = [];

  for (const flow of flows) {
    const title = normalizeForSearch(flow.title);
    const terms = flow.search_terms.map(normalizeForSearch);
    const haystacks = [title, ...terms];

    // Three letters or more may match inside a word („бол“ in „главоболка“);
    // shorter ones only at the start of a word.
    const matches = (hay: string) =>
      words.every((word) =>
        word.length >= 3
          ? hay.includes(word)
          : hay.split(" ").some((token) => token.startsWith(word)),
      );

    if (matches(title)) {
      scored.push({ flow, score: 2 });
    } else if (haystacks.some(matches)) {
      scored.push({ flow, score: 1 });
    }
  }

  return scored
    .sort(
      (a, b) =>
        b.score - a.score || a.flow.title.localeCompare(b.flow.title, "mk"),
    )
    .map((s) => s.flow);
}

export function flowsInArea(
  flows: CatalogFlow[],
  area: BodyArea | null,
): CatalogFlow[] {
  return area === null
    ? flows
    : flows.filter((f) => f.body_areas.includes(area));
}

/** Body areas that have at least one flow, in body-map order. */
export const BODY_AREAS: BodyArea[] = [
  "head",
  "eyes",
  "ears",
  "mouth",
  "throat",
  "chest",
  "abdomen",
  "pelvis",
  "back",
  "arms",
  "legs",
  "skin",
  "general",
  "mind",
];

export function areasWithFlows(flows: CatalogFlow[]): BodyArea[] {
  const present = new Set(flows.flatMap((f) => f.body_areas));

  return BODY_AREAS.filter((area) => present.has(area));
}
