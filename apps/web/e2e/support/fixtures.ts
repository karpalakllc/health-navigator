/**
 * Mirror of apps/api/database/seeders/E2ESeeder.php — the seeded records the
 * specs assert on. Change both together.
 */
export const PASSWORD = "E2eLozinka2026";

export const users = {
  admin: "admin@e2e.test",
  staffModerator: "moderator@e2e.test",
  communityModerator: "forum-moderator@e2e.test",
  member: "member@e2e.test",
} as const;

/**
 * Staff TOTP secret (E2ESeeder::STAFF_TOTP_SECRET), used when the admin panel
 * challenges for app authentication. Staff accounts sign in to /admin only;
 * the specs never use them on the public site.
 */
export const STAFF_TOTP_SECRET = "JBSWY3DPEHPK3PXP";

/**
 * Accounts a spec changes come in numbered copies, one per attempt
 * (E2ESeeder::ATTEMPTS), so a retry starts from an untouched account.
 */
export function attemptUser(
  prefix: "reviewer" | "forum" | "reset",
  retry: number,
): string {
  return `${prefix}-${retry}@e2e.test`;
}

export const doctor = {
  slug: "e2e-ana-testovska",
  name: "д-р Ана Тестовска",
} as const;

export const facilitySlug = "e2e-klinika-centar";
export const pharmacySlug = "e2e-apteka-centar";
export const productSlug = "e2e-paracetamol-500";

export const forum = {
  categorySlug: "e2e-opshto-zdravje",
  categoryName: "Општо здравје",
  topicSlug: "e2e-dobredojdovte",
  topicTitle: "Добредојдовте во E2E заедницата",
  hiddenCategorySlug: "e2e-skriena-kategorija",
  hiddenTopicSlug: "e2e-skriena-tema",
  hiddenTopicTitle: "Ксилофонска тема во скриена категорија",
  /** The one word of the hidden title that nothing else seeded contains. */
  hiddenTopicKeyword: "Ксилофонска",
} as const;

/** TriageSeeder's red flags (the guidance flow the E2ESeeder reuses). */
export const triage = {
  redFlagLabel: "Severe chest pain or pressure",
  emergencyOutcomeTitle: "Seek emergency care now",
} as const;

/**
 * An address no seeded account uses, unique per call so a rerun against an
 * un-reset database, or a retry, never collides with an earlier registration.
 */
export function freshEmail(label: string): string {
  return `${label}-${uniqueSuffix()}@e2e.test`;
}

/** Distinct across parallel workers, which can share a millisecond. */
export function uniqueSuffix(): string {
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;
}
