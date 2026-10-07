import Link from "next/link";
import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import {
  contentTotals,
  reportTotals,
  type TransparencyContentMonth,
  type TransparencyMonth,
  type TransparencyStats,
} from "@/lib/api/transparency";
import { formatMkDate } from "@/lib/mk-date";
import {
  REMOVAL_CATEGORIES,
  formatDecimal,
  monthLabel,
  removalCategoryLabel,
} from "@/lib/review-integrity";
import { t, tFormat, type MessageKey } from "@/i18n/t";

const HERO_POINTS: Array<{ icon: IconName; key: MessageKey }> = [
  { icon: "shield-check", key: "integrity.heroPoint1" },
  { icon: "banknote", key: "integrity.heroPoint2" },
  { icon: "reply", key: "integrity.heroPoint3" },
];

const MODERATION: MessageKey[] = [
  "integrity.moderation1",
  "integrity.moderation2",
  "integrity.moderation3",
  "integrity.moderation4",
  "integrity.moderation5",
];

const VERIFIED_WHEN: MessageKey[] = [
  "verification.howDoctor",
  "verification.howDentist",
  "verification.howDoctorWebsite",
  "verification.howFacility",
  "verification.howTeam",
];

const PAYMENT: MessageKey[] = [
  "integrity.payment1",
  "integrity.payment2",
  "integrity.payment3",
];

const DATA_SOURCES: Array<{
  icon: IconName;
  name: MessageKey;
  body: MessageKey;
}> = [
  {
    icon: "building",
    name: "dataSources.fzomName",
    body: "dataSources.fzomBody",
  },
  {
    icon: "award",
    name: "dataSources.komoraName",
    body: "dataSources.komoraBody",
  },
  {
    icon: "globe",
    name: "dataSources.websitesName",
    body: "dataSources.websitesBody",
  },
  { icon: "user", name: "dataSources.ownName", body: "dataSources.ownBody" },
];

const NOT_TAKEN: MessageKey[] = [
  "dataSources.excluded1",
  "dataSources.excluded2",
  "dataSources.excluded3",
];

const CRITERIA: MessageKey[] = [
  "integrity.criteria1",
  "integrity.criteria2",
  "integrity.criteria3",
  "integrity.criteria4",
  "integrity.criteria5",
];

function hoursText(hours: number | null): string {
  return hours === null
    ? t("integrity.noData")
    : tFormat("integrity.hours", { hours: formatDecimal(hours) });
}

/**
 * The public „Транспарентност“ page: what moderation does, the monthly
 * figures (when the API answers), that reviews cannot be bought, how results
 * are ordered, and the draft featuring criteria.
 */
export function TransparencyContent({
  stats,
}: {
  stats: TransparencyStats | null;
}) {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-10 px-5 pb-14 pt-4 lg:gap-14 lg:px-6 lg:pb-20 lg:pt-10">
      <section
        aria-labelledby="transparency-title"
        className="grid gap-8 rounded-sheet bg-apricot p-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-center lg:gap-14 lg:p-14"
      >
        <div className="flex flex-col gap-3">
          <p className="type-meta font-semibold text-ink">
            {t("integrity.pageTitle")}
          </p>
          <h1 id="transparency-title" className="type-h1 text-ink">
            {t("integrity.heroTitle")}
          </h1>
          <p className="measure type-reading text-ink">
            {t("integrity.heroBody")}
          </p>
        </div>
        <ul className="m-0 flex list-none flex-col gap-4 p-0">
          {HERO_POINTS.map((point) => (
            <li key={point.key} className="flex items-center gap-3">
              <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-ink">
                <Icon name={point.icon} size={24} />
              </span>
              <span className="type-body text-ink">{t(point.key)}</span>
            </li>
          ))}
        </ul>
      </section>

      <Stats stats={stats} />

      <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <TextCard id="moderacija" title={t("integrity.moderationTitle")}>
          <CheckList items={MODERATION} />
        </TextCard>
        <TextCard id="plakanje" title={t("integrity.paymentTitle")}>
          <CheckList items={PAYMENT} />
        </TextCard>
        <TextCard id="redosled" title={t("integrity.orderTitle")}>
          <p className="type-reading text-ink">{t("integrity.orderBody")}</p>
          <dl className="m-0 flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <dt>
                <Tag tone="outline">{t("ui.featured")}</Tag>
              </dt>
              <dd className="m-0 type-body text-ink">
                {t("disclosure.featuredInfo")}
              </dd>
            </div>
            <div className="flex flex-col gap-2">
              <dt>
                <Tag tone="outline">{t("doctors.sponsored")}</Tag>
              </dt>
              <dd className="m-0 type-body text-ink">
                {t("disclosure.sponsoredInfo")}
              </dd>
            </div>
          </dl>
        </TextCard>
        <TextCard
          id="kriteriumi"
          title={t("integrity.criteriaTitle")}
          badge={
            <Tag tone="tint" icon="file-text">
              {t("integrity.criteriaDraft")}
            </Tag>
          }
        >
          <p className="type-meta text-ink-2">
            {t("integrity.criteriaDraftNote")}
          </p>
          <CheckList items={CRITERIA} />
        </TextCard>
      </div>

      <DataSources />

      <VerificationSection />

      <p className="type-body text-ink-2">
        <Link href="/terms#moderacija" className="link-underline text-ink">
          {t("integrity.termsLink")}
        </Link>
      </p>
    </div>
  );
}

function Stats({ stats }: { stats: TransparencyStats | null }) {
  const updated = stats ? formatMkDate(stats.generated_at) : null;

  return (
    <section
      id="brojki"
      aria-labelledby="transparency-stats"
      className="flex flex-col gap-4 lg:gap-5"
    >
      <div className="flex flex-col gap-1">
        <h2 id="transparency-stats" className="type-h2 text-ink">
          {t("integrity.statsTitle")}
        </h2>
        <p className="measure type-body text-ink-2">
          {t("integrity.statsLead")}
          {updated
            ? ` ${tFormat("integrity.statsUpdated", { date: updated })}.`
            : null}
        </p>
      </div>
      {stats && stats.months.length > 0 ? (
        <StatsBody months={stats.months} />
      ) : (
        <Card tone="sand" padding="md">
          <p className="type-body text-ink">
            {t("integrity.statsUnavailable")}
          </p>
        </Card>
      )}
    </section>
  );
}

function StatsBody({ months }: { months: TransparencyMonth[] }) {
  const reviews = contentTotals(months, "reviews");
  const forum = contentTotals(months, "forum");
  const reports = reportTotals(months);
  const tiles: Array<{ key: MessageKey; value: string }> = [
    { key: "integrity.statReviewsReceived", value: String(reviews.received) },
    { key: "integrity.statReviewsPublished", value: String(reviews.published) },
    { key: "integrity.statReviewsRemoved", value: String(reviews.removed) },
    {
      key: "integrity.statModerationTime",
      value: hoursText(reviews.averageModerationHours),
    },
    { key: "integrity.statForumReceived", value: String(forum.received) },
    { key: "integrity.statForumRemoved", value: String(forum.removed) },
    { key: "integrity.statReportsReceived", value: String(reports.received) },
    { key: "integrity.statReportsResolved", value: String(reports.resolved) },
  ];
  const removedTotal = reviews.removed + forum.removed;

  return (
    <>
      <ul className="m-0 grid list-none grid-cols-2 gap-3 p-0 lg:grid-cols-4 lg:gap-4">
        {tiles.map((tile) => (
          <li key={tile.key}>
            <Card padding="md" className="flex h-full flex-col gap-2">
              <span className="text-[1.75rem] font-bold leading-none tabular-nums text-ink">
                {tile.value}
              </span>
              <span className="type-meta text-ink-2">{t(tile.key)}</span>
            </Card>
          </li>
        ))}
      </ul>

      <Card
        as="section"
        aria-labelledby="transparency-categories"
        className="flex flex-col gap-3"
      >
        <div className="flex flex-col gap-1">
          <h3 id="transparency-categories" className="type-h3 text-ink">
            {t("integrity.removedByCategoryTitle")}
          </h3>
          <p className="type-meta text-ink-2">
            {t("integrity.removedByCategoryLead")}
          </p>
        </div>
        {removedTotal === 0 ? (
          <p className="type-body text-ink">{t("integrity.removedNone")}</p>
        ) : (
          <ul className="m-0 grid list-none gap-2 p-0 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-8">
            {REMOVAL_CATEGORIES.map((category) => {
              const count =
                reviews.removedByCategory[category] +
                forum.removedByCategory[category];
              const share = Math.round((count / removedTotal) * 100);

              return (
                <li key={category} className="flex flex-col gap-1">
                  <span className="flex items-baseline justify-between gap-3 type-body text-ink">
                    <span className="first-letter:uppercase">
                      {removalCategoryLabel(category)}
                    </span>
                    <span className="tabular-nums font-semibold">{count}</span>
                  </span>
                  <span
                    aria-hidden="true"
                    className="relative h-2 overflow-hidden rounded-full bg-sand"
                  >
                    <span
                      className="absolute inset-y-0 left-0 rounded-full bg-ink-2"
                      style={{ width: `${share}%` }}
                    />
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </Card>

      <div className="flex flex-col gap-3">
        <MonthTable
          id="tabela-recenzii"
          caption={t("integrity.reviewsTable")}
          months={months}
          row={(month) => contentRow(month.reviews)}
          headers={[
            t("integrity.colReceived"),
            t("integrity.colPublished"),
            t("integrity.colRejected"),
            t("integrity.colRemoved"),
            t("integrity.colHours"),
          ]}
        />
        <MonthTable
          id="tabela-forum"
          caption={t("integrity.forumTable")}
          months={months}
          row={(month) => contentRow(month.forum)}
          headers={[
            t("integrity.colReceived"),
            t("integrity.colPublished"),
            t("integrity.colRejected"),
            t("integrity.colRemoved"),
            t("integrity.colHours"),
          ]}
        />
        <MonthTable
          id="tabela-prijavi"
          caption={t("integrity.reportsTable")}
          months={months}
          row={(month) => [
            String(month.reports.received),
            String(month.reports.resolved),
            String(month.reports.removed),
            String(month.reports.kept),
          ]}
          headers={[
            t("integrity.colReceived"),
            t("integrity.colResolved"),
            t("integrity.colReportsRemoved"),
            t("integrity.colReportsKept"),
          ]}
        />
      </div>
    </>
  );
}

function contentRow(row: TransparencyContentMonth): string[] {
  return [
    String(row.received),
    String(row.published),
    String(row.rejected),
    String(row.removed),
    hoursText(row.average_moderation_hours),
  ];
}

/**
 * One month-by-month table, folded away by default (<details>) so the page
 * stays short on a phone. Months are row headers; on a narrow screen the
 * table scrolls inside its own box, never the page.
 */
function MonthTable({
  id,
  caption,
  months,
  headers,
  row,
}: {
  id: string;
  caption: string;
  months: TransparencyMonth[];
  headers: string[];
  row: (month: TransparencyMonth) => string[];
}) {
  return (
    <details className="group card overflow-hidden">
      <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 type-label text-ink [&::-webkit-details-marker]:hidden">
        <span>{caption}</span>
        <Icon
          name="chevron-down"
          size={20}
          className="shrink-0 transition-transform group-open:rotate-180 motion-reduce:transition-none"
        />
      </summary>
      <div
        className="overflow-x-auto border-t border-line"
        role="region"
        aria-labelledby={`${id}-caption`}
        tabIndex={0}
      >
        <table className="w-full min-w-[34rem] border-collapse text-left type-meta text-ink">
          <caption id={`${id}-caption`} className="sr-only">
            {caption}
          </caption>
          <thead>
            <tr className="bg-sand">
              <th scope="col" className="px-4 py-2 font-semibold">
                {t("integrity.colMonth")}
              </th>
              {headers.map((header) => (
                <th
                  key={header}
                  scope="col"
                  className="px-3 py-2 text-right font-semibold"
                >
                  {header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {months.map((month) => (
              <tr key={month.month} className="border-t border-line">
                <th scope="row" className="px-4 py-2 font-normal">
                  {monthLabel(month.month)}
                </th>
                {row(month).map((value, index) => (
                  <td
                    key={headers[index]}
                    className="px-3 py-2 text-right tabular-nums"
                  >
                    {value}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </details>
  );
}

/**
 * „Извори на податоци“ (W6-C): where directory profiles come from, what is
 * deliberately not taken, and how to get an error fixed. Must match the
 * privacy policy's section for listed health professionals.
 */
function DataSources() {
  return (
    <TextCard id="izvori" title={t("dataSources.title")}>
      <p className="measure type-reading text-ink">{t("dataSources.lead")}</p>
      <ul className="m-0 grid list-none gap-4 p-0 sm:grid-cols-2 lg:gap-6">
        {DATA_SOURCES.map((source) => (
          <li key={source.name} className="flex items-start gap-3">
            <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
              <Icon name={source.icon} size={24} />
            </span>
            <span className="flex flex-col gap-1">
              <span className="type-body font-semibold text-ink">
                {t(source.name)}
              </span>
              <span className="type-body text-ink">{t(source.body)}</span>
            </span>
          </li>
        ))}
      </ul>
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-3">
          <h3 className="type-h3 text-ink">{t("dataSources.excludedTitle")}</h3>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {NOT_TAKEN.map((key) => (
              <li key={key} className="flex items-start gap-3">
                <span className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-chip-tint text-ink">
                  <Icon name="x" size={18} />
                </span>
                <span className="type-body text-ink">{t(key)}</span>
              </li>
            ))}
          </ul>
        </div>
        <div className="flex flex-col gap-3">
          <h3 className="type-h3 text-ink">
            {t("dataSources.correctionsTitle")}
          </h3>
          <p className="type-body text-ink">
            {t("dataSources.correctionsBody")}
          </p>
          <p className="type-body">
            <Link
              href="/privacy#zdravstveni-rabotnici"
              className="link-underline text-ink"
            >
              {t("dataSources.privacyLink")}
            </Link>
          </p>
        </div>
      </div>
    </TextCard>
  );
}

/**
 * „Верифицирани профили“ (W7-B): what the „Верифициран“ / „Неверифициран“
 * badges on profiles mean. Every badge's „Повеќе“ links here
 * (VERIFICATION_MORE_HREF). Must match VerificationBasis on the API.
 */
function VerificationSection() {
  return (
    <TextCard id="verifikacija" title={t("verification.sectionTitle")}>
      <p className="measure type-reading text-ink">
        {t("verification.sectionLead")}
      </p>
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-3">
          <h3 className="flex flex-wrap items-center gap-3 type-h3 text-ink">
            {t("verification.howTitle")}
            <Tag tone="care" icon="shield-check">
              {t("verification.verified")}
            </Tag>
          </h3>
          <CheckList items={VERIFIED_WHEN} />
        </div>
        <div className="flex flex-col gap-3">
          <h3 className="flex flex-wrap items-center gap-3 type-h3 text-ink">
            {t("verification.unverifiedTitle")}
            <Tag>{t("verification.unverified")}</Tag>
          </h3>
          <p className="type-body text-ink">
            {t("verification.unverifiedBody")}
          </p>
          <h3 className="mt-2 type-h3 text-ink">
            {t("verification.keptCurrentTitle")}
          </h3>
          <p className="type-body text-ink">
            {t("verification.keptCurrentBody")}
          </p>
        </div>
      </div>
    </TextCard>
  );
}

function TextCard({
  id,
  title,
  badge,
  children,
}: {
  id: string;
  title: string;
  badge?: ReactNode;
  children: ReactNode;
}) {
  return (
    <Card
      as="section"
      id={id}
      aria-labelledby={`${id}-title`}
      className="flex scroll-mt-24 flex-col gap-4"
    >
      <div className="flex flex-wrap items-center gap-3">
        <h2 id={`${id}-title`} className="type-h2 text-ink">
          {title}
        </h2>
        {badge}
      </div>
      {children}
    </Card>
  );
}

function CheckList({ items }: { items: MessageKey[] }) {
  return (
    <ul className="m-0 flex list-none flex-col gap-3 p-0">
      {items.map((key) => (
        <li key={key} className="flex items-start gap-3">
          <span className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-care-tint text-care">
            <Icon name="check" size={18} />
          </span>
          <span className="type-body text-ink">{t(key)}</span>
        </li>
      ))}
    </ul>
  );
}
