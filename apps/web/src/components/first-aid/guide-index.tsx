import Link from "next/link";
import { FirstAidCallLinks } from "@/components/first-aid/call-links";
import { FirstAidIllustration } from "@/components/first-aid/illustrations";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { FIRST_AID_COPY as COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_BASE,
  FIRST_AID_GROUPS,
  isFirstAidGuidePublic,
} from "@/content/first-aid";
import type { FirstAidGuide } from "@/content/first-aid/types";

const TEXT =
  "text-[1.125rem] leading-7 lg:text-[1.1875rem] lg:leading-[1.875rem]";

/**
 * „Прва помош“ index: the 194/112 calls first, then the guides grouped by
 * situation. `guides` is what the viewer may see — published ones for the
 * public, every guide for staff in preview.
 */
export function FirstAidIndex({
  guides,
  preview = false,
}: {
  guides: readonly FirstAidGuide[];
  preview?: boolean;
}) {
  const groups = FIRST_AID_GROUPS.map((group) => ({
    ...group,
    guides: guides.filter((guide) => guide.group === group.id),
  })).filter((group) => group.guides.length > 0);

  return (
    <div
      data-first-aid-print=""
      className="mx-auto flex w-full max-w-[1120px] flex-col gap-10 px-4 pb-10 pt-6 sm:px-5 lg:gap-14 lg:pt-10"
    >
      <div className="flex flex-col gap-5">
        <h1 className="type-h1 text-ink">{COPY.title}</h1>
        <p className={`measure font-reading text-ink ${TEXT}`}>{COPY.intro}</p>
        <FirstAidCallLinks />
        {preview ? (
          <p
            role="status"
            className={`rounded-[20px] border-2 border-dashed border-ink bg-sand p-4 font-semibold text-ink ${TEXT}`}
          >
            {COPY.previewIndexBanner}
          </p>
        ) : null}
      </div>

      {groups.length === 0 ? (
        <p className={`measure rounded-[20px] bg-sand p-5 text-ink ${TEXT}`}>
          {COPY.emptyIndex}
        </p>
      ) : (
        groups.map((group) => (
          <section
            key={group.id}
            aria-labelledby={`grupa-${group.id}`}
            className="flex flex-col gap-5"
          >
            <h2 id={`grupa-${group.id}`} className="type-h2 text-ink">
              {group.title}
            </h2>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {group.guides.map((guide) => (
                <li key={guide.slug} className="min-w-0">
                  <Link
                    href={`${FIRST_AID_BASE}/${guide.slug}`}
                    className="group flex h-full items-start gap-4 rounded-[24px] border border-line bg-white p-4 text-ink no-underline hover:border-ink"
                  >
                    <FirstAidIllustration
                      id={guide.illustration}
                      className="w-20 shrink-0 rounded-2xl bg-apricot/50 p-1.5 print:hidden"
                    />
                    <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                      <span className="break-words text-[1.25rem] font-semibold leading-7 group-hover:underline group-hover:decoration-coral group-hover:decoration-2 group-hover:underline-offset-4">
                        {guide.title}
                      </span>
                      <span className="font-reading text-[1.125rem] leading-7 text-ink-2">
                        {guide.summary}
                      </span>
                      {!isFirstAidGuidePublic(guide) ? (
                        <Tag
                          tone="outline"
                          className="mt-1 h-auto max-w-full self-start whitespace-normal py-1"
                        >
                          {COPY.reviewDraft}
                        </Tag>
                      ) : null}
                    </span>
                    <Icon
                      name="chevron-right"
                      size={24}
                      className="mt-1 shrink-0 text-ink-2 print:hidden"
                    />
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ))
      )}

      <p className={`measure text-ink-2 ${TEXT}`}>{COPY.disclaimer}</p>
    </div>
  );
}
