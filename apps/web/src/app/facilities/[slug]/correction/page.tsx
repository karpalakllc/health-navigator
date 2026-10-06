import { notFound } from "next/navigation";
import { CorrectionPage } from "@/components/corrections/correction-page";
import { fetchFacility } from "@/lib/api/facilities";
import { ApiRequestError } from "@/lib/api/server";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(
  t("corrections.correctionTitle"),
  undefined,
  { noIndex: true },
);

type Props = { params: Promise<{ slug: string }> };

/** „Пријави грешка во профилот“ for a facility. Open to everyone. */
export default async function FacilityCorrectionPage({ params }: Props) {
  const { slug } = await params;
  let facility;

  try {
    facility = await fetchFacility(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  return (
    <CorrectionPage
      subject="facility"
      slug={facility.slug}
      name={facility.name}
      type="correction"
    />
  );
}
