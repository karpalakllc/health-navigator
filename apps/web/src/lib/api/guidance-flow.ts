import "server-only";
import { apiGet } from "@/lib/api/client";
import type { GuidanceCatalog } from "@/lib/api/guidance-v2";

/**
 * The server-rendered half of the guidance API. Kept apart from the
 * browser-side modules (lib/api/guidance.ts, guidance-v2.ts): this one goes
 * through lib/api/client.ts and therefore carries the web tier's credentials.
 */
export async function fetchGuidanceCatalog(): Promise<GuidanceCatalog> {
  return apiGet<GuidanceCatalog>("/triage/v2/catalog");
}
