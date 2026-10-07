import type { GuidanceOutcomeV2, OutcomeLevel } from "@/lib/api/guidance-v2";

/** Most urgent first (docs/triage-flows.md §8.1). */
export const OUTCOME_LEVELS: OutcomeLevel[] = [
  "emergency_now",
  "urgent_same_day",
  "see_doctor_24_48h",
  "see_gp_this_week",
  "pharmacy_advice",
  "self_care_with_safety_net",
];

export function levelRank(level: OutcomeLevel): number {
  return OUTCOME_LEVELS.length - OUTCOME_LEVELS.indexOf(level);
}

export type DirectoryLink = {
  /** Stable id for keys and tests. */
  id: string;
  href: string;
  /** Message key + vars, rendered by the component. */
  label:
    | { key: "specialty"; specialty: string }
    | { key: "doctors" }
    | { key: "emergencyDepartments" }
    | { key: "hospitals" }
    | { key: "clinics" }
    | { key: "laboratories" }
    | { key: "pharmacies" };
};

function withQuery(
  path: string,
  params: Record<string, string | null>,
): string {
  const query = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value) {
      query.set(key, value);
    }
  }

  const qs = query.toString();

  return qs ? `${path}?${qs}` : path;
}

/**
 * Where the directory can help with this outcome: doctors of the outcome's
 * specialties (verified first, i.e. the verified filter), facilities of its
 * types, emergency departments for same-day care, pharmacies for pharmacy
 * advice. The city is the visitor's own choice, kept in the browser only.
 * Emergency outcomes get none of this — they get 194/112.
 */
export function directoryLinks(
  outcome: GuidanceOutcomeV2,
  city: string,
  { pharmaciesOn }: { pharmaciesOn: boolean },
): DirectoryLink[] {
  if (outcome.level === "emergency_now") {
    return [];
  }

  const place = city.trim() || null;
  const links: DirectoryLink[] = [];
  const { setting, specialties, facility_types: types } = outcome.care;

  if (
    setting === "emergency_department" ||
    setting === "on_call" ||
    outcome.level === "urgent_same_day"
  ) {
    links.push({
      id: "emergency-departments",
      href: withQuery("/facilities", { has_emergency: "1", city: place }),
      label: { key: "emergencyDepartments" },
    });
  }

  if (setting === "pharmacy" && pharmaciesOn) {
    links.push({
      id: "pharmacies",
      href: withQuery("/pharmacies", { city: place }),
      label: { key: "pharmacies" },
    });
  }

  if (setting === "self_care") {
    return links;
  }

  const linked = specialties.filter((s) => s.slug !== null);

  for (const specialty of linked) {
    links.push({
      id: `specialty-${specialty.key}`,
      href: withQuery("/doctors", {
        specialty: specialty.slug,
        city: place,
        verified: "1",
      }),
      label: { key: "specialty", specialty: specialty.name },
    });
  }

  if (setting !== "pharmacy" && (linked.length === 0 || setting === "gp")) {
    links.push({
      id: "doctors",
      href: withQuery("/doctors", { city: place }),
      label: { key: "doctors" },
    });
  }

  const typeLinks: Record<string, DirectoryLink["label"]["key"]> = {
    hospital: "hospitals",
    clinic: "clinics",
    laboratory: "laboratories",
  };

  for (const type of types) {
    const key = typeLinks[type];

    if (key) {
      links.push({
        id: `facilities-${type}`,
        href: withQuery("/facilities", { type, city: place }),
        label: { key } as DirectoryLink["label"],
      });
    }
  }

  return links;
}
