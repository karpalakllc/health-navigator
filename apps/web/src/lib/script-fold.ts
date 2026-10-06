/*
 * Script-insensitive matching for Macedonian place names: „Карпош“,
 * „Karpoš“ and „karposh“ all fold to „karpos“. Both scripts are reduced to
 * one coarse Latin skeleton — diacritics dropped, the digraphs people type
 * without a Macedonian keyboard (sh, ch, zh, gj, kj, lj, nj, dzh) collapsed
 * to the same letter the Cyrillic folds to — so a substring test on the
 * folded strings is enough. Only for matching, never for display.
 */

const CYRILLIC: Record<string, string> = {
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
  љ: "l",
  м: "m",
  н: "n",
  њ: "n",
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

/** Longest first, so „dzh“ wins over „zh“. */
const LATIN_DIGRAPHS: Array<[string, string]> = [
  ["dzh", "dz"],
  ["sh", "s"],
  ["ch", "c"],
  ["zh", "z"],
  ["gj", "g"],
  ["kj", "k"],
  ["lj", "l"],
  ["nj", "n"],
];

export function foldScript(value: string): string {
  let out = "";
  for (const char of value.toLowerCase()) {
    out += CYRILLIC[char] ?? char;
  }
  // Latin with diacritics (š, č, ž, ǵ, ḱ …) → plain letters.
  out = out.normalize("NFD").replace(/[̀-ͯ]/g, "");
  for (const [digraph, letter] of LATIN_DIGRAPHS) {
    out = out.split(digraph).join(letter);
  }
  // „Чучер-Сандево“ / „cucer sandevo“: punctuation and spacing don't matter.
  return out.replace(/[^a-z0-9]+/g, " ").trim();
}

/** Does `haystack` contain `needle`, ignoring script, case and diacritics? */
export function scriptIncludes(haystack: string, needle: string): boolean {
  const n = foldScript(needle);
  return n === "" || foldScript(haystack).includes(n);
}
