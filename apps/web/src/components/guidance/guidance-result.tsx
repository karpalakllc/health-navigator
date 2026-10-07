"use client";

import Link from "next/link";
import { useId, useState, type Ref } from "react";
import {
  CareLadder,
  careLevelForOutcome,
} from "@/components/guidance/care-ladder";
import { EmergencyCard } from "@/components/guidance/emergency-card";
import { HelpfulFeedback } from "@/components/feedback/helpful-feedback";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/field";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { GuidanceOutcomeV2, GuidanceState } from "@/lib/api/guidance-v2";
import { directoryLinks, type DirectoryLink } from "@/lib/guidance/care";
import {
  firstAidLinksForResult,
  guidanceFeedbackItem,
  urgentCareLinkFor,
} from "@/lib/guidance/next-steps";
import {
  LEVEL_KEYS,
  summaryText,
  type GuidanceSummary,
} from "@/lib/guidance/summary";
import type { FirstAidLink } from "@/content/first-aid";
import { t, tFormat } from "@/i18n/t";

type ResultState = Extract<GuidanceState, { stage: "result" }>;

const CITY_KEY = "guidance_city";

function linkLabel(link: DirectoryLink): string {
  switch (link.label.key) {
    case "specialty":
      return tFormat("guidance.linkSpecialty", {
        specialty: link.label.specialty,
      });
    case "doctors":
      return t("guidance.linkDoctors");
    case "emergencyDepartments":
      return t("guidance.linkEmergencyDepartments");
    case "hospitals":
      return t("guidance.linkHospitals");
    case "clinics":
      return t("guidance.linkClinics");
    case "laboratories":
      return t("guidance.linkLaboratories");
    case "pharmacies":
      return t("guidance.linkPharmacies");
  }
}

function List({ items }: { items: string[] }) {
  return (
    <ul className="flex list-disc flex-col gap-2 pl-6 type-reading text-ink marker:text-ink-2">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  );
}

export function GuidanceResult({
  result,
  summary,
  pharmaciesOn,
  headingRef,
  onRestart,
}: {
  result: ResultState;
  summary: GuidanceSummary;
  pharmaciesOn: boolean;
  headingRef: Ref<HTMLHeadingElement>;
  onRestart: () => void;
}) {
  // Results only ever render in the browser, after the visitor answered.
  const [city, setCity] = useState(() => {
    try {
      return window.sessionStorage.getItem(CITY_KEY) ?? "";
    } catch {
      return "";
    }
  });
  const primary = result.outcomes[0]?.outcome;

  if (!primary) {
    return null;
  }

  const feedbackItem = guidanceFeedbackItem(
    result.outcomes[0]?.flow?.key,
    primary.level,
  );
  const feedback = feedbackItem ? (
    <HelpfulFeedback item={feedbackItem} className="print:hidden" />
  ) : null;

  const restart = (
    <Button
      variant="secondary"
      leadingIcon="arrow-left"
      onClick={onRestart}
      className="self-start print:hidden"
    >
      {t("guidance.restart")}
    </Button>
  );

  if (primary.level === "emergency_now") {
    return (
      <div className="flex flex-col gap-6 lg:gap-8">
        <EmergencyOutcome
          outcome={primary}
          reason={result.reason}
          headingRef={headingRef}
          firstAid={firstAidLinksForResult(result.outcomes)}
          urgentCareHref={urgentCareLinkFor(primary, city)}
        />
        {feedback}
        {restart}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6 lg:gap-8">
      <div className="flex flex-col gap-6 lg:gap-8 print:hidden">
        <Card as="section" edge aria-labelledby="guidance-outcome-title">
          <div className="flex flex-col gap-4">
            <p className="type-meta font-semibold text-ink-2">
              {t("guidance.resultEyebrow")}
            </p>
            <h2
              id="guidance-outcome-title"
              ref={headingRef}
              tabIndex={-1}
              className="type-h1 text-ink"
            >
              {primary.title}
            </h2>
            <Tag tone="ink" className="self-start">
              {t(LEVEL_KEYS[primary.level])}
            </Tag>
            <p className="type-reading measure text-ink">{primary.summary}</p>
          </div>
        </Card>

        <OutcomeDetails outcome={primary} />

        <WhereToGo
          outcome={primary}
          pharmaciesOn={pharmaciesOn}
          city={city}
          onCityChange={setCity}
        />

        <CareLadder
          current={careLevelForOutcome(primary.level)}
          pharmaciesOn={pharmaciesOn}
        />

        {result.outcomes.length > 1 ? (
          <section
            aria-labelledby="guidance-per-symptom"
            className="flex flex-col gap-3"
          >
            <h2 id="guidance-per-symptom" className="type-h2 text-ink">
              {t("guidance.perSymptomTitle")}
            </h2>
            <ul className="flex flex-col gap-3">
              {result.outcomes.map(({ flow, outcome }) => (
                <li key={`${flow?.key ?? "global"}-${outcome.id}`}>
                  <details className="card p-5">
                    <summary className="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 type-body font-semibold text-ink">
                      <span>{flow?.title}</span>
                      <span className="font-normal text-ink-2">
                        {t(LEVEL_KEYS[outcome.level])}
                      </span>
                    </summary>
                    <div className="mt-4 flex flex-col gap-4">
                      <p className="type-reading font-semibold text-ink">
                        {outcome.title}
                      </p>
                      <p className="type-reading text-ink">{outcome.summary}</p>
                      <OutcomeDetails outcome={outcome} compact />
                    </div>
                  </details>
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <GuidanceSafetyNotice />
      </div>

      <SummarySection summary={summary} />

      {feedback}

      {restart}
    </div>
  );
}

function OutcomeDetails({
  outcome,
  compact = false,
}: {
  outcome: GuidanceOutcomeV2;
  compact?: boolean;
}) {
  const heading = compact ? "type-h3 text-ink" : "type-h2 text-ink";

  return (
    <div className="flex flex-col gap-6">
      {outcome.reasons.length > 0 ? (
        <section className="flex flex-col gap-3">
          <h3 className={heading}>{t("guidance.whyTitle")}</h3>
          <List items={outcome.reasons} />
        </section>
      ) : null}
      <section className="flex flex-col gap-3">
        <h3 className={heading}>{t("guidance.doNowTitle")}</h3>
        <List items={outcome.do_now} />
      </section>
      {outcome.watch_for.length > 0 ? (
        <section className="flex flex-col gap-3 rounded-card bg-chip-tint p-5">
          <h3 className={`flex items-center gap-2 ${heading}`}>
            <Icon name="alert-triangle" size={22} className="shrink-0" />
            {t("guidance.watchTitle")}
          </h3>
          <List items={outcome.watch_for} />
          <GuidanceSafetyNotice compact />
        </section>
      ) : null}
    </div>
  );
}

function WhereToGo({
  outcome,
  pharmaciesOn,
  city,
  onCityChange,
}: {
  outcome: GuidanceOutcomeV2;
  pharmaciesOn: boolean;
  city: string;
  onCityChange: (city: string) => void;
}) {
  const id = useId();
  const links = directoryLinks(outcome, city, { pharmaciesOn });
  // „Каде веднаш“ first for a same-day outcome (docs/urgent-care.md § 1).
  const urgent = urgentCareLinkFor(outcome, city);

  if (links.length === 0 && urgent === null) {
    return null;
  }

  return (
    <section aria-labelledby={`${id}-where`} className="flex flex-col gap-4">
      <h2 id={`${id}-where`} className="type-h2 text-ink">
        {t("guidance.whereTitle")}
      </h2>
      <div className="sm:max-w-sm">
        <Input
          label={t("guidance.cityLabel")}
          hint={t("guidance.cityHint")}
          autoComplete="address-level2"
          value={city}
          onChange={(e) => {
            onCityChange(e.target.value);
            try {
              window.sessionStorage.setItem(CITY_KEY, e.target.value);
            } catch {
              // Not remembered; the links still use the typed city.
            }
          }}
        />
      </div>
      <ul className="flex flex-wrap gap-3">
        {urgent ? (
          <li>
            <Button
              href={urgent}
              variant="primary"
              trailingIcon="arrow-right"
              data-guidance-link="urgent-care"
            >
              {t("guidance.linkUrgentCare")}
            </Button>
          </li>
        ) : null}
        {links.map((link, index) => (
          <li key={link.id}>
            <Button
              href={link.href}
              variant={index === 0 && !urgent ? "primary" : "soft"}
              trailingIcon="arrow-right"
              data-guidance-link={link.id}
            >
              {linkLabel(link)}
            </Button>
          </li>
        ))}
      </ul>
    </section>
  );
}

function EmergencyOutcome({
  outcome,
  reason,
  headingRef,
  firstAid,
  urgentCareHref,
}: {
  outcome: GuidanceOutcomeV2;
  reason: ResultState["reason"];
  headingRef: Ref<HTMLHeadingElement>;
  firstAid: FirstAidLink[];
  urgentCareHref: string | null;
}) {
  const body =
    reason === "shortcut"
      ? t("guidance.emergencyShortcutBody")
      : reason === "fallback"
        ? t("guidance.fallbackNote")
        : outcome.summary;
  const extra = outcome.call.filter(
    (line) => line.number !== "194" && line.number !== "112",
  );

  return (
    <EmergencyCard
      title={outcome.title}
      body={body}
      headingRef={headingRef}
      extraCalls={extra}
    >
      {outcome.crisis ? (
        <p className="type-reading text-ink">{t("guidance.crisisTitleNote")}</p>
      ) : null}
      {outcome.do_now.length > 0 ? (
        <section className="flex flex-col gap-3">
          <h3 className="type-h3 text-ink">{t("guidance.waitingTitle")}</h3>
          <List items={outcome.do_now} />
        </section>
      ) : null}
      {firstAid.length > 0 ? (
        <section className="flex flex-col gap-3">
          <h3 className="type-h3 text-ink">{t("guidance.firstAidTitle")}</h3>
          <ul className="flex flex-col gap-2">
            {firstAid.map((link) => (
              <li key={link.slug}>
                <Link
                  href={link.href}
                  className="type-reading font-semibold text-ink underline underline-offset-4"
                  data-guidance-link={`first-aid-${link.slug}`}
                >
                  {link.title}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
      {urgentCareHref ? (
        <Button
          href={urgentCareHref}
          variant="soft"
          trailingIcon="arrow-right"
          className="self-start print:hidden"
          data-guidance-link="urgent-care"
        >
          {t("guidance.linkUrgentCareEmergency")}
        </Button>
      ) : null}
    </EmergencyCard>
  );
}

function SummarySection({ summary }: { summary: GuidanceSummary }) {
  const [copied, setCopied] = useState(false);
  const canShare = typeof navigator !== "undefined" && "share" in navigator;

  const text = summaryText(summary);

  async function share() {
    try {
      await navigator.share({ title: t("guidance.summaryTitle"), text });
    } catch {
      // Cancelled by the visitor, or not allowed: nothing to do.
    }
  }

  async function copy() {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  }

  return (
    <section
      data-guidance-summary
      aria-labelledby="guidance-summary-title"
      className="card flex flex-col gap-4 p-5 lg:p-8 print:p-0 print:shadow-none"
    >
      <div className="flex flex-col gap-1">
        <h2 id="guidance-summary-title" className="type-h2 text-ink">
          {t("guidance.summaryTitle")}
        </h2>
        <p className="type-meta text-ink-2 print:hidden">
          {t("guidance.summaryLead")}
        </p>
      </div>
      <dl className="grid gap-x-6 gap-y-2 type-body text-ink sm:grid-cols-[auto_minmax(0,1fr)]">
        <dt className="font-semibold">{t("guidance.summaryDate")}</dt>
        <dd>{summary.date}</dd>
        <dt className="font-semibold">{t("guidance.summaryWho")}</dt>
        <dd>{summary.who}</dd>
        {summary.symptoms.length > 0 ? (
          <>
            <dt className="font-semibold">{t("guidance.summarySymptoms")}</dt>
            <dd>{summary.symptoms.join(", ")}</dd>
          </>
        ) : null}
        <dt className="font-semibold">{t("guidance.summaryRedFlags")}</dt>
        <dd>{summary.redFlags}</dd>
      </dl>
      {summary.answers.length > 0 ? (
        <div className="flex flex-col gap-2">
          <h3 className="type-h3 text-ink">{t("guidance.summaryAnswers")}</h3>
          <ul className="flex flex-col gap-1 type-body text-ink">
            {summary.answers.map((a) => (
              <li key={a.id}>
                {a.question} — <strong>{a.answer}</strong>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      <div className="flex flex-col gap-2">
        <h3 className="type-h3 text-ink">{t("guidance.summaryResult")}</h3>
        <ul className="flex flex-col gap-1 type-body text-ink">
          {summary.result.map((r) => (
            <li key={`${r.symptom}-${r.title}`}>
              {r.symptom ? `${r.symptom}: ` : ""}
              <strong>{r.title}</strong> ({r.level})
            </li>
          ))}
        </ul>
      </div>
      <p className="type-meta text-ink-2">{t("guidance.summaryDisclaimer")}</p>
      <div className="flex flex-wrap gap-3 print:hidden">
        <Button leadingIcon="file-text" onClick={() => window.print()}>
          {t("guidance.printSummary")}
        </Button>
        {canShare ? (
          <Button variant="soft" leadingIcon="send" onClick={share}>
            {t("guidance.shareSummary")}
          </Button>
        ) : null}
        <Button variant="soft" leadingIcon="file-text" onClick={copy}>
          {t("guidance.copySummary")}
        </Button>
      </div>
      <p role="status" className="type-meta text-ink-2 print:hidden">
        {copied ? t("guidance.copied") : ""}
      </p>
    </section>
  );
}
