import Link from "next/link";
import type { ReactNode } from "react";
import {
  correctionProfilePath,
  type CorrectionSubject,
} from "@/lib/api/corrections";
import { t } from "@/i18n/t";

/**
 * Quiet lines at the end of a public profile: anyone can report an error;
 * on a doctor's profile the listed doctor can also object to the listing.
 * Plain underlined links, no buttons, no arrows. `children` (the doctor's
 * „Ова е мој профил“ line) goes between the two.
 */
export function ProfileCorrectionLinks({
  subject,
  slug,
  children,
}: {
  subject: CorrectionSubject;
  slug: string;
  children?: ReactNode;
}) {
  const base = correctionProfilePath(subject, slug);

  return (
    <div className="flex flex-col gap-2">
      <p className="type-meta text-ink-2">
        {t("corrections.reportLead")}{" "}
        <Link
          href={`${base}/correction`}
          className="link-underline font-semibold text-ink"
        >
          {t("corrections.reportLink")}
        </Link>
      </p>
      {children}
      {subject === "doctor" ? (
        <p className="type-meta text-ink-2">
          {t("corrections.objectionLead")}{" "}
          <Link
            href={`${base}/objection`}
            className="link-underline font-semibold text-ink"
          >
            {t("corrections.objectionLink")}
          </Link>
        </p>
      ) : null}
    </div>
  );
}
