import { notFound } from "next/navigation";
import { CorrectionPage } from "@/components/corrections/correction-page";
import { fetchDoctor } from "@/lib/api/doctors";
import { ApiRequestError } from "@/lib/api/server";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(
  t("corrections.objectionTitle"),
  undefined,
  { noIndex: true },
);

type Props = { params: Promise<{ slug: string }> };

/**
 * „Барање за приговор / отстранување“: the listed doctor objects to the
 * listing or asks for its removal. Staff verify who they are by the contact
 * they leave; no sign-in needed.
 */
export default async function DoctorObjectionPage({ params }: Props) {
  const { slug } = await params;
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
    <CorrectionPage
      subject="doctor"
      slug={doctor.slug}
      name={doctor.full_name}
      type="objection"
    />
  );
}
