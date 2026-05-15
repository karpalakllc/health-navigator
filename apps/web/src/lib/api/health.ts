import { apiGet } from "@/lib/api/client";
import type { ApiHealthData } from "@/lib/api/types";

export async function fetchApiHealth(): Promise<ApiHealthData> {
  return apiGet<ApiHealthData>("/v1/health");
}
