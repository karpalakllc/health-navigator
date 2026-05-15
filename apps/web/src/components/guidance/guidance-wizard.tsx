"use client";

import Link from "next/link";
import { useCallback, useState } from "react";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  completeGuidanceEmergency,
  completeGuidanceSession,
  saveGuidanceAnswers,
  startGuidanceSession,
  type GuidanceFlow,
  type GuidanceOutcome,
} from "@/lib/api/guidance";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const SESSION_KEY = "guidance_session_id";

const emergencyButtonClass =
  "border-destructive/40 text-destructive hover:bg-destructive/5";

type Phase = "intro" | "red_flags" | "questions" | "result" | "emergency";

type Props = {
  flow: GuidanceFlow;
};

export function GuidanceWizard({ flow }: Props) {
  const [phase, setPhase] = useState<Phase>("intro");
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [accepted, setAccepted] = useState(false);
  const [redFlags, setRedFlags] = useState<string[]>([]);
  const [stepIndex, setStepIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<string, string[]>>({});
  const [outcome, setOutcome] = useState<GuidanceOutcome | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const currentStep = flow.steps[stepIndex];

  const ensureSession = useCallback(async (): Promise<string> => {
    if (sessionId) {
      return sessionId;
    }

    const stored =
      typeof window !== "undefined"
        ? window.sessionStorage.getItem(SESSION_KEY)
        : null;

    if (stored) {
      setSessionId(stored);

      return stored;
    }

    const id = await startGuidanceSession();
    window.sessionStorage.setItem(SESSION_KEY, id);
    setSessionId(id);

    return id;
  }, [sessionId]);

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
    setLoading(true);
    setError(null);

    try {
      const id = await ensureSession();

      if (redFlags.length > 0) {
        await saveGuidanceAnswers(id, [
          { step_key: "red_flags", values: redFlags },
        ]);
        const result = await completeGuidanceSession(id);
        setOutcome(result);
        setPhase("emergency");
        window.sessionStorage.removeItem(SESSION_KEY);

        return;
      }

      await saveGuidanceAnswers(id, [
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
    setLoading(true);
    setError(null);

    try {
      const id = await ensureSession();
      const result = await completeGuidanceEmergency(id);
      setOutcome(result);
      setPhase("emergency");
      window.sessionStorage.removeItem(SESSION_KEY);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("guidance.emergencyError"));
    } finally {
      setLoading(false);
    }
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
      const id = await ensureSession();
      await saveGuidanceAnswers(id, [
        { step_key: currentStep.key, values: selected },
      ]);

      if (stepIndex < flow.steps.length - 1) {
        setStepIndex((i) => i + 1);

        return;
      }

      const result = await completeGuidanceSession(id);
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
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
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
        <h2 className="text-lg font-semibold text-foreground">{t("guidance.safetyCheck")}</h2>
        <p className="text-sm text-muted-foreground">{t("guidance.redFlagIntro")}</p>
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
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="flex flex-wrap gap-3">
          <Button type="button" disabled={loading} onClick={handleRedFlagsContinue}>
            {loading ? t("guidance.saving") : t("guidance.continue")}
          </Button>
          <EmergencyShortcutButton loading={loading} onEmergency={handleEmergencyNow} />
        </div>
      </div>
    );
  }

  if ((phase === "result" || phase === "emergency") && outcome) {
    return (
      <OutcomeView outcome={outcome} isEmergency={phase === "emergency"} />
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
            {t("guidance.step")} {stepIndex + 1} {t("pagination.of")} {flow.steps.length}
          </p>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-full bg-primary transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
        <h2 className="text-lg font-semibold text-foreground">{currentStep.label}</h2>
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
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="flex flex-wrap gap-3">
          <Button type="button" disabled={loading} onClick={handleQuestionNext}>
            {loading
              ? t("guidance.saving")
              : stepIndex < flow.steps.length - 1
                ? t("common.next")
                : t("guidance.seeGuidance")}
          </Button>
          <EmergencyShortcutButton loading={loading} onEmergency={handleEmergencyNow} />
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

function OutcomeView({
  outcome,
  isEmergency,
}: {
  outcome: GuidanceOutcome;
  isEmergency: boolean;
}) {
  return (
    <div className="flex flex-col gap-6">
      <GuidanceSafetyNotice />
      <Card className="space-y-3 p-5">
        <h2 className="text-lg font-semibold text-foreground">{outcome.title}</h2>
        <p className="text-sm text-muted-foreground">{outcome.body}</p>
      </Card>
      {isEmergency ? (
        <Card className="border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
          {t("guidance.emergencyResultNote")} <strong>194</strong> / <strong>112</strong>.
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
        {t("footer.emergency")} <strong>194</strong> / <strong>112</strong>
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
