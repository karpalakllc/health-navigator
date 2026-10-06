import { apiGetPaginatedServer, apiGetServer } from "@/lib/api/server";
import type {
  DashboardReview,
  DoctorDashboard,
  ReviewFilter,
} from "@/lib/api/doctor-dashboard-types";

export type * from "@/lib/api/doctor-dashboard-types";

export async function fetchDoctorDashboard(): Promise<DoctorDashboard> {
  return apiGetServer<DoctorDashboard>("/me/doctor");
}

export async function fetchDoctorReviews(
  page = 1,
  filter: ReviewFilter = "all",
) {
  const params = new URLSearchParams({ page: String(page) });

  if (filter === "unanswered") {
    params.set("filter", "unanswered");
  }

  return apiGetPaginatedServer<DashboardReview>(
    `/me/doctor/reviews?${params.toString()}`,
  );
}
