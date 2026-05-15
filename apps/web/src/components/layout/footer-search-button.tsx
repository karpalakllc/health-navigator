"use client";

import { useSearchDialog } from "@/components/layout/search-dialog-context";
import { t } from "@/i18n/t";

export function FooterSearchButton({
  className,
}: {
  className?: string;
}) {
  const { openSearch } = useSearchDialog();

  return (
    <button type="button" onClick={() => openSearch()} className={className}>
      {t("footer.openSearch")}
    </button>
  );
}
