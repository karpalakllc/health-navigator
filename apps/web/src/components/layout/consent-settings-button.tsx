"use client";

import { openConsentSettings } from "@/lib/consent/consent";
import { t } from "@/i18n/t";

/** Footer link „Поставки за колачиња“: reopens the consent banner. */
export function ConsentSettingsButton({ className }: { className?: string }) {
  return (
    <button
      type="button"
      onClick={openConsentSettings}
      className={`${className ?? ""} cursor-pointer text-left`}
    >
      {t("footer.cookieSettings")}
    </button>
  );
}
