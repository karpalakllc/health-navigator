import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * One-line explanation of why the sign-up and password-reset screens never say
 * whether an address is registered.
 *
 * The hiding is deliberate — on a health platform, "does this person have an
 * account here" is itself sensitive — so it is worth stating rather than leaving
 * the user to wonder why the wording is vague. Kept to a single meta line so it
 * reads as a footnote, not as instructions.
 */
export function PrivacyNote() {
  return (
    <p className="flex items-start gap-2 type-meta text-ink-2">
      <Icon name="lock" size={20} className="mt-0.5 text-ink" />
      <span>{t("auth.privacyNote")}</span>
    </p>
  );
}
