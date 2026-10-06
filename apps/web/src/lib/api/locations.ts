import { apiGet, DIRECTORY_REVALIDATE_SECONDS } from "@/lib/api/client";

/** A city that holds published profiles (GET /locations/cities). */
export type LocationCity = {
  name: string;
  doctors_count: number;
  facilities_count: number;
};

/**
 * Every city with at least one published doctor or facility, biggest first.
 * The city picker uses it to send a municipality without listings to its
 * parent town, and to show how many profiles each place holds.
 */
export async function fetchLocationCities(): Promise<LocationCity[]> {
  return apiGet<LocationCity[]>("/locations/cities", {
    revalidate: DIRECTORY_REVALIDATE_SECONDS,
  });
}
