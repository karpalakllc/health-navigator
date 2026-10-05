import "server-only";
import { apiGet } from "@/lib/api/client";
import type { GuidanceFlow } from "@/lib/api/guidance";

/**
 * The server-rendered half of the guidance API. Kept apart from
 * lib/api/guidance.ts, which the browser-side wizard imports: this one goes
 * through lib/api/client.ts and therefore carries the web tier's credentials.
 */
export async function fetchGuidanceFlow(): Promise<GuidanceFlow> {
  return apiGet<GuidanceFlow>("/triage/flow");
}
