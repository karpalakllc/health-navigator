import type { MessageKey } from "@/i18n/t";

/**
 * The public username shown next to reviews and forum posts. These checks
 * mirror the format rules of the API (App\Support\Usernames\UsernameValidator)
 * so the form can answer at once; the API decides, and it alone knows the
 * blocked and reserved lists and who holds which name.
 */
export const USERNAME_MIN_LENGTH = 3;
export const USERNAME_MAX_LENGTH = 30;

const LATIN = "a-zA-ZçÇëËčČćĆđĐšŠžŽ";
const CYRILLIC =
  "абвгдѓежзѕијклљмнњопрстќуфхцчџшАБВГДЃЕЖЗЅИЈКЛЉМНЊОПРСТЌУФХЦЧЏШ";

const ALLOWED = new RegExp(`^[${LATIN}${CYRILLIC}0-9._-]+$`, "u");
const STARTS_WITH_LETTER = new RegExp(`^[${LATIN}${CYRILLIC}]`, "u");
const HAS_LATIN = new RegExp(`[${LATIN}]`, "u");
const HAS_CYRILLIC = new RegExp(`[${CYRILLIC}]`, "u");

/** NFKC and trimmed, as the API stores it. */
export function normalizeUsername(value: string): string {
  return value.normalize("NFKC").trim();
}

/** The message for a username that cannot be valid, or null if it may be. */
export function usernameFormatError(value: string): MessageKey | null {
  const username = normalizeUsername(value);
  const length = [...username].length;

  if (length < USERNAME_MIN_LENGTH || length > USERNAME_MAX_LENGTH) {
    return "usernames.errorLength";
  }

  if (!ALLOWED.test(username)) {
    return "usernames.errorAlphabet";
  }

  if (!STARTS_WITH_LETTER.test(username)) {
    return "usernames.errorStart";
  }

  if (/[._-]{2}/.test(username)) {
    return "usernames.errorSeparators";
  }

  if (HAS_LATIN.test(username) && HAS_CYRILLIC.test(username)) {
    return "usernames.errorMixedScript";
  }

  return null;
}

/** The account was given a placeholder („clen-…“) and should choose a name. */
export function isTemporaryUsername(username: string): boolean {
  return username.startsWith("clen-");
}
