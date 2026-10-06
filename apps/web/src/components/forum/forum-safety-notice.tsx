import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/**
 * The persistent forum safety note: support, not medical advice; 194/112 as
 * bold ink tel: links. Deliberately soft (chip-tint), never a red fill — the
 * header's „Итно 194“ pill stays the one emergency control.
 */
export function ForumSafetyNotice({ className }: { className?: string }) {
  return (
    <Notice tone="safety" className={className}>
      {t("forum.safetyLead")} {t("forum.safetyUrgent")}{" "}
      <NoticeTelLink number="194" /> {t("forum.safetyOr")}{" "}
      <NoticeTelLink number="112" />.
    </Notice>
  );
}
