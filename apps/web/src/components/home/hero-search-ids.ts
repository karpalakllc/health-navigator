/*
 * Shared by the home hero and the site header, without pulling the hero's
 * components (city picker) into every page's bundle.
 */

/** Id of the hero's query field (one per page). */
export const HERO_SEARCH_INPUT_ID = "home-hero-q";

/** Id of the hero's search form, which the header watches. */
export const HERO_SEARCH_FORM_ID = "home-hero-search";

/**
 * Set on <html> while the home page is open: "visible" while the hero
 * search is on screen (the header pill steps aside), "hidden" once it has
 * scrolled away (the pill fades in).
 */
export const HERO_SEARCH_ATTR = "data-hero-search";
