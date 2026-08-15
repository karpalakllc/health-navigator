/** Prose / account / legal pages */
export const PAGE_MAX_WIDTH_CLASS = "max-w-4xl";

/** Directory browsing, home, search */
export const PAGE_WIDE_WIDTH_CLASS = "max-w-[1240px]";

export const pageContainerClass = `mx-auto w-full ${PAGE_WIDE_WIDTH_CLASS} px-6`;

export const pageContainerNarrowClass = `mx-auto w-full ${PAGE_MAX_WIDTH_CLASS} px-6`;

export const pageMainClass = `${pageContainerClass} flex min-w-0 flex-col overflow-x-clip py-12`;

/** Minimal side gutters — nearly full viewport (homepage hero, etc.) */
export const pageEdgeBleedClass = "w-full px-4 sm:px-5";

/** Hero copy/search widths when the card is edge-bleed */
export const homeHeroCopyClass = "mx-auto w-full max-w-5xl 2xl:max-w-6xl";
export const homeHeroSearchClass =
  "mx-auto mt-7 w-full max-w-4xl 2xl:max-w-5xl";
