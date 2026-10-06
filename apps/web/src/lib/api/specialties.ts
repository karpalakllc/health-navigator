import { apiGet, TAXONOMY_CACHE } from "@/lib/api/client";
import type { Specialty } from "@/lib/api/types";

export async function fetchSpecialties(): Promise<Specialty[]> {
  return apiGet<Specialty[]>("/specialties", TAXONOMY_CACHE);
}
