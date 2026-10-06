"use client";

import Link from "next/link";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
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
import { FormError } from "@/components/ui/form-message";

// Holds {id, token}; the token is what lets this tab continue the session.
const SESSION_KEY = "guidance_session";
// Pre-token builds stored the bare id here; it can no longer continue anything.
const LEGACY_SESSION_KEY = "guidance_session_id";

const emergencyButtonClass =
  "border-destructive/40 text-destructive hover:bg-destructive/5";

const stepHeadingClass =
  "scroll-mt-24 text-lg font-semibold text-foreground focus:outline-none";

type Phase = "intro" | "red_flags" | "questions" | "result" | "emergency";

type Props = {
  flow: GuidanceFlow;
};

export function GuidanceWizard({ flow }: Props) {
  const [phase, setPhase] = useState<Phase>("intro");
  const [session, setSession] = useState<GuidanceSessionHandle | null>(null);
  const [accepted, setAccepted] = useState(false);
  const [redFlags, setRedFlags] = useState<string[]>([]);
  const [stepIndex, setStepIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<string, string[]>>({});
  const [outcome, setOutcome] = useState<GuidanceOutcome | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const currentStep = flow.steps[stepIndex];
  const optionIdPrefix = useId();

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
    heading?.scrollIntoView?.({ block: "start" });
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
    setError(null);
    setOutcome(null);
    setPhase("emergency");
    setLoading(true);

    try {
      setOutcome(await withFreshSessionOnStale(record));
      window.sessionStorage.removeItem(SESSION_KEY);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("guidance.emergencyError"));
    } finally {
      setLoading(false);
    }
  }

  async function handleStart() {
    if (!accepted) {
      setError(t("guidance.confirmRequired"));

      return;
    }

    setError(null);
    setLoading(true);

    try {
      await ensureSession();
      setPhase("red_flags");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("guidance.sessionError"));
    } finally {
      setLoading(false);
    }
  }

  async function handleRedFlagsContinue() {
    if (redFlags.length > 0) {
      await enterEmergency(async (current) => {
        await saveGuidanceAnswers(current, [
          { step_key: "red_flags", values: redFlags },
        ]);

        return completeGuidanceSession(current);
      });

      return;
    }

    setLoading(true);
    setError(null);

    try {
      const current = await ensureSession();

      await saveGuidanceAnswers(current, [
        { step_key: "red_flags", values: [] },
      ]);
      setPhase("questions");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("guidance.saveError"));
    } finally {
      setLoading(false);
    }
  }

  async function handleEmergencyNow() {
    await enterEmergency(completeGuidanceEmergency);
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

    setError(null);
    setLoading(true);

    try {
      const current = await ensureSession();
      await saveGuidanceAnswers(current, [
        { step_key: currentStep.key, values: selected },
      ]);

      if (stepIndex < flow.steps.length - 1) {
        setStepIndex((i) => i + 1);

        return;
      }

      const result = await completeGuidanceSession(current);
      setOutcome(result);
      setPhase("result");
      window.sessionStorage.removeItem(SESSION_KEY);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("guidance.saveError"));
    } finally {
      setLoading(false);
    }
  }

  if (phase === "intro") {
    return (
      <div className="flex flex-col gap-6">
        <GuidanceSafetyNotice />
        {flow.intro_body ? (
          <p className="text-sm text-muted-foreground">{flow.intro_body}</p>
        ) : null}
        <label className="flex items-start gap-3 rounded-xl border border-border bg-card p-4 text-sm">
          <input
            type="checkbox"
            checked={accepted}
            onChange={(e) => setAccepted(e.target.checked)}
            className="mt-1"
          />
          <span>{t("guidance.acceptLabel")}</span>
        </label>
        {error ? <FormError>{error}</FormError> : null}
        <div className="flex flex-wrap gap-3">
          <Button type="button" disabled={loading} onClick={handleStart}>
            {loading ? t("guidance.starting") : t("guidance.continue")}
          </Button>
          <Button
            type="button"
            variant="outline"
            disabled={loading}
            onClick={handleEmergencyNow}
            className={emergencyButtonClass}
          >
            {t("guidance.emergencyNow")}
          </Button>
        </div>
      </div>
    );
  }

  if (phase === "red_flags") {
    return (
      <div className="flex flex-col gap-6">
        <GuidanceSafetyNotice compact />
        <h2 ref={headingRef} tabIndex={-1} className={stepHeadingClass}>
          {t("guidance.safetyCheck")}
        </h2>
        <p className="text-sm text-muted-foreground">
          {t("guidance.redFlagIntro")}
        </p>
        <ul className="space-y-2">
          {flow.red_flags.map((flag) => (
            <li key={flag.code}>
              <label
                className={cn(
                  "flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm transition",
                  redFlags.includes(flag.code)
                    ? "border-destructive/50 bg-destructive/5"
                    : "border-border bg-card hover:border-primary/30",
                )}
              >
                <input
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
                  className="mt-0.5"
                />
                <span>{flag.label}</span>
              </label>
            </li>
          ))}
        </ul>
        {error ? <FormError>{error}</FormError> : null}
        <div className="flex flex-wrap gap-3">
          <Button
            type="button"
            disabled={loading}
            onClick={handleRedFlagsContinue}
          >
            {loading ? t("guidance.saving") : t("guidance.continue")}
          </Button>
          <EmergencyShortcutButton
            loading={loading}
            onEmergency={handleEmergencyNow}
          />
        </div>
      </div>
    );
  }

  if (phase === "emergency" && !outcome) {
    return (
      <EmergencyInterimView
        loading={loading}
        error={error}
        headingRef={headingRef}
      />
    );
  }

  if ((phase === "result" || phase === "emergency") && outcome) {
    return (
      <OutcomeView
        outcome={outcome}
        isEmergency={
          phase === "emergency" || outcome.outcome_code === "emergency"
        }
        headingRef={headingRef}
      />
    );
  }

  if (phase === "questions" && currentStep) {
    const selected = answers[currentStep.key] ?? [];
    const multi = currentStep.type === "multi_select";
    const progress = ((stepIndex + 1) / flow.steps.length) * 100;

    return (
      <div className="flex flex-col gap-6">
        <GuidanceSafetyNotice compact />
        <div className="space-y-2">
          <p className="text-xs text-muted-foreground">
            {t("guidance.step")} {stepIndex + 1} {t("pagination.of")}{" "}
            {flow.steps.length}
          </p>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-full bg-primary transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
        <h2 ref={headingRef} tabIndex={-1} className={stepHeadingClass}>
          {currentStep.label}
        </h2>
        <ul className="space-y-2">
          {currentStep.options.map((option) => {
            const isSelected = selected.includes(option.value);

            return (
              <li key={option.value}>
                <label
                  className={cn(
                    "flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm transition",
                    isSelected
                      ? "border-primary bg-primary/5"
                      : "border-border bg-card hover:border-primary/30",
                  )}
                >
                  <input
                    type={multi ? "checkbox" : "radio"}
                    name={currentStep.key}
                    id={`${optionIdPrefix}-${currentStep.key}-${option.value}`}
                    value={option.value}
                    checked={isSelected}
                    onChange={() =>
                      selectOption(currentStep.key, option.value, multi)
                    }
                    className="mt-0.5"
                  />
                  <span>{option.label}</span>
                </label>
              </li>
            );
          })}
        </ul>
        {error ? <FormError>{error}</FormError> : null}
        <div className="flex flex-wrap gap-3">
          <Button
            type="button"
            variant="outline"
            disabled={loading}
            onClick={handleQuestionBack}
          >
            {t("common.back")}
          </Button>
          <Button type="button" disabled={loading} onClick={handleQuestionNext}>
            {loading
              ? t("guidance.saving")
              : stepIndex < flow.steps.length - 1
                ? t("common.next")
                : t("guidance.seeGuidance")}
          </Button>
          <EmergencyShortcutButton
            loading={loading}
            onEmergency={handleEmergencyNow}
          />
        </div>
      </div>
    );
  }

  return null;
}

function EmergencyShortcutButton({
  loading,
  onEmergency,
}: {
  loading: boolean;
  onEmergency: () => void;
}) {
  return (
    <Button
      type="button"
      variant="outline"
      disabled={loading}
      onClick={onEmergency}
      className={emergencyButtonClass}
    >
      {t("guidance.emergencyNow")}
    </Button>
  );
}

/**
 * What the emergency path shows until (or unless) the API returns its
 * outcome: the call links first, then the reason to use them.
 */
function EmergencyInterimView({
  loading,
  error,
  headingRef,
}: {
  loading: boolean;
  error: string | null;
  headingRef: React.Ref<HTMLHeadingElement>;
}) {
  return (
    <div className="flex flex-col gap-6">
      <Card className="space-y-3 p-5">
        <h2 ref={headingRef} tabIndex={-1} className={stepHeadingClass}>
          {t("guidance.emergencyInterimTitle")}
        </h2>
        <p className="text-sm text-muted-foreground">
          {t("guidance.emergencyDelay")}
        </p>
        <EmergencyCallLinks />
      </Card>
      {loading ? (
        <p className="text-xs text-muted-foreground" role="status">
          {t("guidance.saving")}
        </p>
      ) : null}
      {error ? <FormError>{error}</FormError> : null}
      <GuidanceSafetyNotice />
    </div>
  );
}

function OutcomeView({
  outcome,
  isEmergency,
  headingRef,
}: {
  outcome: GuidanceOutcome;
  isEmergency: boolean;
  headingRef: React.Ref<HTMLHeadingElement>;
}) {
  return (
    <div className="flex flex-col gap-6">
      {/* The emergency outcome leads with what to do; the general disclaimer
          follows it instead of pushing the call buttons down. */}
      {isEmergency ? null : <GuidanceSafetyNotice />}
      <Card className="space-y-3 p-5">
        <h2 ref={headingRef} tabIndex={-1} className={stepHeadingClass}>
          {outcome.title}
        </h2>
        <p className="text-sm text-muted-foreground">{outcome.body}</p>
        {isEmergency ? <EmergencyCallLinks /> : null}
      </Card>
      {isEmergency ? <GuidanceSafetyNotice /> : null}
      {isEmergency ? (
        <Card className="border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
          {t("guidance.emergencyResultNote")} <strong>194</strong> /{" "}
          <strong>112</strong>.
        </Card>
      ) : null}
      <ul className="flex flex-wrap gap-3">
        {outcome.handoffs.map((handoff) => (
          <li key={`${handoff.type}-${handoff.label}`}>
            <HandoffLink handoff={handoff} />
          </li>
        ))}
      </ul>
      <p className="text-xs text-muted-foreground">
        {t("guidance.startAgainLink")}{" "}
        <Link href="/guidance" className="underline hover:text-primary">
          {t("nav.guidance")}
        </Link>{" "}
        {t("guidance.startAgain")}
      </p>
    </div>
  );
}

function HandoffLink({
  handoff,
}: {
  handoff: GuidanceOutcome["handoffs"][number];
}) {
  if (handoff.type === "emergency") {
    return (
      <span className="text-sm font-medium text-destructive">
        {t("footer.emergency")}{" "}
        <a href="tel:194" className="font-bold underline">
          194
        </a>{" "}
        /{" "}
        <a href="tel:112" className="font-bold underline">
          112
        </a>
      </span>
    );
  }

  const href =
    handoff.href ??
    (handoff.type === "doctors"
      ? "/doctors"
      : handoff.type === "facilities"
        ? "/facilities"
        : "/");

  const label =
    handoff.label ??
    (handoff.type === "doctors"
      ? t("nav.doctors")
      : handoff.type === "facilities"
        ? t("nav.facilities")
        : t("common.home"));

  return (
    <Button href={href} variant="outline">
      {label}
    </Button>
  );
}
