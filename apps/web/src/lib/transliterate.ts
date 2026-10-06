/**
 * Macedonian Cyrillic → the diacritic-free Latin people type into Google
 * ("операција за проширени вени" → "operacija za prosireni veni").
 *
 * Mirrors ForumTagNormalizer::latin() in the API, which stores the same
 * spelling for forum keywords. Used once per page, in the meta description,
 * so Latin-script searches match a Cyrillic title (docs/seo.md).
 */
const CYRILLIC_TO_ASCII: Record<string, string> = {
  а: "a",
  б: "b",
  в: "v",
  г: "g",
  д: "d",
  ѓ: "g",
  е: "e",
  ж: "z",
  з: "z",
  ѕ: "dz",
  и: "i",
  ј: "j",
  к: "k",
  л: "l",
  љ: "lj",
  м: "m",
  н: "n",
  њ: "nj",
  о: "o",
  п: "p",
  р: "r",
  с: "s",
  т: "t",
  ќ: "k",
  у: "u",
  ф: "f",
  х: "h",
  ц: "c",
  ч: "c",
  џ: "dz",
  ш: "s",
};

const CYRILLIC = /[Ѐ-ӿ]/;

export function hasCyrillic(text: string): boolean {
  return CYRILLIC.test(text);
}

/** Lower-case Latin; characters outside the alphabet pass through. */
export function toLatin(text: string): string {
  let out = "";

  for (const char of text.toLowerCase()) {
    out += CYRILLIC_TO_ASCII[char] ?? char;
  }

  return out;
}

/**
 * The Latin spelling of a Cyrillic title, without punctuation, or null when
 * the title is already Latin (nothing to add).
 */
export function latinSearchVariant(title: string): string | null {
  if (!hasCyrillic(title)) {
    return null;
  }

  const latin = toLatin(title)
    // „д-р“ (doctor) is written "dr" in Latin, not "d r".
    .replace(/(^|[^\p{L}])d-r(?=$|[^\p{L}])/gu, "$1dr")
    .replace(/[^\p{L}\p{N}]+/gu, " ")
    .trim();

  return latin === "" ? null : latin;
}
