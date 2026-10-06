"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";
import {
  CareLadder,
  LADDER_HREFS,
  careLevelForOutcome,
} from "@/components/guidance/care-ladder";
import { ChoiceCard } from "@/components/guidance/choice-card";
import { EmergencyCard } from "@/components/guidance/emergency-card";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { NoticeTelLink } from "@/components/ui/notice";
import {
  completeGuidanceEmergency,
  completeGuidanceSession,
  isStaleGuidanceSession,
  saveGuidanceAnswers,
  parseStoredGuidanceSession,
  startGuidanceSession,
  type GuidanceFlow,
  type GuidanceSessionHandle,
  type GuidanceOutcome,
} from "@/lib/api/guidance";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

// Holds {id, token}; the token is what lets this tab continue the session.
const SESSION_KEY = "guidance_session";
// Pre-token builds stored the bare id here; it can no longer continue anything.
const LEGACY_SESSION_KEY = "guidance_session_id";

const stepHeadingClass = "type-h2 text-ink";

type Phase = "intro" | "red_flags" | "questions" | "result" | "emergency";

type Props = {
  flow: GuidanceFlow;
  /** Whether the pharmacy directory is on, so the care ladder may link to it. */
  pharmaciesOn?: boolean;
};

export function GuidanceWizard({ flow, pharmaciesOn = false }: Props) {
  const [phase, setPhase] = useState<Phase>("intro");
  const [session, setSession] = useState<GuidanceSessionHandle | null>(null);
  const [accepted, setAccepted] = useState(false);
  const [redFlags, setRedFlags] = useState<string[]>([]);
  const [stepIndex, setStepIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<string, string[]>>({});
  const [outcome, setOutcome] = useState<GuidanceOutcome | null>(null);
  const [error, setError] = useState<string | null>(null);
  // `loading` is the ordinary flow's pending save; the emergency path has its
  // own flag so neither can switch the other's indicator off.
  const [loading, setLoading] = useState(false);
  const [emergencySaving, setEmergencySaving] = useState(false);
  // True when the visitor pressed „Потребна ми е итна помош“ rather than
  // reaching the emergency outcome through answers: the outcome's own body
  // („Според вашите одговори…“) would then be wrong, so a neutral one shows.
  const [emergencyFromShortcut, setEmergencyFromShortcut] = useState(false);

  const currentStep = flow.steps[stepIndex];
  const optionIdPrefix = useId();

  /*
   * Bumped whenever the emergency path takes over (and on a restart). A
   * request started before that checks it when it settles and drops its
   * result, so a late ordinary answer — the next question, a normal outcome
   * or a save error — can never replace the emergency screen.
   */
  const generation = useRef(0);

  // Each new step replaces the panel in place; without this the viewport
  // stayed where the previous step's button was (often the footer) and focus
  // was lost on the removed button. The heading takes focus and is scrolled
  // to, so both sighted and screen-reader users start at the new question.
  const headingRef = useRef<HTMLHeadingElement>(null);
  const firstRender = useRef(true);

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;

      return;
    }

    const heading = headingRef.current;
    // Scroll to the top of the step, not the heading: Back, the emergency
    // shortcut and the emergency card's „ИТНО“ band sit above the heading.
    const step = heading?.closest("[data-guidance-step]") ?? heading;
    step?.scrollIntoView?.({ block: "start" });
    heading?.focus({ preventScroll: true });
    // `outcome` too: on the emergency path the call links render before the
    // API answers, and the outcome's heading then replaces the interim one.
  }, [phase, stepIndex, outcome]);

  const ensureSession = useCallback(
    async (fresh = false): Promise<GuidanceSessionHandle> => {
      if (session && !fresh) {
        return session;
      }

      if (fresh) {
        window.sessionStorage.removeItem(SESSION_KEY);
      }

      const stored =
        typeof window !== "undefined" && !fresh
          ? parseStoredGuidanceSession(
              window.sessionStorage.getItem(SESSION_KEY),
            )
          : null;

      if (stored) {
        setSession(stored);

        return stored;
      }

      window.sessionStorage.removeItem(LEGACY_SESSION_KEY);
      const started = await startGuidanceSession();
      window.sessionStorage.setItem(SESSION_KEY, JSON.stringify(started));
      setSession(started);

      return started;
    },
    [session],
  );

  /**
   * Runs an emergency-path call against the session, and once more against a
   * new session if the stored handle turned out to be stale (expired,
   * completed, or from another deploy) — rather than leaving someone who
   * asked for emergency help looking at an error.
   */
  async function withFreshSessionOnStale<T>(
    call: (current: GuidanceSessionHandle) => Promise<T>,
  ): Promise<T> {
    try {
      return await call(await ensureSession());
    } catch (e) {
      if (!isStaleGuidanceSession(e)) {
        throw e;
      }

      return call(await ensureSession(true));
    }
  }

  /**
   * The emergency phase renders the tap-to-call links straight away, before
   * and regardless of the API: recording the outcome is bookkeeping, and a
   * failed request (the per-address session cap on shared wifi, a stale
   * handle, no connection) must never stand between the visitor and 194/112.
   */
  async function enterEmergency(
    record: (current: GuidanceSessionHandle) => Promise<GuidanceOutcome>,
  ) {
    const gen = ++generation.current;

    setError(null);
    setOutcome(null);
    setPhase("emergency");
    setLoading(false);
    setEmergencySaving(true);

    try {
      const result = await withFreshSessionOnStale(record);

      if (generation.current !== gen) {
        return;
      }

      setOutcome(result);
      window.sessionStorage.removeItem(SESSION_KEY);
    } catch (e) {
      if (generation.current === gen) {
        setError(e instanceof Error ? e.message : t("guidance.emergencyError"));
      }
    } finally {
      if (generation.current === gen) {
        setEmergencySaving(false);
      }
    }
  }

  /**
   * Runs one step of the ordinary flow. `apply` only runs, and errors only
   * show, while nothing newer (the emergency path, a restart) has taken over.
   */
  async function runStep(
    work: () => Promise<(() => void) | void>,
    fallbackError: string,
  ) {
    const gen = generation.current;

    setError(null);
    setLoading(true);

    try {
      const apply = await work();

      if (generation.current === gen && apply) {
        apply();
      }
    } catch (e) {
      if (generation.current === gen) {
        setError(e instanceof Error ? e.message : fallbackError);
      }
    } finally {
      if (generation.current === gen) {
        setLoading(false);
      }
    }
  }

  async function handleStart() {
    if (!accepted) {
      setError(t("guidance.confirmRequired"));

      return;
    }

    await runStep(async () => {
      await ensureSession();

      return () => setPhase("red_flags");
    }, t("guidance.sessionError"));
  }

  async function handleRedFlagsContinue() {
    if (redFlags.length > 0) {
      setEmergencyFromShortcut(false);
      await enterEmergency(async (current) => {
        await saveGuidanceAnswers(current, [
          { step_key: "red_flags", values: redFlags },
        ]);

        return completeGuidanceSession(current);
      });

      return;
    }

    await runStep(async () => {
      const current = await ensureSession();

      await saveGuidanceAnswers(current, [
        { step_key: "red_flags", values: [] },
      ]);

      return () => setPhase("questions");
    }, t("guidance.saveError"));
  }

  async function handleEmergencyNow() {
    setEmergencyFromShortcut(true);
    await enterEmergency(completeGuidanceEmergency);
  }

  function handleRestart() {
    generation.current++;
    window.sessionStorage.removeItem(SESSION_KEY);
    setSession(null);
    setAccepted(false);
    setRedFlags([]);
    setStepIndex(0);
    setAnswers({});
    setOutcome(null);
    setError(null);
    setLoading(false);
    setEmergencySaving(false);
    setEmergencyFromShortcut(false);
    setPhase("intro");
  }

  function selectOption(stepKey: string, value: string, multi: boolean) {
    setAnswers((prev) => {
      if (multi) {
        const current = prev[stepKey] ?? [];
        const next = current.includes(value)
          ? current.filter((v) => v !== value)
          : [...current, value];

        return { ...prev, [stepKey]: next };
      }

      return { ...prev, [stepKey]: [value] };
    });
  }

  function handleRedFlagsBack() {
    setError(null);
    setPhase("intro");
  }

  function handleQuestionBack() {
    setError(null);

    if (stepIndex > 0) {
      setStepIndex((i) => i - 1);

      return;
    }

    setPhase("red_flags");
  }

  async function handleQuestionNext() {
    if (!currentStep) {
      return;
    }

    const selected = answers[currentStep.key] ?? [];

    if (currentStep.required && selected.length === 0) {
      setError(t("guidance.selectOption"));

      return;
    }

    await runStep(async () => {
      const current = await ensureSession();
      await saveGuidanceAnswers(current, [
        { step_key: currentStep.key, values: selected },
      ]);

      if (stepIndex < flow.steps.length - 1) {
        return () => setStepIndex((i) => i + 1);
      }

      const result = await completeGuidanceSession(current);

      return () => {
        setOutcome(result);
        setPhase("result");
        window.sessionStorage.removeItem(SESSION_KEY);
      };
    }, t("guidance.saveError"));
  }

  // On the intro the h1 is the step heading too, so a restart lands on it.
  const pageTitle =
    phase === "intro" ? (
      <h1 ref={headingRef} tabIndex={-1} className="type-h1 text-ink">
        {t("guidance.title")}
      </h1>
    ) : (
      <h1 className="sr-only">{t("guidance.title")}</h1>
    );

  if (phase === "intro") {
    return (
      <div data-guidance-step className="flex flex-col gap-6 lg:gap-8">
        <section className="flex flex-col gap-3 rounded-sheet bg-apricot px-5 py-6 lg:gap-4 lg:px-10 lg:py-10">
          <div className="flex items-center gap-4">
            <span
              aria-hidden="true"
              className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-ink lg:size-14"
            >
              <Icon name="compass" size={26} />
            </span>
            {pageTitle}
          </div>
          {/* The flow's own intro when staff wrote one, else the page's. */}
          <p className="type-reading measure text-ink">
            {flow.intro_body ?? t("guidance.description")}
          </p>
          <p className="flex items-center gap-2 type-meta text-ink-2">
            <Icon name="shield-check" size={20} className="shrink-0" />
            {t("guidance.introAnonymous")}
          </p>
        </section>

        <Card as="section" aria-labelledby="guidance-red-flags-reminder">
          <div className="flex flex-col gap-4">
            <h2
              id="guidance-red-flags-reminder"
              className="flex items-center gap-3 type-h3 text-ink"
            >
              <Icon name="alert-triangle" size={24} className="shrink-0" />
              {t("guidance.redFlagsReminderTitle")}
            </h2>
            <p className="type-reading text-ink">
              {t("guidance.redFlagsReminderLead")}{" "}
              <NoticeTelLink number="194" /> {t("guidance.redFlagsReminderOr")}{" "}
              <NoticeTelLink number="112" />{" "}
              {t("guidance.redFlagsReminderTail")}
            </p>
            {flow.red_flags.length > 0 ? (
              <ul className="flex list-disc flex-col gap-1 pl-6 type-reading text-ink marker:text-ink-2">
                {flow.red_flags.map((flag) => (
                  <li key={flag.code}>{flag.label}</li>
                ))}
              </ul>
            ) : null}
            <EmergencyShortcutButton
              onEmergency={handleEmergencyNow}
              className="w-full lg:w-auto lg:self-start"
            />
          </div>
        </Card>

        <GuidanceSafetyNotice />

        <div className="flex flex-col gap-3">
          <Card padding="md">
            <Checkbox
              label={t("guidance.acceptLabel")}
              checked={accepted}
              onChange={(e) => setAccepted(e.target.checked)}
            />
          </Card>
          {error ? <FormError>{error}</FormError> : null}
        </div>

        <div>
          <Button
            size="lg"
            fullWidth
            loading={loading}
            onClick={handleStart}
            trailingIcon={loading ? undefined : "arrow-right"}
            className="lg:w-auto"
          >
            {loading ? t("guidance.starting") : t("guidance.continue")}
          </Button>
        </div>
      </div>
    );
  }

  if (phase === "red_flags") {
    return (
      <div data-guidance-step className="flex flex-col gap-6">
        {pageTitle}
        <StepTopBar
          onBack={handleRedFlagsBack}
          backDisabled={loading}
          onEmergency={handleEmergencyNow}
        />
        <div className="flex flex-col gap-2">
          <h2
            id="guidance-step-heading"
            ref={headingRef}
            tabIndex={-1}
            className={stepHeadingClass}
          >
            {t("guidance.safetyCheck")}
          </h2>
          <p className="type-reading text-ink-2">
            {t("guidance.redFlagIntro")}
          </p>
        </div>
        <fieldset aria-labelledby="guidance-step-heading" className="min-w-0">
          <ul className="flex flex-col gap-3">
            {flow.red_flags.map((flag) => (
              <li key={flag.code}>
                <ChoiceCard
                  type="checkbox"
                  id={`${optionIdPrefix}-flag-${flag.code}`}
                  value={flag.code}
                  checked={redFlags.includes(flag.code)}
                  onChange={() => {
                    setRedFlags((prev) =>
                      prev.includes(flag.code)
                        ? prev.filter((c) => c !== flag.code)
                        : [...prev, flag.code],
                    );
                  }}
                  label={flag.label}
                />
              </li>
            ))}
          </ul>
        </fieldset>
        {error ? <FormError>{error}</FormError> : null}
        <StickyContinue>
          <Button
            size="lg"
            fullWidth
            loading={loading}
            onClick={handleRedFlagsContinue}
            trailingIcon={loading ? undefined : "arrow-right"}
            className="lg:w-auto"
          >
            {loading ? t("guidance.saving") : t("guidance.continue")}
          </Button>
        </StickyContinue>
      </div>
    );
  }

  if (phase === "emergency" && !outcome) {
    return (
      <div data-guidance-step className="flex flex-col gap-6">
        {pageTitle}
        <EmergencyCard
          title={t("guidance.emergencyInterimTitle")}
          body={t("guidance.emergencyDelay")}
          headingRef={headingRef}
        >
          {emergencySaving ? (
            <p className="type-meta text-ink-2" role="status">
              {t("guidance.saving")}
            </p>
          ) : null}
        </EmergencyCard>
        {error ? <FormError>{error}</FormError> : null}
      </div>
    );
  }

  if ((phase === "result" || phase === "emergency") && outcome) {
    const isEmergency =
      phase === "emergency" || outcome.outcome_code === "emergency";

    return (
      <div data-guidance-step className="flex flex-col gap-6 lg:gap-8">
        {pageTitle}
        {isEmergency ? (
          <EmergencyOutcome
            outcome={outcome}
            fromShortcut={phase === "emergency" && emergencyFromShortcut}
            headingRef={headingRef}
          />
        ) : (
          <OutcomeView
            outcome={outcome}
            pharmaciesOn={pharmaciesOn}
            headingRef={headingRef}
          />
        )}
        {isEmergency ? null : <GuidanceSafetyNotice />}
        <Button
          variant="secondary"
          leadingIcon="arrow-left"
          onClick={handleRestart}
          className="self-start"
        >
          {t("guidance.restart")}
        </Button>
      </div>
    );
  }

  if (phase === "questions" && currentStep) {
    const selected = answers[currentStep.key] ?? [];
    const multi = currentStep.type === "multi_select";
    const progress = ((stepIndex + 1) / flow.steps.length) * 100;
    const isLast = stepIndex >= flow.steps.length - 1;

    return (
      <div data-guidance-step className="flex flex-col gap-6">
        {pageTitle}
        <StepTopBar
          onBack={handleQuestionBack}
          backDisabled={loading}
          onEmergency={handleEmergencyNow}
        />
        <div className="flex flex-col gap-3">
          <p className="type-meta font-semibold text-ink-2">
            {t("guidance.step")} {stepIndex + 1} {t("pagination.of")}{" "}
            {flow.steps.length}
          </p>
          <div
            aria-hidden="true"
            className="h-1.5 w-full overflow-hidden rounded-full bg-sand"
          >
            <div
              className="h-full rounded-full bg-ink"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
        <h2
          id="guidance-step-heading"
          ref={headingRef}
          tabIndex={-1}
          className={stepHeadingClass}
        >
          {currentStep.label}
        </h2>
        <fieldset aria-labelledby="guidance-step-heading" className="min-w-0">
          <ul className="flex flex-col gap-3">
            {currentStep.options.map((option) => (
              <li key={option.value}>
                <ChoiceCard
                  type={multi ? "checkbox" : "radio"}
                  name={currentStep.key}
                  id={`${optionIdPrefix}-${currentStep.key}-${option.value}`}
                  value={option.value}
                  checked={selected.includes(option.value)}
                  onChange={() =>
                    selectOption(currentStep.key, option.value, multi)
                  }
                  label={option.label}
                />
              </li>
            ))}
          </ul>
        </fieldset>
        {error ? <FormError>{error}</FormError> : null}
        <GuidanceSafetyNotice compact />
        <StickyContinue>
          <Button
            size="lg"
            fullWidth
            loading={loading}
            onClick={handleQuestionNext}
            trailingIcon={loading ? undefined : "arrow-right"}
            className="lg:w-auto"
          >
            {loading
              ? t("guidance.saving")
              : isLast
                ? t("guidance.seeGuidance")
                : t("guidance.continue")}
          </Button>
        </StickyContinue>
      </div>
    );
  }

  return null;
}

/**
 * The step's main action. On a phone it stays in the thumb zone, just above
 * the bottom tab bar (--tabbar-space), on a cream strip so the options scroll
 * under it; on desktop it sits in the flow.
 */
function StickyContinue({ children }: { children: React.ReactNode }) {
  return (
    <div
      data-sticky-action-bar
      className="sticky bottom-[var(--tabbar-space)] z-10 -mx-5 bg-cream px-5 py-3 shadow-[0_-1px_0_var(--color-line)] lg:static lg:mx-0 lg:bg-transparent lg:p-0 lg:shadow-none"
    >
      {children}
    </div>
  );
}

/** Back (top left) and the emergency shortcut, on every step. */
function StepTopBar({
  onBack,
  backDisabled,
  onEmergency,
}: {
  onBack: () => void;
  backDisabled: boolean;
  onEmergency: () => void;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <Button
        variant="soft"
        leadingIcon="chevron-left"
        disabled={backDisabled}
        onClick={onBack}
        className="pl-3"
      >
        {t("common.back")}
      </Button>
      <EmergencyShortcutButton onEmergency={onEmergency} size="md" />
    </div>
  );
}

/**
 * „Потребна ми е итна помош“, in the emergency treatment (red, white text,
 * 2px ink frame) on the intro and on every step. Full-width 56px on the intro;
 * a compact 48px pill beside Back on the steps, so the steps stay calm while
 * the button stays in view. It is never disabled — not even while an ordinary
 * answer is saving; the wizard's generation guard keeps that late answer from
 * replacing the emergency screen.
 */
function EmergencyShortcutButton({
  onEmergency,
  size = "lg",
  className,
}: {
  onEmergency: () => void;
  size?: "md" | "lg";
  className?: string;
}) {
  return (
    <Button
      size={size}
      leadingIcon="alert-triangle"
      onClick={onEmergency}
      className={cn(
        "border-2 border-ink bg-emergency text-white hover:bg-emergency-hover",
        size === "md" && "px-4",
        className,
      )}
    >
      {t("guidance.emergencyNow")}
    </Button>
  );
}

function EmergencyOutcome({
  outcome,
  fromShortcut,
  headingRef,
}: {
  outcome: GuidanceOutcome;
  /** Reached through the urgent-help button, not through answers. */
  fromShortcut: boolean;
  headingRef: React.Ref<HTMLHeadingElement>;
}) {
  return (
    <EmergencyCard
      title={outcome.title}
      body={fromShortcut ? t("guidance.emergencyShortcutBody") : outcome.body}
      headingRef={headingRef}
    >
      <p className="type-reading text-ink">
        {t("guidance.emergencyResultNote")} <strong>194</strong> /{" "}
        <strong>112</strong>.
      </p>
    </EmergencyCard>
  );
}

function OutcomeView({
  outcome,
  pharmaciesOn,
  headingRef,
}: {
  outcome: GuidanceOutcome;
  pharmaciesOn: boolean;
  headingRef: React.Ref<HTMLHeadingElement>;
}) {
  // The ladder already links to the directory and lists 194/112; handoffs
  // repeating those are left out.
  const handoffs = outcome.handoffs.filter(
    (handoff) =>
      handoff.type !== "emergency" &&
      !(LADDER_HREFS as readonly string[]).includes(handoffHref(handoff)),
  );

  return (
    <>
      <Card as="section" edge aria-labelledby="guidance-outcome-title">
        <div className="flex flex-col gap-3">
          <p className="type-meta font-semibold text-ink-2">
            {t("guidance.resultEyebrow")}
          </p>
          <h2
            id="guidance-outcome-title"
            ref={headingRef}
            tabIndex={-1}
            className="type-h1 text-ink"
          >
            {outcome.title}
          </h2>
          <p className="type-reading measure text-ink">{outcome.body}</p>
        </div>
      </Card>
      <CareLadder
        current={careLevelForOutcome(outcome.outcome_code)}
        pharmaciesOn={pharmaciesOn}
      />
      {handoffs.length > 0 ? (
        <section
          aria-labelledby="guidance-more-links"
          className="flex flex-col gap-3"
        >
          <h2 id="guidance-more-links" className="type-h3 text-ink">
            {t("guidance.moreLinks")}
          </h2>
          <ul className="flex flex-wrap gap-3">
            {handoffs.map((handoff) => (
              <li key={`${handoff.type}-${handoff.label}`}>
                <Button
                  href={handoffHref(handoff)}
                  variant="soft"
                  trailingIcon="arrow-right"
                >
                  {handoffLabel(handoff)}
                </Button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </>
  );
}

type Handoff = GuidanceOutcome["handoffs"][number];

function handoffHref(handoff: Handoff): string {
  return (
    handoff.href ??
    (handoff.type === "doctors"
      ? "/doctors"
      : handoff.type === "facilities"
        ? "/facilities"
        : "/")
  );
}

function handoffLabel(handoff: Handoff): string {
  return (
    handoff.label ??
    (handoff.type === "doctors"
      ? t("nav.doctors")
      : handoff.type === "facilities"
        ? t("nav.facilities")
        : t("common.home"))
  );
}
