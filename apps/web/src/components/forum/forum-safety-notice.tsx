import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/**
 * The persistent forum safety note: support, not medical advice; 194/112 as
 * bold ink tel: links. Deliberately soft (chip-tint), never a red fill. Shown
 * only on the forum home and the new-topic composer, not on every page.
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
