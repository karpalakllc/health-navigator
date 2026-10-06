import type { IconName } from "@/components/ui/icons";

/*
 * Specialties are free data (admin-managed), so their icon is picked by
 * keyword in the slug or name — Latin and Cyrillic, since either can appear —
 * with the stethoscope as the fallback. First match wins.
 */
const RULES: ReadonlyArray<readonly [RegExp, IconName]> = [
  [/kardio|кардио/, "heart"],
  [/pedijat|pediat|педијат/, "baby"],
  [/ginek|гинек|aku[sš]|акуш/, "venus"],
  [/ortoped|ортопед|travmat|трауматол|fizijat|физијат/, "bone"],
  [/nevro|neuro|невро|psih|псих/, "brain"],
  [/stomat|стомат|dent|ortodon|ортодон/, "tooth"],
  [/dermat|дерматол/, "droplet"],
  [/oftalm|офталм|okulist|окулист|o[cč]ni|очни/, "eye"],
  [/otorino|оторино|(^|[\s-])(orl|орл)($|[\s-])/, "ear"],
  [/interna|интерн|op[sš]t|општ|famil|фамил|mati[cč]|матич/, "activity"],
];

export function specialtyIcon(specialty: {
  slug: string;
  name?: string;
}): IconName {
  const haystack = `${specialty.slug} ${specialty.name ?? ""}`.toLowerCase();

  for (const [pattern, icon] of RULES) {
    if (pattern.test(haystack)) {
      return icon;
    }
  }

  return "stethoscope";
}
