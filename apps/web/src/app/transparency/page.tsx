import type { Metadata } from "next";
import { TransparencyContent } from "@/components/transparency/transparency-content";
import { fetchTransparencyStats } from "@/lib/api/transparency";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("integrity.pageTitle"),
  t("integrity.pageDescription"),
  { path: "/transparency" },
);

/** The figures are fetched with an hour's revalidation, as the API caches them. */
export default async function TransparencyPage() {
  const stats = await fetchTransparencyStats();

  return <TransparencyContent stats={stats} />;
}
