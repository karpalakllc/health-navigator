import { t } from "@/i18n/t";

type ContactBlockProps = {
  phone?: string | null;
  email?: string | null;
};

export function ContactBlock({ phone, email }: ContactBlockProps) {
  if (!phone && !email) {
    return null;
  }

  return (
    <section className="space-y-1 text-sm text-zinc-600">
      {phone ? (
        <p>
          {t("common.phone")}: {phone}
        </p>
      ) : null}
      {email ? (
        <p>
          {t("common.email")}: {email}
        </p>
      ) : null}
    </section>
  );
}
