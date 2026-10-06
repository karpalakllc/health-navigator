import type { ReactNode } from "react";
import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

export type LegalSection = { id: string; title: string };

type Props = {
  titleKey: "legal.privacyTitle" | "legal.termsTitle" | "legal.disclaimerTitle";
  lastUpdated: string;
  /** The document's h2s, in order — the table of contents. */
  sections: readonly LegalSection[];
  children: ReactNode;
};

/*
 * Styles for the plain elements of src/content/legal/* (which carry no
 * classes): reading type for text, h2 at 22/30 with room above, real list
 * bullets. Scoped here so the content files stay text only.
 */
const PROSE = [
  "measure flex flex-col gap-4 text-ink",
  "[&_p]:type-reading [&_li]:type-reading",
  "[&_h2]:type-h2 [&_h2]:mt-6 [&_h2]:text-ink",
  "[&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-2 [&_ul]:pl-6",
  "[&_li]:marker:text-ink-2 [&_strong]:font-bold",
].join(" ");

const RELATED = [
  { href: "/privacy", key: "footer.privacy" },
  { href: "/terms", key: "footer.terms" },
  { href: "/disclaimer", key: "footer.disclaimer" },
] as const;

/**
 * Long-form legal text: Source Sans 3 at reading size, ≈68ch measure, on a
 * white card. Desktop gets a sticky table of contents beside it; on a phone
 * the same list is a collapsed disclosure above the text.
 */
export function LegalPage({
  titleKey,
  lastUpdated,
  sections,
  children,
}: Props) {
  const title = t(titleKey);
  const toc = (
    <ol className="flex flex-col">
      {sections.map((section) => (
        <li key={section.id}>
          <a
            href={`#${section.id}`}
            className="flex min-h-12 items-center rounded-sm px-3 type-body text-ink hover:bg-sand lg:min-h-11"
          >
            {section.title}
          </a>
        </li>
      ))}
    </ol>
  );

  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-6 lg:gap-10 lg:px-6 lg:pb-20 lg:pt-10">
      <header className="flex flex-col gap-2">
        <p className="type-meta font-semibold text-ink-2">
          {t("footer.legal")}
        </p>
        <h1 className="type-h1 text-ink">{title}</h1>
        <p className="type-meta text-ink-2">
          {t("legal.lastUpdated")}:{" "}
          <time dateTime={lastUpdated}>{lastUpdated}</time>
        </p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start lg:gap-14">
        <nav
          aria-labelledby="legal-toc"
          className="hidden lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:block"
        >
          <h2 id="legal-toc" className="mb-2 px-3 type-label text-ink-2">
            {t("legal.toc")}
          </h2>
          {toc}
        </nav>

        <div className="flex min-w-0 flex-col gap-6">
          <details className="group card p-0 lg:hidden">
            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 type-label text-ink [&::-webkit-details-marker]:hidden">
              {t("legal.toc")}
              <Icon
                name="chevron-down"
                size={24}
                className="motion-safe:transition-transform group-open:rotate-180"
              />
            </summary>
            <nav aria-label={t("legal.toc")} className="px-2 pb-3">
              {toc}
            </nav>
          </details>

          <Card
            as="article"
            aria-label={title}
            className="lg:max-w-[52rem] lg:p-12"
          >
            <div className={PROSE}>{children}</div>
          </Card>

          <nav aria-labelledby="legal-related" className="flex flex-col gap-1">
            <h2 id="legal-related" className="type-label text-ink-2">
              {t("legal.related")}
            </h2>
            <ul className="flex flex-wrap gap-x-6">
              <li>
                <TextLink href="/">{t("common.home")}</TextLink>
              </li>
              {RELATED.map((link) => (
                <li key={link.href}>
                  <TextLink href={link.href}>{t(link.key)}</TextLink>
                </li>
              ))}
            </ul>
          </nav>
        </div>
      </div>
    </div>
  );
}

/** A section heading of a legal document; `section` comes from its TOC list. */
export function LegalHeading({ section }: { section: LegalSection }) {
  return <h2 id={section.id}>{section.title}</h2>;
}
