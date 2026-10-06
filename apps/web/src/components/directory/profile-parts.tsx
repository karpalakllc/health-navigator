import Link from "next/link";
import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { cn } from "@/lib/cn";
import { officeHoursRows, type HoursRow } from "@/lib/office-hours";
import { t, type MessageKey } from "@/i18n/t";

/*
 * Building blocks shared by the doctor, facility and pharmacy profiles
 * (D2a): white section cards, the contact list, the hours table with today
 * marked, tag lists and the map placeholder.
 */

/** A white card section with its own heading (h2, card-title size). */
export function ProfileSection({
  id,
  title,
  children,
  className,
  action,
}: {
  id: string;
  title: string;
  children: ReactNode;
  className?: string;
  action?: ReactNode;
}) {
  return (
    <Card
      as="section"
      aria-labelledby={`${id}-title`}
      id={id}
      padding="none"
      className={cn("p-5 lg:p-8", className)}
    >
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 id={`${id}-title`} className="type-h3 text-ink">
          {title}
        </h2>
        {action}
      </div>
      {children}
    </Card>
  );
}

/** Non-interactive sand pills (services, interests, departments). */
export function ProfileTagList({ items }: { items: string[] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <ul className="m-0 flex list-none flex-wrap gap-2 p-0">
      {items.map((item) => (
        <li key={item}>
          <Tag className="min-h-9 whitespace-normal py-1">{item}</Tag>
        </li>
      ))}
    </ul>
  );
}

type ContactRowProps = {
  icon: IconName;
  title: ReactNode;
  sub?: ReactNode;
  /** tel:, mailto:, a map URL, an in-page anchor or a site path. */
  href?: string;
  external?: boolean;
  titleClassName?: string;
};

/** One 64px row of the mobile contact list: icon disc, text, chevron. */
export function ContactRow({
  icon,
  title,
  sub,
  href,
  external,
  titleClassName,
}: ContactRowProps) {
  const body = (
    <>
      <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-sand text-ink">
        <Icon name={icon} size={22} />
      </span>
      <span className="flex min-w-0 flex-1 flex-col">
        <span
          className={cn("type-body font-semibold text-ink", titleClassName)}
        >
          {title}
        </span>
        {sub ? <span className="type-meta text-ink-2">{sub}</span> : null}
      </span>
      {href ? (
        <Icon name="chevron-right" size={20} className="text-ink-2" />
      ) : null}
    </>
  );
  const rowClass =
    "flex min-h-16 items-center gap-3 rounded-2xl px-3 py-2 text-ink no-underline";

  if (!href) {
    return <div className={rowClass}>{body}</div>;
  }

  if (/^(tel:|mailto:|https?:|#)/.test(href)) {
    return (
      <a
        href={href}
        className={cn(rowClass, "hover:bg-sand")}
        {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
      >
        {body}
      </a>
    );
  }

  return (
    <Link href={href} className={cn(rowClass, "hover:bg-sand")}>
      {body}
    </Link>
  );
}

/** Rows separated by hairlines, inside a white card. */
export function ContactList({
  label,
  children,
  className,
}: {
  label: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card
      as="section"
      aria-label={label}
      padding="none"
      className={cn(
        "flex flex-col p-2 [&>*+*]:border-t [&>*+*]:border-line",
        className,
      )}
    >
      {children}
    </Card>
  );
}

const DAY_KEYS: MessageKey[] = [
  "directory.dayMon",
  "directory.dayTue",
  "directory.dayWed",
  "directory.dayThu",
  "directory.dayFri",
  "directory.daySat",
  "directory.daySun",
];

function contiguous(days: number[]): boolean {
  return days.every((day, i) => i === 0 || day === days[i - 1] + 1);
}

/** „Понеделник“, „Понеделник–Петок“; the admin's own label otherwise. */
export function dayLabel(row: HoursRow): string {
  if (row.days.length === 1) {
    return t(DAY_KEYS[row.days[0]]);
  }
  if (row.days.length > 1 && contiguous(row.days)) {
    return `${t(DAY_KEYS[row.days[0]])}–${t(DAY_KEYS[row.days[row.days.length - 1]])}`;
  }
  return row.label;
}

/**
 * The hours as a two-column table; today's row is sand with a „Денес“ tag.
 * Computed on the server in Skopje time.
 */
export function HoursTable({
  hours,
  now,
}: {
  hours: Record<string, string> | unknown[] | null | undefined;
  now?: Date;
}) {
  const rows = officeHoursRows(hours, now);

  if (rows.length === 0) {
    return null;
  }

  return (
    <table className="w-full border-collapse type-body">
      <caption className="sr-only">{t("directory.officeHours")}</caption>
      <tbody>
        {rows.map((row) => (
          <tr
            key={row.label}
            aria-current={row.isToday ? "date" : undefined}
            className={cn(
              "[&+tr>*]:border-t [&+tr>*]:border-line",
              row.isToday &&
                "bg-sand font-semibold [&+tr>*]:border-transparent [&>*]:border-transparent",
            )}
          >
            <th
              scope="row"
              className={cn(
                "py-3 pl-3 pr-2 text-left",
                row.isToday ? "rounded-l-lg font-semibold" : "font-normal",
              )}
            >
              <span className="inline-flex flex-wrap items-center gap-2">
                {dayLabel(row)}
                {row.isToday ? (
                  <Tag tone="ink" className="min-h-7 px-2.5">
                    {t("directory.today")}
                  </Tag>
                ) : null}
              </span>
            </th>
            <td
              className={cn(
                "py-3 pl-2 pr-3 text-right tabular-nums",
                row.closed ? "text-ink-2" : "text-ink",
                row.isToday && "rounded-r-lg",
              )}
            >
              {row.closed ? t("directory.closed") : row.hours}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/** The sand map stand-in when there are no coordinates to embed. */
export function MapPlaceholder({
  href,
  className,
}: {
  href: string | null;
  className?: string;
}) {
  const inner = (
    <>
      <Icon name="map-pin" size={24} />
      <span className="type-meta">
        {href ? t("directory.viewOnMap") : t("directory.map")}
      </span>
    </>
  );
  const box = cn(
    "flex aspect-[16/10] w-full flex-col items-center justify-center gap-2 rounded-xl bg-sand text-ink-2",
    className,
  );

  return href ? (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={cn(box, "no-underline hover:text-ink")}
    >
      {inner}
    </a>
  ) : (
    <div className={box}>{inner}</div>
  );
}
