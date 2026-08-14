import Link from "next/link";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { t } from "@/i18n/t";

type Props = {
  titleKey: "legal.privacyTitle" | "legal.termsTitle" | "legal.disclaimerTitle";
  lastUpdated: string;
  children: React.ReactNode;
};

export function LegalPage({ titleKey, lastUpdated, children }: Props) {
  return (
    <PageShell>
      <PageHeader
        title={t(titleKey)}
        description={`${t("legal.lastUpdated")}: ${lastUpdated}`}
      />
      <article className="prose prose-zinc max-w-none space-y-4 text-sm text-zinc-700">
        {children}
      </article>
      <p className="text-xs text-zinc-500">
        <Link href="/" className="underline">
          {t("common.home")}
        </Link>
        {" · "}
        <Link href="/privacy" className="underline">
          {t("footer.privacy")}
        </Link>
        {" · "}
        <Link href="/terms" className="underline">
          {t("footer.terms")}
        </Link>
      </p>
    </PageShell>
  );
}
