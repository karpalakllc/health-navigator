import { DisclosureBadge } from "@/components/ui/disclosure-badge";
import type { Verification } from "@/lib/api/types";
import { t, tFormat, type MessageKey } from "@/i18n/t";

/** The transparency section that explains verification. */
export const VERIFICATION_MORE_HREF = "/transparency#verifikacija";

export type VerificationKind = "doctor" | "facility" | "pharmacy";

const LABELS: Record<
  VerificationKind,
  { verified: MessageKey; unverified: MessageKey }
> = {
  doctor: {
    verified: "verification.verified",
    unverified: "verification.unverified",
  },
  facility: {
    verified: "verification.verifiedFeminine",
    unverified: "verification.unverifiedFeminine",
  },
  pharmacy: {
    verified: "verification.verifiedFeminine",
    unverified: "verification.unverifiedFeminine",
  },
};

/** What a verification rests on when the API sends no public basis. */
const SOURCES: Record<VerificationKind, MessageKey> = {
  doctor: "verification.verifiedInfo",
  facility: "verification.verifiedInfoFacility",
  pharmacy: "verification.verifiedInfoPharmacy",
};

/**
 * „Верификуван“ / „Неверификуван“ (the short feminine „Верификувана“ /
 * „Неверификувана“ for facilities and pharmacies) with the DisclosureBadge
 * toggletip: hover, tap or keyboard
 * shows what it means, and „Повеќе“ leads to /transparency#verifikacija.
 *
 * Verified is the care-green tag with a shield; unverified is the plain sand
 * tag, the same as any neutral fact — it says the data is not confirmed yet,
 * never that something is wrong. Nothing renders when the API did not send
 * a status (an older cached payload).
 */
export function VerificationBadge({
  verification,
  kind,
  className,
}: {
  verification: Verification | undefined;
  kind: VerificationKind;
  className?: string;
}) {
  if (!verification) {
    return null;
  }

  const verified = verification.status === "verified";
  const feminine = kind !== "doctor";

  // The public basis names the source; without one, the kind's sources.
  const explanation = verified
    ? verification.basis_label
      ? tFormat("verification.verifiedInfoBasis", {
          basis: verification.basis_label,
        })
      : t(SOURCES[kind])
    : t("verification.unverifiedInfo");

  const buttonLabel = verified
    ? t(
        feminine
          ? "verification.whyVerifiedFeminine"
          : "verification.whyVerified",
      )
    : t(
        feminine
          ? "verification.whyUnverifiedFeminine"
          : "verification.whyUnverified",
      );

  return (
    <DisclosureBadge
      tone={verified ? "care" : "sand"}
      icon={verified ? "shield-check" : undefined}
      label={t(LABELS[kind][verified ? "verified" : "unverified"])}
      buttonLabel={buttonLabel}
      explanation={explanation}
      moreHref={VERIFICATION_MORE_HREF}
      className={className}
    />
  );
}
