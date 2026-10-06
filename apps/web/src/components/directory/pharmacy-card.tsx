"use client";

import Link from "next/link";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { OpenStatusLine } from "@/components/directory/open-status";
import { RatingLine } from "@/components/directory/rating-line";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import type { PharmacyListItem } from "@/lib/api/types";
import { telHref } from "@/lib/phone";
import { t, tFormat } from "@/i18n/t";

/** A pharmacy result (D2a card). */
export function PharmacyCard({ pharmacy }: { pharmacy: PharmacyListItem }) {
  const href = `/pharmacies/${pharmacy.slug}`;
  const tel = telHref(pharmacy.phone);

  return (
    <article className="card flex h-full flex-col p-5">
      <div className="flex items-start gap-3 lg:gap-4">
        <DirectoryAvatar
          kind="pharmacy"
          avatarUrl={pharmacy.avatar_url}
          name={pharmacy.name}
          size={56}
        />
        <div className="min-w-0 flex-1 pt-1">
          <h2 className="type-h3 text-ink">
            <Link href={href} className="hover:underline">
              {pharmacy.name}
            </Link>
          </h2>
          <p className="type-meta mt-0.5 text-ink-2">{t("pharmacies.kind")}</p>
        </div>
      </div>

      <div className="mt-4 flex flex-col gap-2">
        {pharmacy.city ? (
          <p className="flex items-start gap-2 type-body text-ink">
            <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
            {pharmacy.city}
          </p>
        ) : null}
        <RatingLine summary={pharmacy.review_summary} showStars={false} />
        {pharmacy.office_hours ? (
          <OpenStatusLine hours={pharmacy.office_hours} />
        ) : null}
      </div>

      <div className="mt-auto flex gap-2 pt-4">
        {tel ? (
          <Button
            href={tel}
            variant="soft"
            leadingIcon="phone"
            className="flex-1"
            aria-label={tFormat("directory.callName", { name: pharmacy.name })}
          >
            {t("directory.call")}
          </Button>
        ) : null}
        <Button
          href={href}
          className="flex-1"
          aria-label={tFormat("directory.viewProfileOf", {
            name: pharmacy.name,
          })}
        >
          {t("doctors.viewProfile")}
        </Button>
      </div>
    </article>
  );
}
