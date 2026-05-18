"use client";

import Link from "next/link";
import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { cn } from "@/lib/cn";
import type { FacilityListItem } from "@/lib/api/types";
import { facilityKindLabel } from "@/lib/facility-labels";
import { t, tFormat } from "@/i18n/t";

export function HomeFacilityCard({
  facility,
  variant = "default",
}: {
  facility: FacilityListItem;
  variant?: "default" | "teal";
}) {
  const placeholders = useSitePlaceholders();
  const avatarSrc = facility.avatar_url ?? placeholders.facility;

  return (
    <Link href={`/facilities/${facility.slug}`} className="block h-full">
      <article className="surface-glass card-lift flex h-full flex-col overflow-hidden rounded-[1.75rem]">
        <div
          className={cn(
            "flex h-[220px] items-center justify-center p-5",
            variant === "teal"
              ? "bg-gradient-to-b from-[#eff9f9] to-[#f8fbfb]"
              : "bg-gradient-to-b from-[#fff3f3] to-[#fff9f9]",
          )}
        >
          {avatarSrc ? (
            <img
              src={avatarSrc}
              alt=""
              className="h-full w-full rounded-3xl border border-accent/10 object-cover"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center rounded-3xl border border-accent/10 bg-accent/5 text-accent">
              <FacilityGlyph type={facility.type} />
            </div>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-2 p-5">
          <h3 className="text-lg font-extrabold tracking-tight text-foreground">{facility.name}</h3>
          <p className="text-sm text-muted-foreground">
            {[facilityKindLabel(facility.type), facility.city].filter(Boolean).join(" · ")}
          </p>
          <div className="mt-2 flex flex-wrap gap-2">
            {facility.has_emergency_services ? (
              <span className="rounded-full bg-[#fff1f1] px-3 py-1 text-xs font-bold text-primary">
                {t("facilities.emergencyBadge")}
              </span>
            ) : null}
            {facility.departments_count > 0 ? (
              <span className="rounded-full bg-[#f1f4f6] px-3 py-1 text-xs font-bold text-[#5d6771]">
                {tFormat("facilities.departmentCount", {
                  count: String(facility.departments_count),
                })}
              </span>
            ) : null}
            {facility.type === "clinic" && facility.departments_count === 0 ? (
              <span className="rounded-full bg-[#f1f4f6] px-3 py-1 text-xs font-bold text-[#5d6771]">
                {facilityKindLabel(facility.type)}
              </span>
            ) : null}
          </div>
        </div>
      </article>
    </Link>
  );
}

function FacilityGlyph({ type }: { type: FacilityListItem["type"] }) {
  const className = "h-12 w-12";
  if (type === "hospital") {
    return (
      <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden>
        <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M12 7v4M10 9h4" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    );
  }
  if (type === "clinic") {
    return (
      <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden>
        <path d="M6 22V4a2 2 0 012-2h8a2 2 0 012 2v18M6 12H4a2 2 0 00-2 2v6a2 2 0 002 2h2M18 12h2a2 2 0 012 2v6a2 2 0 01-2 2h-2M10 6h4M10 10h4M10 14h4M10 18h4" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    );
  }
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden>
      <path d="M12 6v12M6 12h12" strokeLinecap="round" />
    </svg>
  );
}
