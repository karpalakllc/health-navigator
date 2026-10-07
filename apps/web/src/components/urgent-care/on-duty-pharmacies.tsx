import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import type { OnDutyPharmacies, OnDutyPharmacy } from "@/lib/api/urgent-care";
import { directionsUrl } from "@/lib/urgent-care";
import { formatMkDate } from "@/lib/mk-date";
import { telHref } from "@/lib/phone";
import { t, tFormat } from "@/i18n/t";

/** ФЗОМ's monthly on-duty pharmacy schedule page. */
export const FZOM_ON_DUTY_PHARMACIES = "https://fzo.org.mk/dezurni-apteki";

function modeLine(pharmacy: OnDutyPharmacy): string | null {
  switch (pharmacy.mode) {
    case "all_day":
      return t("urgentCare.pharmacyAllDay");
    case "on_call":
      return t("urgentCare.pharmacyOnCall");
    case "hours":
      return pharmacy.hours_text;
    default:
      return null;
  }
}

/** The first number of „079-396-031, 072-250-570“. */
function firstPhone(phone: string | null): string | null {
  return phone?.split(",")[0]?.trim() || null;
}

/**
 * „Дежурни аптеки“ on „Каде веднаш“ from ФЗОМ's schedule (imported monthly,
 * docs/urgent-care.md): tonight's pharmacies of the chosen city with their
 * hours as ФЗОМ gives them, a call button and directions; the source and
 * its link always shown. Without the month's schedule: the placeholder.
 */
export function OnDutyPharmaciesSection({
  data,
  cityName,
}: {
  data: OnDutyPharmacies;
  cityName: string | null;
}) {
  const sourceLink = (
    <a
      href={data.source_url ?? FZOM_ON_DUTY_PHARMACIES}
      target="_blank"
      rel="noopener noreferrer"
      className="link-underline"
    >
      {t("urgentCare.pharmaciesLink")}
    </a>
  );

  if (!data.available) {
    return (
      <section aria-labelledby="urgent-pharmacies">
        <Notice
          tone="info"
          icon="pill"
          title={
            <span id="urgent-pharmacies">
              {t("urgentCare.pharmaciesTitle")}
            </span>
          }
        >
          <p>{t("urgentCare.pharmaciesBody")}</p>
          <p className="mt-2">{sourceLink}</p>
        </Notice>
      </section>
    );
  }

  const date = formatMkDate(data.date) ?? data.date ?? "";
  const items = data.items ?? [];

  return (
    <section
      aria-labelledby="urgent-pharmacies"
      className="flex flex-col gap-3 rounded-card bg-white p-5 ring-1 ring-line lg:p-6"
    >
      <div className="flex flex-col gap-1">
        <h2 id="urgent-pharmacies" className="type-h3 text-ink">
          {cityName
            ? tFormat("urgentCare.pharmaciesTonight", { city: cityName })
            : t("urgentCare.pharmaciesTitle")}
        </h2>
        {cityName ? (
          <p className="type-meta text-ink-2">
            {tFormat("urgentCare.pharmaciesFor", { date })}
          </p>
        ) : null}
      </div>

      {!cityName ? (
        <p className="type-body text-ink">
          {t("urgentCare.pharmaciesPickCity")}
        </p>
      ) : items.length === 0 ? (
        <p className="type-body text-ink">
          {tFormat("urgentCare.pharmaciesNoneInCity", { city: cityName, date })}
        </p>
      ) : (
        <ul className="flex flex-col divide-y divide-line">
          {items.map((pharmacy, index) => {
            const tel = telHref(firstPhone(pharmacy.phone));
            const line = modeLine(pharmacy);
            const where = [pharmacy.address, pharmacy.municipality]
              .filter(Boolean)
              .join(", ");

            return (
              <li
                key={`${pharmacy.name}-${index}`}
                className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex min-w-0 flex-col gap-0.5">
                  <p className="type-label text-ink">
                    {pharmacy.slug ? (
                      <Link
                        href={`/pharmacies/${pharmacy.slug}`}
                        className="underline decoration-1 underline-offset-4"
                        aria-label={tFormat("urgentCare.pharmacyProfileOf", {
                          name: pharmacy.name,
                        })}
                      >
                        {pharmacy.name}
                      </Link>
                    ) : (
                      pharmacy.name
                    )}
                  </p>
                  {where ? (
                    <p className="type-meta text-ink-2">{where}</p>
                  ) : null}
                  {line ? (
                    <p className="flex items-center gap-1.5 type-meta text-ink">
                      <Icon name="clock" size={18} className="shrink-0" />
                      <span>{line}</span>
                    </p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2 sm:shrink-0">
                  {tel ? (
                    <Button
                      href={tel}
                      variant="secondary"
                      size="md"
                      leadingIcon="phone"
                      aria-label={tFormat("urgentCare.callName", {
                        name: pharmacy.name,
                      })}
                    >
                      {t("urgentCare.call")}
                    </Button>
                  ) : null}
                  {pharmacy.address ||
                  (pharmacy.latitude != null && pharmacy.longitude != null) ? (
                    <Button
                      href={directionsUrl({
                        name: pharmacy.name,
                        address: pharmacy.address,
                        city: cityName,
                        latitude: pharmacy.latitude,
                        longitude: pharmacy.longitude,
                      })}
                      variant="ghost"
                      size="md"
                      leadingIcon="navigation"
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label={tFormat("urgentCare.directionsTo", {
                        name: pharmacy.name,
                      })}
                    >
                      {t("urgentCare.directions")}
                    </Button>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ul>
      )}

      <p className="type-meta text-ink-2">
        {t("urgentCare.pharmaciesSource")} {sourceLink}
      </p>
    </section>
  );
}
