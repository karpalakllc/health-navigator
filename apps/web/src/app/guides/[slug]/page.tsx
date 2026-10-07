import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { HelpfulFeedback } from "@/components/feedback/helpful-feedback";
import { JsonLd } from "@/components/seo/json-ld";
import { Card } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { GUIDES, guideBySlug } from "@/content/guides/guides";
import { pageMetadata } from "@/lib/metadata";
import { absoluteUrl } from "@/lib/site-url";
import { breadcrumbJsonLd } from "@/lib/structured-data";
import { t, tFormat } from "@/i18n/t";

type Props = { params: Promise<{ slug: string }> };

/*
 * Plain elements of the guide bodies (content/guides/guides.tsx carries no
 * classes): reading type, h2 with room above, real list markers, underlined
 * links.
 */
const PROSE = [
  "measure flex flex-col gap-4 text-ink",
  "[&_p]:type-reading [&_li]:type-reading",
  "[&_h2]:type-h2 [&_h2]:mt-6 [&_h2]:text-ink [&_h2:first-child]:mt-0",
  "[&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-2 [&_ul]:pl-6",
  "[&_ol]:flex [&_ol]:list-decimal [&_ol]:flex-col [&_ol]:gap-3 [&_ol]:pl-6",
  "[&_li]:marker:text-ink-2 [&_strong]:font-bold",
  "[&_a]:underline [&_a]:decoration-1 [&_a]:underline-offset-4",
].join(" ");

export function generateStaticParams() {
  return GUIDES.map((guide) => ({ slug: guide.slug }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const guide = guideBySlug((await params).slug);

  if (!guide) {
    return {};
  }

  return pageMetadata(guide.title, guide.summary, {
    path: `/guides/${guide.slug}`,
    ogType: "article",
  });
}

export default async function GuidePage({ params }: Props) {
  const guide = guideBySlug((await params).slug);

  if (!guide) {
    notFound();
  }

  const url = absoluteUrl(`/guides/${guide.slug}`);
  const crumbs = [
    { label: t("common.home"), href: "/" },
    { label: t("guides.title"), href: "/guides" },
    { label: guide.title },
  ];
  const others = GUIDES.filter((other) => other.slug !== guide.slug);
  const breadcrumbs = breadcrumbJsonLd(
    crumbs.map((item) => ({
      name: item.label,
      url: item.href ? absoluteUrl(item.href) : url,
    })),
  );

  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-4 lg:gap-8 lg:px-6 lg:pb-20 lg:pt-8">
      <div>
        <Breadcrumbs items={crumbs} className="mb-3" />
        <header className="flex flex-col gap-2">
          <h1 className="type-h1 text-ink">{guide.title}</h1>
          <p className="measure type-reading text-ink-2">{guide.summary}</p>
          <p className="type-meta text-ink-2">
            <time dateTime={guide.checked}>
              {tFormat("guides.checked", { date: guide.checked })}
            </time>
          </p>
        </header>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start lg:gap-10">
        <div className="flex min-w-0 flex-col gap-6">
          <Notice tone="info">{t("guides.informational")}</Notice>

          <Card as="article" aria-label={guide.title} className="lg:p-12">
            <div className={PROSE}>{guide.body()}</div>
          </Card>

          <section
            aria-labelledby="guide-sources"
            className="flex flex-col gap-2"
          >
            <h2 id="guide-sources" className="type-h3 text-ink">
              {t("guides.sources")}
            </h2>
            <ul className="flex list-disc flex-col gap-1 pl-6 type-body text-ink marker:text-ink-2">
              {guide.sources.map((source) => (
                <li key={source.url}>
                  <a
                    href={source.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="link-underline break-words"
                  >
                    {source.title}
                  </a>
                </li>
              ))}
            </ul>
            <p className="type-meta text-ink-2">
              {tFormat("guides.checked", { date: guide.checked })}
            </p>
          </section>

          <HelpfulFeedback item={`guide:${guide.slug}`} />
        </div>

        <nav
          aria-labelledby="guide-others"
          className="flex flex-col gap-2 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]"
        >
          <h2 id="guide-others" className="type-label text-ink-2">
            {t("guides.others")}
          </h2>
          <ul className="flex flex-col">
            {others.map((other) => (
              <li key={other.slug}>
                <Link
                  href={`/guides/${other.slug}`}
                  className="flex min-h-12 items-center rounded-sm px-3 type-body text-ink hover:bg-sand"
                >
                  {other.title}
                </Link>
              </li>
            ))}
            <li>
              <Link
                href="/urgent-care"
                className="flex min-h-12 items-center rounded-sm px-3 type-body text-ink hover:bg-sand"
              >
                {t("urgentCare.title")}
              </Link>
            </li>
          </ul>
        </nav>
      </div>

      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Article",
          headline: guide.title,
          description: guide.summary,
          url,
          dateModified: guide.checked,
          inLanguage: "mk",
          isBasedOn: guide.sources.map((source) => source.url),
        }}
      />
      {breadcrumbs ? <JsonLd data={breadcrumbs} /> : null}
    </div>
  );
}
