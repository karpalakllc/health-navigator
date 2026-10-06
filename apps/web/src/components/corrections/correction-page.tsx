import Link from "next/link";
import { AccountPage } from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { ProfileCorrectionForm } from "@/components/corrections/profile-correction-form";
import { BackLink } from "@/components/ui/back-link";
import { Card } from "@/components/ui/card";
import {
  correctionProfilePath,
  type CorrectionSubject,
  type CorrectionType,
} from "@/lib/api/corrections";
import { t, tFormat } from "@/i18n/t";

/**
 * The page around a correction or objection form: back to the profile, what
 * happens next (15 or 30 days), the form, and one line on how the message and
 * contact are used. No sign-in needed.
 */
export function CorrectionPage({
  subject,
  slug,
  name,
  type,
}: {
  subject: CorrectionSubject;
  slug: string;
  name: string;
  type: CorrectionType;
}) {
  const profilePath = correctionProfilePath(subject, slug);
  const objection = type === "objection";

  return (
    <AccountPage>
      <BackLink href={profilePath} label={t("corrections.back")} />
      <AccountPageHero
        badge={name}
        title={t(
          objection
            ? "corrections.objectionTitle"
            : "corrections.correctionTitle",
        )}
        description={tFormat(
          objection
            ? "corrections.objectionDescription"
            : "corrections.correctionDescription",
          { name },
        )}
      />
      <Card className="flex max-w-2xl flex-col gap-6">
        {objection ? (
          <p className="type-body text-ink-2">
            {t("corrections.objectionFixInstead")}{" "}
            <Link
              href={`${profilePath}/correction`}
              className="link-underline font-semibold text-ink"
            >
              {t("corrections.reportLink")}
            </Link>
          </p>
        ) : null}
        <ProfileCorrectionForm subject={subject} slug={slug} type={type} />
        <p className="type-meta text-ink-2">
          {t("corrections.privacyLead")}{" "}
          <Link
            href="/privacy#zdravstveni-rabotnici"
            className="link-underline text-ink"
          >
            {t("corrections.privacyLink")}
          </Link>
        </p>
      </Card>
    </AccountPage>
  );
}
