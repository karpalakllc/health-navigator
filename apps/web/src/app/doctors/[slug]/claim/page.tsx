import { notFound, redirect } from "next/navigation";
import { AccountPage } from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { DoctorClaimForm } from "@/components/doctor-dashboard/doctor-claim-form";
import { BackLink } from "@/components/ui/back-link";
import { Card } from "@/components/ui/card";
import { getSessionToken } from "@/lib/auth/session";
import { loginHref } from "@/lib/auth/login-href";
import { fetchDoctor } from "@/lib/api/doctors";
import { ApiRequestError } from "@/lib/api/server";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

export const metadata = pageMetadata(
  t("doctorDashboard.claimTitle"),
  undefined,
  { noIndex: true },
);

type ClaimPageProps = {
  params: Promise<{ slug: string }>;
};

/**
 * „Ова е мој профил“: a signed-in member asks staff to link their account to
 * this doctor profile. Staff verify the person outside the platform.
 */
export default async function DoctorClaimPage({ params }: ClaimPageProps) {
  const { slug } = await params;
  const profilePath = `/doctors/${encodeURIComponent(slug)}`;

  if (!(await getSessionToken())) {
    redirect(loginHref(`${profilePath}/claim`));
  }

  let doctor;

  try {
    doctor = await fetchDoctor(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  return (
    <AccountPage>
      <BackLink href={profilePath} label={t("doctorDashboard.claimBack")} />
      <AccountPageHero
        badge={doctor.full_name}
        title={t("doctorDashboard.claimTitle")}
        description={tFormat("doctorDashboard.claimDescription", {
          name: doctor.full_name,
        })}
      />
      <Card className="max-w-2xl">
        <DoctorClaimForm slug={doctor.slug} />
      </Card>
    </AccountPage>
  );
}
