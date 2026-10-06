/**
 * The public display name shown next to reviews and forum posts. The name a
 * person registers with stays private; these rules mirror the API's
 * (App\Support\DisplayName), which is the one that actually decides.
 */
export const DISPLAY_NAME_MAX_LENGTH = 40;

const DISPLAY_NAME_PATTERN = /^\p{L}\p{M}*[\p{L}\p{M} .'-]*$/u;

/** Trim and collapse runs of whitespace, as the API stores it. */
export function normalizeDisplayName(value: string): string {
  return value.replace(/\s+/gu, " ").trim();
}

export function isValidDisplayName(value: string): boolean {
  const normalized = normalizeDisplayName(value);

  return (
    normalized !== "" &&
    [...normalized].length <= DISPLAY_NAME_MAX_LENGTH &&
    DISPLAY_NAME_PATTERN.test(normalized)
  );
}

/**
 * First word + initial of the last word: "Марија Костовска" → "Марија К.".
 * A single word is suggested as it is. Same rule the API used to backfill
 * existing accounts.
 */
export function suggestDisplayName(fullName: string): string {
  const parts = fullName.trim().split(/\s+/u).filter(Boolean);

  if (parts.length === 0) {
    return "";
  }

  const first = [...parts[0]];

  if (parts.length === 1) {
    return first.slice(0, DISPLAY_NAME_MAX_LENGTH).join("");
  }

  const [lastInitial = ""] = [...parts[parts.length - 1]];
  const initial = `${lastInitial.toLocaleUpperCase("mk")}.`;
  const room = DISPLAY_NAME_MAX_LENGTH - [...initial].length - 1;

  return `${first.slice(0, room).join("")} ${initial}`;
}
