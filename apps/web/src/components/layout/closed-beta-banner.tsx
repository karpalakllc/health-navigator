import { t } from "@/i18n/t";

export function ClosedBetaBanner() {
  if (process.env.NEXT_PUBLIC_CLOSED_BETA !== "true") {
    return null;
  }

  return (
    <div className="border-b border-amber-200 bg-amber-50 px-6 py-2 text-center text-sm text-amber-950">
      {t("beta.banner")}
    </div>
  );
}
