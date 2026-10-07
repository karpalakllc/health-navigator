import type { FirstAidSource } from "@/content/first-aid/types";

/*
 * Public sources the guides were written from (original wording, nothing
 * copied). `accessed` is the day the page was read; `sourceReviewed` is the
 * page's own „last reviewed“ date where it shows one. Pages that could not be
 * opened during writing are NOT cited here — see docs/first-aid.md.
 */
const ACCESSED = "2026-10-07";

export const SOURCES = {
  ercGuidelines2025: {
    publisher: "European Resuscitation Council (ERC)",
    title: "ERC Guidelines 2025 — Cardiopulmonary Resuscitation",
    url: "https://www.erc.edu/",
    accessed: ACCESSED,
  },
  sjaCpr: {
    publisher: "St John Ambulance",
    title: "CPR (адреса на која води и NHS „first aid“)",
    url: "https://www.sja.org.uk/first-aid-advice/cpr/",
    accessed: ACCESSED,
  },
  sjaChoking: {
    publisher: "St John Ambulance",
    title: "Choking",
    url: "https://www.sja.org.uk/first-aid-advice/choking/",
    accessed: ACCESSED,
  },
  nhsStroke: {
    publisher: "NHS",
    title: "Stroke — Symptoms",
    url: "https://www.nhs.uk/conditions/stroke/symptoms/",
    accessed: ACCESSED,
    sourceReviewed: "2024-09-12",
  },
  nhsHeartAttack: {
    publisher: "NHS",
    title: "Heart attack",
    url: "https://www.nhs.uk/conditions/heart-attack/",
    accessed: ACCESSED,
    sourceReviewed: "2026-03-31",
  },
  nhsAnaphylaxis: {
    publisher: "NHS",
    title: "Anaphylaxis",
    url: "https://www.nhs.uk/conditions/anaphylaxis/",
    accessed: ACCESSED,
    sourceReviewed: "2023-06-21",
  },
  nhsBurns: {
    publisher: "NHS",
    title: "Burns and scalds",
    url: "https://www.nhs.uk/conditions/burns-and-scalds/",
    accessed: ACCESSED,
  },
  nhsHeadInjury: {
    publisher: "NHS",
    title: "Head injury and concussion",
    url: "https://www.nhs.uk/conditions/head-injury-and-concussion/",
    accessed: ACCESSED,
  },
  nhsPoisoning: {
    publisher: "NHS",
    title: "Poisoning",
    url: "https://www.nhs.uk/conditions/poisoning/",
    accessed: ACCESSED,
    sourceReviewed: "2025-06-12",
  },
  nhsFainting: {
    publisher: "NHS",
    title: "Fainting",
    url: "https://www.nhs.uk/conditions/fainting/",
    accessed: ACCESSED,
    sourceReviewed: "2026-08-17",
  },
  nhsHypo: {
    publisher: "NHS",
    title: "Low blood sugar (hypoglycaemia)",
    url: "https://www.nhs.uk/conditions/low-blood-sugar-hypoglycaemia/",
    accessed: ACCESSED,
    sourceReviewed: "2023-08-03",
  },
  nhsHeat: {
    publisher: "NHS",
    title: "Heat exhaustion and heatstroke",
    url: "https://www.nhs.uk/conditions/heat-exhaustion-heatstroke/",
    accessed: ACCESSED,
    sourceReviewed: "2026-05-28",
  },
  nhsHypothermia: {
    publisher: "NHS",
    title: "Hypothermia",
    url: "https://www.nhs.uk/conditions/hypothermia/",
    accessed: ACCESSED,
  },
  nhsNosebleed: {
    publisher: "NHS",
    title: "Nosebleed",
    url: "https://www.nhs.uk/conditions/nosebleed/",
    accessed: ACCESSED,
    sourceReviewed: "2023-12-05",
  },
  nhsSprains: {
    publisher: "NHS",
    title: "Sprains and strains",
    url: "https://www.nhs.uk/conditions/sprains-and-strains/",
    accessed: ACCESSED,
    sourceReviewed: "2024-04-23",
  },
  nhsEpilepsy: {
    publisher: "NHS",
    title: "What to do if someone has a seizure (fit)",
    url: "https://www.nhs.uk/symptoms/what-to-do-if-someone-has-a-seizure-fit/",
    accessed: ACCESSED,
    sourceReviewed: "2023-12-19",
  },
  nhsBleeding: {
    publisher: "NHS",
    title: "Cuts and grazes",
    url: "https://www.nhs.uk/conditions/cuts-and-grazes/",
    accessed: ACCESSED,
    sourceReviewed: "2026-04-02",
  },
} as const satisfies Record<string, FirstAidSource>;
