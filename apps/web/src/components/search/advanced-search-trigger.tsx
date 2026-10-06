"use client";

import { useSearchDialog } from "@/components/layout/search-dialog-context";
import { Button, type ButtonVariant } from "@/components/ui/button";
import { t } from "@/i18n/t";

type AdvancedSearchTriggerProps = {
  className?: string;
  variant?: Extract<ButtonVariant, "primary" | "secondary" | "soft" | "ghost">;
};

/** Opens the „Напредно пребарување“ dialog (search one directory type). */
export function AdvancedSearchTrigger({
  className,
  variant = "soft",
}: AdvancedSearchTriggerProps) {
  const { openAdvancedSearch } = useSearchDialog();

  return (
    <Button
      variant={variant}
      leadingIcon="sliders"
      onClick={() => openAdvancedSearch()}
      className={className}
    >
      {t("search.advancedButton")}
      <span className="sr-only">, {t("search.advancedShortHint")}</span>
    </Button>
  );
}
