import { FacilityMapEmbed } from "@/components/directory/facility-map-embed";
import {
  OpenStatusLine,
  openStatusText,
} from "@/components/directory/open-status";
import {
  ContactList,
  ContactRow,
  MapPlaceholder,
} from "@/components/directory/profile-parts";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { StickyActionBar } from "@/components/ui/sticky-action-bar";
import { hasMapCoordinates } from "@/lib/maps";
import { officeHoursRows, openStatus } from "@/lib/office-hours";
import { telHref } from "@/lib/phone";
import { t } from "@/i18n/t";

export type ContactInfo = {
  name: string;
  phone: string | null;
  email: string | null;
  website?: string | null;
  /** Where: „ПЗУ Кардиомед“ + „Карпош, Скопје“. */
  place: { title: string; sub?: string | null; href?: string } | null;
  /** Map / directions URL (opens in a new tab). */
  directionsHref: string | null;
  hours: Record<string, string> | unknown[] | null | undefined;
  fee?: { label: string; note?: string } | null;
  coordinates?: { latitude: number | null; longitude: number | null };
  /** Id of the hours section, for the status row's link. */
  hoursAnchor?: string;
  now?: Date;
};

function hostOf(url: string): string | undefined {
  try {
    return new URL(url).host;
  } catch {
    return undefined;
  }
}

function statusRow(info: ContactInfo) {
  const rows = officeHoursRows(info.hours, info.now);
  const status = openStatus(rows, info.now);
  const today = rows.find((row) => row.isToday);

  return { status, today };
}

/** Mobile: contact first, as a list of tappable rows. */
export function ProfileContactList({ info }: { info: ContactInfo }) {
  const tel = telHref(info.phone);
  const { status, today } = statusRow(info);

  return (
    <ContactList label={t("directory.contact")} className="lg:hidden">
      {tel && info.phone ? (
        <ContactRow
          icon="phone"
          href={tel}
          title={info.phone}
          sub={t("directory.call")}
        />
      ) : null}
      {info.place ? (
        <ContactRow
          icon="map-pin"
          href={info.directionsHref ?? info.place.href}
          external={Boolean(info.directionsHref)}
          title={info.place.title}
          sub={[
            info.place.sub,
            info.directionsHref ? t("directory.directionsTo") : null,
          ]
            .filter(Boolean)
            .join(" · ")}
        />
      ) : null}
      {status ? (
        <ContactRow
          icon="clock"
          href={info.hoursAnchor ? `#${info.hoursAnchor}` : undefined}
          title={<span suppressHydrationWarning>{openStatusText(status)}</span>}
          titleClassName={status.state === "closed" ? undefined : "text-care"}
          sub={
            today && status.state !== "closed"
              ? `${t("directory.today")} · ${today.hours}`
              : undefined
          }
        />
      ) : null}
      {info.email ? (
        <ContactRow
          icon="mail"
          href={`mailto:${info.email}`}
          title={t("common.email")}
          sub={t("directory.sendEmail")}
        />
      ) : null}
      {info.website ? (
        <ContactRow
          icon="globe"
          href={info.website}
          external
          title={t("directory.website")}
          sub={hostOf(info.website)}
        />
      ) : null}
      {info.fee ? (
        <ContactRow
          icon="banknote"
          title={info.fee.label}
          sub={info.fee.note}
        />
      ) : null}
    </ContactList>
  );
}

/** Desktop: the sticky „Контакт“ card with the coral top edge. */
export function ProfileContactCard({ info }: { info: ContactInfo }) {
  const tel = telHref(info.phone);
  const lat = info.coordinates?.latitude ?? null;
  const lng = info.coordinates?.longitude ?? null;

  return (
    <Card
      as="aside"
      edge
      aria-labelledby="contact-card-title"
      className="sticky top-[calc(var(--header-h)+1rem)] hidden flex-col gap-5 lg:flex"
    >
      <div>
        <h2 id="contact-card-title" className="type-h3 text-ink">
          {t("directory.contact")}
        </h2>
        {info.phone ? (
          <p className="mt-1 text-2xl font-bold leading-8 text-ink">
            {info.phone}
          </p>
        ) : (
          <p className="type-meta mt-1 text-ink-2">
            {t("facilities.sidebarNoPhone")}
          </p>
        )}
      </div>
      {tel ? (
        <Button href={tel} size="lg" fullWidth leadingIcon="phone">
          {t("directory.call")}
        </Button>
      ) : null}

      {info.place || info.directionsHref || info.email || info.website ? (
        <div className="flex flex-col gap-3 border-t border-line pt-5">
          {info.place ? (
            <p className="flex gap-2 type-body text-ink">
              <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
              <span>
                {info.place.title}
                {info.place.sub ? (
                  <span className="block text-ink-2">{info.place.sub}</span>
                ) : null}
              </span>
            </p>
          ) : null}
          {info.directionsHref ? (
            <Button
              href={info.directionsHref}
              variant="soft"
              fullWidth
              leadingIcon="navigation"
              target="_blank"
              rel="noopener noreferrer"
            >
              {t("directory.directionsTo")}
            </Button>
          ) : null}
          {info.email ? (
            <Button
              href={`mailto:${info.email}`}
              variant="secondary"
              fullWidth
              leadingIcon="mail"
            >
              {t("common.email")}
            </Button>
          ) : null}
          {info.website ? (
            <Button
              href={info.website}
              variant="secondary"
              fullWidth
              leadingIcon="globe"
              target="_blank"
              rel="noopener noreferrer"
            >
              {t("directory.website")}
            </Button>
          ) : null}
        </div>
      ) : null}

      <ProfileStatusAndFee info={info} />

      {hasMapCoordinates(lat, lng) ? (
        <FacilityMapEmbed
          latitude={lat}
          longitude={lng}
          name={info.name}
          mapFallbackUrl={info.directionsHref}
        />
      ) : info.directionsHref ? (
        <MapPlaceholder href={info.directionsHref} />
      ) : null}
    </Card>
  );
}

function ProfileStatusAndFee({ info }: { info: ContactInfo }) {
  const { today } = statusRow(info);
  const status = openStatus(officeHoursRows(info.hours, info.now), info.now);

  if (!status && !info.fee) {
    return null;
  }

  return (
    <div className="flex flex-col gap-3 border-t border-line pt-5">
      {status ? (
        <div>
          <OpenStatusLine hours={info.hours} now={info.now} />
          {today && status.state !== "closed" ? (
            <p className="type-meta pl-7 text-ink-2">
              {t("directory.today")} · {today.hours}
            </p>
          ) : null}
        </div>
      ) : null}
      {info.fee ? (
        <div className="flex gap-2">
          <Icon name="banknote" size={20} className="mt-0.5 text-ink-2" />
          <div>
            <p className="type-body font-semibold text-ink">{info.fee.label}</p>
            {info.fee.note ? (
              <p className="type-meta text-ink-2">{info.fee.note}</p>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}

/** Mobile: „Јави се“ + „Насоки“ pinned above the tab bar. */
export function ProfileCallBar({ info }: { info: ContactInfo }) {
  const tel = telHref(info.phone);

  if (!tel && !info.directionsHref) {
    return null;
  }

  return (
    <StickyActionBar label={t("directory.quickContact")}>
      {tel ? (
        <Button href={tel} size="lg" leadingIcon="phone" className="flex-[1.2]">
          {t("directory.call")}
        </Button>
      ) : null}
      {info.directionsHref ? (
        <Button
          href={info.directionsHref}
          variant="soft"
          size="lg"
          leadingIcon="navigation"
          className="flex-1"
          target="_blank"
          rel="noopener noreferrer"
        >
          {t("directory.directions")}
        </Button>
      ) : null}
    </StickyActionBar>
  );
}
