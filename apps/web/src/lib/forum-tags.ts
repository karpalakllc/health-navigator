/** API limits (ForumTagNormalizer). */
export const MAX_TOPIC_TAGS = 5;
const MIN_TAG_LENGTH = 2;
const MAX_TAG_LENGTH = 40;

export type TagInputResult =
  { ok: true; tags: string[] } | { ok: false; reason: "too-many" | "invalid" };

/**
 * The composer's comma-separated keyword field → a list for the API, which
 * does the real normalisation (scripts, punctuation, duplicates by spelling).
 * This only catches what the member can fix before sending.
 */
export function parseTagInput(raw: string): TagInputResult {
  const seen = new Set<string>();
  const tags: string[] = [];

  for (const part of raw.split(/[,;\n]+/)) {
    const tag = part.trim().replace(/^#+/, "").replace(/\s+/g, " ").trim();
    const key = tag.toLowerCase();

    if (tag === "" || seen.has(key)) {
      continue;
    }

    if (
      tag.length < MIN_TAG_LENGTH ||
      tag.length > MAX_TAG_LENGTH ||
      /^[\d\s-]+$/.test(tag)
    ) {
      return { ok: false, reason: "invalid" };
    }

    seen.add(key);
    tags.push(tag);
  }

  if (tags.length > MAX_TOPIC_TAGS) {
    return { ok: false, reason: "too-many" };
  }

  return { ok: true, tags };
}
