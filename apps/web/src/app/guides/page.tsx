import type { Metadata } from "next";
import Link from "next/link";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { Icon } from "@/components/ui/icons";
import { GUIDES } from "@/content/guides/guides";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("guides.title"),
  t("guides.description"),
  { path: "/guides" },
);

/** „Водичи низ здравството“: the list of guides, plus „Каде веднаш“. */
export default function GuidesIndex() {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-4 lg:gap-8 lg:px-6 lg:pb-20 lg:pt-8">
      <div>
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("guides.title") },
          ]}
          className="mb-3"
        />
        <header className="flex flex-col gap-2">
          <h1 className="type-h1 text-ink">{t("guides.title")}</h1>
          <p className="measure type-reading text-ink-2">{t("guides.lead")}</p>
        </header>
      </div>

      <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {GUIDES.map((guide) => (
          <li key={guide.slug} className="flex">
            <Link
              href={`/guides/${guide.slug}`}
              className="card flex w-full flex-col gap-2 p-5 no-underline hover:shadow-card-hover"
            >
              <h2 className="type-h3 text-ink">{guide.title}</h2>
              <p className="type-body text-ink-2">{guide.summary}</p>
              <p className="mt-auto flex items-center justify-between gap-2 pt-2 type-meta text-ink-2">
                <span>
                  {tFormat("guides.checked", { date: guide.checked })}
                </span>
                <Icon name="arrow-right" size={20} className="text-ink" />
              </p>
            </Link>
          </li>
        ))}
        <li className="flex">
          <Link
            href="/urgent-care"
            className="flex w-full flex-col gap-2 rounded-card bg-apricot p-5 no-underline"
          >
            <h2 className="type-h3 text-ink">{t("urgentCare.title")}</h2>
            <p className="type-body text-ink">{t("urgentCare.description")}</p>
            <p className="mt-auto flex justify-end pt-2">
              <Icon name="arrow-right" size={20} className="text-ink" />
            </p>
          </Link>
        </li>
      </ul>

      <p className="measure type-meta text-ink-2">
        {t("guides.informational")}
      </p>
    </div>
  );
}
