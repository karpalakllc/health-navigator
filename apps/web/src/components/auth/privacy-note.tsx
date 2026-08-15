import { t } from "@/i18n/t";

/**
 * One-line explanation of why the sign-up and password-reset screens never say
 * whether an address is registered.
 *
 * The hiding is deliberate — on a health platform, "does this person have an
 * account here" is itself sensitive — so it is worth stating rather than leaving
 * the user to wonder why the wording is vague. Kept to a single muted line so it
 * reads as a footnote, not as instructions.
 */
export function PrivacyNote() {
  return (
    <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
      <LockIcon />
      <span>{t("auth.privacyNote")}</span>
    </p>
  );
}

function LockIcon() {
  return (
    <svg
      className="mt-0.5 h-3.5 w-3.5 shrink-0"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <rect x="4" y="10" width="16" height="10" rx="2" />
      <path d="M8 10V7a4 4 0 118 0v3" strokeLinecap="round" />
    </svg>
  );
}
