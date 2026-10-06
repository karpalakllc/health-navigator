import Link from "next/link";
import { t } from "@/i18n/t";

/**
 * One quiet line at the end of a doctor's public profile: the doctor can ask
 * to manage it („Ова е мој профил“). Not a call to action for patients.
 */
export function DoctorClaimLink({ slug }: { slug: string }) {
  return (
    <p className="type-meta text-ink-2">
      {t("doctorDashboard.claimLead")}{" "}
      <Link
        href={`/doctors/${encodeURIComponent(slug)}/claim`}
        className="link-underline font-semibold text-ink"
      >
        {t("doctorDashboard.claimLink")}
      </Link>
    </p>
  );
}
