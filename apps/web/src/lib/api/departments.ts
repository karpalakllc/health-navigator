import { apiGet } from "@/lib/api/client";
import type { DepartmentListItem } from "@/lib/api/types";

export async function fetchDepartments(): Promise<DepartmentListItem[]> {
  return apiGet<DepartmentListItem[]>("/departments");
}
