import type { PaginatedEnvelope } from "@/lib/api/types";

export async function safePaginated<T>(
  fn: () => Promise<PaginatedEnvelope<T>>,
  perPage: number = 5,
): Promise<PaginatedEnvelope<T>> {
  try {
    return await fn();
  } catch {
    return {
      data: [],
      meta: {
        current_page: 1,
        per_page: perPage,
        total: 0,
        last_page: 1,
      },
    };
  }
}
