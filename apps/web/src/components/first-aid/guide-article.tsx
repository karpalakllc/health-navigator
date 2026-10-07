import Link from "next/link";
import type { ReactNode } from "react";
import { FirstAidCallLinks } from "@/components/first-aid/call-links";
import { FirstAidIllustration } from "@/components/first-aid/illustrations";
import { PrintButton } from "@/components/first-aid/print-button";
import { BackLink } from "@/components/ui/back-link";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { FIRST_AID_COPY as COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_ANCHORS as ANCHORS,
  FIRST_AID_BASE,
  getFirstAidGuide,
  isFirstAidGuidePublic,
} from "@/content/first-aid";
import type { FirstAidGuide, FirstAidStep } from "@/content/first-aid/types";
import { formatMkDate } from "@/lib/mk-date";

/** Reading size, never under 18px; headings get room above. */
const TEXT =
  "text-[1.125rem] leading-7 lg:text-[1.1875rem] lg:leading-[1.875rem]";
const H2 = "type-h2 scroll-mt-28 text-ink";

function Section({
  id,
  title,
  headingId,
  children,
}: {
  id: string;
  title: string;
  headingId?: string;
  children: ReactNode;
}) {
  return (
    <section
      id={id}
      aria-labelledby={headingId ?? `${id}-naslov`}
      className="flex scroll-mt-28 flex-col gap-4 break-inside-avoid-page"
    >
      <h2 id={headingId ?? `${id}-naslov`} className={H2}>
        {title}
      </h2>
      {children}
    </section>
  );
}

function BulletList({
  items,
  marker,
}: {
  items: string[];
  marker: "dot" | "no" | "alert";
}) {
  return (
    <ul className={`flex flex-col gap-3 ${TEXT}`}>
      {items.map((item) => (
        <li key={item} className="flex gap-3">
          {marker === "dot" ? (
            <span
              aria-hidden="true"
              className="mt-[0.7rem] size-2 shrink-0 rounded-full border-4 border-ink"
            />
          ) : (
            // The cross / warning sign is a shape plus the heading's words,
            // never colour alone.
            <Icon
              name={marker === "no" ? "x" : "alert-triangle"}
              size={22}
              className="mt-[0.2rem] shrink-0 text-ink"
            />
          )}
          <span>{item}</span>
        </li>
      ))}
    </ul>
  );
}

function StepItem({ step, number }: { step: FirstAidStep; number: number }) {
  return (
    <li className="flex gap-4 break-inside-avoid">
      <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-ink text-xl font-bold text-white print:border-2 print:border-ink print:bg-white print:text-ink">
        {number}
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-2 pt-1.5 sm:flex-row sm:items-start sm:gap-5">
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <p className="text-[1.25rem] font-semibold leading-8 text-ink lg:text-[1.375rem]">
            {step.text}
          </p>
          {step.detail ? (
            <p className={`${TEXT} font-reading text-ink-2`}>{step.detail}</p>
          ) : null}
        </div>
        {step.illustration ? (
          <FirstAidIllustration
            id={step.illustration}
            className="w-40 shrink-0 rounded-2xl bg-white p-2 sm:w-44"
          />
        ) : null}
      </div>
    </li>
  );
}

function SourcesList({ guide }: { guide: FirstAidGuide }) {
  return (
    <ul className={`flex flex-col gap-3 ${TEXT}`}>
      {guide.sources.map((source) => (
        <li key={source.url}>
          <a
            href={source.url}
            className="link-underline break-words text-ink"
            rel="noopener noreferrer"
          >
            {source.publisher}: {source.title}
          </a>
          <span className="text-ink-2">
            {" "}
            ({COPY.accessed} {formatMkDate(source.accessed)}
            {source.sourceReviewed
              ? `; ${COPY.sourceReviewed} ${formatMkDate(source.sourceReviewed)}`
              : ""}
            )
          </span>
          {/* Printed pages keep the address, not just the link text. */}
          <span className="hidden break-all text-base print:block">
            {source.url}
          </span>
        </li>
      ))}
    </ul>
  );
}

/**
 * One first-aid guide: title, „Прво повикајте 194“ (or when to call), signs,
 * numbered steps, what not to do, escalation, sources and review status.
 * Server-rendered text; the print button is the only client script. Every
 * anchor in FIRST_AID_ANCHORS exists on every guide (link contract).
 */
export function FirstAidGuideArticle({
  guide,
  preview = false,
}: {
  guide: FirstAidGuide;
  /** Staff preview of a draft: shows the banner. */
  preview?: boolean;
}) {
  const isPublic = isFirstAidGuidePublic(guide);
  const related = (guide.related ?? [])
    .map((slug) => getFirstAidGuide(slug))
    .filter((item): item is FirstAidGuide =>
      Boolean(item && (preview || isFirstAidGuidePublic(item))),
    );

  const escalateList = <BulletList items={guide.escalate} marker="alert" />;

  return (
    <article
      data-first-aid-print=""
      data-first-aid-guide={guide.slug}
      className="mx-auto flex w-full max-w-[880px] flex-col gap-10 px-4 pb-10 pt-6 sm:px-5 lg:gap-12 lg:pt-10"
    >
      <div className="flex flex-col gap-5 print:hidden">
        <BackLink href={FIRST_AID_BASE} label={COPY.backToIndex} />
        {preview && !isPublic ? (
          <p
            role="status"
            className={`rounded-[20px] border-2 border-dashed border-ink bg-sand p-4 font-semibold text-ink ${TEXT}`}
          >
            {COPY.previewBanner}
          </p>
        ) : null}
      </div>

      <header className="flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-8">
        <div className="flex min-w-0 flex-1 flex-col gap-3">
          <p className="type-label text-ink-2">{COPY.title}</p>
          <h1 className="type-h1 text-ink">{guide.title}</h1>
          <p className={`${TEXT} font-reading text-ink`}>{guide.summary}</p>
          {!isPublic ? (
            <Tag tone="outline" className="self-start">
              {COPY.reviewDraft}
            </Tag>
          ) : null}
        </div>
        <FirstAidIllustration
          id={guide.illustration}
          className="w-44 shrink-0 self-center rounded-[28px] bg-apricot/50 p-3 sm:w-56 print:hidden"
        />
      </header>

      {guide.call === "first" ? (
        <section
          id={ANCHORS.call}
          aria-labelledby={`${ANCHORS.call}-naslov`}
          className="flex scroll-mt-28 flex-col gap-4 rounded-[28px] border-2 border-ink bg-white p-5 lg:p-6"
        >
          <h2 id={`${ANCHORS.call}-naslov`} className="type-h2 text-ink">
            {COPY.callFirstTitle}
          </h2>
          <FirstAidCallLinks />
          {guide.callNote ? (
            <p className={`${TEXT} text-ink`}>{guide.callNote}</p>
          ) : null}
        </section>
      ) : (
        // Not every case needs an ambulance: the box lists when it does, and
        // doubles as the escalation section (both anchors live here).
        <section
          id={ANCHORS.call}
          aria-labelledby={ANCHORS.escalate}
          className="flex scroll-mt-28 flex-col gap-4 rounded-[28px] border-2 border-ink bg-white p-5 lg:p-6"
        >
          <h2 id={ANCHORS.escalate} className="type-h2 scroll-mt-28 text-ink">
            {COPY.callIfTitle}
          </h2>
          <p className={`${TEXT} font-semibold text-ink`}>{COPY.callIfLead}</p>
          {escalateList}
          <FirstAidCallLinks />
          {guide.callNote ? (
            <p className={`${TEXT} text-ink`}>{guide.callNote}</p>
          ) : null}
        </section>
      )}

      <Section id={ANCHORS.recognise} title={COPY.recognise}>
        <BulletList items={guide.recognise} marker="dot" />
      </Section>

      <Section id={ANCHORS.steps} title={COPY.steps}>
        <div className="flex flex-col gap-8">
          {guide.sections.map((section) => {
            const multiple = guide.sections.length > 1;
            return (
              <div
                key={section.id}
                id={section.id}
                className="flex scroll-mt-28 flex-col gap-4"
              >
                {multiple ? (
                  <h3 className="type-h3 text-ink">{section.title}</h3>
                ) : null}
                {section.intro ? (
                  <p className={`${TEXT} font-reading text-ink-2`}>
                    {section.intro}
                  </p>
                ) : null}
                <ol className="flex flex-col gap-6">
                  {section.steps.map((step, index) => (
                    <StepItem key={step.text} step={step} number={index + 1} />
                  ))}
                </ol>
              </div>
            );
          })}
          {guide.untilHelp ? (
            <p className={`rounded-[20px] bg-care-tint p-4 text-ink ${TEXT}`}>
              <strong className="font-semibold">{COPY.untilHelp}: </strong>
              {guide.untilHelp}
            </p>
          ) : null}
        </div>
      </Section>

      <Section id={ANCHORS.dont} title={COPY.dont}>
        <BulletList items={guide.dont} marker="no" />
      </Section>

      {guide.call === "first" ? (
        <section
          aria-labelledby={ANCHORS.escalate}
          className="flex flex-col gap-4 break-inside-avoid-page"
        >
          <h2 id={ANCHORS.escalate} className={H2}>
            {COPY.escalate}
          </h2>
          {escalateList}
        </section>
      ) : null}

      {related.length > 0 ? (
        <section
          aria-labelledby="povrzani-naslov"
          className="flex flex-col gap-4 print:hidden"
        >
          <h2 id="povrzani-naslov" className={H2}>
            {COPY.related}
          </h2>
          <ul className="flex flex-wrap gap-3">
            {related.map((item) => (
              <li key={item.slug} className="max-w-full">
                <Link
                  href={`${FIRST_AID_BASE}/${item.slug}`}
                  className="btn btn-soft btn-md h-auto max-w-full whitespace-normal py-2 text-left"
                >
                  {item.title}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <footer className="flex flex-col gap-6 border-t border-line pt-8">
        <p className={`${TEXT} text-ink`}>{COPY.disclaimer}</p>
        <div className="flex flex-col gap-3 print:hidden">
          <p className={`${TEXT} text-ink-2`}>{COPY.printHint}</p>
          <PrintButton />
        </div>

        <Section id={ANCHORS.sources} title={COPY.sources}>
          <SourcesList guide={guide} />
          <p className={`${TEXT} text-ink-2`}>
            {isPublic && guide.review.reviewedOn
              ? `${COPY.reviewDone}: ${formatMkDate(guide.review.reviewedOn)}${guide.review.reviewer ? ` (${guide.review.reviewer})` : ""}. `
              : `${COPY.reviewDraft}. `}
            {COPY.updated}: {formatMkDate(guide.updated)}.
          </p>
        </Section>
      </footer>
    </article>
  );
}
