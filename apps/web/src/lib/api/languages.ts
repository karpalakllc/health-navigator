import { apiGet, TAXONOMY_CACHE } from "@/lib/api/client";

/** A language some published doctor speaks (GET /languages). */
export type DoctorLanguage = {
  slug: string;
  name: string;
  doctors_count: number;
};

export async function fetchLanguages(): Promise<DoctorLanguage[]> {
  return apiGet<DoctorLanguage[]>("/languages", TAXONOMY_CACHE);
}

/**
 * The ?language= value to send to the API, or undefined. The API answers an
 * unknown slug with 422, so a stale or hand-made URL drops the filter rather
 * than breaking the page.
 */
export function knownLanguage(
  raw: string | undefined,
  languages: readonly DoctorLanguage[],
): string | undefined {
  return raw !== undefined && languages.some(({ slug }) => slug === raw)
    ? raw
    : undefined;
}
