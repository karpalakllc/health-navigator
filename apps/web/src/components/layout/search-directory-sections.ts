/**
 * Sections for global search overlay (same destinations as /search page).
 */
export const SEARCH_DIRECTORY_SECTIONS = [
  {
    titleKey: "home.doctorsTitle" as const,
    descKey: "home.doctorsDesc" as const,
    basePath: "/doctors",
  },
  {
    titleKey: "home.facilitiesTitle" as const,
    descKey: "home.facilitiesDesc" as const,
    basePath: "/facilities",
  },
  {
    titleKey: "home.pharmaciesTitle" as const,
    descKey: "home.pharmaciesDesc" as const,
    basePath: "/pharmacies",
  },
  {
    titleKey: "home.productsTitle" as const,
    descKey: "home.productsDesc" as const,
    basePath: "/products",
  },
] as const;
