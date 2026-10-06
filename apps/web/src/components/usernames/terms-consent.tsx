import { Checkbox } from "@/components/ui/field";
import { t } from "@/i18n/t";

type TermsConsentProps = {
  id: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  error?: string;
};

function NewWindowLink({ href, children }: { href: string; children: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener"
      className="link-underline font-semibold text-ink hover:text-black"
    >
      {children}
      <span className="sr-only"> {t("usernames.opensInNewWindow")}</span>
    </a>
  );
}

/**
 * The single sign-up consent: „Имам најмалку 14 години и ги прифаќам
 * Условите за користење и Политиката за приватност“. Required; the API
 * records when it was given and which version of the terms. The texts open
 * in a new window so the half-filled form is not lost.
 */
export function TermsConsent({
  id,
  checked,
  onChange,
  error,
}: TermsConsentProps) {
  return (
    <Checkbox
      id={id}
      name="accept_terms"
      required
      checked={checked}
      onChange={(event) => onChange(event.target.checked)}
      error={error}
      label={
        <>
          {t("usernames.termsBefore")}
          <NewWindowLink href="/terms">
            {t("usernames.termsLink")}
          </NewWindowLink>
          {t("usernames.termsAnd")}
          <NewWindowLink href="/privacy">
            {t("usernames.privacyLink")}
          </NewWindowLink>
        </>
      }
    />
  );
}
